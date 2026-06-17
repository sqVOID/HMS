<?php
if (defined('DETAILED_BOOKING_REPORT_FUNCTIONS_LOADED')) {
    return;
}
define('DETAILED_BOOKING_REPORT_FUNCTIONS_LOADED', true);
require_once __DIR__ . '/report_helpers.php';
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
                'payment_date_time' => $dt->format('m/d/Y'),
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
            return $dt->format('m/d/Y') . '<br>' . $dt->format('h:i a');
        } catch (Exception $e) {
            return '—';
        }
    }

    function formatDateOnlyDisplay($rawTimestamp): string
    {
        if (empty($rawTimestamp) || !is_string($rawTimestamp)) {
            return '—';
        }
        $rawTimestamp = trim($rawTimestamp);
        if ($rawTimestamp === '' || $rawTimestamp === '0000-00-00 00:00:00') {
            return '—';
        }
        try {
            return (new DateTime($rawTimestamp))->format('m/d/Y');
        } catch (Exception $e) {
            return '—';
        }
    }

    function formatExtendDateDisplay(array $payment, int $paymentTimestampIndex, string $paymentRaw, bool $bundledExtension = false): string
    {
        $extHours = intval($payment['extend_hours'] ?? 0);
        $extMinutes = intval($payment['extend_minutes'] ?? 0);
        $extPrice = floatval($payment['extend_price'] ?? 0);
        
        // CRITICAL FIX: Check if there's actual extension data
        $hasExtensionData = ($extHours > 0 || $extMinutes > 0 || $extPrice > 0.005);
        
        if ($paymentTimestampIndex === 0 && !$bundledExtension && !$hasExtensionData) {
            return '—';
        }

        if (!$hasExtensionData && !$bundledExtension) {
            return '—';
        }

        if ($bundledExtension || $hasExtensionData) {
            $extTimestamps = array_values(array_filter(array_map('trim', explode('|', (string) ($payment['extension_time_at'] ?? '')))));
            if (isset($extTimestamps[0]) && $extTimestamps[0] !== '') {
                return formatDateOnlyDisplay($extTimestamps[0]);
            }
            if ($paymentRaw !== '') {
                return formatDateOnlyDisplay($paymentRaw);
            }
            if (!empty($payment['check_in'])) {
                return formatDateOnlyDisplay($payment['check_in']);
            }
            return '—';
        }

        $extTimestamps = array_values(array_filter(array_map('trim', explode('|', (string) ($payment['extension_time_at'] ?? '')))));
        $segIndex = $paymentTimestampIndex - 1;
        if (isset($extTimestamps[$segIndex]) && $extTimestamps[$segIndex] !== '') {
            return formatDateOnlyDisplay($extTimestamps[$segIndex]);
        }

        if ($paymentRaw !== '') {
            return formatDateOnlyDisplay($paymentRaw);
        }

        return '—';
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

    function buildDurationDisplays(array $payment, int $paymentTimestampIndex = 0, string $paymentRaw = ''): array
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

        // Check if there's actual extension data
        $hasExtensionData = ($extHours > 0 || $extMinutes > 0 || $extPrice > 0.005);
        
        // First payment = room rate only, unless book + extend were paid together (single timestamp).
        $bundledExtension = $paymentTimestampIndex === 0 && isBundledExtensionExportPayment($payment);
        
        // CRITICAL FIX: Show extension data if it exists, regardless of payment index
        // This ensures extensions show in the export even when paid together with the booking
        if ($paymentTimestampIndex === 0 && !$bundledExtension && !$hasExtensionData) {
            return [
                'duration' => formatBaseDurationDisplay($baseDuration, $baseUnit),
                'extension_duration' => '—',
                'extend_date' => '—',
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
            'extension_duration' => $extDurationDisplay,
            'extend_date' => formatExtendDateDisplay($payment, $paymentTimestampIndex, $paymentRaw, $bundledExtension || $hasExtensionData),
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

    function getReferenceNoForPaymentMethod(array $payment, string $paymentMethod): string
    {
        $methodMap = [
            'G-Cash' => ['reference_no_g_cash', 'deposit_gcash_ref', 'downpayment_gcash_ref'],
            'Maya' => ['reference_no_maya', 'deposit_maya_ref', 'downpayment_maya_ref'],
            'Instapay' => ['reference_no_instapay', 'deposit_instapay_ref', 'downpayment_instapay_ref'],
            'Online Banking' => ['reference_no_online_banking', 'deposit_online_banking_ref', 'downpayment_online_banking_ref'],
            'Airbnb' => ['reference_no_airbnb', 'deposit_airbnb_ref', 'downpayment_airbnb_ref'],
        ];

        if (!isset($methodMap[$paymentMethod])) {
            return '—';
        }

        foreach ($methodMap[$paymentMethod] as $field) {
            $ref = trim((string) ($payment[$field] ?? ''));
            if ($ref !== '' && strcasecmp($ref, 'NULL') !== 0) {
                return $ref;
            }
        }

        $generic = trim((string) ($payment['reference_no'] ?? ''));
        if ($generic !== '' && strcasecmp($generic, 'NULL') !== 0) {
            $decoded = json_decode($generic, true);
            if (is_array($decoded)) {
                $parts = array_filter(array_map('trim', $decoded));
                if (!empty($parts)) {
                    return implode(', ', $parts);
                }
            }
            return $generic;
        }

        return '—';
    }

    function clearCanceledBookingRowFinancials(array $row): array
    {
        return applyCanceledBookingFinancialsToRow($row);
    }

    function formatStatusForExport(array $payment): string
    {
        if (function_exists('resolvePaymentExportDisplayStatus')) {
            $resolved = resolvePaymentExportDisplayStatus($payment);
            return $resolved !== 'N/A' ? $resolved : '—';
        }

        $status = trim((string) ($payment['status'] ?? ''));
        if ($status === '') {
            return '—';
        }
        if (strcasecmp($status, 'Confirmed') === 0) {
            return 'Check-in';
        }

        if ((strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0)
            && strcasecmp(trim((string) ($payment['booking_type'] ?? '')), 'Reservation') === 0
        ) {
            $checkIn = trim((string) ($payment['check_in'] ?? ''));
            $hasValidCheckIn = ($checkIn !== ''
                && strpos($checkIn, '0000') === false
                && intval(substr($checkIn, 0, 4)) >= 1000);
            if (!$hasValidCheckIn) {
                return 'Canceled Reservation';
            }
        }

        return $status;
    }

    function buildBookingMetaFields(array $payment): array
    {
        $modifiedIndicator = (!empty($payment['modification_updated_at']) && trim((string) $payment['modification_updated_at']) !== '') ? 'M' : '';
        $promoMeta = parsePromoSelection($payment['promo'] ?? '');

        $reservationDate = '—';
        if (!empty($payment['reservation_date'])) {
            try {
                $reservationDate = (new DateTime($payment['reservation_date']))->format('m/d/Y');
            } catch (Exception $e) {
                $reservationDate = (string) $payment['reservation_date'];
            }
        }

        $transferRoomFrom = trim((string) ($payment['transfer_room_from'] ?? ''));
        $salesChannel = trim((string) ($payment['sales_channel'] ?? ''));
        $roomType = trim((string) ($payment['room_type'] ?? ''));
        $roomId = trim((string) ($payment['room_id'] ?? ''));
        $currentRoom = trim($roomType . ' ' . $roomId);
        $address = trim((string) ($payment['address'] ?? ''));
        $contactNo = trim((string) ($payment['contact_no'] ?? ''));
        $vehicleDescStr = trim((string) ($payment['vehicle_description'] ?? ''));
        $plateNoStr = trim((string) ($payment['plate_number'] ?? ''));
        $combinedVehicleDesc = [];
        if ($vehicleDescStr !== '') {
            $combinedVehicleDesc[] = $vehicleDescStr;
        }
        if ($plateNoStr !== '') {
            $combinedVehicleDesc[] = $plateNoStr;
        }

        return [
            'modified' => $modifiedIndicator,
            'original_room' => $transferRoomFrom !== '' ? $transferRoomFrom : '—',
            'sales_channel' => $salesChannel !== '' ? $salesChannel : '—',
            'current_room' => $currentRoom !== '' ? $currentRoom : '—',
            'booking_type' => $payment['booking_type'] ?: '—',
            'room_type' => $payment['room_type'] ?? '',
            'guest_type' => $payment['guest_type'] ?: '—',
            'address' => $address !== '' ? $address : '—',
            'contact_no' => $contactNo !== '' ? $contactNo : '—',
            'vehicle_description' => !empty($combinedVehicleDesc) ? implode(' - ', $combinedVehicleDesc) : '—',
            'status' => formatStatusForExport($payment),
            'promo' => $promoMeta['title'] ?: '—',
            'reservation_amount' => getReservationAmountDisplay($payment),
            'reservation_date' => $reservationDate,
            'breakfast' => formatBreakfastForExport($payment),
        ];
    }