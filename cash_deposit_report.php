<?php
require_once 'access_check.php';
checkAccess('CashDeposit.php');

// Database connection
require_once 'config.php';
$pdo = $conn; // Use the connection from config.php

// Get date range from URL parameters
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';

if (empty($fromDate) || empty($toDate)) {
    die("Error: From Date and To Date are required.");
}

// Format dates for display
$fromDateDisplay = date('F d, Y', strtotime($fromDate));
$toDateDisplay = date('F d, Y', strtotime($toDate));

// Set time range: from date at 8:00 AM to to date at 8:00 AM
$startDateTime = $fromDate . ' 08:00:00';
$endDateTime = $toDate . ' 08:00:00';

// Query to get all cash bookings within the date range
$sql = "SELECT 
            booking_id,
            check_in,
            check_out,
            payment_date_time,
            downpayment_date,
            deposit_cash,
            downpayment_cash,
            payment_amount_cash_history,
            payment_status_cash,
            encoder,
            confirmed_at,
            created_at
        FROM reports
        WHERE (
            (payment_date_time IS NOT NULL AND TRIM(payment_date_time) <> '')
            OR (downpayment_date IS NOT NULL AND downpayment_date <> '')
        )
        AND (
            COALESCE(deposit_cash, 0) > 0.005
            OR COALESCE(downpayment_cash, 0) > 0.005
        )
        ORDER BY booking_id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$allBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process bookings to filter by date range and calculate amounts
$bookings = [];
$startDT = new DateTime($startDateTime);
$endDT = new DateTime($endDateTime);

