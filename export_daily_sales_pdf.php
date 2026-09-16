<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
require_once 'report_helpers.php';
require_once 'fpdf.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Resolve who is exporting
$_exp_first = trim($_SESSION['first_name'] ?? '');
$_exp_last  = trim($_SESSION['last_name'] ?? '');
$_exp_user  = ($_exp_first !== '' || $_exp_last !== '') ? trim($_exp_first . ' ' . $_exp_last) : trim($_SESSION['username'] ?? 'Unknown');

logActivity($conn, 'report', 'REPORT_VIEW_PDF',
    "Daily Sales PDF viewed by {$_exp_user} for {$startDate} to {$endDate}",
    ['start_date' => $startDate, 'end_date' => $endDate, 'format' => 'PDF', 'viewed_by' => $_exp_user]
);

// Helper functions
function parsePaymentAmountsAll($paymentString): array {
    if (empty($paymentString) || !is_string($paymentString)) return [];
    preg_match_all('/₱\s*([\d,]+(?:\.\d+)?)/', $paymentString, $matches);
    if (empty($matches[1])) return [];
    $out = [];
    foreach ($matches[1] as $raw) {
        $out[] = floatval(str_replace(',', '', $raw));
    }
    return $out;
}

function formatTimestampForExport($rawTimestamp): array {
    $rawTimestamp = is_string($rawTimestamp) ? trim($rawTimestamp) : '';
    if ($rawTimestamp === '') return ['date' => 'N/A', 'payment_date_time' => 'N/A'];
    try {
        $dt = new DateTime($rawTimestamp);
        return ['date' => $dt->format('m/d/Y'), 'payment_date_time' => $dt->format('m/d/Y')];
    } catch (Exception $e) {
        return ['date' => 'N/A', 'payment_date_time' => $rawTimestamp];
    }
}

function formatDateTimeDisplayPDF($rawTimestamp): string {
    if (empty($rawTimestamp) || !is_string($rawTimestamp)) return '-';
    $rawTimestamp = trim($rawTimestamp);
    if ($rawTimestamp === '' || $rawTimestamp === '0000-00-00 00:00:00') return '-';
    try {
        $dt = new DateTime($rawTimestamp);
        return $dt->format('m/d/Y') . "\n" . $dt->format('h:i a');
    } catch (Exception $e) {
        return '-';
    }
}

function perPaymentReportRowInDateRange(array $row, string $startDate, string $endDate): bool {
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
                    if ($dt >= $start && $dt <= $end) return true;
                } catch (Exception $e) { continue; }
            }
        }
    }
    if (empty($row['payment_date_time']) && !empty($row['downpayment_date'])) {
        try {
            $dt = new DateTime(trim((string) $row['downpayment_date']));
            if ($dt >= $start && $dt <= $end) return true;
        } catch (Exception $e) {}
    }
    return false;
}

