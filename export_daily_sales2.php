<?php
require_once 'config.php';
require_once 'report_helpers.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$isPdf = isset($_GET['pdf']) && $_GET['pdf'] === '1';

// Set headers for Excel download (only if not PDF)
if (!$isPdf) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Daily_Sales_2.0_Report_' . $startDate . '_to_' . $endDate . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$checkInCount = 0;
$checkOutCount = 0;
$canceledCount = 0;
$totalRoomsCount = 0;
$guestTypeSolo = 0;
$guestTypeDuo = 0;
$guestTypeFamily = 0;
$guestTypeGroup = 0;
$guestTypeCompany = 0;
$dailyStats = [];
$breakdownGrandCash = 0.0;
$breakdownGrandGcash = 0.0;
$breakdownGrandMaya = 0.0;
$breakdownGrandInstapay = 0.0;
$breakdownGrandOnlineBanking = 0.0;
$breakdownGrandAirbnb = 0.0;
$breakdownGrandTotal = 0.0;
$breakdownGrandBookings = 0;

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
            COALESCE(r.encoder, b.encoder) AS encoder,
            COALESCE(r.modification_updated_at, b.modification_updated_at) AS modification_updated_at,
            COALESCE(r.booking_type, b.booking_type) AS booking_type,
            COALESCE(r.room_type, b.room_type) AS room_type,
            COALESCE(r.guest_type, b.guest_type) AS guest_type,
            COALESCE(r.promo, b.promo) AS promo,
            COALESCE(r.breakfast, b.breakfast) AS breakfast,
            COALESCE(r.extend_bundle_breakfast, b.extend_bundle_breakfast) AS extend_bundle_breakfast,
            COALESCE(r.reservation_date, b.reservation_date) AS reservation_date,
            COALESCE(r.duration, b.duration, 0) AS duration,
            COALESCE(r.duration_unit, b.duration_unit, 'hours') AS duration_unit,
            COALESCE(b.extend_hours, r.extend_hours, 0) AS extend_hours,
            COALESCE(b.extend_minutes, r.extend_minutes, 0) AS extend_minutes,
            COALESCE(b.extend_price, r.extend_price, 0) AS extend_price,
            GREATEST(COALESCE(r.room_price, 0), COALESCE(b.room_price, 0)) AS room_price,
            COALESCE(r.total_amount_reservation, b.total_amount_reservation, 0) AS total_amount_reservation,
            COALESCE(r.downpayment_amount, b.downpayment_amount, 0) AS downpayment_amount,
            COALESCE(r.deposit_details, b.deposit_details) AS deposit_details,
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
            r.extension_withdraw,
            r.withdrawn_extend_price,
            r.discount_amount,
            COALESCE(r.discount_amount_history, b.discount_amount_history) AS discount_amount_history,
            COALESCE(r.additional_items, b.additional_items) AS additional_items,
            COALESCE(r.additional_food, b.additional_food) AS additional_food,
            GREATEST(COALESCE(r.additional_guest, 0), COALESCE(b.additional_guest, 0)) AS additional_guest,
            GREATEST(COALESCE(r.extend_additional_guest, 0), COALESCE(b.extend_additional_guest, 0)) AS extend_additional_guest,
            GREATEST(COALESCE(r.additional_pet, 0), COALESCE(b.additional_pet, 0)) AS additional_pet,
            COALESCE(r.additional_food_date, b.additional_food_date) AS additional_food_date,
            COALESCE(r.additional_items_date, b.additional_items_date) AS additional_items_date,
            COALESCE(r.additional_guest_date, b.additional_guest_date) AS additional_guest_date,
            COALESCE(r.additional_pet_date, b.additional_pet_date) AS additional_pet_date,
            COALESCE(r.additional_fees_paid_date, b.additional_fees_paid_date) AS additional_fees_paid_date,
            GREATEST(COALESCE(r.penalty_amount, 0), COALESCE(b.penalty_amount, 0)) AS penalty_amount,
            COALESCE(r.penalty_list, b.penalty_list) AS penalty_list,
            GREATEST(COALESCE(r.missing_items_fees, 0), COALESCE(b.missing_items_fees, 0)) AS missing_items_fees,
            COALESCE(r.missing_items_list, b.missing_items_list) AS missing_items_list,
            COALESCE(r.reference_no, b.reference_no) AS reference_no,
            COALESCE(r.reference_no_g_cash, b.reference_no_g_cash) AS reference_no_g_cash,
            COALESCE(r.reference_no_maya, b.reference_no_maya) AS reference_no_maya,
            COALESCE(r.reference_no_instapay, b.reference_no_instapay) AS reference_no_instapay,
            COALESCE(r.reference_no_online_banking, b.reference_no_online_banking) AS reference_no_online_banking,
            COALESCE(r.reference_no_airbnb, b.reference_no_airbnb) AS reference_no_airbnb,
            COALESCE(r.deposit_gcash_ref, b.deposit_gcash_ref) AS deposit_gcash_ref,
            COALESCE(r.deposit_maya_ref, b.deposit_maya_ref) AS deposit_maya_ref,
            COALESCE(r.deposit_instapay_ref, b.deposit_instapay_ref) AS deposit_instapay_ref,
            COALESCE(r.deposit_online_banking_ref, b.deposit_online_banking_ref) AS deposit_online_banking_ref,
            COALESCE(r.deposit_airbnb_ref, b.deposit_airbnb_ref) AS deposit_airbnb_ref,
            COALESCE(r.downpayment_gcash_ref, b.downpayment_gcash_ref) AS downpayment_gcash_ref,
            COALESCE(r.downpayment_maya_ref, b.downpayment_maya_ref) AS downpayment_maya_ref,
            COALESCE(r.downpayment_instapay_ref, b.downpayment_instapay_ref) AS downpayment_instapay_ref,
            COALESCE(r.downpayment_online_banking_ref, b.downpayment_online_banking_ref) AS downpayment_online_banking_ref,
            COALESCE(r.downpayment_airbnb_ref, b.downpayment_airbnb_ref) AS downpayment_airbnb_ref
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
            // Format as: date time (no <br> for Excel)
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

        // Only check payment_date_time timestamps (actual payment dates)
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

    // Booking Statistics (whole date range)
    $checkInStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status IN ('Confirming', 'Confirmed') AND DATE(check_in) BETWEEN :start AND :end");
    $checkInStmt->bindParam(':start', $startDate);
    $checkInStmt->bindParam(':end', $endDate);
    $checkInStmt->execute();
    $checkInCount = (int) ($checkInStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $checkOutStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'Checked Out' AND DATE(checked_out_at) BETWEEN :start AND :end");
    $checkOutStmt->bindParam(':start', $startDate);
    $checkOutStmt->bindParam(':end', $endDate);
    $checkOutStmt->execute();
    $checkOutCount = (int) ($checkOutStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $canceledStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM reports
        WHERE status = 'Canceled'
          AND DATE(COALESCE(canceled_at, check_in)) BETWEEN :start AND :end
    ");
    $canceledStmt->bindParam(':start', $startDate);
    $canceledStmt->bindParam(':end', $endDate);
    $canceledStmt->execute();
    $canceledCount = (int) ($canceledStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $totalRoomsStmt = $conn->query("SELECT COUNT(*) as count FROM rooms");
    $totalRoomsCount = (int) ($totalRoomsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Type of Guest (check-in date in range)
    $guestTypeStmt = $conn->prepare("SELECT guest_type, COUNT(*) as count FROM reports WHERE DATE(check_in) BETWEEN :start AND :end GROUP BY guest_type");
    $guestTypeStmt->bindParam(':start', $startDate);
    $guestTypeStmt->bindParam(':end', $endDate);
    $guestTypeStmt->execute();
    foreach ($guestTypeStmt->fetchAll(PDO::FETCH_ASSOC) as $guestRow) {
        $type = strtolower(trim((string) ($guestRow['guest_type'] ?? '')));
        $count = (int) ($guestRow['count'] ?? 0);
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

    function getDiscountDateForPaymentTimestamp($payment, $paymentDateStr): string
    {
        $discountHistoryRaw = trim((string) ($payment['discount_amount_history'] ?? ''));
        $paymentDate = strlen($paymentDateStr) >= 10 ? substr($paymentDateStr, 0, 10) : $paymentDateStr;

        if ($discountHistoryRaw !== '') {
            foreach (explode('|', $discountHistoryRaw) as $discEntry) {
                $discEntry = trim($discEntry);
                if ($discEntry === '') {
                    continue;
                }
                $discParts = explode(':', $discEntry, 2);
                $discDateTime = isset($discParts[1]) ? trim($discParts[1]) : '';
                $discDate = strlen($discDateTime) >= 10 ? substr($discDateTime, 0, 10) : $discDateTime;
                if ($discDate === $paymentDate && $discDateTime !== '') {
                    try {
                        return (new DateTime($discDateTime))->format('m/d/Y');
                    } catch (Exception $e) {
                        return $discDateTime;
                    }
                }
            }
        }

        return '—';
    }

    function formatBreakfastForExport(array $payment): string
    {
        $breakfastRaw = trim((string) ($payment['breakfast'] ?? ''));
        $extendBundleBreakfast = trim((string) ($payment['extend_bundle_breakfast'] ?? ''));

        $combinedBreakfastList = [];
        if ($breakfastRaw !== '' && $breakfastRaw !== 'None') {
            $combinedBreakfastList[] = $breakfastRaw;
        }
        if ($extendBundleBreakfast !== '' && $extendBundleBreakfast !== 'None' && $extendBundleBreakfast !== 'NULL') {
            $combinedBreakfastList[] = $extendBundleBreakfast;
        }

        if (empty($combinedBreakfastList)) {
            return '—';
        }

        $bParts = [];
        foreach (explode('|', implode('|', $combinedBreakfastList)) as $bItem) {
            $bItem = trim($bItem);
            if ($bItem === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s+(.*?)\s*-\s*(?:₱|P)?([0-9,.]+)/u', $bItem, $m)) {
                $bParts[] = intval($m[1]) . ' ' . trim($m[2]) . ' - ' . number_format(floatval(str_replace(',', '', $m[3])), 2);
            } elseif (preg_match('/^(\d+)\s+(.*)$/u', $bItem, $m)) {
                $bParts[] = intval($m[1]) . ' ' . trim($m[2]);
            } else {
                $bParts[] = $bItem;
            }
        }

        return !empty($bParts) ? implode(' | ', $bParts) : '—';
    }

    function formatBaseDurationDisplay(int $baseDuration, string $baseUnit): string
    {
        $unit = strtolower(trim($baseUnit));
        if (in_array($unit, ['hours', 'hour', 'hrs', 'hr'], true)) {
            return $baseDuration . ':00 Hours';
        }

        return $baseDuration . ' ' . $baseUnit;
    }

    function buildDurationDisplays(array $payment, int $paymentTimestampIndex = 0): array
    {
        $baseDuration = intval($payment['duration'] ?? 0);
        $baseUnit = $payment['duration_unit'] ?? 'hours';
        $extHours = intval($payment['extend_hours'] ?? 0);
        $extMinutes = intval($payment['extend_minutes'] ?? 0);
        $extPrice = floatval($payment['extend_price'] ?? 0);

        $promoValue = $payment['promo'] ?? '';
        $hasPromo = !empty($promoValue) && $promoValue !== 'None' && $promoValue !== 'Select Promo';
        if ($hasPromo && $baseDuration == 0) {
            if (preg_match('/(\d+)\s*hrs?/i', $promoValue, $matches)) {
                $baseDuration = intval($matches[1]);
            } else {
                $baseDuration = 12;
            }
        }

        // First payment = room rate only; later payments = extension (matches Modification.php / export_daily_sales.php)
        if ($paymentTimestampIndex === 0) {
            return [
                'duration' => formatBaseDurationDisplay($baseDuration, $baseUnit),
                'extension_duration' => '—',
            ];
        }

        if ($extHours > 0 || $extMinutes > 0) {
            $baseTotalMinutes = $baseDuration * 60;
            $extTotalMinutes = ($extHours * 60) + $extMinutes;
            $grandTotalMinutes = $baseTotalMinutes + $extTotalMinutes;
            $displayHours = intdiv($grandTotalMinutes, 60);
            $displayMinutes = $grandTotalMinutes % 60;
            $durationDisplay = $displayHours . ':' . str_pad((string) $displayMinutes, 2, '0', STR_PAD_LEFT) . ' ' . $baseUnit . ' (Extended)';

            if ($extHours > 0 && $extMinutes > 0) {
                $extDurationDisplay = $extHours . ':' . str_pad((string) $extMinutes, 2, '0', STR_PAD_LEFT) . ' Hr = ' . number_format($extPrice, 0);
            } elseif ($extHours > 0) {
                $extDurationDisplay = $extHours . ' Hr = ' . number_format($extPrice, 0);
            } else {
                $extDurationDisplay = $extMinutes . ' Mins = ' . number_format($extPrice, 0);
            }
        } else {
            $durationDisplay = formatBaseDurationDisplay($baseDuration, $baseUnit);
            $extDurationDisplay = '—';
        }

        return [
            'duration' => $durationDisplay,
            'extension_duration' => $extDurationDisplay
        ];
    }

    function getReservationAmountDisplay(array $payment): string
    {
        $bookingType = trim((string) ($payment['booking_type'] ?? ''));
        if (strcasecmp($bookingType, 'Reservation') !== 0) {
            return '—';
        }

        $reservationFee = floatval($payment['total_amount_reservation'] ?? 0);
        if ($reservationFee <= 0) {
            $downpaymentFee = floatval($payment['downpayment_amount'] ?? 0);
            if ($downpaymentFee > 0) {
                $reservationFee = $downpaymentFee;
            } else {
                $discountAmount = floatval($payment['discount_amount'] ?? 0);
                if ($discountAmount > 0) {
                    $reservationFee = $discountAmount;
                } else {
                    $reservationFee = parseCurrencyFromString($payment['deposit_details'] ?? '');
                }
            }
        }

        return $reservationFee > 0 ? number_format($reservationFee, 2) : '—';
    }

    function parse_additional_total($raw)
    {
        if (!$raw) {
            return 0.0;
        }
        $total = 0.0;
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $it) {
                $qty = floatval($it['quantity'] ?? ($it['qty'] ?? 1));
                $price = floatval($it['price'] ?? 0);
                $total += $qty * $price;
            }
            return $total;
        }
        $lines = preg_split('/\r?\n/', $raw);
        foreach ($lines as $line) {
            if (preg_match('/₱\s*([0-9,]+\.?[0-9]*)/', $line, $m)) {
                $total += floatval(str_replace(',', '', $m[1]));
            } elseif (preg_match('/([0-9]+\.?[0-9]*)\s*$/', trim($line), $m2)) {
                $total += floatval($m2[1]);
            }
        }
        return $total;
    }

    function isAdditionalDateInReportRange(?string $dateStr, string $startDate, string $endDate): bool
    {
        if (empty($dateStr) || $dateStr === 'NULL' || $dateStr === '0000-00-00 00:00:00') {
            return true;
        }

        $dates = json_decode($dateStr, true);
        if (!is_array($dates)) {
            $dates = [$dateStr];
        }

        foreach ($dates as $d) {
            if (empty($d) || $d === 'NULL' || $d === '0000-00-00 00:00:00') {
                continue;
            }
            try {
                $dateOnly = (new DateTime($d))->format('Y-m-d');
                if ($dateOnly >= $startDate && $dateOnly <= $endDate) {
                    return true;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return false;
    }

    function emptyAdditionalFeesRow(): array
    {
        return [
            'additional_items' => '—',
            'additional_foods' => '—',
            'additional_guest' => '—',
            'additional_pet' => '—',
            'additional_missing_items' => '—',
            'additional_penalty' => '—',
            'additional_total_fees' => 0.0,
        ];
    }

    function isAdditionalPaidOnPaymentDate(?string $dateStr, string $paymentDateRaw): bool
    {
        if (empty($paymentDateRaw) || trim($paymentDateRaw) === '') {
            return false;
        }

        try {
            $paymentDay = (new DateTime($paymentDateRaw))->format('Y-m-d');
        } catch (Exception $e) {
            return false;
        }

        if (empty($dateStr) || $dateStr === 'NULL' || $dateStr === '0000-00-00 00:00:00') {
            return false;
        }

        $dates = json_decode($dateStr, true);
        if (!is_array($dates)) {
            $dates = [$dateStr];
        }

        foreach ($dates as $d) {
            if (empty($d) || $d === 'NULL' || $d === '0000-00-00 00:00:00') {
                continue;
            }
            try {
                if ((new DateTime($d))->format('Y-m-d') === $paymentDay) {
                    return true;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return false;
    }

    function hasValidAdditionalDate(?string $dateStr): bool
    {
        return !empty($dateStr) && $dateStr !== 'NULL' && $dateStr !== '0000-00-00 00:00:00';
    }

    function inferChargeCountFromPayment(float $paymentTotal, float $baseAmount, float $unitPrice): int
    {
        if ($paymentTotal <= 0 || $baseAmount <= 0 || $unitPrice <= 0) {
            return 0;
        }

        $portion = $paymentTotal - $baseAmount;
        if ($portion <= 0.01) {
            return 0;
        }

        $count = $portion / $unitPrice;
        if (abs($count - round($count)) < 0.02) {
            return (int) round($count);
        }

        return 0;
    }

    function countAdditionalDatesOnPaymentDay(?string $dateStr, string $paymentDateRaw): int
    {
        if (!hasValidAdditionalDate($dateStr)) {
            return 0;
        }

        try {
            $paymentDay = (new DateTime($paymentDateRaw))->format('Y-m-d');
        } catch (Exception $e) {
            return 0;
        }

        $dates = json_decode($dateStr, true);
        if (!is_array($dates)) {
            $dates = [$dateStr];
        }

        $count = 0;
        foreach ($dates as $d) {
            if (empty($d) || $d === 'NULL' || $d === '0000-00-00 00:00:00') {
                continue;
            }
            try {
                if ((new DateTime($d))->format('Y-m-d') === $paymentDay) {
                    $count++;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $count;
    }

    function getExtensionGuestBase(float $roomPrice, float $extendPrice, int $nPaymentTimestamps): float
    {
        if ($extendPrice <= 0) {
            return 0.0;
        }

        // Split extension across multiple payments: each installment is typically room rate
        if ($nPaymentTimestamps > 2 || ($roomPrice > 0 && $extendPrice > ($roomPrice * 1.01))) {
            return $roomPrice > 0 ? $roomPrice : $extendPrice;
        }

        return $extendPrice;
    }

    function formatGuestPetCountDisplay(int $count, int $unitPrice): string
    {
        return $count > 0 ? $count . ' (' . number_format($count * $unitPrice, 2) . ')' : '—';
    }

    function mergeGroupedAdditionalFees(array $existing, array $incoming): array
    {
        $guestCount = intval($existing['_guest_count'] ?? 0) + intval($incoming['_guest_count'] ?? 0);
        $petCount = intval($existing['_pet_count'] ?? 0) + intval($incoming['_pet_count'] ?? 0);

        $existing['_guest_count'] = $guestCount;
        $existing['_pet_count'] = $petCount;
        $existing['additional_guest'] = formatGuestPetCountDisplay($guestCount, 300);
        $existing['additional_pet'] = formatGuestPetCountDisplay($petCount, 500);
        $existing['additional_total_fees'] = floatval($existing['additional_total_fees'] ?? 0)
            + floatval($incoming['additional_total_fees'] ?? 0);

        foreach (['additional_items', 'additional_foods', 'additional_missing_items', 'additional_penalty'] as $field) {
            $prev = trim((string) ($existing[$field] ?? '—'));
            $next = trim((string) ($incoming[$field] ?? '—'));
            if ($prev === '—' || $prev === '') {
                $existing[$field] = $next !== '' ? $next : '—';
            } elseif ($next !== '—' && $next !== '' && strpos($prev, $next) === false) {
                $existing[$field] = $prev . ' | ' . $next;
            }
        }

        return $existing;
    }

    function getGuestPetCountsForPaymentRow(
        array $payment,
        float $paymentAmount,
        string $paymentDateRaw,
        int $paymentTimestampIndex,
        int $nPaymentTimestamps,
        string $startDate,
        string $endDate
    ): array {
        $roomPrice = floatval($payment['room_price'] ?? 0);
        $extendPrice = floatval($payment['extend_price'] ?? 0);
        $cumulativeGuest = intval($payment['additional_guest'] ?? 0) + intval($payment['extend_additional_guest'] ?? 0);
        $cumulativePet = intval($payment['additional_pet'] ?? 0);

        $guestBase = 0.0;
        if ($paymentTimestampIndex === 0 && $roomPrice > 0) {
            $guestBase = $roomPrice;
        } elseif ($paymentTimestampIndex >= 1) {
            $guestBase = getExtensionGuestBase($roomPrice, $extendPrice, $nPaymentTimestamps);
        }

        $guestCount = 0;
        $petCount = 0;

        if ($guestBase > 0 && $paymentAmount > 0) {
            $guestCount = inferChargeCountFromPayment($paymentAmount, $guestBase, 300);
            $petBase = $guestBase + ($guestCount * 300);
            $petCount = inferChargeCountFromPayment($paymentAmount, $petBase, 500);
        }

        if ($guestCount === 0 && $cumulativeGuest > 0) {
            $dateMatchCount = countAdditionalDatesOnPaymentDay($payment['additional_guest_date'] ?? null, $paymentDateRaw);
            if ($dateMatchCount > 0) {
                $guestCount = $dateMatchCount;
            } elseif ($nPaymentTimestamps <= 1 && shouldIncludeAdditionalOnPaymentRow(
                $payment['additional_guest_date'] ?? null,
                true,
                $paymentDateRaw,
                $paymentTimestampIndex,
                $startDate,
                $endDate
            )) {
                $guestCount = $cumulativeGuest;
            }
        }

        if ($petCount === 0 && $cumulativePet > 0) {
            $dateMatchCount = countAdditionalDatesOnPaymentDay($payment['additional_pet_date'] ?? null, $paymentDateRaw);
            if ($dateMatchCount > 0) {
                $petCount = $dateMatchCount;
            } elseif ($nPaymentTimestamps <= 1 && shouldIncludeAdditionalOnPaymentRow(
                $payment['additional_pet_date'] ?? null,
                true,
                $paymentDateRaw,
                $paymentTimestampIndex,
                $startDate,
                $endDate
            )) {
                $petCount = $cumulativePet;
            }
        }

        return [
            'guest' => max(0, $guestCount),
            'pet' => max(0, $petCount),
        ];
    }

    function shouldIncludeAdditionalOnPaymentRow(
        ?string $dateStr,
        bool $hasData,
        string $paymentDateRaw,
        int $paymentTimestampIndex,
        string $startDate,
        string $endDate
    ): bool {
        if (!$hasData) {
            return false;
        }

        if (hasValidAdditionalDate($dateStr) && !isAdditionalDateInReportRange($dateStr, $startDate, $endDate)) {
            return false;
        }

        if (isAdditionalPaidOnPaymentDate($dateStr, $paymentDateRaw)) {
            return true;
        }

        // No valid paid date: show on first payment row only (check-in / room rate payment)
        return !hasValidAdditionalDate($dateStr) && $paymentTimestampIndex === 0;
    }

    function buildAdditionalFeesForPaymentRow(
        array $payment,
        string $paymentDateRaw,
        int $paymentTimestampIndex,
        int $nPaymentTimestamps,
        float $paymentAmount,
        string $startDate,
        string $endDate
    ): array {
        $hasFood = !empty(trim((string) ($payment['additional_food'] ?? '')));
        $hasItems = !empty(trim((string) ($payment['additional_items'] ?? '')));
        $hasCheckoutFees = floatval($payment['missing_items_fees'] ?? 0) > 0
            || floatval($payment['penalty_amount'] ?? 0) > 0
            || !empty($payment['missing_items_list'])
            || !empty($payment['penalty_list']);

        $includeFoodFees = shouldIncludeAdditionalOnPaymentRow(
            $payment['additional_food_date'] ?? null,
            $hasFood,
            $paymentDateRaw,
            $paymentTimestampIndex,
            $startDate,
            $endDate
        );
        $includeItemsFees = shouldIncludeAdditionalOnPaymentRow(
            $payment['additional_items_date'] ?? null,
            $hasItems,
            $paymentDateRaw,
            $paymentTimestampIndex,
            $startDate,
            $endDate
        );
        $includeCheckoutFees = shouldIncludeAdditionalOnPaymentRow(
            $payment['additional_fees_paid_date'] ?? null,
            $hasCheckoutFees || !empty($payment['additional_fees_paid_date']),
            $paymentDateRaw,
            $paymentTimestampIndex,
            $startDate,
            $endDate
        );

        $additionalItemsRaw = $includeItemsFees ? trim((string) ($payment['additional_items'] ?? '')) : '';
        $additionalFoodRaw = $includeFoodFees ? trim((string) ($payment['additional_food'] ?? '')) : '';

        if (preg_match('/^1\s*(?:Food Item|Item)?\s*[=-]\s*[₱P]?0\.00\s*$/iu', $additionalItemsRaw)) {
            $additionalItemsRaw = '';
        }
        if (preg_match('/^1\s*(?:Food Item|Item)?\s*[=-]\s*[₱P]?0\.00\s*$/iu', $additionalFoodRaw)) {
            $additionalFoodRaw = '';
        }

        $guestPetCounts = getGuestPetCountsForPaymentRow(
            $payment,
            $paymentAmount,
            $paymentDateRaw,
            $paymentTimestampIndex,
            $nPaymentTimestamps,
            $startDate,
            $endDate
        );
        $additionalGuest = $guestPetCounts['guest'];
        $additionalPet = $guestPetCounts['pet'];
        $additionalGuestPrice = $additionalGuest * 300;
        $additionalPetPrice = $additionalPet * 500;
        $additionalItemsTotal = parse_additional_total($additionalItemsRaw);
        $additionalFoodTotal = parse_additional_total($additionalFoodRaw);

        $penaltyAmount = $includeCheckoutFees ? floatval($payment['penalty_amount'] ?? 0) : 0;
        $penaltyDisplay = '—';
        $penaltyListRaw = $payment['penalty_list'] ?? null;
        if ($includeCheckoutFees && !empty($penaltyListRaw) && $penaltyListRaw !== 'null') {
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
                if ($calculatedPenalty > 0) {
                    $penaltyAmount = $calculatedPenalty;
                }
            } elseif ($penaltyAmount > 0) {
                $penaltyDisplay = 'Penalty Applied';
            }
        } elseif ($includeCheckoutFees && $penaltyAmount > 0) {
            $penaltyDisplay = 'Penalty Applied';
        }

        $missingItemsFees = $includeCheckoutFees ? floatval($payment['missing_items_fees'] ?? 0) : 0;
        $missingItemsDisplay = '—';
        if ($includeCheckoutFees && !empty($payment['missing_items_list'])) {
            $mItems = json_decode($payment['missing_items_list'], true);
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
        } elseif ($includeCheckoutFees && $missingItemsFees > 0) {
            $missingItemsDisplay = 'Missing Items (' . number_format($missingItemsFees, 2) . ')';
        }

        $totalAdditionalFees = $missingItemsFees + $additionalItemsTotal + $additionalFoodTotal + $additionalGuestPrice + $additionalPetPrice + $penaltyAmount;

        return [
            'additional_items' => $additionalItemsRaw !== '' ? $additionalItemsRaw : '—',
            'additional_foods' => $additionalFoodRaw !== '' ? $additionalFoodRaw : '—',
            'additional_guest' => formatGuestPetCountDisplay($additionalGuest, 300),
            'additional_pet' => formatGuestPetCountDisplay($additionalPet, 500),
            'additional_missing_items' => $missingItemsDisplay,
            'additional_penalty' => $penaltyDisplay,
            'additional_total_fees' => $totalAdditionalFees,
            '_guest_count' => $additionalGuest,
            '_pet_count' => $additionalPet,
        ];
    }

    function buildBookingMetaFields(array $payment): array
    {
        $modifiedIndicator = (!empty($payment['modification_updated_at']) && trim((string) $payment['modification_updated_at']) !== '') ? 'M' : '';
        $promoMeta = parsePromoSelection($payment['promo'] ?? '');

        return [
            'modified' => $modifiedIndicator,
            'booking_type' => $payment['booking_type'] ?: '—',
            'room_type' => $payment['room_type'] ?? '',
            'guest_type' => $payment['guest_type'] ?: '—',
            'promo' => $promoMeta['title'] ?: '—',
            'breakfast' => formatBreakfastForExport($payment),
            'status' => trim((string) ($payment['status'] ?? '')) ?: '—',
        ];
    }

    function getMethodAmountsForTimestamp(array $payment, array $timestampRows, int $idx): array
    {
        $nTimestamps = count($timestampRows);
        $methodConfigs = [
            'cash' => ['deposit' => 'deposit_cash', 'downpayment' => 'downpayment_cash', 'status' => 'payment_status_cash', 'history' => 'payment_amount_cash_history'],
            'gcash' => ['deposit' => 'deposit_g_cash', 'downpayment' => 'downpayment_gcash', 'status' => 'payment_status_g_cash', 'history' => 'payment_amount_g_cash_history'],
            'maya' => ['deposit' => 'deposit_maya', 'downpayment' => 'downpayment_maya', 'status' => 'payment_status_maya', 'history' => 'payment_amount_maya_history'],
            'instapay' => ['deposit' => 'deposit_instapay', 'downpayment' => 'downpayment_instapay', 'status' => 'payment_status_instapay', 'history' => 'payment_amount_instapay_history'],
            'online_banking' => ['deposit' => 'deposit_online_banking', 'downpayment' => 'downpayment_online_banking', 'status' => 'payment_status_online_banking', 'history' => 'payment_amount_online_banking_history'],
            'airbnb' => ['deposit' => 'deposit_airbnb', 'downpayment' => 'downpayment_airbnb', 'status' => 'payment_status_airbnb', 'history' => 'payment_amount_airbnb_history'],
        ];

        $amounts = [];
        foreach ($methodConfigs as $key => $cfg) {
            $deposit = floatval($payment[$cfg['deposit']] ?? 0);
            $downpayment = floatval($payment[$cfg['downpayment']] ?? 0);
            $total = max($deposit, $downpayment);
            $historyArr = !empty($payment[$cfg['history']])
                ? explode('|', (string) $payment[$cfg['history']])
                : null;

            if (is_array($historyArr) && count($historyArr) === $nTimestamps) {
                $amounts[$key] = floatval($historyArr[$idx] ?? 0);
            } else {
                $methodAmounts = parsePaymentAmountsAll($payment[$cfg['status']] ?? '');
                $allocated = allocateAmountsToPaymentTimestamps($timestampRows, $methodAmounts, $total);
                $amounts[$key] = floatval($allocated[$idx] ?? 0);
            }
        }

        return $amounts;
    }

    function buildPaymentMethodLabel(array $amounts): string
    {
        $labels = [
            'cash' => 'Cash',
            'gcash' => 'G-Cash',
            'maya' => 'Maya',
            'instapay' => 'Instapay',
            'online_banking' => 'Online Banking',
            'airbnb' => 'Airbnb',
        ];
        $parts = [];
        foreach ($labels as $key => $label) {
            if (($amounts[$key] ?? 0) > 0.005) {
                $parts[] = $label;
            }
        }
        return !empty($parts) ? implode(' & ', $parts) : '—';
    }

    function paymentRowMatchesDownpaymentDate(array $payment, string $paymentDateRaw, string $displayDate): bool
    {
        $dpDateRaw = trim((string) ($payment['downpayment_date'] ?? ''));
        if ($dpDateRaw === '') {
            return false;
        }

        try {
            $dpDay = (new DateTime($dpDateRaw))->format('Y-m-d');
        } catch (Exception $e) {
            return false;
        }

        if ($paymentDateRaw !== '') {
            try {
                return (new DateTime($paymentDateRaw))->format('Y-m-d') === $dpDay;
            } catch (Exception $e) {
                // fall through to display date
            }
        }

        if ($displayDate === '' || $displayDate === 'N/A') {
            return false;
        }

        try {
            return (new DateTime($displayDate))->format('Y-m-d') === $dpDay;
        } catch (Exception $e) {
            return false;
        }
    }

    function buildDownpaymentSummaryExportFields(array $payment, string $startDate, string $endDate, string $paymentDateRaw, string $displayDate): array
    {
        $empty = [
            'downpayment_method' => '—',
            'downpayment_amount' => '—',
            'downpayment_date_time' => '—',
        ];

        $dpDateRaw = trim((string) ($payment['downpayment_date'] ?? ''));
        if ($dpDateRaw === ''
            || !isDateInRange($dpDateRaw, $startDate, $endDate)
            || !paymentRowMatchesDownpaymentDate($payment, $paymentDateRaw, $displayDate)) {
            return $empty;
        }

        $dpAmounts = [
            'cash' => floatval($payment['downpayment_cash'] ?? 0),
            'gcash' => floatval($payment['downpayment_gcash'] ?? 0),
            'maya' => floatval($payment['downpayment_maya'] ?? 0),
            'instapay' => floatval($payment['downpayment_instapay'] ?? 0),
            'online_banking' => floatval($payment['downpayment_online_banking'] ?? 0),
            'airbnb' => floatval($payment['downpayment_airbnb'] ?? 0),
        ];
        $dpTotal = array_sum($dpAmounts);
        if ($dpTotal <= 0.005) {
            $dpTotal = floatval($payment['downpayment_amount'] ?? 0);
        }

        if ($dpTotal <= 0.005) {
            return $empty;
        }

        $methodLabel = buildPaymentMethodLabel($dpAmounts);
        if ($methodLabel === '—' && $dpTotal > 0.005) {
            $methodLabel = 'Downpayment';
        }

        $dpDateTime = '—';
        try {
            $dpDateTime = (new DateTime($dpDateRaw))->format('m/d/Y h:i a');
        } catch (Exception $e) {
            $dpDateTime = $dpDateRaw;
        }

        return [
            'downpayment_method' => $methodLabel,
            'downpayment_amount' => number_format($dpTotal, 2),
            'downpayment_date_time' => $dpDateTime,
        ];
    }

    function buildReferenceNoExport(array $payment, array $amounts): string
    {
        $refs = [];
        $rawStatus = trim((string) ($payment['status'] ?? ''));
        $isCheckedOut = strcasecmp($rawStatus, 'Checked Out') === 0;

        $refMap = [
            'gcash' => $isCheckedOut ? 'reference_no_g_cash' : 'deposit_gcash_ref',
            'maya' => $isCheckedOut ? 'reference_no_maya' : 'deposit_maya_ref',
            'instapay' => $isCheckedOut ? 'reference_no_instapay' : 'deposit_instapay_ref',
            'online_banking' => $isCheckedOut ? 'reference_no_online_banking' : 'deposit_online_banking_ref',
            'airbnb' => $isCheckedOut ? 'reference_no_airbnb' : 'deposit_airbnb_ref',
        ];

        foreach ($refMap as $key => $field) {
            if (($amounts[$key] ?? 0) <= 0.005) {
                continue;
            }
            $ref = trim((string) ($payment[$field] ?? ''));
            if ($ref !== '' && $ref !== 'NULL') {
                $refs[$ref] = $ref;
            }
        }

        if (empty($refs)) {
            $referenceNoRaw = trim((string) ($payment['reference_no'] ?? ''));
            if ($referenceNoRaw !== '' && $referenceNoRaw !== 'NULL') {
                $refs[$referenceNoRaw] = $referenceNoRaw;
            }
        }

        return !empty($refs) ? implode(' & ', array_values($refs)) : '—';
    }

    function mergeGroupedPaymentAmounts(array $existing, array $incoming): array
    {
        foreach (['cash_amt', 'gcash_amt', 'maya_amt', 'instapay_amt', 'online_banking_amt', 'airbnb_amt', 'amount'] as $field) {
            $existing[$field] = floatval($existing[$field] ?? 0) + floatval($incoming[$field] ?? 0);
        }
        $existing['payment_method'] = buildPaymentMethodLabel([
            'cash' => $existing['cash_amt'],
            'gcash' => $existing['gcash_amt'],
            'maya' => $existing['maya_amt'],
            'instapay' => $existing['instapay_amt'],
            'online_banking' => $existing['online_banking_amt'],
            'airbnb' => $existing['airbnb_amt'],
        ]);
        return $existing;
    }

    function formatExportAmountCell(float $amount): string
    {
        return $amount > 0.005 ? number_format($amount, 2) : '—';
    }

    $dataRows = [];
    $grandTotal = 0;
    $grandTotalAdditional = 0;
    $groupedData = [];

    foreach ($payments as $payment) {
        $bookingId = $payment['booking_id'] ?: 'N/A';
        $guestName = $payment['guest_name'] ?: 'N/A';
        $roomId = $payment['room_id'] ?: 'N/A';
        $encoder = $payment['encoder'] ?: 'N/A';
        $checkIn = formatDateTimeDisplay($payment['check_in'] ?? '');
        $checkOut = formatDateTimeDisplay($payment['checked_out_at'] ?? '');
        $bookingMeta = buildBookingMetaFields($payment);

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

        foreach ($timestampRows as $idx => $tsRow) {
            if (!isDateInRange($tsRow['date'], $startDate, $endDate)) {
                continue;
            }

            $methodAmounts = getMethodAmountsForTimestamp($payment, $timestampRows, $idx);
            $totalAmt = array_sum($methodAmounts);
            if ($totalAmt <= 0.005) {
                continue;
            }

            $paymentRaw = (string) ($tsRow['raw'] ?? '');
            $rowDiscount = getDiscountAmountForPaymentTimestamp($payment, $paymentRaw, $nTimestamps);

            $dataRows[] = array_merge([
                'booking_id' => $bookingId,
                'room_id' => $roomId,
                'encoder' => $encoder,
                'guest_name' => $guestName,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'discount_amount' => $rowDiscount,
                'discount_date' => getDiscountDateForPaymentTimestamp($payment, $paymentRaw),
                'date' => $tsRow['date'],
                'payment_date_time' => $tsRow['payment_date_time'],
                'payment_method' => buildPaymentMethodLabel($methodAmounts),
                'cash_amt' => $methodAmounts['cash'],
                'gcash_amt' => $methodAmounts['gcash'],
                'maya_amt' => $methodAmounts['maya'],
                'instapay_amt' => $methodAmounts['instapay'],
                'online_banking_amt' => $methodAmounts['online_banking'],
                'airbnb_amt' => $methodAmounts['airbnb'],
                'reference_no' => buildReferenceNoExport($payment, $methodAmounts),
                'amount' => $totalAmt,
            ], $bookingMeta, buildDownpaymentSummaryExportFields($payment, $startDate, $endDate, $paymentRaw, $tsRow['date']), buildDurationDisplays($payment, $idx), buildAdditionalFeesForPaymentRow($payment, $paymentRaw, $idx, $nTimestamps, $totalAmt, $startDate, $endDate));
            $grandTotal += $totalAmt;
        }
    }

    // Normalize per-payment rows before grouping (duplicate downpayment must not inflate totals).
    $dataRows = normalizeAllReservationPaymentExportRows($dataRows, $payments);

    // Group rows by booking_id and payment date (one row per booking per payment day).
    foreach ($dataRows as $row) {
        $key = $row['booking_id'] . '|' . $row['date'];

        if (!isset($groupedData[$key])) {
            $groupedData[$key] = $row;
        } else {
            $groupedData[$key] = mergeGroupedPaymentAmounts($groupedData[$key], $row);
            $groupedData[$key] = mergeGroupedAdditionalFees($groupedData[$key], $row);
        }
    }
    
    // Convert grouped data back to indexed array
    $dataRows = array_values($groupedData);
    $dataRows = applyCanceledBookingFinancialsToRows($dataRows);
    foreach ($dataRows as $idx => $row) {
        if (strcasecmp(trim((string) ($row['status'] ?? '')), 'Canceled') === 0) {
            foreach (['cash_amt', 'gcash_amt', 'maya_amt', 'instapay_amt', 'online_banking_amt', 'airbnb_amt'] as $amtField) {
                $dataRows[$idx][$amtField] = 0.0;
            }
            $dataRows[$idx]['payment_method'] = '—';
        }
    }
    $grandTotal = sumPaymentRowAmounts($dataRows);
    $grandTotalAdditional = sumPaymentRowAdditionalFees($dataRows);

    // Daily Sales Breakdown — aggregate payment rows by date and method
    $bookingIdsByDate = [];
    foreach ($dataRows as $row) {
        $displayDate = trim((string) ($row['date'] ?? ''));
        if ($displayDate === '' || $displayDate === 'N/A') {
            continue;
        }

        $sortKey = $displayDate;
        try {
            $parsed = DateTime::createFromFormat('m/d/Y', $displayDate);
            if ($parsed) {
                $sortKey = $parsed->format('Y-m-d');
            }
        } catch (Exception $e) {
            // keep display date as sort key
        }

        if (!isset($dailyStats[$sortKey])) {
            $dailyStats[$sortKey] = [
                'display' => $displayDate,
                'cash' => 0.0,
                'gcash' => 0.0,
                'maya' => 0.0,
                'instapay' => 0.0,
                'online_banking' => 0.0,
                'airbnb' => 0.0,
                'total' => 0.0,
            ];
            $bookingIdsByDate[$sortKey] = [];
        }

        $bookingId = (string) ($row['booking_id'] ?? '');
        if ($bookingId !== '') {
            $bookingIdsByDate[$sortKey][$bookingId] = true;
        }

        $rowCash = floatval($row['cash_amt'] ?? 0);
        $rowGcash = floatval($row['gcash_amt'] ?? 0);
        $rowMaya = floatval($row['maya_amt'] ?? 0);
        $rowInstapay = floatval($row['instapay_amt'] ?? 0);
        $rowOnlineBanking = floatval($row['online_banking_amt'] ?? 0);
        $rowAirbnb = floatval($row['airbnb_amt'] ?? 0);

        $dailyStats[$sortKey]['cash'] += $rowCash;
        $dailyStats[$sortKey]['gcash'] += $rowGcash;
        $dailyStats[$sortKey]['maya'] += $rowMaya;
        $dailyStats[$sortKey]['instapay'] += $rowInstapay;
        $dailyStats[$sortKey]['online_banking'] += $rowOnlineBanking;
        $dailyStats[$sortKey]['airbnb'] += $rowAirbnb;
        $dailyStats[$sortKey]['total'] += $rowCash + $rowGcash + $rowMaya + $rowInstapay + $rowOnlineBanking + $rowAirbnb;
    }

    krsort($dailyStats);
    foreach ($dailyStats as $sortKey => $stats) {
        $bookingCount = count($bookingIdsByDate[$sortKey] ?? []);
        $dailyStats[$sortKey]['count'] = $bookingCount;
        $breakdownGrandBookings += $bookingCount;
        $breakdownGrandCash += $stats['cash'];
        $breakdownGrandGcash += $stats['gcash'];
        $breakdownGrandMaya += $stats['maya'];
        $breakdownGrandInstapay += $stats['instapay'];
        $breakdownGrandOnlineBanking += $stats['online_banking'];
        $breakdownGrandAirbnb += $stats['airbnb'];
        $breakdownGrandTotal += $stats['total'];
    }

} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit;
}

// Prepare output variables
$currentTime = date('d/m/Y H:i:s');
$rangeLabel = $startDate . ' - ' . $endDate;
$columnCount = 37;
$thStyle = 'padding: 8px; font-weight: bold; text-align: left;';

// Output Excel or PDF
if ($isPdf) {
    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="UTF-8">';
    echo '<title>Daily Sales 2.0 Report</title>';
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
            text-align: left !important;
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
    echo '<h1>Hotel Management System - Daily Sales 2.0 Report</h1>';
    echo '<div class="meta">Generated on: ' . $currentTime . '</div>';
    echo '<div class="date-range">Date Range: ' . htmlspecialchars($rangeLabel) . '</div>';
    echo '</div>';
} else {
    echo '<html><head><meta charset="UTF-8"></head><body>';
}

// Title + summary sections (Excel: single table; PDF: separate tables)
if (!$isPdf) {
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><td colspan="' . $columnCount . '" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 16px; text-align: center; padding: 10px;">Hotel Management System - Daily Sales Report</td></tr>';
    echo '<tr><td colspan="' . $columnCount . '" style="text-align: center; padding: 5px;">Generated on: ' . $currentTime . '</td></tr>';
    echo '<tr><td colspan="' . $columnCount . '" style="text-align: center; padding: 5px; font-weight: bold; color: #256d27;">Date Range: Custom Range (' . htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate) . ')</td></tr>';
    echo '<tr><td colspan="' . $columnCount . '"></td></tr>';

    // Booking Statistics
    echo '<tr><td colspan="' . $columnCount . '" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Booking Statistics</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Check-In</td><td style="padding: 8px;">' . $checkInCount . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Check-Out</td><td style="padding: 8px;">' . $checkOutCount . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Canceled</td><td style="padding: 8px;">' . $canceledCount . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Total Rooms</td><td style="padding: 8px;">' . $totalRoomsCount . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td colspan="' . $columnCount . '"></td></tr>';

    // Type of Guest
    echo '<tr><td colspan="' . $columnCount . '" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Type of Guest</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Solo</td><td style="padding: 8px;">' . $guestTypeSolo . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Duo</td><td style="padding: 8px;">' . $guestTypeDuo . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Family</td><td style="padding: 8px;">' . $guestTypeFamily . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Group</td><td style="padding: 8px;">' . $guestTypeGroup . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Company</td><td style="padding: 8px;">' . $guestTypeCompany . '</td><td colspan="' . ($columnCount - 2) . '"></td></tr>';
    echo '<tr><td colspan="' . $columnCount . '"></td></tr>';

    // Daily Sales Breakdown
    echo '<tr><td colspan="' . $columnCount . '" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 14px; padding: 8px;">Daily Sales Breakdown</td></tr>';
    echo '<tr style="background-color: #e8f5e9;">';
    echo '<td style="font-weight: bold; padding: 8px;">Date</td>';
    echo '<td style="font-weight: bold; padding: 8px;">Bookings</td>';
    echo '<td style="font-weight: bold; padding: 8px;">Cash Sales</td>';
    echo '<td style="font-weight: bold; padding: 8px;">G-Cash Sales</td>';
    echo '<td style="font-weight: bold; padding: 8px;">Maya Sales</td>';
    echo '<td style="font-weight: bold; padding: 8px;">Instapay Sales</td>';
    echo '<td style="font-weight: bold; padding: 8px;">Online Banking Sales</td>';
    echo '<td style="font-weight: bold; padding: 8px;">Airbnb Sales</td>';
    echo '<td style="font-weight: bold; padding: 8px;">Total Sales</td>';
    echo '<td colspan="' . ($columnCount - 9) . '"></td>';
    echo '</tr>';

    if (empty($dailyStats)) {
        echo '<tr><td colspan="' . $columnCount . '" style="text-align:center; padding:10px;">No sales data found for this range.</td></tr>';
    } else {
        foreach ($dailyStats as $stats) {
            echo '<tr>';
            echo '<td style="padding: 8px;">' . htmlspecialchars($stats['display']) . '</td>';
            echo '<td style="padding: 8px;">' . (int) ($stats['count'] ?? 0) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($stats['cash'], 2) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($stats['gcash'], 2) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($stats['maya'], 2) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($stats['instapay'], 2) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($stats['online_banking'], 2) . '</td>';
            echo '<td style="padding: 8px;">' . number_format($stats['airbnb'], 2) . '</td>';
            echo '<td style="padding: 8px; font-weight: bold;">' . number_format($stats['total'], 2) . '</td>';
            echo '<td colspan="' . ($columnCount - 9) . '"></td>';
            echo '</tr>';
        }
        echo '<tr style="background-color: #e8f5e9; font-weight: bold;">';
        echo '<td style="padding: 8px;">GRAND TOTAL</td>';
        echo '<td style="padding: 8px;">' . $breakdownGrandBookings . '</td>';
        echo '<td style="padding: 8px;">' . number_format($breakdownGrandCash, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($breakdownGrandGcash, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($breakdownGrandMaya, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($breakdownGrandInstapay, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($breakdownGrandOnlineBanking, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($breakdownGrandAirbnb, 2) . '</td>';
        echo '<td style="padding: 8px;">' . number_format($breakdownGrandTotal, 2) . '</td>';
        echo '<td colspan="' . ($columnCount - 9) . '"></td>';
        echo '</tr>';
    }
    echo '<tr><td colspan="' . $columnCount . '"></td></tr>';
}

if ($isPdf) {
    echo '<div class="section-title">Booking Statistics</div>';
    echo '<table border="1" cellpadding="5" cellspacing="0" style="width: auto; margin-bottom: 16px;">';
    echo '<tr><td style="font-weight:bold;">Check-In</td><td>' . $checkInCount . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Check-Out</td><td>' . $checkOutCount . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Canceled</td><td>' . $canceledCount . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Total Rooms</td><td>' . $totalRoomsCount . '</td></tr>';
    echo '</table>';

    echo '<div class="section-title">Type of Guest</div>';
    echo '<table border="1" cellpadding="5" cellspacing="0" style="width: auto; margin-bottom: 16px;">';
    echo '<tr><td style="font-weight:bold;">Solo</td><td>' . $guestTypeSolo . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Duo</td><td>' . $guestTypeDuo . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Family</td><td>' . $guestTypeFamily . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Group</td><td>' . $guestTypeGroup . '</td></tr>';
    echo '<tr><td style="font-weight:bold;">Company</td><td>' . $guestTypeCompany . '</td></tr>';
    echo '</table>';

    echo '<div class="section-title">Daily Sales Breakdown</div>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<thead><tr>';
    echo '<th>Date</th><th>Bookings</th><th>Cash Sales</th><th>G-Cash Sales</th><th>Maya Sales</th>';
    echo '<th>Instapay Sales</th><th>Online Banking Sales</th><th>Airbnb Sales</th><th>Total Sales</th>';
    echo '</tr></thead><tbody>';
    if (empty($dailyStats)) {
        echo '<tr><td colspan="9" style="text-align:center; padding:10px;">No sales data found for this range.</td></tr>';
    } else {
        foreach ($dailyStats as $stats) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($stats['display']) . '</td>';
            echo '<td>' . (int) ($stats['count'] ?? 0) . '</td>';
            echo '<td>' . number_format($stats['cash'], 2) . '</td>';
            echo '<td>' . number_format($stats['gcash'], 2) . '</td>';
            echo '<td>' . number_format($stats['maya'], 2) . '</td>';
            echo '<td>' . number_format($stats['instapay'], 2) . '</td>';
            echo '<td>' . number_format($stats['online_banking'], 2) . '</td>';
            echo '<td>' . number_format($stats['airbnb'], 2) . '</td>';
            echo '<td style="font-weight:bold;">' . number_format($stats['total'], 2) . '</td>';
            echo '</tr>';
        }
        echo '<tr class="total-row">';
        echo '<td>GRAND TOTAL</td>';
        echo '<td>' . $breakdownGrandBookings . '</td>';
        echo '<td>' . number_format($breakdownGrandCash, 2) . '</td>';
        echo '<td>' . number_format($breakdownGrandGcash, 2) . '</td>';
        echo '<td>' . number_format($breakdownGrandMaya, 2) . '</td>';
        echo '<td>' . number_format($breakdownGrandInstapay, 2) . '</td>';
        echo '<td>' . number_format($breakdownGrandOnlineBanking, 2) . '</td>';
        echo '<td>' . number_format($breakdownGrandAirbnb, 2) . '</td>';
        echo '<td>' . number_format($breakdownGrandTotal, 2) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<br>';
    echo '<div class="section-title">Daily Sales 2.0 Details</div>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<thead>';
    echo '<tr>';
}

// Section title for detailed data
if (!$isPdf) {
    echo '<tr><td colspan="' . $columnCount . '" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Daily Sales 2.0 (' . htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate) . ')</td></tr>';
    echo '<tr style="background-color: #4CAF50; color: white;">';
}

// Table headers (left-aligned)
$detailHeaders = [
    'Booking ID', 'Room ID', 'Encoder', 'Modified', 'Booking Type', 'Room Type', 'Type of Guest',
    'Promo', 'Discount Amount', 'Discount Date', 'Guest Name', 'Check-in', 'Check out',
    'Duration', 'Extension Duration', 'Breakfast', 'Status',
    'Downpayment/Reservation Method', 'Downpayment Amount', 'Downpayment Date & Time',
    'Payment Method',
    'Cash', 'G-cash', 'Maya', 'Instapay', 'Online Banking', 'Airbnb',
    'Reference No.', 'Payment Date',
    'Additional Items', 'Additional Foods', 'Additional Guest', 'Additional Pet',
    'Additional Missing Items', 'Additional Penalty', 'Additional Total Amount Fees', 'Amount Paid',
];
foreach ($detailHeaders as $headerLabel) {
    echo '<th style="' . $thStyle . '">' . htmlspecialchars($headerLabel) . '</th>';
}
echo '</tr>';

if ($isPdf) {
    echo '</thead>';
}

echo '<tbody>';

// Data rows
if (empty($dataRows)) {
    echo '<tr>';
    echo '<td colspan="' . $columnCount . '" style="text-align: center; padding: 20px; color: #999;">No payment records found for the selected date range.</td>';
    echo '</tr>';
} else {
    $grandCash = 0.0;
    $grandGcash = 0.0;
    $grandMaya = 0.0;
    $grandInstapay = 0.0;
    $grandOnlineBanking = 0.0;
    $grandAirbnb = 0.0;
    foreach ($dataRows as $row) {
        $discountDisplay = ($row['discount_amount'] ?? 0) > 0 ? number_format($row['discount_amount'], 2) : '—';
        $additionalTotalFees = floatval($row['additional_total_fees'] ?? 0);

        $grandCash += floatval($row['cash_amt'] ?? 0);
        $grandGcash += floatval($row['gcash_amt'] ?? 0);
        $grandMaya += floatval($row['maya_amt'] ?? 0);
        $grandInstapay += floatval($row['instapay_amt'] ?? 0);
        $grandOnlineBanking += floatval($row['online_banking_amt'] ?? 0);
        $grandAirbnb += floatval($row['airbnb_amt'] ?? 0);

        echo '<tr>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['booking_id']) . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['room_id']) . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['encoder']) . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['modified'] ?? '') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['booking_type'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['room_type'] ?? '') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['guest_type'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['promo'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . $discountDisplay . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['discount_date'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['guest_name']) . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['check_in']) . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['check_out']) . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['duration'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['extension_duration'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['breakfast'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['status'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['downpayment_method'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['downpayment_amount'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['downpayment_date_time'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['payment_method'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . formatExportAmountCell(floatval($row['cash_amt'] ?? 0)) . '</td>';
        echo '<td style="padding: 5px;">' . formatExportAmountCell(floatval($row['gcash_amt'] ?? 0)) . '</td>';
        echo '<td style="padding: 5px;">' . formatExportAmountCell(floatval($row['maya_amt'] ?? 0)) . '</td>';
        echo '<td style="padding: 5px;">' . formatExportAmountCell(floatval($row['instapay_amt'] ?? 0)) . '</td>';
        echo '<td style="padding: 5px;">' . formatExportAmountCell(floatval($row['online_banking_amt'] ?? 0)) . '</td>';
        echo '<td style="padding: 5px;">' . formatExportAmountCell(floatval($row['airbnb_amt'] ?? 0)) . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['reference_no'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['payment_date_time'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['additional_items'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['additional_foods'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['additional_guest'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['additional_pet'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['additional_missing_items'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . htmlspecialchars($row['additional_penalty'] ?? '—') . '</td>';
        echo '<td style="padding: 5px;">' . ($additionalTotalFees > 0 ? number_format($additionalTotalFees, 2) : '—') . '</td>';
        echo '<td style="padding: 5px;">' . number_format(floatval($row['amount'] ?? 0), 2) . '</td>';
        echo '</tr>';
    }

    // Grand total row
    echo '<tr style="background-color: #e0e0e0; font-weight: bold;">';
    echo '<td colspan="21" style="padding: 8px; text-align: left;">GRAND TOTAL</td>';
    echo '<td style="padding: 8px;">' . formatExportAmountCell($grandCash) . '</td>';
    echo '<td style="padding: 8px;">' . formatExportAmountCell($grandGcash) . '</td>';
    echo '<td style="padding: 8px;">' . formatExportAmountCell($grandMaya) . '</td>';
    echo '<td style="padding: 8px;">' . formatExportAmountCell($grandInstapay) . '</td>';
    echo '<td style="padding: 8px;">' . formatExportAmountCell($grandOnlineBanking) . '</td>';
    echo '<td style="padding: 8px;">' . formatExportAmountCell($grandAirbnb) . '</td>';
    echo '<td style="padding: 8px;">—</td>';
    echo '<td style="padding: 8px;">—</td>';
    echo '<td colspan="6" style="padding: 8px;">—</td>';
    echo '<td style="padding: 8px;">' . number_format($grandTotalAdditional, 2) . '</td>';
    echo '<td style="padding: 8px;">' . number_format($grandTotal, 2) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '</body></html>';
?>
