<?php
// Download TCPDF library from: https://github.com/tecnickcom/TCPDF/archive/refs/heads/main.zip
// Extract and place in: c:\xampp\htdocs\HMS9\tcpdf\
// Or we'll use a simpler approach with DomPDF-lite functionality

require_once 'config.php';
require_once 'report_helpers.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// ============= Generate HTML Content =============
ob_start();

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
            // Format as: date time (single line for PDF)
            return $dt->format('m/d/Y h:i a');
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

    function getDiscountAmountForPaymentTimestamp($payment, $paymentDateStr, $totalRows = 1): float
    {
        $discountAmount = 0.0;
        $discountHistoryRaw = trim((string) ($payment['discount_amount_history'] ?? ''));
        $paymentDate = strlen($paymentDateStr) >= 10 ? substr($paymentDateStr, 0, 10) : $paymentDateStr;

        if ($discountHistoryRaw !== '') {
            foreach (parseDiscountAmountHistory($discountHistoryRaw) as $parsedEntry) {
                $discAmt = floatval($parsedEntry['amount'] ?? 0);
                $discDateTime = trim((string) ($parsedEntry['datetime'] ?? ''));
                $discDate = strlen($discDateTime) >= 10 ? substr($discDateTime, 0, 10) : $discDateTime;
                if ($discDate === $paymentDate) {
                    $discountAmount += $discAmt;
                }
            }
        } else {
            $discountAmount = getBookingTotalDiscountFromRecord($payment);
            if ($totalRows > 1 && $discountAmount > 0) {
                $discountAmount = $discountAmount / $totalRows;
            }
        }

        return $discountAmount;
    }

    $dataRows = [];
    $grandTotal = 0;
    $groupedData = [];

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

    $dataRows = normalizeAllReservationPaymentExportRows($dataRows, $payments);

    // Group rows by booking_id, date, and payment_method
    $groupedData = [];
    foreach ($dataRows as $row) {
        $key = $row['booking_id'] . '|' . $row['date'] . '|' . $row['payment_method'];
        
        if (!isset($groupedData[$key])) {
            $groupedData[$key] = $row;
        } else {
            $groupedData[$key]['amount'] += $row['amount'];
        }
    }
    
    $dataRows = array_values($groupedData);
    $dataRows = applyCanceledBookingFinancialsToRows($dataRows);
    $grandTotal = sumPaymentRowAmounts($dataRows);

    // Check if turnover_records table exists
    $checkTurnoverTable = $conn->query("SHOW TABLES LIKE 'turnover_records'");
    $hasTurnoverTable = $checkTurnoverTable->rowCount() > 0;
    
    $turnoverRecords = [];
    $totalCashTurnover = 0;
    
    if ($hasTurnoverTable) {
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
        
        foreach ($turnoverRecords as $turnover) {
            $totalCashTurnover += floatval($turnover['cash_amount'] ?? 0);
        }
    }

} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit;
}

$htmlContent = ob_get_clean();

// ============= Set PDF Headers and Output =============
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Daily_Sales_Report_' . $startDate . '_to_' . $endDate . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Simple message - User needs to use browser's Print to PDF
// For true PDF generation, we need a library like TCPDF, mPDF, or DomPDF
echo "To generate PDF: Please use your browser's Print function (Ctrl+P) and select 'Save as PDF'.\n\n";
echo "Or install a PDF library like TCPDF for automatic PDF generation.\n";
echo "This file currently generates HTML optimized for PDF printing.";
exit;
?>