function allocateAmountsToPaymentTimestamps(array $timestamps, array $methodAmounts, float $baseAmount): array {
    $n = count($timestamps);
    if ($n === 0) return [];
    if (count($methodAmounts) === 0) {
        if ($baseAmount > 0) return array_merge([$baseAmount], array_fill(0, $n - 1, 0));
        return array_fill(0, $n, 0);
    }
    if (count($methodAmounts) === $n) {
        return array_values(array_map(fn($v) => max(0, floatval($v)), $methodAmounts));
    }
    if (count($methodAmounts) === 1) {
        $total = max(0, floatval($methodAmounts[0]));
        $base = max(0, floatval($baseAmount));
        $first = ($base > 0 && $base <= $total) ? $base : (($base > 0) ? min($base, $total) : 0);
        if ($n === 1) return [$total];
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

function isDateInRange($dateString, $startDate, $endDate): bool {
    try {
        $date = new DateTime($dateString);
        $start = new DateTime($startDate . ' 00:00:00');
        $end = new DateTime($endDate . ' 23:59:59');
        return ($date >= $start && $date <= $end);
    } catch (Exception $e) {
        return false;
    }
}

function getDiscountAmountForPaymentTimestamp($payment, $paymentDateStr, $totalRows = 1): float {
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

// DATA PROCESSING
try {
    $histColumns = [
        'payment_amount_cash_history', 'payment_amount_g_cash_history',
        'payment_amount_maya_history', 'payment_amount_instapay_history',
        'payment_amount_online_banking_history', 'payment_amount_airbnb_history',
        'discount_amount_history'
    ];
    foreach ($histColumns as $colName) {
        try {
            $chk = $conn->query("SHOW COLUMNS FROM reports LIKE '" . $colName . "'");
            if ($chk && $chk->rowCount() == 0) {
                $conn->exec("ALTER TABLE reports ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
            }
        } catch (PDOException $e) {}
    }

    $stmt = $conn->prepare("
        SELECT r.booking_id, r.payment_date_time,
            DATE(COALESCE(NULLIF(TRIM(SUBSTRING_INDEX(r.payment_date_time, '|', 1)), ''), r.check_in)) as payment_date,
            r.check_in, r.checked_out_at, r.guest_name, r.second_guest_name, r.additional_guest_names, r.room_id, r.status, r.encoder,
            r.payment_status, r.payment_status_cash, r.payment_status_g_cash,
            r.payment_status_maya, r.payment_status_instapay,
            r.payment_status_online_banking, r.payment_status_airbnb,
            r.payment_amount_cash_history, r.payment_amount_g_cash_history,
            r.payment_amount_maya_history, r.payment_amount_instapay_history,
            r.payment_amount_online_banking_history, r.payment_amount_airbnb_history,
            r.deposit_cash, r.deposit_g_cash, r.deposit_maya, r.deposit_instapay,
            r.deposit_online_banking, r.deposit_airbnb,
            r.downpayment_cash, r.downpayment_gcash, r.downpayment_maya,
            r.downpayment_instapay, r.downpayment_online_banking, r.downpayment_airbnb,
            r.downpayment_date, COALESCE(r.booking_type, b.booking_type) AS booking_type,
            r.extension_withdraw, r.withdrawn_extend_price, r.discount_amount,
            COALESCE(r.discount_amount_history, b.discount_amount_history) AS discount_amount_history
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
        WHERE ((r.payment_date_time IS NOT NULL AND TRIM(r.payment_date_time) <> '')
                OR (r.downpayment_date IS NOT NULL))
          AND (r.paid_status = 'Paid'
                OR (COALESCE(r.deposit_cash, 0) + COALESCE(r.deposit_g_cash, 0) + COALESCE(r.deposit_maya, 0)
                    + COALESCE(r.deposit_instapay, 0) + COALESCE(r.deposit_online_banking, 0) + COALESCE(r.deposit_airbnb, 0)
                    + COALESCE(r.downpayment_cash, 0) + COALESCE(r.downpayment_gcash, 0) + COALESCE(r.downpayment_maya, 0)
                    + COALESCE(r.downpayment_instapay, 0) + COALESCE(r.downpayment_online_banking, 0) + COALESCE(r.downpayment_airbnb, 0)
                ) > 0.005)
        ORDER BY COALESCE(r.payment_date_time, r.downpayment_date) ASC, r.booking_id ASC
    ");
    
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $payments = array_values(array_filter($payments, function ($r) use ($startDate, $endDate) {
        return perPaymentReportRowInDateRange($r, $startDate, $endDate);
    }));

    $dataRows = [];
    $grandTotal = 0;

    foreach ($payments as $payment) {
        $bookingId = $payment['booking_id'] ?: 'N/A';
        
        // Consolidate all guest names (primary, second, additional)
        $allGuestNames = [];
        if (!empty($payment['guest_name'])) {
            $allGuestNames[] = trim($payment['guest_name']);
        }
        if (!empty($payment['second_guest_name'])) {
            $secondNames = array_filter(array_map('trim', explode('|', $payment['second_guest_name'])));
            $allGuestNames = array_merge($allGuestNames, $secondNames);
        }
        if (!empty($payment['additional_guest_names'])) {
            $additionalNames = array_filter(array_map('trim', explode('|', $payment['additional_guest_names'])));
            $allGuestNames = array_merge($allGuestNames, $additionalNames);
        }
        $guestName = !empty($allGuestNames) ? implode("\n", $allGuestNames) : 'N/A';
        
        $roomId = $payment['room_id'] ?: 'N/A';
        $status = $payment['status'] ?: 'N/A';
        $encoder = $payment['encoder'] ?: 'N/A';
        $checkIn = formatDateTimeDisplayPDF($payment['check_in'] ?? '');
        $checkOut = formatDateTimeDisplayPDF($payment['checked_out_at'] ?? '');

        if (strcasecmp($status, 'Confirmed') === 0) {
            $status = 'Check-in';
        }

        $timestampRows = [];
        if (!empty($payment['payment_date_time'])) {
            $rawTimestamps = explode('|', (string) $payment['payment_date_time']);
            foreach ($rawTimestamps as $ts) {
                $ts = trim($ts);
                if ($ts === '') continue;
                $timestampRows[] = array_merge(formatTimestampForExport($ts), ['raw' => $ts]);
            }
        }
        if (empty($timestampRows) && !empty($payment['downpayment_date'])) {
            $downpaymentDate = (string) $payment['downpayment_date'];
            $timestampRows[] = array_merge(formatTimestampForExport($downpaymentDate), ['raw' => $downpaymentDate]);
        }
        if (empty($timestampRows)) {
            $timestampRows[] = ['date' => $payment['payment_date'] ?: 'N/A', 'payment_date_time' => 'N/A', 'raw' => ''];
        }

        $nTimestamps = count($timestampRows);

        // Process Cash
        $depositCash = floatval($payment['deposit_cash'] ?? 0);
        $downpaymentCash = floatval($payment['downpayment_cash'] ?? 0);
        $totalCash = max($depositCash, $downpaymentCash);
        
        $cashHistoryArr = !empty($payment['payment_amount_cash_history'])
            ? explode('|', (string) $payment['payment_amount_cash_history']) : null;
        
        if (is_array($cashHistoryArr) && count($cashHistoryArr) === $nTimestamps) {
            $cashAmountsByTimestamp = array_map(fn($v) => floatval($v), $cashHistoryArr);
        } else {
            $cashMethodAmounts = parsePaymentAmountsAll($payment['payment_status_cash']);
            $cashAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $cashMethodAmounts, $totalCash);
        }
        
        $cashLikeMethodLabel = 'Cash';
        $cashStatusRaw = (string) ($payment['payment_status_cash'] ?? '');
        if (stripos($cashStatusRaw, 'Instapay') !== false) $cashLikeMethodLabel = 'Instapay';
        elseif (stripos($cashStatusRaw, 'Online Banking') !== false) $cashLikeMethodLabel = 'Online Banking';
        elseif (stripos($cashStatusRaw, 'Airbnb') !== false) $cashLikeMethodLabel = 'Airbnb';
        
        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $cashAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0 && isDateInRange($tsRow['date'], $startDate, $endDate)) {
                $rowDiscount = getDiscountAmountForPaymentTimestamp($payment, (string) ($tsRow['raw'] ?? ''), $nTimestamps);
                $dataRows[] = [
                    'booking_id' => $bookingId, 'encoder' => $encoder, 'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'], 'guest_name' => $guestName,
                    'check_in' => $checkIn, 'check_out' => $checkOut, 'room_id' => $roomId,
                    'status' => $status, 'discount_amount' => $rowDiscount,
                    'payment_method' => $cashLikeMethodLabel, 'amount' => $amt
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
                ? explode('|', (string) $payment[$method['history']]) : null;
            
            if (is_array($historyArr) && count($historyArr) === $nTimestamps) {
                $amountsByTimestamp = array_map(fn($v) => floatval($v), $historyArr);
            } else {
                $methodAmounts = parsePaymentAmountsAll($payment[$method['status']]);
                $amountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $methodAmounts, $total);
            }
            
            foreach ($timestampRows as $idx => $tsRow) {
                $amt = $amountsByTimestamp[$idx] ?? 0;
                if ($amt > 0 && isDateInRange($tsRow['date'], $startDate, $endDate)) {
                    $rowDiscount = getDiscountAmountForPaymentTimestamp($payment, (string) ($tsRow['raw'] ?? ''), $nTimestamps);
                    $dataRows[] = [
                        'booking_id' => $bookingId, 'encoder' => $encoder, 'date' => $tsRow['date'],
                        'payment_date_time' => $tsRow['payment_date_time'], 'guest_name' => $guestName,
                        'check_in' => $checkIn, 'check_out' => $checkOut, 'room_id' => $roomId,
                        'status' => $status, 'discount_amount' => $rowDiscount,
                        'payment_method' => $method['name'], 'amount' => $amt
                    ];
                    $grandTotal += $amt;
                }
            }
        }
    }

    // Normalize and group data
    $dataRows = normalizeAllReservationPaymentExportRows($dataRows, $payments);
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

    // Get turnover records
    $turnoverRecords = [];
    $totalCashTurnover = 0;
    $checkTurnoverTable = $conn->query("SHOW TABLES LIKE 'turnover_records'");
    if ($checkTurnoverTable->rowCount() > 0) {
        $turnoverStmt = $conn->prepare("
            SELECT t.id, t.session_id, t.username, t.cash_amount, t.total_amount, t.turnover_at,
                   u.first_name, u.last_name
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
    die("Database error: " . $e->getMessage());
}

// CREATE PDF
class PDF extends FPDF {
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

// Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'DAILY SALES REPORT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, $startDate . ' - ' . $endDate, 0, 1, 'C');
$pdf->Ln(3);

// Table Header
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(76, 175, 80);
$pdf->SetTextColor(255, 255, 255);

$w = [25, 15, 20, 35, 25, 25, 20, 20, 25, 20, 25]; // Column widths

$pdf->Cell($w[0], 7, 'Booking ID', 1, 0, 'C', true);
$pdf->Cell($w[1], 7, 'Room', 1, 0, 'C', true);
$pdf->Cell($w[2], 7, 'Encoder', 1, 0, 'C', true);
$pdf->Cell($w[3], 7, 'Guest Name', 1, 0, 'C', true);
$pdf->Cell($w[4], 7, 'Check In', 1, 0, 'C', true);
$pdf->Cell($w[5], 7, 'Check Out', 1, 0, 'C', true);
$pdf->Cell($w[6], 7, 'Status', 1, 0, 'C', true);
$pdf->Cell($w[7], 7, 'Discount', 1, 0, 'C', true);
$pdf->Cell($w[8], 7, 'Payment Method', 1, 0, 'C', true);
$pdf->Cell($w[9], 7, 'Pay Date', 1, 0, 'C', true);
$pdf->Cell($w[10], 7, 'Amount', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Arial', '', 6);
$pdf->SetTextColor(0, 0, 0);

if (empty($dataRows)) {
    $pdf->Cell(array_sum($w), 10, 'No payment records found', 1, 1, 'C');
} else {
    // Group by booking_id for rowspan effect
    $groupedRows = [];
    foreach ($dataRows as $row) {
        $bookingId = $row['booking_id'];
        if (!isset($groupedRows[$bookingId])) {
            $groupedRows[$bookingId] = [];
        }
        $groupedRows[$bookingId][] = $row;
    }
    
    foreach ($groupedRows as $bookingId => $rows) {
        $rowCount = count($rows);
        $firstRow = true;
        
        foreach ($rows as $row) {
            $h = 6; // Row height
            
            // Handle page break
            if ($pdf->GetY() > 180) {
                $pdf->AddPage();
                // Reprint header
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->SetFillColor(76, 175, 80);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell($w[0], 7, 'Booking ID', 1, 0, 'C', true);
                $pdf->Cell($w[1], 7, 'Room', 1, 0, 'C', true);
                $pdf->Cell($w[2], 7, 'Encoder', 1, 0, 'C', true);
                $pdf->Cell($w[3], 7, 'Guest Name', 1, 0, 'C', true);
                $pdf->Cell($w[4], 7, 'Check In', 1, 0, 'C', true);
                $pdf->Cell($w[5], 7, 'Check Out', 1, 0, 'C', true);
                $pdf->Cell($w[6], 7, 'Status', 1, 0, 'C', true);
                $pdf->Cell($w[7], 7, 'Discount', 1, 0, 'C', true);
                $pdf->Cell($w[8], 7, 'Payment Method', 1, 0, 'C', true);
                $pdf->Cell($w[9], 7, 'Pay Date', 1, 0, 'C', true);
                $pdf->Cell($w[10], 7, 'Amount', 1, 1, 'C', true);
                $pdf->SetFont('Arial', '', 6);
                $pdf->SetTextColor(0, 0, 0);
            }
            
            // Calculate row height based on encoder text length
            $encoderText = $row['encoder'];
            // Replace & with line break for multi-encoder display
            $encoderText = str_replace(' & ', "\n", $encoderText);
            $encoderLines = substr_count($encoderText, "\n") + 1;
            $encoderRowHeight = max($h, $encoderLines * 3); // Minimum 6mm, 3mm per line
            
            // Calculate guest name height
            $guestNameText = $row['guest_name'];
            $guestNameLines = explode("\n", $guestNameText);
            $numGuestLines = count($guestNameLines);
            $guestLineHeight = 4;
            $guestTopPadding = 1.5;
            $guestBottomPadding = 1.5;
            $guestRowHeight = max($h, ($numGuestLines * $guestLineHeight) + $guestTopPadding + $guestBottomPadding);
            
            // Use the maximum of encoder height and guest name height
            $rowHeight = max($encoderRowHeight, $guestRowHeight);
            
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            
            if ($firstRow) {
                $pdf->Cell($w[0], $rowHeight, $row['booking_id'], 1, 0, 'C');
                $pdf->Cell($w[1], $rowHeight, $row['room_id'], 1, 0, 'C');
                
                // Draw border for encoder cell first
                $pdf->Rect($x + $w[0] + $w[1], $y, $w[2], $rowHeight);
                
                // Calculate vertical centering offset for encoder text
                $textHeight = $encoderLines * 3;
                $yOffset = ($rowHeight - $textHeight) / 2;
                
                // Use MultiCell for encoder to allow wrapping, centered vertically
                $pdf->SetXY($x + $w[0] + $w[1], $y + $yOffset);
                $pdf->MultiCell($w[2], 3, $encoderText, 0, 'C');
                
                $firstRow = false;
            } else {
                $pdf->Cell($w[0], $rowHeight, '', 1, 0, 'C');
                $pdf->Cell($w[1], $rowHeight, '', 1, 0, 'C');
                $pdf->Cell($w[2], $rowHeight, '', 1, 0, 'C');
            }
            
            // Set position for remaining cells on the same row
            $pdf->SetXY($x + $w[0] + $w[1] + $w[2], $y);
            
            // Guest Name - draw border and overlay text lines
            $guestX = $x + $w[0] + $w[1] + $w[2];
            $pdf->SetXY($guestX, $y);
            $pdf->Cell($w[3], $rowHeight, '', 1, 0, 'L');
            
            // Overlay guest name text lines
            $guestTextY = $y + $guestTopPadding;
            foreach ($guestNameLines as $line) {
                $pdf->SetXY($guestX + 1, $guestTextY);
                $pdf->Cell($w[3] - 2, $guestLineHeight, substr(trim($line), 0, 25), 0, 0, 'L');
                $guestTextY += $guestLineHeight;
            }
            
            // Handle Check In/Out with line breaks
            $checkInText = str_replace('<br>', "\n", $row['check_in']);
            $checkInText = str_replace("\n", " ", $checkInText);
            $checkOutText = str_replace('<br>', "\n", $row['check_out']);
            $checkOutText = str_replace("\n", " ", $checkOutText);
            
            $pdf->SetXY($guestX + $w[3], $y);
            $pdf->Cell($w[4], $rowHeight, $checkInText, 1, 0, 'C');
            $pdf->Cell($w[5], $rowHeight, $checkOutText, 1, 0, 'C');
            $pdf->Cell($w[6], $rowHeight, $row['status'], 1, 0, 'C');
            $pdf->Cell($w[7], $rowHeight, number_format($row['discount_amount'], 2), 1, 0, 'R');
            $pdf->Cell($w[8], $rowHeight, $row['payment_method'], 1, 0, 'C');
            $pdf->Cell($w[9], $rowHeight, $row['payment_date_time'], 1, 0, 'C');
            $pdf->Cell($w[10], $rowHeight, number_format($row['amount'], 2), 1, 1, 'R');
        }
    }
    
    // Grand Total
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(232, 245, 233);
    $pdf->Cell($w[0]+$w[1]+$w[2]+$w[3]+$w[4]+$w[5]+$w[6]+$w[7]+$w[8]+$w[9], 7, 'GRAND TOTAL:', 1, 0, 'R', true);
    $pdf->Cell($w[10], 7, number_format($grandTotal, 2), 1, 1, 'R', true);
}

// Turnover Records Section
if (count($turnoverRecords) > 0) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Cash Turnover Records', 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(200, 200, 200);
    $tw = [60, 40, 40, 35]; // Turnover column widths
    $pdf->Cell($tw[0], 6, 'Employee Name', 1, 0, 'L', true);
    $pdf->Cell($tw[1], 6, 'Turn-over Date', 1, 0, 'C', true);
    $pdf->Cell($tw[2], 6, 'Turn-over Time', 1, 0, 'C', true);
    $pdf->Cell($tw[3], 6, 'Cash Amount', 1, 1, 'R', true);
    
    $pdf->SetFont('Arial', '', 7);
    foreach ($turnoverRecords as $turnover) {
        $employeeName = trim(($turnover['first_name'] ?? '') . ' ' . ($turnover['last_name'] ?? ''));
        if (empty($employeeName)) {
            $employeeName = $turnover['username'] ?? 'N/A';
        }
        
        $turnoverDate = '—';
        $turnoverTime = '—';
        if (!empty($turnover['turnover_at'])) {
            $dt = new DateTime($turnover['turnover_at']);
            $turnoverDate = $dt->format('m/d/Y');
            $turnoverTime = $dt->format('h:i a');
        }
        
        $cashAmt = floatval($turnover['cash_amount'] ?? 0);
        
        $pdf->Cell($tw[0], 6, substr($employeeName, 0, 40), 1, 0, 'L');
        $pdf->Cell($tw[1], 6, $turnoverDate, 1, 0, 'C');
        $pdf->Cell($tw[2], 6, $turnoverTime, 1, 0, 'C');
        $pdf->Cell($tw[3], 6, number_format($cashAmt, 2), 1, 1, 'R');
    }
    
    // Total Turnover
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(232, 245, 233);
    $pdf->Cell($tw[0]+$tw[1]+$tw[2], 6, 'Total Turnover:', 1, 0, 'R', true);
    $pdf->Cell($tw[3], 6, number_format($totalCashTurnover, 2), 1, 1, 'R', true);
}

// Output PDF
$pdf->Output('I', 'Daily_Sales_Report_' . $startDate . '_to_' . $endDate . '.pdf');
?>