foreach ($allBookings as $row) {
    // Skip rows where cash column is actually another method
    $cashStatusRaw = strtolower(trim((string)($row['payment_status_cash'] ?? '')));
    if (!empty($cashStatusRaw)) {
        $nonCash = ['instapay', 'online banking', 'airbnb', 'gcash', 'maya'];
        $skip = false;
        foreach ($nonCash as $method) {
            if (stripos($cashStatusRaw, $method) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;
    }

    // Build timestamp list
    $timestamps = [];
    if (!empty($row['payment_date_time'])) {
        foreach (explode('|', (string)$row['payment_date_time']) as $ts) {
            $ts = trim($ts);
            if ($ts !== '' && $ts !== '0000-00-00 00:00:00') {
                $timestamps[] = $ts;
            }
        }
    }
    if (empty($timestamps) && !empty($row['downpayment_date'])) {
        $dp = trim((string)$row['downpayment_date']);
        if ($dp !== '' && $dp !== '0000-00-00 00:00:00') {
            $timestamps[] = $dp;
        }
    }
    if (empty($timestamps)) continue;

    $nTs = count($timestamps);
    $depCash = floatval($row['deposit_cash'] ?? 0);
    $downCash = floatval($row['downpayment_cash'] ?? 0);
    $totalCash = max($depCash, $downCash);

    // Build per-timestamp cash amounts using history column
    $cashAmounts = [];
    $histArr = !empty($row['payment_amount_cash_history'])
        ? explode('|', (string)$row['payment_amount_cash_history'])
        : null;

    if (is_array($histArr) && count($histArr) === $nTs) {
        // Exact match: align 1:1
        $cashAmounts = array_map('floatval', $histArr);
    } elseif (is_array($histArr) && $nTs === count($histArr) + 1) {
        // History is one behind — last payment not yet in history
        $sumPrior = array_sum(array_map('floatval', $histArr));
        $lastAmt = max(0, $totalCash - $sumPrior);
        $cashAmounts = array_merge(array_map('floatval', $histArr), [$lastAmt]);
    } elseif ($totalCash > 0) {
        // No precise history: put all on first timestamp
        $cashAmounts = array_fill(0, $nTs, 0);
        $cashAmounts[0] = $totalCash;
    } else {
        continue; // No cash at all
    }

    // Check each timestamp to see if it falls within the date range
    $bookingCashTotal = 0;
    $bookingPaymentDates = [];
    
    foreach ($timestamps as $idx => $ts) {
        $amt = floatval($cashAmounts[$idx] ?? 0);
        if ($amt <= 0.005) continue;
        
        try {
            $dt = new DateTime($ts);
            
            // Fix invalid dates (year < 2020) by using confirmed_at or created_at
            if ($dt->format('Y') < 2020) {
                if (!empty($row['confirmed_at']) && $row['confirmed_at'] !== '0000-00-00 00:00:00') {
                    $dt = new DateTime($row['confirmed_at']);
                } elseif (!empty($row['created_at']) && $row['created_at'] !== '0000-00-00 00:00:00') {
                    $dt = new DateTime($row['created_at']);
                }
            }
            
            $dtFormatted = $dt->format('Y-m-d H:i:s');
            $inRange = ($dt >= $startDT && $dt < $endDT);
            
            // Check if this payment falls within the date range
            // Range is: $startDT (inclusive) to $endDT (exclusive)
            // Example: July 1, 2026 8:00 AM to July 1, 2026 8:00 AM (same day = no data)
            // Example: July 1, 2026 8:00 AM to July 2, 2026 8:00 AM (24 hour period)
            // Example: July 1, 2026 8:00 AM to July 8, 2026 8:00 AM (7 days)
            // INCLUDE: Any datetime >= start time and < end time
            // EXCLUDE: Any datetime before start time or at/after end time
            if ($inRange) {
                $bookingCashTotal += $amt;
                $bookingPaymentDates[] = [
                    'date' => $dtFormatted,
                    'amount' => $amt
                ];
            }
        } catch (Exception $e) {
            continue;
        }
    }

    // Only include bookings that have cash payments in the date range
    if ($bookingCashTotal > 0) {
        $bookings[] = [
            'booking_id' => $row['booking_id'],
            'check_in' => $row['check_in'],
            'check_out' => $row['check_out'],
            'payment_dates' => $bookingPaymentDates,
            'cash_amount' => $bookingCashTotal
        ];
    }
}

// Format currency
function formatMoney($amount) {
    return '₱' . number_format((float)$amount, 2);
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format datetime
function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Deposit Report - <?php echo $fromDateDisplay; ?> to <?php echo $toDateDisplay; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            padding: 30px;
            color: #1f2937;
        }

        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .report-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #1b5e20;
            padding-bottom: 20px;
        }

        .report-title {
            font-size: 28px;
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 8px;
        }

        .report-subtitle {
            font-size: 16px;
            color: #6b7280;
            font-weight: 500;
        }

        .report-date-range {
            font-size: 14px;
            color: #374151;
            margin-top: 8px;
            font-weight: 600;
        }

        .deposit-section {
            margin-bottom: 40px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        .deposit-header {
            background: #f3f4f6;
            padding: 16px 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            border-bottom: 2px solid #e5e7eb;
        }

        .deposit-info-item {
            display: flex;
            flex-direction: column;
        }

        .deposit-info-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .deposit-info-value {
            font-size: 14px;
            color: #1f2937;
            font-weight: 600;
        }

        .deposit-info-value.large {
            font-size: 16px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.exact {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-badge.over {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-badge.short {
            background: #fee2e2;
            color: #dc2626;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table thead {
            background: #f9fafb;
        }

        .bookings-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            color: #374151;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
        }

        .bookings-table td {
            padding: 12px 16px;
            font-size: 13px;
            color: #1f2937;
            border-bottom: 1px solid #f3f4f6;
        }

        .bookings-table tbody tr:hover {
            background: #f9fafb;
        }

        .no-bookings {
            padding: 40px;
            text-align: center;
            color: #9ca3af;
            font-size: 14px;
        }

        .total-row {
            background: #f9fafb;
            font-weight: 700;
        }

        .print-btn {
            background: #1b5e20;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .print-btn:hover {
            background: #15501a;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .report-container {
                box-shadow: none;
                padding: 20px;
            }

            .print-btn {
                display: none;
            }

            .deposit-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <button class="print-btn" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print Report
        </button>

        <div class="report-header">
            <h1 class="report-title">Cash Deposit Report</h1>
            <p class="report-subtitle">Booking IDs and Amounts Used</p>
            <p class="report-date-range">
                <strong>Date Range:</strong> <?php echo $fromDateDisplay; ?> 8:00 AM to <?php echo $toDateDisplay; ?> 8:00 AM
            </p>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="no-bookings">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 12px; opacity: 0.3;">
                    <path d="M9 2v1m6-1v1M4 8h16M3 10v9a2 2 0 002 2h14a2 2 0 002-2v-9H3z"/>
                </svg>
                <p>No cash bookings found for the selected date range.</p>
            </div>
        <?php else: ?>
            <div class="deposit-section">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Payment Date(s) & Amount</th>
                            <th>Check-in Date</th>
                            <th>Checkout Date</th>
                            <th>Total Cash</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grandTotal = 0;
                        foreach ($bookings as $booking): 
                            $grandTotal += $booking['cash_amount'];
                            
                            // Format payment dates with amounts
                            $paymentDatesDisplay = '';
                            if (!empty($booking['payment_dates'])) {
                                $formattedPayments = array_map(function($payment) {
                                    return formatDateTime($payment['date']) . ' - ' . formatMoney($payment['amount']);
                                }, $booking['payment_dates']);
                                $paymentDatesDisplay = implode('<br>', $formattedPayments);
                            } else {
                                $paymentDatesDisplay = 'N/A';
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['booking_id']); ?></strong></td>
                                <td style="font-size: 12px;">
                                    <?php echo $paymentDatesDisplay; ?>
                                </td>
                                <td><?php echo !empty($booking['check_in']) ? formatDate($booking['check_in']) : 'N/A'; ?></td>
                                <td><?php echo !empty($booking['check_out']) ? formatDate($booking['check_out']) : 'N/A'; ?></td>
                                <td><strong><?php echo formatMoney($booking['cash_amount']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right; font-size: 15px;">Grand Total:</td>
                            <td><strong style="font-size: 16px;"><?php echo formatMoney($grandTotal); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
