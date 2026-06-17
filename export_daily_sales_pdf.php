<?php
require_once 'config.php';
require_once 'report_helpers.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

try {
    // Ensure payment amount history columns exist.
    $histColumns = [
        'payment_amount_cash_history',
        'payment_amount_g_cash_history',
        'payment_amount_maya_history',
        'payment_amount_instapay_history',
        'payment_amount_online_banking_history',
        'payment_amount_airbnb_history',
        'discount_amount_history'
    ];
    foreach ($histColumns as $colName) {
        try {
            $chk = $conn->query("SHOW COLUMNS FROM reports LIKE '" . $colName . "'");
            if ($chk && $chk->rowCount() == 0) {
                $conn->exec("ALTER TABLE reports ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
            }
        } catch (PDOException $e) {
            // If migration fails, exports will fall back to old heuristic parsing.
        }
    }

    // Fetch all paid reports in the date range
    $stmt = $conn->prepare("
        SELECT 
            r.booking_id,
            r.payment_date_time,
            DATE(COALESCE(NULLIF(TRIM(SUBSTRING_INDEX(r.payment_date_time, '|', 1)), ''), r.check_in)) as payment_date,
            r.check_in,
            r.checked_out_at,
            r.guest_name,
            r.room_id,
            r.status,
            r.encoder,
            r.payment_status,
            r.payment_status_cash,
            r.payment_status_g_cash,
            r.payment_status_maya,
            r.payment_status_instapay,
            r.payment_status_online_banking,
            r.payment_status_airbnb,
            r.payment_amount_cash_history,
            r.payment_amount_g_cash_history,
            r.payment_amount_maya_history,
            r.payment_amount_instapay_history,
            r.payment_amount_online_banking_history,
            r.payment_amount_airbnb_history,
            r.deposit_cash,
            r.deposit_g_cash,
            r.deposit_maya,
            r.deposit_instapay,
            r.deposit_online_banking,
            r.deposit_airbnb,
            r.downpayment_cash,
            r.downpayment_gcash,
            r.downpayment_maya,
            r.downpayment_instapay,
            r.downpayment_online_banking,
            r.downpayment_airbnb,
            r.downpayment_date,
            COALESCE(r.booking_type, b.booking_type) AS booking_type,
            r.extension_withdraw,
            r.withdrawn_extend_price,
            r.discount_amount,
            COALESCE(r.discount_amount_history, b.discount_amount_history) AS discount_amount_history
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
        WHERE (
                (r.payment_date_time IS NOT NULL AND TRIM(r.payment_date_time) <> '')
                OR (r.downpayment_date IS NOT NULL)
            )
          AND (
                r.paid_status = 'Paid'
                OR (
                    COALESCE(r.deposit_cash, 0) + COALESCE(r.deposit_g_cash, 0) + COALESCE(r.deposit_maya, 0)
                    + COALESCE(r.deposit_instapay, 0) + COALESCE(r.deposit_online_banking, 0) + COALESCE(r.deposit_airbnb, 0)
                    + COALESCE(r.downpayment_cash, 0) + COALESCE(r.downpayment_gcash, 0) + COALESCE(r.downpayment_maya, 0)
                    + COALESCE(r.downpayment_instapay, 0) + COALESCE(r.downpayment_online_banking, 0) + COALESCE(r.downpayment_airbnb, 0)
                ) > 0.005
            )
        ORDER BY COALESCE(r.payment_date_time, r.downpayment_date) ASC, r.booking_id ASC
    ");

    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function parsePaymentAmountsAll($paymentString): array
    {
        if (empty($paymentString) || !is_string($paymentString))
            return [];
        preg_match_all('/₱\s*([\d,]+(?:\.\d+)?)/', $paymentString, $matches);
        if (empty($matches[1]))
            return [];
        $out = [];
        foreach ($matches[1] as $raw) {
            $out[] = floatval(str_replace(',', '', $raw));
        }
        return $out;
    }

    function formatTimestampForExport($rawTimestamp): array
    {
        $rawTimestamp = is_string($rawTimestamp) ? trim($rawTimestamp) : '';
        if ($rawTimestamp === '')
            return ['date' => 'N/A', 'payment_date_time' => 'N/A'];

        try {
            $dt = new DateTime($rawTimestamp);
            return [
                'date' => $dt->format('m/d/Y'),
                'payment_date_time' => $dt->format('m/d/Y') // Only date, no time
            ];
        } catch (Exception $e) {
            return ['date' => 'N/A', 'payment_date_time' => $rawTimestamp];
        }
    }

    function formatDateTimeDisplay($rawTimestamp): string
    {
        if (empty($rawTimestamp) || !is_string($rawTimestamp)) {
            return '—';
        }
        $rawTimestamp = trim($rawTimestamp);
        if ($rawTimestamp === '' || $rawTimestamp === '0000-00-00 00:00:00') {
            return '—';
        }
        try {
            $dt = new DateTime($rawTimestamp);
            // Format as: date<br>time
            return $dt->format('m/d/Y') . '<br>' . $dt->format('h:i a');
        } catch (Exception $e) {
            return '—';
        }
    }

    function perPaymentReportRowInDateRange(array $row, string $startDate, string $endDate): bool
    {
        try {
            $start = new DateTime($startDate . ' 00:00:00');
            $end = new DateTime($endDate . ' 23:59:59');
        } catch (Exception $e) {
            return true;
        }

        // Only check payment_date_time timestamps (actual payment dates)
        // Do NOT check check_in or downpayment_date as those may be outside the payment range
        if (!empty($row['payment_date_time'])) {
            foreach (explode('|', (string) $row['payment_date_time']) as $seg) {
                $s = trim($seg);
                if ($s !== '') {
                    try {
                        $dt = new DateTime($s);
                        if ($dt >= $start && $dt <= $end) {
                            return true;
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // If payment_date_time is empty, check downpayment_date
        if (empty($row['payment_date_time']) && !empty($row['downpayment_date'])) {
            try {
                $dt = new DateTime(trim((string) $row['downpayment_date']));
                if ($dt >= $start && $dt <= $end) {
                    return true;
                }
            } catch (Exception $e) {
                // Continue to next check
            }
        }

        return false;
    }

    $payments = array_values(array_filter($payments, function ($r) use ($startDate, $endDate) {
        return perPaymentReportRowInDateRange($r, $startDate, $endDate);
    }));

    function allocateAmountsToPaymentTimestamps(array $timestamps, array $methodAmounts, float $baseAmount): array
    {
        $n = count($timestamps);
        if ($n === 0)
            return [];

        if (count($methodAmounts) === 0) {
            if ($baseAmount > 0) {
                return array_merge([$baseAmount], array_fill(0, $n - 1, 0));
            }
            return array_fill(0, $n, 0);
        }

        if (count($methodAmounts) === $n) {
            return array_values(array_map(fn($v) => max(0, floatval($v)), $methodAmounts));
        }

        if (count($methodAmounts) === 1) {
            $total = max(0, floatval($methodAmounts[0]));
            $base = max(0, floatval($baseAmount));

            $first = ($base > 0 && $base <= $total) ? $base : (($base > 0) ? min($base, $total) : 0);
            if ($n === 1)
                return [$total];
            if ($first <= 0) {
                $per = $n > 0 ? $total / $n : 0;
                return array_fill(0, $n, $per);
            }

            $remainder = max(0, $total - $first);
            $restCount = $n - 1;
            $perRest = $restCount > 0 ? ($remainder / $restCount) : 0;
            return array_merge([$first], array_fill(0, $restCount, $perRest));
        }

        $out = array_fill(0, $n, 0);
        $limit = min(count($methodAmounts), $n);
        for ($i = 0; $i < $limit; $i++) {
            $out[$i] = max(0, floatval($methodAmounts[$i]));
        }
        return $out;
    }

    function isDateInRange($dateString, $startDate, $endDate): bool
    {
        try {
            $date = new DateTime($dateString);
            $start = new DateTime($startDate . ' 00:00:00');
            $end = new DateTime($endDate . ' 23:59:59');
            return ($date >= $start && $date <= $end);
        } catch (Exception $e) {
            return false;
        }
    }

    function normalizeExportTimestamp(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return (new DateTime($raw))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    function getDiscountAmountForPaymentTimestamp($payment, $paymentDateStr, $totalRows = 1): float
    {
        $discountAmount = 0.0;
        $discountHistoryRaw = trim((string) ($payment['discount_amount_history'] ?? ''));
        $paymentDate = strlen($paymentDateStr) >= 10 ? substr($paymentDateStr, 0, 10) : $paymentDateStr;

        if ($discountHistoryRaw !== '') {
            // Parse discount history: format is "amount:datetime|amount:datetime"
            foreach (explode('|', $discountHistoryRaw) as $discEntry) {
                $discEntry = trim($discEntry);
                if ($discEntry === '')
                    continue;
                $discParts = explode(':', $discEntry, 2);
                $discAmt = floatval($discParts[0] ?? 0);
                $discDateTime = isset($discParts[1]) ? trim($discParts[1]) : '';
                $discDate = strlen($discDateTime) >= 10 ? substr($discDateTime, 0, 10) : $discDateTime;
                if ($discDate === $paymentDate) {
                    $discountAmount += $discAmt;
                }
            }
        } else {
            // No history, use flat discount amount and divide by number of payment rows
            $discountAmount = floatval($payment['discount_amount'] ?? 0);
            if ($totalRows > 1 && $discountAmount > 0) {
                $discountAmount = $discountAmount / $totalRows;
            }
        }

        return $discountAmount;
    }

    $dataRows = [];
    $grandTotal = 0;
    $groupedData = []; // For grouping same payment method and date

    foreach ($payments as $payment) {
        $bookingId = $payment['booking_id'] ?: 'N/A';
        $guestName = $payment['guest_name'] ?: 'N/A';
        $roomId = $payment['room_id'] ?: 'N/A';
        $status = $payment['status'] ?: 'N/A';
        $encoder = $payment['encoder'] ?: 'N/A';
        $checkIn = formatDateTimeDisplay($payment['check_in'] ?? '');
        $checkOut = formatDateTimeDisplay($payment['checked_out_at'] ?? '');

        if (strcasecmp($status, 'Confirmed') === 0) {
            $status = 'Check-in';
        }

        $timestampRows = [];
        if (!empty($payment['payment_date_time'])) {
            $rawTimestamps = explode('|', (string) $payment['payment_date_time']);
            foreach ($rawTimestamps as $ts) {
                $ts = trim($ts);
                if ($ts === '') {
                    continue;
                }
                $timestampRows[] = array_merge(formatTimestampForExport($ts), ['raw' => $ts]);
            }
        }
        if (empty($timestampRows) && !empty($payment['downpayment_date'])) {
            $downpaymentDate = (string) $payment['downpayment_date'];
            $timestampRows[] = array_merge(formatTimestampForExport($downpaymentDate), ['raw' => $downpaymentDate]);
        }

        if (empty($timestampRows)) {
            $timestampRows[] = [
                'date' => $payment['payment_date'] ?: 'N/A',
                'payment_date_time' => 'N/A',
                'raw' => ''
            ];
        }

        $nTimestamps = count($timestampRows);

        // Process Cash
        $depositCash = floatval($payment['deposit_cash'] ?? 0);
        $downpaymentCash = floatval($payment['downpayment_cash'] ?? 0);
        $totalCash = max($depositCash, $downpaymentCash);

        $cashHistoryArr = !empty($payment['payment_amount_cash_history'])
            ? explode('|', (string) $payment['payment_amount_cash_history'])
            : null;

        if (is_array($cashHistoryArr) && count($cashHistoryArr) === $nTimestamps) {
            $cashAmountsByTimestamp = array_map(fn($v) => floatval($v), $cashHistoryArr);
        } else {
            $cashMethodAmounts = parsePaymentAmountsAll($payment['payment_status_cash']);
            $cashAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $cashMethodAmounts, $totalCash);
        }

        $cashLikeMethodLabel = 'Cash';
        $cashStatusRaw = (string) ($payment['payment_status_cash'] ?? '');
        if (stripos($cashStatusRaw, 'Instapay') !== false) {
            $cashLikeMethodLabel = 'Instapay';
        } elseif (stripos($cashStatusRaw, 'Online Banking') !== false) {
            $cashLikeMethodLabel = 'Online Banking';
        } elseif (stripos($cashStatusRaw, 'Airbnb') !== false) {
            $cashLikeMethodLabel = 'Airbnb';
        }

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $cashAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0 && isDateInRange($tsRow['date'], $startDate, $endDate)) {
                $rowDiscount = getDiscountAmountForPaymentTimestamp(
                    $payment,
                    (string) ($tsRow['raw'] ?? ''),
                    $nTimestamps
                );
                $dataRows[] = [
                    'booking_id' => $bookingId,
                    'encoder' => $encoder,
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'guest_name' => $guestName,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'room_id' => $roomId,
                    'status' => $status,
                    'discount_amount' => $rowDiscount,
                    'payment_method' => $cashLikeMethodLabel,
                    'amount' => $amt
                ];
                $grandTotal += $amt;
            }
        }

        // Process other payment methods
        $paymentMethods = [
            ['name' => 'G-Cash', 'deposit' => 'deposit_g_cash', 'downpayment' => 'downpayment_gcash', 'status' => 'payment_status_g_cash', 'history' => 'payment_amount_g_cash_history'],
            ['name' => 'Maya', 'deposit' => 'deposit_maya', 'downpayment' => 'downpayment_maya', 'status' => 'payment_status_maya', 'history' => 'payment_amount_maya_history'],
            ['name' => 'Instapay', 'deposit' => 'deposit_instapay', 'downpayment' => 'downpayment_instapay', 'status' => 'payment_status_instapay', 'history' => 'payment_amount_instapay_history'],
            ['name' => 'Online Banking', 'deposit' => 'deposit_online_banking', 'downpayment' => 'downpayment_online_banking', 'status' => 'payment_status_online_banking', 'history' => 'payment_amount_online_banking_history'],
            ['name' => 'Airbnb', 'deposit' => 'deposit_airbnb', 'downpayment' => 'downpayment_airbnb', 'status' => 'payment_status_airbnb', 'history' => 'payment_amount_airbnb_history'],
        ];

        foreach ($paymentMethods as $method) {
            $deposit = floatval($payment[$method['deposit']] ?? 0);
            $downpayment = floatval($payment[$method['downpayment']] ?? 0);
            $total = max($deposit, $downpayment);

            $historyArr = !empty($payment[$method['history']])
                ? explode('|', (string) $payment[$method['history']])
                : null;

            if (is_array($historyArr) && count($historyArr) === $nTimestamps) {
                $amountsByTimestamp = array_map(fn($v) => floatval($v), $historyArr);
            } else {
                $methodAmounts = parsePaymentAmountsAll($payment[$method['status']]);
                $amountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $methodAmounts, $total);
            }

            foreach ($timestampRows as $idx => $tsRow) {
                $amt = $amountsByTimestamp[$idx] ?? 0;
                if ($amt > 0 && isDateInRange($tsRow['date'], $startDate, $endDate)) {
                    $rowDiscount = getDiscountAmountForPaymentTimestamp(
                        $payment,
                        (string) ($tsRow['raw'] ?? ''),
                        $nTimestamps
                    );
                    $dataRows[] = [
                        'booking_id' => $bookingId,
                        'encoder' => $encoder,
                        'date' => $tsRow['date'],
                        'payment_date_time' => $tsRow['payment_date_time'],
                        'guest_name' => $guestName,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'room_id' => $roomId,
                        'status' => $status,
                        'discount_amount' => $rowDiscount,
                        'payment_method' => $method['name'],
                        'amount' => $amt
                    ];
                    $grandTotal += $amt;
                }
            }
        }
    }

    // Normalize per-payment rows before grouping (duplicate downpayment must not inflate totals).
    $dataRows = normalizeAllReservationPaymentExportRows($dataRows, $payments);

    // Group rows by booking_id, date, and payment_method
    $groupedData = [];
    foreach ($dataRows as $row) {
        $key = $row['booking_id'] . '|' . $row['date'] . '|' . $row['payment_method'];
        
        if (!isset($groupedData[$key])) {
            $groupedData[$key] = $row;
        } else {
            // Only sum the amounts for same booking_id, date, and payment_method
            // Do NOT sum discount_amount
            $groupedData[$key]['amount'] += $row['amount'];
        }
    }
    
    // Convert grouped data back to indexed array
    $dataRows = array_values($groupedData);
    $dataRows = applyCanceledBookingFinancialsToRows($dataRows);
    $grandTotal = sumPaymentRowAmounts($dataRows);

} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Sales 2.0 Report - PDF</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 0px;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #000000ff;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .header .info {
            color: #666;
            font-size: 13px;
            margin: 5px 0;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .print-btn:hover {
            background: #45a049;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }

        th {
            background: #4CAF50;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #45a049;
        }

        td {
            padding: 8px;
            border: 1px solid #ddd;
            background: white;
        }

        tr:nth-child(even) td {
            background: #f9f9f9;
        }

        .total-row td {
            background: #e8f5e9 !important;
            font-weight: bold;
            border-top: 2px solid #4CAF50;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: 10px;
            }

            .print-btn {
                display: none;
            }

            @page {
                size: landscape;
                margin: 0.5in;
            }
        }
    </style>
</head>

<body>
    <!-- <button class="print-btn" onclick="window.print()">Print / Save as PDF</button> -->

    <div class="container">
        <div class="header">
            <h1>Daily Sales 2.0 Report</h1>
            <div class="info" style="color: #000000ff; font-weight: bold;">
                <?php echo htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Room ID</th>
                    <th>Encoder</th>
                    <th>Guest Name</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Status</th>
                    <th>Discount Amount</th>
                    <th>Payment Method</th>
                    <th>Payment Date</th>
                    <th>Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dataRows)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 20px; color: #999;">
                            No payment records found for the selected date range.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    // Group rows by booking_id to calculate rowspan
                    $groupedRows = [];
                    foreach ($dataRows as $row) {
                        $bookingId = $row['booking_id'];
                        if (!isset($groupedRows[$bookingId])) {
                            $groupedRows[$bookingId] = [];
                        }
                        $groupedRows[$bookingId][] = $row;
                    }

                    // Output rows with rowspan for booking_id
                    foreach ($groupedRows as $bookingId => $rows):
                        $rowCount = count($rows);
                        foreach ($rows as $index => $row):
                            ?>
                            <tr>
                                <?php if ($index === 0): ?>
                                    <td style="vertical-align: top;" rowspan="<?php echo $rowCount; ?>">
                                        <?php echo htmlspecialchars($row['booking_id']); ?>
                                    </td>
                                    <td style="vertical-align: top;" rowspan="<?php echo $rowCount; ?>">
                                        <?php echo htmlspecialchars($row['room_id']); ?>
                                    </td>
                                    <td style="vertical-align: top;" rowspan="<?php echo $rowCount; ?>">
                                        <?php echo htmlspecialchars($row['encoder']); ?>
                                    </td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                                <td><?php echo $row['check_in']; ?></td>
                                <td><?php echo $row['check_out']; ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td>₱<?php echo number_format($row['discount_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_date_time']); ?></td>
                                <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                            <?php
                        endforeach;
                    endforeach;
                    ?>
                    <tr class="total-row">
                        <td colspan="10" style="text-align: right;">GRAND TOTAL:</td>
                        <td>₱<?php echo number_format($grandTotal, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
        // ========== TURNOVER RECORDS SECTION ==========
        // Check if turnover_records table exists
        $checkTurnoverTable = $conn->query("SHOW TABLES LIKE 'turnover_records'");
        $hasTurnoverTable = $checkTurnoverTable->rowCount() > 0;

        if ($hasTurnoverTable) {
            // Fetch turnover records for the selected date range
            $turnoverStmt = $conn->prepare("
                SELECT 
                    t.id,
                    t.session_id,
                    t.username,
                    t.cash_amount,
                    t.total_amount,
                    t.turnover_at,
                    u.first_name,
                    u.last_name
                FROM turnover_records t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE DATE(t.turnover_at) BETWEEN :start AND :end
                ORDER BY t.turnover_at DESC
            ");
            $turnoverStmt->bindParam(':start', $startDate);
            $turnoverStmt->bindParam(':end', $endDate);
            $turnoverStmt->execute();
            $turnoverRecords = $turnoverStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($turnoverRecords) > 0) {
                $totalCashTurnover = 0;
                ?>
                <div style="margin-top: 30px;">
                    <h3 style="font-size: 15px; font-weight: bold; margin-bottom: 10px; color: #000;">Cash Turnover Records</h3>
                    <div style="font-size: 12px;"> 
                        <!-- Header Row -->
                        <div style="line-height: 1.8; margin-bottom: 5px;">
                            <span style="display: inline-block; width: 180px;"><strong>Employee name</strong></span>
                            <span style="display: inline-block; width: 150px;"><strong>Turn-over Date</strong></span>
                            <span style="display: inline-block; width: 150px;"><strong>Turn-over Time</strong></span>
                            <span style="display: inline-block; width: 120px;"><strong>Cash Amount</strong></span>
                        </div>
                        <?php
                        foreach ($turnoverRecords as $turnover) {
                            $employeeName = trim(($turnover['first_name'] ?? '') . ' ' . ($turnover['last_name'] ?? ''));
                            if (empty($employeeName)) {
                                $employeeName = $turnover['username'] ?? 'N/A';
                            }

                            $turnoverDate = '—';
                            $turnoverTime = '—';
                            if (!empty($turnover['turnover_at'])) {
                                $dt = new DateTime($turnover['turnover_at']);
                                $turnoverDate = $dt->format('d/m/Y');
                                $turnoverTime = $dt->format('h:i a');
                            }

                            $cashAmt = floatval($turnover['cash_amount'] ?? 0);
                            $totalCashTurnover += $cashAmt;
                            ?>
                            <!-- Data Row -->
                            <div style="line-height: 1.8;">
                                <span
                                    style="display: inline-block; width: 180px;"><?php echo htmlspecialchars($employeeName); ?></span>
                                <span
                                    style="display: inline-block; width: 150px;"><?php echo htmlspecialchars($turnoverDate); ?></span>
                                <span
                                    style="display: inline-block; width: 150px;"><?php echo htmlspecialchars($turnoverTime); ?></span>
                                <span style="display: inline-block; width: 120px;">₱<?php echo number_format($cashAmt, 2); ?></span>
                            </div>
                        <?php } ?>
                        <!-- Total Row -->
                        <div style="line-height: 1.8; margin-top: 8px;">
                            <span style="display: inline-block; width: 180px;"></span>
                            <span style="display: inline-block; width: 150px;"></span>
                            <span style="display: inline-block; width: 150px;"><strong>Total Turnover:</strong></span>
                            <span
                                style="display: inline-block; width: 120px;"><strong>₱<?php echo number_format($totalCashTurnover, 2); ?></strong></span>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>
</body>

</html>
