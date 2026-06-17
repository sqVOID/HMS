<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'report_helpers.php';

// Check if format is PDF
$format = $_GET['format'] ?? 'excel';
$isPdf = ($format === 'pdf');

if (!$isPdf) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="daily_sales_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

$selectedRangeKey = strtolower($_GET['range'] ?? 'today');
$customStart = $_GET['start_date'] ?? null;
$customEnd = $_GET['end_date'] ?? null;
$validRanges = ['today', 'last_week', 'last_month', 'custom'];
if (!in_array($selectedRangeKey, $validRanges, true)) {
    $selectedRangeKey = 'today';
}
if ($selectedRangeKey === 'custom' && (!$customStart || !$customEnd)) {
    $selectedRangeKey = 'today';
}

try {

    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    $hasReportsTable = $checkTable->rowCount() > 0;

    if (!$hasReportsTable) {
        echo "Reports table does not exist yet.";
        exit;
    }

    ensureReportFinancialColumns($conn);

    $filterRangeMeta = buildDateRange($selectedRangeKey, $customStart, $customEnd);
    $filterStart = $filterRangeMeta['start'];
    $filterEnd = $filterRangeMeta['end'];

    // Top Statistics (Count for the whole range)
    // Same queries as export_report.php
    $checkInStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status IN ('Confirming', 'Confirmed') AND DATE(check_in) BETWEEN :start AND :end");
    $checkInStmt->bindParam(':start', $filterStart);
    $checkInStmt->bindParam(':end', $filterEnd);
    $checkInStmt->execute();
    $checkInCount = $checkInStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $checkOutStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'Checked Out' AND DATE(checked_out_at) BETWEEN :start AND :end");
    $checkOutStmt->bindParam(':start', $filterStart);
    $checkOutStmt->bindParam(':end', $filterEnd);
    $checkOutStmt->execute();
    $checkOutCount = $checkOutStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $canceledStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE status = 'Canceled' 
          AND DATE(COALESCE(canceled_at, check_in)) BETWEEN :start AND :end
    ");
    $canceledStmt->bindParam(':start', $filterStart);
    $canceledStmt->bindParam(':end', $filterEnd);
    $canceledStmt->execute();
    $canceledCount = $canceledStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $totalRoomsStmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms");
    $totalRoomsStmt->execute();
    $totalRoomsCount = $totalRoomsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $promoStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE promo IS NOT NULL AND promo <> '' AND promo <> 'None' AND DATE(check_in) BETWEEN :start AND :end");
    $promoStmt->bindParam(':start', $filterStart);
    $promoStmt->bindParam(':end', $filterEnd);
    $promoStmt->execute();
    $promoCount = $promoStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Guest Type counts - filtered by check_in date
    $guestTypeSolo = 0;
    $guestTypeDuo = 0;
    $guestTypeFamily = 0;
    $guestTypeGroup = 0;
    $guestTypeCompany = 0;

    $guestTypeStmt = $conn->prepare("SELECT guest_type, COUNT(*) as count FROM reports WHERE DATE(check_in) BETWEEN :start AND :end GROUP BY guest_type");
    $guestTypeStmt->bindParam(':start', $filterStart);
    $guestTypeStmt->bindParam(':end', $filterEnd);
    $guestTypeStmt->execute();
    $guestTypeResults = $guestTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($guestTypeResults as $row) {
        $type = strtolower($row['guest_type'] ?? '');
        $count = intval($row['count'] ?? 0);

        if ($type === 'solo') {
            $guestTypeSolo = $count;
        } elseif ($type === 'duo') {
            $guestTypeDuo = $count;
        } elseif ($type === 'family') {
            $guestTypeFamily = $count;
        } elseif ($type === 'group') {
            $guestTypeGroup = $count;
        } elseif ($type === 'company') {
            $guestTypeCompany = $count;
        }
    }

    // Fetch All Checked-Out Reports in Range for Daily Aggregation
    // We need payment_status_* columns to accurately calculate daily sales by method.
    // List only Active/History reports in range
    // MATCHING export_report.php logic: No exclusive status filter, just date range.
    $reportsStmt = $conn->prepare("
        SELECT 
            r.*,
            COALESCE(r.encoder, b.encoder) as encoder,
            COALESCE(r.guest_type, b.guest_type) as guest_type,
            COALESCE(b.status, r.status) as status,
            COALESCE(r.booking_type, b.booking_type) as booking_type,
            COALESCE(r.payment_status_cash, b.payment_status_cash) as payment_status_cash,
            COALESCE(r.payment_status_g_cash, b.payment_status_g_cash) as payment_status_g_cash,
            COALESCE(r.payment_status_maya, b.payment_status_maya) as payment_status_maya,
            COALESCE(r.deposit_details, b.deposit_details) as deposit_details,
            COALESCE(r.deposit_cash, b.deposit_cash, 0) as deposit_cash,
            COALESCE(r.deposit_g_cash, b.deposit_g_cash, 0) as deposit_g_cash,
            COALESCE(r.deposit_maya, b.deposit_maya, 0) as deposit_maya,
            COALESCE(r.deposit_gcash_ref, b.deposit_gcash_ref) as deposit_gcash_ref,
            COALESCE(r.deposit_maya_ref, b.deposit_maya_ref) as deposit_maya_ref,
            COALESCE(r.change_amount, b.change_amount, 0) as change_amount,
            COALESCE(r.downpayment_amount, b.downpayment_amount, 0) as downpayment_amount,
            COALESCE(r.downpayment_cash, b.downpayment_cash, 0) as downpayment_cash,
            COALESCE(r.downpayment_gcash, b.downpayment_gcash, 0) as downpayment_gcash,
            COALESCE(r.downpayment_maya, b.downpayment_maya, 0) as downpayment_maya,
            COALESCE(r.downpayment_gcash_ref, b.downpayment_gcash_ref) as downpayment_gcash_ref,
            COALESCE(r.downpayment_maya_ref, b.downpayment_maya_ref) as downpayment_maya_ref,
            COALESCE(r.discount_amount, b.discount_amount, 0) as discount_amount,
            GREATEST(COALESCE(r.additional_guest, 0), COALESCE(b.additional_guest, 0)) as additional_guest,
            GREATEST(COALESCE(r.additional_pet, 0), COALESCE(b.additional_pet, 0)) as additional_pet
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
        -- Filter preference:
        -- 1) Use `check_in` as the primary basis (align with revenue/stat counts)
        -- 2) Fallback to `reservation_date` only when `check_in` is missing
        WHERE DATE(COALESCE(r.check_in, r.reservation_date)) BETWEEN :start AND :end
        ORDER BY r.check_in DESC
    ");
    $reportsStmt->bindParam(':start', $filterStart);
    $reportsStmt->bindParam(':end', $filterEnd);
    $reportsStmt->execute();
    $allReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Aggregate Daily Sales
    $dailyStats = [];
    // Helper to parse amount
    function parseAmount($str)
    {
        if (!$str)
            return 0.0;
        if (preg_match('/(?:P|₱)?([0-9,.]+)/', $str, $m)) {
            return floatval(str_replace(',', '', $m[1]));
        }
        return 0.0;
    }

    // Helper: parse additional charges stored as either JSON array or formatted lines
    // Copied from export_report.php for consistency
    function parse_additional_total($raw)
    {
        if (!$raw)
            return 0.0;
        $total = 0.0;
        // Try JSON first
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $it) {
                $qty = floatval($it['quantity'] ?? ($it['qty'] ?? 1));
                $price = floatval($it['price'] ?? 0);
                $total += $qty * $price;
            }
            return $total;
        }
        // Otherwise parse lines like "1 hotdog = 120.00" or lines containing amounts
        $lines = preg_split('/\r?\n/', $raw);
        foreach ($lines as $line) {
            if (preg_match('/₱\s*([0-9,]+\.?[0-9]*)/', $line, $m)) {
                $num = str_replace(',', '', $m[1]);
                $total += floatval($num);
            } else {
                // try to find plain numbers at end
                if (preg_match('/([0-9]+\.?[0-9]*)\s*$/', trim($line), $m2)) {
                    $total += floatval($m2[1]);
                }
            }
        }
        return $total;
    }

    $overallCash = 0;
    $overallGcash = 0;
    $overallMaya = 0;
    $overallDeposit = 0;

    $overallCashCount = 0;
    $overallGcashCount = 0;
    $overallMayaCount = 0;

    // Track detailed deposit stats for overall totals
    $overallDepositCash = 0;
    $overallDepositGcash = 0;
    $overallDepositMaya = 0;

    foreach ($allReports as $row) {
        // Determine Date
        // Use reservation_date as a fallback when there is no check-in yet
        // so pure reservations are still visible in the Daily Sales export.
        $dateRaw = $row['checked_out_at'] ?? $row['check_out'] ?? $row['check_in'] ?? $row['reservation_date'];
        $date = date('Y-m-d', strtotime($dateRaw));

        if (!isset($dailyStats[$date])) {
            $dailyStats[$date] = [
                'count' => 0,
                'cash' => 0.0,
                'gcash' => 0.0,
                'maya' => 0.0,
                'deposit_cash' => 0.0,
                'deposit_gcash' => 0.0,
                'deposit_maya' => 0.0,
                'deposit_total' => 0.0,
                'total' => 0.0
            ];
        }

        $rowStatus = $row['status'] ?? '';
        $isCanceledRow = strcasecmp(trim((string) $rowStatus), 'Canceled') === 0;

        // Checkout Payments (preferred when present)
        $cash = parseAmount($row['payment_status_cash'] ?? '');
        $gcash = parseAmount($row['payment_status_g_cash'] ?? '');
        $maya = parseAmount($row['payment_status_maya'] ?? '');

        // Fallback for Check-In/Confirmed rows:
        // If checkout payment columns are empty/zero, use the check-in deposit breakdown fields.
        if (($cash + $gcash + $maya) <= 0) {
            $cash = floatval($row['deposit_cash'] ?? 0) + floatval($row['downpayment_cash'] ?? 0);
            $gcash = floatval($row['deposit_g_cash'] ?? 0) + floatval($row['downpayment_gcash'] ?? 0);
            $maya = floatval($row['deposit_maya'] ?? 0) + floatval($row['downpayment_maya'] ?? 0);
        }

        // Parse deposit
        $depositAmt = 0.0;
        $depositDetails = $row['deposit_details'] ?? '';
        if (!empty($depositDetails)) {
            $depositAmt = parseCurrencyFromString($depositDetails);
        }

        // Parse downpayment
        $downpaymentAmt = floatval($row['downpayment_amount'] ?? 0);

        // Determine Deposit Method
        $dGcash = $row['deposit_gcash_ref'] ?? null;
        $dMaya = $row['deposit_maya_ref'] ?? null;

        $isDepGcash = false;
        $isDepMaya = false;

        if ($depositAmt > 0) {
            if (!empty($dGcash) && $dGcash !== 'NULL' && $dGcash !== '') {
                $isDepGcash = true;
            } elseif (!empty($dMaya) && $dMaya !== 'NULL' && $dMaya !== '') {
                $isDepMaya = true;
            } else {
                if (stripos($depositDetails, 'G-Cash') !== false || stripos($depositDetails, 'Gcash') !== false) {
                    $isDepGcash = true;
                } elseif (stripos($depositDetails, 'Maya') !== false) {
                    $isDepMaya = true;
                }
            }
        }

        // Business rule for Daily Sales Breakdown:
        // - If canceled: Cash/G-Cash/Maya sales must always be 0.
        if ($isCanceledRow) {
            $cash = 0.0;
            $gcash = 0.0;
            $maya = 0.0;
        }

        if ($cash > 0)
            $overallCashCount++;
        if ($gcash > 0)
            $overallGcashCount++;
        if ($maya > 0)
            $overallMayaCount++;

        $dailyStats[$date]['count']++;

        // Checkout Stats (checkout payments only, no deposits)
        $dailyStats[$date]['cash'] += $cash;
        $dailyStats[$date]['gcash'] += $gcash;
        $dailyStats[$date]['maya'] += $maya;

        // Deposit Stats (track separately but don't add to sales)
        if ($depositAmt > 0) {
            $dailyStats[$date]['deposit_total'] += $depositAmt;
            if ($isDepGcash) {
                $dailyStats[$date]['deposit_gcash'] += $depositAmt;
                $overallDepositGcash += $depositAmt;
            } elseif ($isDepMaya) {
                $dailyStats[$date]['deposit_maya'] += $depositAmt;
                $overallDepositMaya += $depositAmt;
            } else {
                // Default to cash
                $dailyStats[$date]['deposit_cash'] += $depositAmt;
                $overallDepositCash += $depositAmt;
            }
        }

        // Total = checkout payments only (no deposits/downpayments)
        $dailyStats[$date]['total'] += ($cash + $gcash + $maya);

        $overallCash += $cash;
        $overallGcash += $gcash;
        $overallMaya += $maya;
        $overallDeposit += ($depositAmt + $downpaymentAmt);
    }

    // Sort daily stats by date descending
    krsort($dailyStats);

    // Output Excel or PDF
    if ($isPdf) {
        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="UTF-8">';
        echo '<title>Daily Sales Report</title>';
        echo '<style>
            @media print {
                @page { 
                    margin: 0.5in; 
                    size: landscape;
                }
                body { margin: 0; }
            }
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                margin: 20px;
                background: white;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 3px solid #4CAF50;
            }
            .header h1 {
                margin: 0;
                color: #4CAF50;
                font-size: 24px;
                font-weight: 600;
            }
            .header .meta {
                margin-top: 8px;
                color: #666;
                font-size: 13px;
            }
            .header .date-range {
                margin-top: 5px;
                color: #4CAF50;
                font-weight: 600;
                font-size: 14px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                font-size: 11px;
            }
            th {
                background-color: #4CAF50;
                color: white;
                font-weight: 600;
                padding: 10px 6px;
                text-align: left;
                font-size: 11px;
                border: 1px solid #45a049;
                white-space: nowrap;
            }
            td { 
                border: 1px solid #e0e0e0; 
                padding: 8px 6px; 
                text-align: left;
                font-size: 10px;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            tr:hover {
                background-color: #f5f5f5;
            }
            .total-row {
                background-color: #e8f5e9 !important;
                font-weight: bold;
                border-top: 2px solid #4CAF50;
            }
            .section-title {
                background-color: #4CAF50;
                color: white;
                font-weight: 600;
                font-size: 14px;
                padding: 10px;
                margin-top: 20px;
            }
            .print-button {
                position: fixed;
                top: 10px;
                right: 10px;
                background: #4CAF50;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                z-index: 1000;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                transition: background 0.3s;
            }
            .print-button:hover {
                background: #45a049;
            }
            @media print {
                .print-button { display: none; }
                body { font-size: 10px; }
                table { font-size: 9px; }
                th { font-size: 9px; padding: 6px 4px; }
                td { font-size: 8px; padding: 5px 4px; }
            }
        </style>';
        echo '<script>
            function printPDF() {
                window.print();
            }
        </script>';
        echo '</head><body>';
        echo '<button class="print-button" onclick="printPDF()">Print / Save as PDF</button>';

        // PDF Header
        echo '<div class="header">';
        echo '<h1>Hotel Management System - Daily Sales Report</h1>';
        echo '<div class="meta">Generated on: ' . $currentTime . '</div>';
        echo '<div class="date-range">Date Range: ' . htmlspecialchars($rangeLabel) . '</div>';
        echo '</div>';
    } else {
        echo '<html><head><meta charset="UTF-8"></head><body>';
    }

    echo '<table border="1" cellpadding="5" cellspacing="0">';

    // Title
    $currentTime = date('d/m/Y H:i:s');
    $rangeLabel = $filterRangeMeta['label'];
    if (!empty($filterRangeMeta['start']) && !empty($filterRangeMeta['end'])) {
        $rangeLabel .= ' (' . $filterRangeMeta['start'] . ' - ' . $filterRangeMeta['end'] . ')';
    }

    // Only show these sections for Excel export, not PDF
    if (!$isPdf) {
        echo '<tr><td colspan="31" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 16px; text-align: center; padding: 10px;">Hotel Management System - Daily Sales Report</td></tr>';
        echo '<tr><td colspan="31" style="text-align: center; padding: 5px;">Generated on: ' . $currentTime . '</td></tr>';
        echo '<tr><td colspan="31" style="text-align: center; padding: 5px; font-weight: bold; color: #256d27;">Date Range: ' . htmlspecialchars($rangeLabel) . '</td></tr>';
        echo '<tr><td colspan="31"></td></tr>';

        // Top Stats
        echo '<tr><td colspan="31" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Booking Statistics</td></tr>';
        echo '<tr><td style="font-weight: bold;">Check-In</td><td>' . $checkInCount . '</td><td colspan="29"></td></tr>';
        echo '<tr><td style="font-weight: bold;">Check-Out</td><td>' . $checkOutCount . '</td><td colspan="29"></td></tr>';
        echo '<tr><td style="font-weight: bold;">Canceled</td><td>' . $canceledCount . '</td><td colspan="29"></td></tr>';
        echo '<tr><td style="font-weight: bold;">Total Rooms</td><td>' . $totalRoomsCount . '</td><td colspan="29"></td></tr>';
        echo '<tr><td colspan="31"></td></tr>';

        echo '<tr><td colspan="31" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Type of Guest</td></tr>';
        echo '<tr><td style="font-weight: bold;">Solo</td><td>' . $guestTypeSolo . '</td><td colspan="29"></td></tr>';
        echo '<tr><td style="font-weight: bold;">Duo</td><td>' . $guestTypeDuo . '</td><td colspan="29"></td></tr>';
        echo '<tr><td style="font-weight: bold;">Family</td><td>' . $guestTypeFamily . '</td><td colspan="29"></td></tr>';
        echo '<tr><td style="font-weight: bold;">Group</td><td>' . $guestTypeGroup . '</td><td colspan="29"></td></tr>';
        echo '<tr><td style="font-weight: bold;">Company</td><td>' . $guestTypeCompany . '</td><td colspan="29"></td></tr>';
        echo '<tr><td colspan="31"></td></tr>';
    }

    // Daily Sales Table
    if ($isPdf) {
        echo '</table>'; // Close the first table
        echo '<div class="section-title">Daily Sales Breakdown</div>';
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>Date</th>';
        echo '<th>Bookings</th>';
        echo '<th>Cash Sales</th>';
        echo '<th>G-Cash Sales</th>';
        echo '<th>Maya Sales</th>';
        echo '<th>Total Sales</th>';
        echo '</tr></thead>';
        echo '<tbody>';
    } else {
        echo '<tr><td colspan="31" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 14px; padding: 8px;">Daily Sales Breakdown</td></tr>';
        echo '<tr style="background-color: #e8f5e9;">';
        echo '<td style="font-weight: bold; padding: 8px;">Date</td>';
        echo '<td style="font-weight: bold; padding: 8px;">Bookings</td>';
        echo '<td style="font-weight: bold; padding: 8px;">Cash Sales</td>';
        echo '<td style="font-weight: bold; padding: 8px;">G-Cash Sales</td>';
        echo '<td style="font-weight: bold; padding: 8px;">Maya Sales</td>';
        echo '<td style="font-weight: bold; padding: 8px;">Total Sales</td>';
        echo '<td colspan="25"></td>';
        echo '</tr>';
    }

    if (empty($dailyStats)) {
        $colspanValue = $isPdf ? '6' : '31';
        echo '<tr><td colspan="' . $colspanValue . '" style="text-align:center; padding:10px;">No sales data found for this range.</td></tr>';
    } else {
        foreach ($dailyStats as $date => $stats) {
            echo '<tr>';
            echo '<td style="padding: 8px;">' . date('d/m/Y', strtotime($date)) . '</td>';
            echo '<td style="padding: 8px;">' . $stats['count'] . '</td>';
            // Output Checkout Sales ONLY (without deposits)
            $rowCash = $stats['cash'];
            $rowGcash = $stats['gcash'];
            $rowMaya = $stats['maya'];

            echo '<td style="padding: 8px;">' . number_format($rowCash, 2) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($rowGcash, 2) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($rowMaya, 2) . '</td>';
            // Total Sales = sum of checkout payments only
            $totalSales = $rowCash + $rowGcash + $rowMaya;
            echo '<td style="padding: 8px; font-weight: bold;">' . number_format($totalSales, 2) . '</td>';
            if (!$isPdf) {
                echo '<td colspan="25"></td>';
            }
            echo '</tr>';
        }
        // Grand Total for the table - Checkout payments only
        $grandCash = $overallCash;
        $grandGcash = $overallGcash;
        $grandMaya = $overallMaya;
        $grandTotalSales = $grandCash + $grandGcash + $grandMaya;

        echo '<tr class="total-row">';
        echo '<td style="padding: 8px;">GRAND TOTAL</td>';
        echo '<td style="padding: 8px;">' . $checkOutCount . '</td>';
        echo '<td style="padding: 8px;">' . number_format($grandCash, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($grandGcash, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($grandMaya, 2) . '</td>';
        // Total of checkout sales only
        echo '<td style="padding: 8px;">' . number_format($grandTotalSales, 2) . '</td>';
        if (!$isPdf) {
            echo '<td colspan="25"></td>';
        }
        echo '</tr>';
    }

    if ($isPdf) {
        echo '</tbody>';
    }

    echo '<tr><td colspan="31"></td></tr>';

    // Detailed Report (Recycled from export_report.php but simplified column count if needed, or kept full)
    // The user said "style it should be just like in the Export Excel".
    // I should probably keep the detailed report.
    // However, the detailed report has MANY columns (20+). My Daily Sales table has 6.
    // Excel doesn't handle mixed column counts in the same sheet well unless I colspan carefully.
    // export_report.php uses colspan="24" for headers, so it supports up to 24 columns.

    // I should wrap the Daily Sales table in a colspan if I want to align with the wider detailed table below.
    // Or just let the detailed table expand naturally.

    // Let's add the Detailed Booking Report below.

    // Booking Revenue Breakdown
    if ($isPdf) {
        echo '</table>'; // Close daily sales table
        echo '<div class="section-title" style="margin-top: 30px;">Daily Sales Details (' . $filterRangeMeta['start'] . ' - ' . $filterRangeMeta['end'] . ')</div>';
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>Booking ID</th>';
        echo '<th>Room ID</th>';
        echo '<th>Encoder</th>';
        echo '<th>Booking Type</th>';
        echo '<th>Room Type</th>';
        echo '<th>Type of Guest</th>';
        echo '<th>Promo</th>';
        echo '<th>Discount Amount</th>';
        echo '<th>Guest</th>';
        echo '<th>Reservation Date</th>';
        echo '<th>Check-In Date</th>';
        echo '<th>Check-In Time</th>';
        echo '<th>Check-Out Date</th>';
        echo '<th>Check-Out Time</th>';
        echo '<th>Duration</th>';
        echo '<th>Extension Duration</th>';
        echo '<th>Breakfast</th>';
        echo '<th>Status</th>';
        echo '<th>Payment</th>';
        echo '<th>Cash</th>';
        echo '<th>G-Cash</th>';
        echo '<th>Maya</th>';
        echo '<th>Reference No.</th>';
        echo '<th>DP Payment</th>';
        echo '<th>DP Cash</th>';
        echo '<th>DP G-Cash</th>';
        echo '<th>DP Maya</th>';
        echo '<th>DP Ref</th>';
        echo '<th>Total Amount</th>';
        echo '<th>Additional Items</th>';
        echo '<th>Additional Foods</th>';
        echo '<th>Additional Guest</th>';
        echo '<th>Additional Pet</th>';
        echo '<th>Additional Missing Items</th>';
        echo '<th>Additional Penalty</th>';
        echo '<th>Additional Total Amount Fees</th>';
        echo '<th>Overall Amount</th>';
        echo '</tr></thead>';
        echo '<tbody>';
    } else {
        echo '<tr><td colspan="31" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Daily Sales (' . $filterRangeMeta['start'] . ' - ' . $filterRangeMeta['end'] . ')</td></tr>';

        // Headers (Matching export_report.php Booking Revenue Breakdown)
        echo '<tr style="background-color: #4CAF50; color: white;">';
        echo '<td style="padding: 8px; font-weight: bold;">Booking ID</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Room ID</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Encoder</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Booking Type</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Room Type</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Type of Guest</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Promo</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Discount Amount</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Guest</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Reservation Date</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Check-In Date</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Check-In Time</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Check-Out Date</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Check-Out Time</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Duration</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Extension Duration</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Breakfast</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Status</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Payment</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Cash</td>';
        echo '<td style="padding: 8px; font-weight: bold;">G-Cash</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Maya</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Reference No.</td>';
        echo '<td style="padding: 8px; font-weight: bold;">DP Payment</td>';
        echo '<td style="padding: 8px; font-weight: bold;">DP Cash</td>';
        echo '<td style="padding: 8px; font-weight: bold;">DP G-Cash</td>';
        echo '<td style="padding: 8px; font-weight: bold;">DP Maya</td>';
        echo '<td style="padding: 8px; font-weight: bold;">DP Ref</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Total Amount</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Additional Items</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Additional Foods</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Additional Guest</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Additional Pet</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Additional Missing Items</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Additional Penalty</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Additional Total Amount Fees</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Overall Amount</td>';
        echo '</tr>';
    }

    $grandTotalAmount = 0;
    $grandTotalAdditional = 0;
    $grandTotalOverall = 0;
    $grandTotalDpCash = 0;
    $grandTotalDpGcash = 0;
    $grandTotalDpMaya = 0;

    foreach ($allReports as $report) {
        // Calculate deposit first (needed for payment method logic)
        $depositDetails = $report['deposit_details'] ?? null;
        $dGcash = $report['deposit_gcash_ref'] ?? null;
        $dMaya = $report['deposit_maya_ref'] ?? null;
        $depositAmount = parseCurrencyFromString($depositDetails);
        $cleanDepositAmount = $depositDetails ? number_format($depositAmount, 2) : '—';

        $depositMethodStr = '—';
        if ($depositDetails) {
            if (!empty($dGcash) && $dGcash !== 'NULL')
                $depositMethodStr = 'G-Cash';
            elseif (!empty($dMaya) && $dMaya !== 'NULL')
                $depositMethodStr = 'Maya';
            else {
                if (stripos($depositDetails, 'G-Cash') !== false)
                    $depositMethodStr = 'G-Cash';
                elseif (stripos($depositDetails, 'Maya') !== false)
                    $depositMethodStr = 'Maya';
                else
                    $depositMethodStr = 'Cash';
            }
        }
        $depositRefDisplay = (!empty($dGcash) && $dGcash !== 'NULL') ? $dGcash : ((!empty($dMaya) && $dMaya !== 'NULL') ? $dMaya : '—');

        // Get downpayment data
        $downpaymentAmount = floatval($report['downpayment_amount'] ?? 0);
        $downpaymentCash = floatval($report['downpayment_cash'] ?? 0);
        $downpaymentGcash = floatval($report['downpayment_gcash'] ?? 0);
        $downpaymentMaya = floatval($report['downpayment_maya'] ?? 0);
        $downpaymentGcashRef = $report['downpayment_gcash_ref'] ?? null;
        $downpaymentMayaRef = $report['downpayment_maya_ref'] ?? null;

        // Format downpayment displays
        // DP Payment: Show payment method(s) used
        $dpPaymentMethods = [];
        if ($downpaymentCash > 0) {
            $dpPaymentMethods[] = 'Cash';
        }
        if ($downpaymentGcash > 0) {
            $dpPaymentMethods[] = 'G-Cash';
        }
        if ($downpaymentMaya > 0) {
            $dpPaymentMethods[] = 'Maya';
        }
        $dpPaymentDisplay = !empty($dpPaymentMethods) ? implode(', ', $dpPaymentMethods) : '—';

        $dpCashDisplay = $downpaymentCash > 0 ? number_format($downpaymentCash, 2) : '—';
        $dpGcashDisplay = $downpaymentGcash > 0 ? number_format($downpaymentGcash, 2) : '—';
        $dpMayaDisplay = $downpaymentMaya > 0 ? number_format($downpaymentMaya, 2) : '—';

        // Build downpayment reference display
        $dpRefs = [];
        if (!empty($downpaymentGcashRef) && $downpaymentGcashRef !== 'NULL') {
            $dpRefs[] = $downpaymentGcashRef;
        }
        if (!empty($downpaymentMayaRef) && $downpaymentMayaRef !== 'NULL') {
            $dpRefs[] = $downpaymentMayaRef;
        }
        $dpRefDisplay = !empty($dpRefs) ? implode(', ', $dpRefs) : '—';

        $paymentMethodRaw = $report['payment_status'] ?? '—';

        if ($paymentMethodRaw !== '—') {
            $paymentMethodRaw = preg_replace('/\s*\([^)]*\)/', '', $paymentMethodRaw);
        }
        $paymentMethod = htmlspecialchars($paymentMethodRaw);

        // Add deposit method to payment method if deposit exists
        if ($depositAmount > 0 && $depositMethodStr !== '—') {
            if ($paymentMethod === '—' || empty($paymentMethod)) {
                $paymentMethod = $depositMethodStr;
            } else {
                // Only add deposit method if it's not already in the payment method string
                $existingMethods = array_map('trim', explode('&', str_replace(',', '&', $paymentMethod)));
                $depositMethods = array_map('trim', explode('&', str_replace(',', '&', $depositMethodStr)));

                foreach ($depositMethods as $depMethod) {
                    $found = false;
                    foreach ($existingMethods as $existMethod) {
                        if (stripos($existMethod, $depMethod) !== false || stripos($depMethod, $existMethod) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $paymentMethod .= ', ' . $depMethod;
                    }
                }
            }
        }

        $rawStatusForPayment = trim((string) ($report['status'] ?? ''));
        $isCheckedOutForPayment = strcasecmp($rawStatusForPayment, 'Checked Out') === 0;

        $referenceNoRaw = $report['reference_no'] ?? null;
        $refGcash = null;
        $refMaya = null;
        if ($isCheckedOutForPayment) {
            $refGcash = $report['reference_no_g_cash'] ?? null;
            $refMaya = $report['reference_no_maya'] ?? null;
        } else {
            $refGcash = $report['deposit_gcash_ref'] ?? null;
            $refMaya = $report['deposit_maya_ref'] ?? null;
        }
        $refs = [];
        if (!empty($refGcash) && $refGcash !== '')
            $refs[] = "$refGcash";
        if (!empty($refMaya) && $refMaya !== '')
            $refs[] = " $refMaya";

        // Deposit reference will be added later after depositRefDisplay is defined

        if (empty($refs)) {
            if ($referenceNoRaw && $referenceNoRaw !== '') {
                $cleanedRef = htmlspecialchars($referenceNoRaw);
                $lowerPayment = strtolower($paymentMethodRaw);
                if (strpos($lowerPayment, 'g-cash') !== false || strpos($lowerPayment, 'gcash') !== false) {
                    $refs[] = " $cleanedRef";
                } elseif (strpos($lowerPayment, 'maya') !== false) {
                    $refs[] = " $cleanedRef";
                } else {
                    $refs[] = $cleanedRef;
                }
            } else {
                $refs[] = '—';
            }
        }
        // De-duplicate reference numbers (handles comma-separated strings)
        $flatRefs = [];
        foreach ($refs as $r) {
            $parts = preg_split('/\\s*(?:,|&|\\|)\\s*/', (string) $r);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '' || $p === '—')
                    continue;
                $flatRefs[] = $p;
            }
        }
        $refMap = [];
        foreach ($flatRefs as $p) {
            $k = mb_strtolower($p);
            if (!isset($refMap[$k]))
                $refMap[$k] = $p;
        }
        $referenceNo = !empty($refMap) ? implode(' & ', array_values($refMap)) : '—';

        // --- Additional Fees Logic (Moved Up) ---
        $additionalItemsRaw = $report['additional_items'] ?? '';
        $additionalFoodRaw = $report['additional_food'] ?? '';

        // Filter out invalid placeholder entries
        if (preg_match('/^1\s*(?:Food Item|Item)?\s*[=-]\s*[₱P]?0\.00\s*$/iu', trim($additionalItemsRaw))) {
            $additionalItemsRaw = '';
        }
        if (preg_match('/^1\s*(?:Food Item|Item)?\s*[=-]\s*[₱P]?0\.00\s*$/iu', trim($additionalFoodRaw))) {
            $additionalFoodRaw = '';
        }
        $additionalGuest = intval($report['additional_guest'] ?? 0);
        $additionalPet = intval($report['additional_pet'] ?? 0);
        $additionalItemsDisplay = $additionalItemsRaw ? nl2br(htmlspecialchars($additionalItemsRaw)) : '—';
        $additionalFoodDisplay = $additionalFoodRaw ? nl2br(htmlspecialchars($additionalFoodRaw)) : '—';
        $additionalItemsTotal = parse_additional_total($additionalItemsRaw);
        $additionalFoodTotal = parse_additional_total($additionalFoodRaw);
        $additionalSum = $additionalItemsTotal + $additionalFoodTotal;
        $additionalGuestPrice = $additionalGuest * 300;
        $additionalPetPrice = $additionalPet * 500;

        $penaltyAmount = floatval($report['penalty_amount'] ?? 0);
        $penaltyListRaw = $report['penalty_list'] ?? null;
        $penaltyDisplay = '—';
        if (!empty($penaltyListRaw) && $penaltyListRaw !== 'null') {
            $pItems = json_decode($penaltyListRaw, true);
            if (is_array($pItems) && count($pItems) > 0) {
                $pStrings = [];
                $calculatedPenalty = 0;
                foreach ($pItems as $p) {
                    $pName = $p['name'] ?? 'Penalty';
                    $pPrice = floatval($p['price'] ?? 0);
                    $pStrings[] = $pName . ' (' . number_format($pPrice, 2) . ')';
                    $calculatedPenalty += $pPrice;
                }
                $penaltyDisplay = implode(', ', $pStrings);

                // Use calculated penalty if it's greater than the stored amount (which might be 0)
                if ($calculatedPenalty > 0) {
                    $penaltyAmount = $calculatedPenalty;
                }
            } elseif ($penaltyAmount > 0) {
                $penaltyDisplay = 'Penalty Applied';
            }
        } elseif ($penaltyAmount > 0) {
            $penaltyDisplay = 'Penalty Applied';
        }

        $missingItemsFees = floatval($report['missing_items_fees'] ?? 0);
        $missingItemsDisplay = '—';
        if (!empty($report['missing_items_list'])) {
            $mItems = json_decode($report['missing_items_list'], true);
            if (is_array($mItems) && count($mItems) > 0) {
                $mStrings = [];
                foreach ($mItems as $m) {
                    $mName = $m['name'] ?? 'Item';
                    $mPrice = floatval($m['price'] ?? 0);
                    $mStrings[] = $mName . ' (' . number_format($mPrice, 2) . ')';
                }
                $missingItemsDisplay = implode(', ', $mStrings);
            } elseif ($missingItemsFees > 0) {
                $missingItemsDisplay = 'Missing Items (' . number_format($missingItemsFees, 2) . ')';
            }
        } elseif ($missingItemsFees > 0) {
            $missingItemsDisplay = 'Missing Items (' . number_format($missingItemsFees, 2) . ')';
        }

        $totalAdditionalFees = $missingItemsFees + $additionalSum + $additionalGuestPrice + $additionalPetPrice + $penaltyAmount;
        $grandTotalAdditional += $totalAdditionalFees;

        // Now add deposit reference to the refs array (after depositRefDisplay is defined)
        if ($depositRefDisplay !== '—' && !empty($depositRefDisplay)) {
            // Check if refs array was already built earlier
            if (!isset($refs)) {
                $refs = [];
            }
            // Only add if not already in the array
            if (!in_array($depositRefDisplay, $refs)) {
                $refs[] = $depositRefDisplay;
            }
            // Rebuild referenceNo with deposit reference included
            $referenceNo = implode(', ', array_filter($refs, function ($r) {
                return $r !== '—';
            }));
            if (empty($referenceNo)) {
                $referenceNo = '—';
            }
        }

        $totalAmount = computeBookingTotalAmount([
            'room_type' => $report['room_type'] ?? '',
            'duration' => intval($report['duration'] ?? 0),
            'duration_unit' => $report['duration_unit'] ?? 'hours',
            'promo' => $report['promo'] ?? null,
            'breakfast' => $report['breakfast'] ?? null,
            'hygiene_kit_used' => intval($report['hygiene_kit_used'] ?? 0),
            'hygiene_kit_price' => floatval($report['hygiene_kit_price'] ?? 0),
            'room_price' => floatval($report['room_price'] ?? 0),
            'extend_price' => floatval($report['extend_price'] ?? 0)
        ]);

        // DO NOT add downpayment to totalAmount - it's a payment method, not part of the booking cost

        // Fix: Use Net Checkout Payment to set Total Amount (matching export_report.php logic)
        $payCash = parseAmount($report['payment_status_cash'] ?? '');
        $payGcash = parseAmount($report['payment_status_g_cash'] ?? '');
        $payMaya = parseAmount($report['payment_status_maya'] ?? '');
        $checkoutPaid = $payCash + $payGcash + $payMaya;

        $changeAmt = floatval($report['change_amount'] ?? 0);
        $netCheckoutPaid = max(0, $checkoutPaid - $changeAmt);
        $totalMoneyPaid = $netCheckoutPaid + $depositAmount + $downpaymentAmount;

        // Set totalAmount to match the checkout payment (not including deposit/downpayment)
        if ($netCheckoutPaid > 0) {
            $totalAmount = $netCheckoutPaid;
        } elseif ($depositAmount > 0 || $downpaymentAmount > 0) {
            // If no checkout payment, use deposit + downpayment
            $totalAmount = $depositAmount + $downpaymentAmount;
        } elseif ($depositAmount > 0 && abs($totalAmount - $depositAmount) <= 1.00) {
            // Fallback for deposit logic only if detailed payments missing
            $totalAmount = $depositAmount;
        }

        // Special handling for Reservation bookings (align with export_report.php):
        // - Reserved/Pending: Total Amount Booking = reservation fee only (500)
        // - Check-In/Checked Out: Total Amount Booking = reservation fee + check-in payment (e.g. 500 + 460 = 960)
        $isReservationBooking =
            isset($report['booking_type']) &&
            strcasecmp(trim((string) $report['booking_type']), 'Reservation') === 0;

        if ($isReservationBooking) {
            $statusDisplay = $report['status'] ?? '';

            // Reservation fee: prefer total_amount_reservation, then downpayment_amount, then discount/deposit_details.
            $reservationFee = floatval($report['total_amount_reservation'] ?? 0);
            if ($reservationFee <= 0) {
                $downpaymentFee = floatval($report['downpayment_amount'] ?? 0);
                if ($downpaymentFee > 0) {
                    $reservationFee = $downpaymentFee;
                } else {
                    $discountAmount = floatval($report['discount_amount'] ?? 0);
                    if ($discountAmount > 0) {
                        $reservationFee = $discountAmount;
                    } elseif ($depositAmount > 0) {
                        $reservationFee = $depositAmount;
                    }
                }
            }

            if ($statusDisplay === 'Reserved' || $statusDisplay === 'Pending' || $statusDisplay === 'Canceled') {
                $totalAmount = max(0, $reservationFee);
            } else {
                // For checked-in/checked-out Reservation rows, align with checkout paid amounts
                // so extension (and other add-ons) are reflected.
                $totalAmount = max(0, $netCheckoutPaid > 0 ? $netCheckoutPaid : resolveTotalAmount($report));
            }
        }

        // Deduct penalties and missing items from the total amount
        $totalAmount = max(0, $totalAmount - $penaltyAmount - $missingItemsFees);

        // Overall Amount: same rules as export_report.php — reservation checkouts need line-item
        // additionals (food/items/guest/pet) added to the booking slice; walk-ins already embed them in paid total.
        $checkOutStatus = $report['status'] ?? '';
        $isCanceledRow = strcasecmp(trim((string) $checkOutStatus), 'Canceled') === 0;
        $isCheckedOutOverall = strcasecmp(trim((string) $checkOutStatus), 'Checked Out') === 0;
        $reservationExtrasDetailed =
            $additionalSum + $additionalGuestPrice + $additionalPetPrice;
        // Business rule: canceled bookings should always show 0.00 amounts in exports.
        if ($isCanceledRow) {
            $totalAmount = 0;
            $totalAdditionalFees = 0;
            $overallAmount = 0;
        } else {
            if ($isReservationBooking && $isCheckedOutOverall) {
                // Reservation totals already align with checkout paid amounts,
                // so do NOT add reservation line-item extras again.
                $overallAmount = $totalAmount + $missingItemsFees + $penaltyAmount;
            } else {
                $overallAmount = $totalAmount + $missingItemsFees + $penaltyAmount;
            }
            $grandTotalOverall += $overallAmount;
            $grandTotalAmount += $totalAmount;
            // Accumulate downpayment totals (we now also include canceled reservations,
            // because their downpayment is non‑refundable and should stay in totals).
            $grandTotalDpCash += $downpaymentCash;
            $grandTotalDpGcash += $downpaymentGcash;
            $grandTotalDpMaya += $downpaymentMaya;
        }


        // Use the actual checked_out_at time for Check-Out Date column, fallback to scheduled check_out
        $checkOutTimestamp = $report['checked_out_at'] ?? $report['check_out'];
        $checkedOutDate = $checkOutTimestamp ? date('d/m/Y', strtotime($checkOutTimestamp)) : '';
        $checkedOutTime = $checkOutTimestamp ? date('h:i A', strtotime($checkOutTimestamp)) : '';

        // Check-in Time
        $checkInRaw = $report['check_in'];
        $checkInDate = $checkInRaw ? date('d/m/Y', strtotime($checkInRaw)) : '';
        $checkInTime = $checkInRaw ? date('h:i A', strtotime($checkInRaw)) : '';

        // Extract split amounts (checkout payment columns preferred)
        $cashAmt = parseAmount($report['payment_status_cash'] ?? '');
        $gcashAmt = parseAmount($report['payment_status_g_cash'] ?? '');
        $mayaAmt = parseAmount($report['payment_status_maya'] ?? '');

        // Fallback for Check-In/Confirmed rows (no checkout payment columns yet)
        if (($cashAmt + $gcashAmt + $mayaAmt) <= 0) {
            $cashAmt = floatval($report['deposit_cash'] ?? 0);
            $gcashAmt = floatval($report['deposit_g_cash'] ?? 0);
            $mayaAmt = floatval($report['deposit_maya'] ?? 0);
        }

        // Reservation check-in payments are stored in deposit_cash in your DB.
        // Show that value in the Cash column for Reservation + (Check-In / Checked Out)
        // when the normal checkout payment columns are empty.
        $statusDisplay = $report['status'] ?? '';
        if ($isReservationBooking && $statusDisplay !== 'Reserved' && $statusDisplay !== 'Pending') {
            $depositCash = floatval($report['deposit_cash'] ?? 0);
            if ($depositCash > 0 && ($cashAmt + $gcashAmt + $mayaAmt) <= 0) {
                $cashAmt = $depositCash;
            }
        }

        // Daily Sales detail table rule: canceled rows must show 0 for Cash/G-Cash/Maya columns.
        if (strcasecmp(trim((string) $statusDisplay), 'Canceled') === 0) {
            $cashAmt = 0.0;
            $gcashAmt = 0.0;
            $mayaAmt = 0.0;
        }

        // DO NOT add deposit to payment amounts - they should show checkout payment only
        // Deposit is already included in the Total Amount calculation

        // Check against totalAmount (use a small buffer for comparisons)
        $cashDisplay = $cashAmt > 0 ? number_format($cashAmt, 2) : '—';
        $gcashDisplay = $gcashAmt > 0 ? number_format($gcashAmt, 2) : '—';
        $mayaDisplay = $mayaAmt > 0 ? number_format($mayaAmt, 2) : '—';

        // Breakfast Aggregation
        $breakfastRaw = $report['breakfast'] ?? '';
        $breakfastDisplay = '—';

        if ($breakfastRaw && $breakfastRaw !== '' && $breakfastRaw !== 'None') {
            $bItems = explode('|', $breakfastRaw);
            $bAggregated = [];

            foreach ($bItems as $bItem) {
                $bItem = trim($bItem);
                if (empty($bItem))
                    continue;

                // 1. Try to match standard format with price: "1 Tocino - 150.00"
                if (preg_match('/^(\d+)\s+(.*?)\s*-\s*(?:₱|P)?([0-9,.]+)/u', $bItem, $m)) {
                    $qty = intval($m[1]);
                    $name = trim($m[2]); // Name part
                    $priceRaw = str_replace(',', '', $m[3]);
                    $price = floatval($priceRaw);
                }
                // 2. Try to match "Quantity Name" WITHOUT price (common for promos, e.g. "1 Tapa (Promo)")
                elseif (preg_match('/^(\d+)\s+(.*)$/u', $bItem, $m)) {
                    $qty = intval($m[1]);
                    $name = trim($m[2]);
                    $price = 0.0;
                }
                // 3. Fallback: Assume the whole string is the name, quantity 1
                else {
                    $qty = 1;
                    $name = $bItem;
                    $price = 0.0;
                }

                $key = strtoupper($name); // Case-insensitive key

                if (!isset($bAggregated[$key])) {
                    $bAggregated[$key] = [
                        'name' => $name,
                        'qty' => 0,
                        'price' => 0.0
                    ];
                }
                $bAggregated[$key]['qty'] += $qty;
                $bAggregated[$key]['price'] += $price;
            }

            // Rebuild string
            $bParts = [];
            foreach ($bAggregated as $item) {
                // Determine display format: "2 Tocino - 300.00"
                $priceStr = '';
                if ($item['price'] > 0) {
                    $priceStr = ' - ' . number_format($item['price'], 2);
                }
                $bParts[] = $item['qty'] . ' ' . $item['name'] . $priceStr;
            }
            $breakfastDisplay = htmlspecialchars(implode(' | ', $bParts));
        }
        // Duration display - show extended info if present (matching export_report.php)
        $baseDuration = intval($report['duration'] ?? 0);
        $baseUnit = htmlspecialchars($report['duration_unit'] ?? 'hours');
        $extHours = intval($report['extend_hours'] ?? 0);
        $extMinutes = intval($report['extend_minutes'] ?? 0);
        $extPrice = floatval($report['extend_price'] ?? 0);

        // Check if promo is selected - extract duration from promo string
        $promoValue = $report['promo'] ?? '';
        $hasPromo = !empty($promoValue) && $promoValue !== 'None' && $promoValue !== 'Select Promo';

        // If promo is selected and duration is 0, extract duration from promo string
        if ($hasPromo && $baseDuration == 0) {
            // Try to extract hours from promo string (e.g., "Package 1 12hrs" or "Package 2 24hrs")
            if (preg_match('/(\d+)\s*hrs?/i', $promoValue, $matches)) {
                $baseDuration = intval($matches[1]);
            } else {
                // Default to 12 hours if no duration found in promo string
                $baseDuration = 12;
            }
        }

        if ($extHours > 0 || $extMinutes > 0) {
            $baseTotalMinutes = $baseDuration * 60;
            $extTotalMinutes = ($extHours * 60) + $extMinutes;
            $grandTotalMinutes = $baseTotalMinutes + $extTotalMinutes;
            $displayHours = intdiv($grandTotalMinutes, 60);
            $displayMinutes = $grandTotalMinutes % 60;
            $durationDisplay = $displayHours . ':' . str_pad($displayMinutes, 2, '0', STR_PAD_LEFT) . ' ' . $baseUnit . ' (Extended)';
        } else {
            $durationDisplay = $baseDuration . ' ' . $baseUnit;
        }

        // Extension Duration formatted display
        if ($extHours > 0 && $extMinutes > 0) {
            $extDurationDisplay = $extHours . ':' . str_pad($extMinutes, 2, '0', STR_PAD_LEFT) . ' Hr = ' . number_format($extPrice, 0);
        } elseif ($extHours > 0) {
            $extDurationDisplay = $extHours . ' Hr = ' . number_format($extPrice, 0);
        } elseif ($extMinutes > 0) {
            $extDurationDisplay = $extMinutes . ' Mins = ' . number_format($extPrice, 0);
        } else {
            $extDurationDisplay = '—';
        }

        $additionalGuestDisplay = $additionalGuest > 0
            ? $additionalGuest . ' (' . number_format($additionalGuestPrice, 2) . ')'
            : '—';

        $additionalPetDisplay = $additionalPet > 0
            ? $additionalPet . ' (' . number_format($additionalPetPrice, 2) . ')'
            : '—';

        echo '<tr>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($report['booking_id'] ?? '') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($report['room_id'] ?? '') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($report['encoder'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($report['booking_type'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($report['room_type'] ?? '') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($report['guest_type'] ?? '—') . '</td>';

        $pMeta = parsePromoSelection($report['promo'] ?? '');
        $promoDisplay = $pMeta['title'] ?: '—';
        echo '<td style="padding: 5px;">' . htmlspecialchars($promoDisplay) . '</td>';

        // Discount Amount from database
        $discountAmount = floatval($report['discount_amount'] ?? 0);
        $discountDisplay = $discountAmount > 0 ? number_format($discountAmount, 2) : '—';
        echo '<td style="padding: 5px;">' . $discountDisplay . '</td>';

        echo '<td style="padding: 5px;">' . htmlspecialchars($report['guest_name'] ?? '') . '</td>';
        $reservationDate = $report['reservation_date'] ? date('d/m/Y', strtotime($report['reservation_date'])) : '—';
        echo '<td style="padding: 5px;">' . $reservationDate . '</td>';
        echo '<td style="padding: 5px;">' . $checkInDate . '</td>';
        echo '<td style="padding: 5px;">' . $checkInTime . '</td>';
        echo '<td style="padding: 5px;">' . $checkedOutDate . '</td>';
        echo '<td style="padding: 5px;">' . $checkedOutTime . '</td>';
        echo '<td style="padding: 5px;">' . $durationDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $extDurationDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $breakfastDisplay . '</td>';

        $statusDisplay = $report['status'] ?? '';
        if ($statusDisplay === 'Confirmed') {
            $statusDisplay = 'Check-In';
        }
        echo '<td style="padding: 5px;">' . htmlspecialchars($statusDisplay) . '</td>';

        echo '<td style="padding: 5px;">' . $paymentMethod . '</td>';
        echo '<td style="padding: 5px;">' . $cashDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $gcashDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $mayaDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $referenceNo . '</td>';
        echo '<td style="padding: 5px;">' . $dpPaymentDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $dpCashDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $dpGcashDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $dpMayaDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $dpRefDisplay . '</td>';
        echo '<td style="padding: 5px;">' . number_format($totalAmount, 2) . '</td>';

        // Output Additional cols
        echo '<td style="padding: 5px;">' . $additionalItemsDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $additionalFoodDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $additionalGuestDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $additionalPetDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $missingItemsDisplay . '</td>';
        echo '<td style="padding: 5px;">' . $penaltyDisplay . '</td>';
        echo '<td style="padding: 5px; mso-number-format:\'\\#\\,\\#\\#0\\.00\';">' . number_format($totalAdditionalFees, 2) . '</td>';
        echo '<td style="padding: 5px; mso-number-format:\'\\#\\,\\#\\#0\\.00\';">' . number_format($overallAmount, 2) . '</td>';

        echo '</tr>';
    }

    // Grand Total Row
    echo '<tr style="background-color: #e0e0e0; font-weight: bold;">';
    echo '<td colspan="19" style="padding: 8px; text-align: right;">GRAND TOTAL</td>';

    // Calculate total payments - checkout only (no deposits)
    $sumCash = $overallCash;
    $sumGcash = $overallGcash;
    $sumMaya = $overallMaya;

    echo '<td style="padding: 8px;">' . number_format($sumCash, 2) . '</td>';
    echo '<td style="padding: 8px;">' . number_format($sumGcash, 2) . '</td>';
    echo '<td style="padding: 8px;">' . number_format($sumMaya, 2) . '</td>';
    echo '<td style="padding: 8px;">—</td>'; // Reference No.
    echo '<td style="padding: 8px;">—</td>'; // DP Payment
    echo '<td style="padding: 8px;">' . number_format($grandTotalDpCash, 2) . '</td>'; // DP Cash
    echo '<td style="padding: 8px;">' . number_format($grandTotalDpGcash, 2) . '</td>'; // DP G-Cash
    echo '<td style="padding: 8px;">' . number_format($grandTotalDpMaya, 2) . '</td>'; // DP Maya
    echo '<td style="padding: 8px;">—</td>'; // DP Ref
    echo '<td style="padding: 8px;">' . number_format($grandTotalAmount, 2) . '</td>'; // Total Amount
    echo '<td style="padding: 8px;">—</td>'; // Additional Items
    echo '<td style="padding: 8px;">—</td>'; // Additional Foods
    echo '<td style="padding: 8px;">—</td>'; // Additional Guest
    echo '<td style="padding: 8px;">—</td>'; // Additional Pet
    echo '<td style="padding: 8px;">—</td>'; // Additional Missing Items
    echo '<td style="padding: 8px;">—</td>'; // Additional Penalty
    echo '<td style="padding: 8px; font-weight: bold; mso-number-format:\'\\#\\,\\#\\#0\\.00\';">' . number_format($grandTotalAdditional, 2) . '</td>'; // Additional Total Amount Fees
    echo '<td style="padding: 8px; font-weight: bold; mso-number-format:\'\\#\\,\\#\\#0\\.00\';">' . number_format($grandTotalOverall, 2) . '</td>'; // Overall Amount
    echo '</tr>';

    // Grand Additional Amount Row - aligned to Additional Total Amount Fees column
    echo '<tr class="total-row">';
    echo '<td colspan="35" style="padding: 8px; text-align: right;">Grand Additional Amount:</td>';
    echo '<td style="padding: 8px; text-align: right; mso-number-format:\'\\#\\,\\#\\#0\\.00\';">' . number_format($grandTotalAdditional, 2) . '</td>';
    echo '<td></td>'; // Overall Amount column (empty)
    echo '</tr>';

    if ($isPdf) {
        echo '</tbody>';
    }

    echo '</table></body></html>';

} catch (PDOException $e) {
    echo "Error generating report: " . $e->getMessage();
}
?>