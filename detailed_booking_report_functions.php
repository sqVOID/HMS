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

    /**
     * Align per-timestamp payment amounts with payment_amount_*_history columns.
     * Mirrors export_payment_type_report_pdf.php so detailed booking exports match payment type totals.
     */
    function allocatePaymentMethodAmountsByHistory(
        array $payment,
        array $timestampRows,
        string $historyKey,
        string $statusKey,
        float $methodTotal
    ): array {
        $nTimestamps = count($timestampRows);
        if ($nTimestamps === 0) {
            return [];
        }

        $historyRaw = trim((string) ($payment[$historyKey] ?? ''));
        $historyArr = $historyRaw !== '' ? explode('|', $historyRaw) : null;

        if (is_array($historyArr) && count($historyArr) === $nTimestamps) {
            $amountsByTimestamp = array_map(fn($v) => max(0, floatval($v)), $historyArr);
            $sumAll = array_sum($amountsByTimestamp);

            if ($nTimestamps >= 2 && $methodTotal > 0 && $sumAll > $methodTotal + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $sumPrior = array_sum(array_slice($amountsByTimestamp, 0, $lastIdx));
                if ($amountsByTimestamp[$lastIdx] >= $methodTotal - 0.02) {
                    $amountsByTimestamp[$lastIdx] = max(0, $methodTotal - $sumPrior);
                }
            } elseif ($methodTotal > 0 && $sumAll < $methodTotal - 0.02) {
                $missing = $methodTotal - $sumAll;
                $filled = false;
                foreach ($amountsByTimestamp as $idx => $value) {
                    if ($value <= 0.01 && $missing > 0.01) {
                        $amountsByTimestamp[$idx] += $missing;
                        $missing = 0;
                        $filled = true;
                        break;
                    }
                }
                if (!$filled && $missing > 0.01) {
                    $amountsByTimestamp[0] += $missing;
                }
            }

            return $amountsByTimestamp;
        }

        if (is_array($historyArr) && $nTimestamps === count($historyArr) + 1) {
            $sumPrior = 0.0;
            foreach ($historyArr as $seg) {
                $sumPrior += floatval($seg);
            }
            return array_merge(
                array_map(fn($v) => max(0, floatval($v)), $historyArr),
                [max(0, $methodTotal - $sumPrior)]
            );
        }

        if (is_array($historyArr) && count($historyArr) === 1 && $nTimestamps === 2) {
            $lastAmount = floatval($historyArr[0] ?? 0);
            $firstAmount = max(0, $methodTotal - $lastAmount);
            return [$firstAmount, $lastAmount];
        }

        $methodAmounts = parsePaymentAmountsAll($payment[$statusKey] ?? '');

        if ($nTimestamps === 2 && count($methodAmounts) === 1) {
            $lastAmount = floatval($methodAmounts[0]);
            if ($lastAmount > 0 && $methodTotal > $lastAmount + 0.01) {
                return [$methodTotal - $lastAmount, $lastAmount];
            }
        }

        return allocateAmountsToPaymentTimestamps($timestampRows, $methodAmounts, $methodTotal);
    }

    function resolveDetailedBookingMethodTotal(array $payment, string $depositKey, string $downpaymentKey, string $heldKey = ''): float
    {
        $heldByMethod = getHeldNewDepositAmountsByMethod($payment);
        $heldAmount = $heldKey !== '' ? floatval($heldByMethod[$heldKey] ?? 0) : 0.0;
        $deposit = max(0, floatval($payment[$depositKey] ?? 0) - $heldAmount);
        $downpayment = floatval($payment[$downpaymentKey] ?? 0);

        if (function_exists('isLiveReservedPaymentExport') && isLiveReservedPaymentExport($payment)) {
            return $downpayment;
        }

        return max($deposit, $downpayment);
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
            foreach (parseDiscountAmountHistory($discountHistoryRaw) as $parsedEntry) {
                $discAmt = floatval($parsedEntry['amount'] ?? 0);
                $discDateTime = trim((string) ($parsedEntry['datetime'] ?? ''));
                $discDate = strlen($discDateTime) >= 10 ? substr($discDateTime, 0, 10) : $discDateTime;
                if ($discDate === $paymentDate) {
                    $discountAmount += $discAmt;
                }
            }
        } else {
            // No history, use total discount amount (SC/PWD + LGP + VIP) and divide by payment rows
            $discountAmount = getBookingTotalDiscountFromRecord($payment);
            if ($totalRows > 1 && $discountAmount > 0) {
                $discountAmount = $discountAmount / $totalRows;
            }
        }

        return $discountAmount;
    }

    function getTypedDiscountBreakdownForPaymentTimestamp($payment, $paymentDateStr, $totalRows = 1): array
    {
        $result = [
            'sc_pwd_discount_amount' => 0.0,
            'lgp_discount_amount' => 0.0,
            'vip_discount_amount' => 0.0,
            'long_discount_amount' => 0.0,
            'long_discount_percent' => floatval($payment['long_discount_percent'] ?? 0),
            'lgp_approver_name' => trim((string) ($payment['lgp_approver_name'] ?? '')),
            'vip_approver_name' => trim((string) ($payment['vip_approver_name'] ?? '')),
        ];

        $paymentDate = strlen($paymentDateStr) >= 10 ? substr($paymentDateStr, 0, 10) : $paymentDateStr;
        $discountHistoryRaw = trim((string) ($payment['discount_amount_history'] ?? ''));

        if ($discountHistoryRaw !== '') {
            foreach (parseDiscountAmountHistory($discountHistoryRaw) as $parsedEntry) {
                $discAmt = floatval($parsedEntry['amount'] ?? 0);
                $discDateTime = trim((string) ($parsedEntry['datetime'] ?? ''));
                $discDate = strlen($discDateTime) >= 10 ? substr($discDateTime, 0, 10) : $discDateTime;
                if ($discDate !== $paymentDate) {
                    continue;
                }

                $type = strtoupper(trim((string) ($parsedEntry['type'] ?? '')));
                if ($type === 'SC/PWD') {
                    $result['sc_pwd_discount_amount'] += $discAmt;
                } elseif ($type === 'LGP' || $type === 'LPG') {
                    $result['lgp_discount_amount'] += $discAmt;
                } elseif ($type === 'VIP') {
                    $result['vip_discount_amount'] += $discAmt;
                } elseif ($type === 'LONG TERM' || $type === 'LONGTERM') {
                    $result['long_discount_amount'] += $discAmt;
                } else {
                    $result['sc_pwd_discount_amount'] += $discAmt;
                }
            }
        } else {
            $scPwdAmount = (!empty($payment['discount_enabled']) || floatval($payment['discount_amount'] ?? 0) > 0)
                ? floatval($payment['discount_amount'] ?? 0) : 0.0;
            $lgpAmount = (!empty($payment['lgp_discount_enabled']) || floatval($payment['lgp_discount_amount'] ?? 0) > 0)
                ? floatval($payment['lgp_discount_amount'] ?? 0) : 0.0;
            $vipAmount = (!empty($payment['vip_discount_enabled']) || floatval($payment['vip_discount_amount'] ?? 0) > 0)
                ? floatval($payment['vip_discount_amount'] ?? 0) : 0.0;
            $longAmount = (!empty($payment['long_discount_enabled']) || floatval($payment['long_discount_amount'] ?? 0) > 0)
                ? floatval($payment['long_discount_amount'] ?? 0) : 0.0;

            if ($totalRows > 1) {
                $scPwdAmount /= $totalRows;
                $lgpAmount /= $totalRows;
                $vipAmount /= $totalRows;
                $longAmount /= $totalRows;
            }

            $result['sc_pwd_discount_amount'] = $scPwdAmount;
            $result['lgp_discount_amount'] = $lgpAmount;
            $result['vip_discount_amount'] = $vipAmount;
            $result['long_discount_amount'] = $longAmount;
        }

        // Prefer booking percent; keep it even when amount is allocated from history
        if ($result['long_discount_percent'] <= 0 && $result['long_discount_amount'] > 0) {
            $result['long_discount_percent'] = floatval($payment['long_discount_percent'] ?? 0);
        }

        return $result;
    }

    function formatDetailedReportDiscountAmountDisplay(float $amount): string
    {
        return $amount > 0 ? '₱' . number_format($amount, 2) : '—';
    }

    /** e.g. "5% = 90.00" */
    function formatDetailedReportLongTermDiscountDisplay(float $amount, float $percent): string
    {
        if ($amount <= 0 && $percent <= 0) {
            return '—';
        }
        $pctText = (abs($percent - round($percent)) < 0.001)
            ? (string) intval(round($percent))
            : rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
        if ($percent > 0 && $amount > 0) {
            return $pctText . '% = ' . number_format($amount, 2, '.', '');
        }
        if ($percent > 0) {
            return $pctText . '%';
        }
        return number_format($amount, 2, '.', '');
    }

    function getDiscountDateForPaymentTimestamp($payment, $paymentDateStr): string
    {
        $discountHistoryRaw = trim((string) ($payment['discount_amount_history'] ?? ''));
        $paymentDate = strlen($paymentDateStr) >= 10 ? substr($paymentDateStr, 0, 10) : $paymentDateStr;

        if ($discountHistoryRaw !== '') {
            foreach (parseDiscountAmountHistory($discountHistoryRaw) as $parsedEntry) {
                $discDateTime = trim((string) ($parsedEntry['datetime'] ?? ''));
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

    function loadReportRoomDurationMaps(PDO $conn): array
    {
        $durationsMap = [];
        $roomIdToDbId = [];

        try {
            try {
                $conn->exec("ALTER TABLE room_durations ADD COLUMN duration_unit VARCHAR(10) NOT NULL DEFAULT 'hours'");
            } catch (Exception $e) {
                // Column may already exist
            }

            $roomStmt = $conn->query("SELECT id, room_id FROM rooms");
            while ($r = $roomStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($r['room_id'])) {
                    $roomIdToDbId[(string) $r['room_id']] = (int) $r['id'];
                }
            }

            $durStmt = $conn->query("SELECT room_id, duration_hours, price, duration_unit FROM room_durations");
            while ($row = $durStmt->fetch(PDO::FETCH_ASSOC)) {
                $rid = (int) $row['room_id'];
                if (!isset($durationsMap[$rid])) {
                    $durationsMap[$rid] = [];
                }
                $unit = (isset($row['duration_unit']) && $row['duration_unit'] === 'days') ? 'days' : 'hours';
                $durationsMap[$rid][] = [
                    'duration_hours' => $row['duration_hours'],
                    'price' => floatval($row['price']),
                    'duration_unit' => $unit,
                    'unit' => $unit,
                ];
            }
        } catch (Exception $e) {
            try {
                $durStmt = $conn->query("SELECT room_id, duration_hours, price FROM room_durations");
                while ($row = $durStmt->fetch(PDO::FETCH_ASSOC)) {
                    $rid = (int) $row['room_id'];
                    if (!isset($durationsMap[$rid])) {
                        $durationsMap[$rid] = [];
                    }
                    $durationsMap[$rid][] = [
                        'duration_hours' => $row['duration_hours'],
                        'price' => floatval($row['price']),
                        'duration_unit' => 'hours',
                        'unit' => 'hours',
                    ];
                }
            } catch (Exception $e2) {
                // ignore
            }
        }

        return [
            'durationsMap' => $durationsMap,
            'roomIdToDbId' => $roomIdToDbId,
        ];
    }

    function attachReportRoomRates(array &$payment, array $durationMaps): void
    {
        $roomKey = isset($payment['room_id']) ? (string) $payment['room_id'] : '';
        $dbId = $durationMaps['roomIdToDbId'][$roomKey] ?? null;
        $payment['room_db_id'] = $dbId;
        $payment['rates'] = ($dbId !== null && isset($durationMaps['durationsMap'][$dbId]))
            ? $durationMaps['durationsMap'][$dbId]
            : [];
    }

    function getReportBookingDurationHours(array $payment): float
    {
        $dur = floatval($payment['duration'] ?? 0);
        $unit = strtolower(trim($payment['duration_unit'] ?? 'hours'));

        if ($dur == 0) {
            $promoValue = $payment['promo'] ?? '';
            $hasPromo = !empty($promoValue) && $promoValue !== 'None' && $promoValue !== 'Select Promo';
            if ($hasPromo) {
                if (preg_match('/(\d+)\s*hrs?/i', $promoValue, $matches)) {
                    return floatval($matches[1]);
                }
                return 12.0;
            }
        }

        if ($unit === 'night' || $unit === 'nights') {
            return $dur * 12;
        }
        if ($unit === 'day' || $unit === 'days') {
            return $dur * 24;
        }

        return $dur;
    }

    function resolveReportDurationLabel(array $payment, float $hours): array
    {
        $safeHours = $hours;
        if ($safeHours <= 0) {
            $safeHours = getReportBookingDurationHours($payment);
        }

        $rates = isset($payment['rates']) && is_array($payment['rates']) ? $payment['rates'] : [];
        $isDayRate = function ($r) {
            return is_array($r) && (($r['unit'] ?? '') === 'days' || ($r['duration_unit'] ?? '') === 'days');
        };

        $match = null;
        foreach ($rates as $r) {
            if (floatval($r['duration_hours'] ?? 0) === floatval($safeHours)) {
                $match = $r;
                break;
            }
        }

        $useDays = false;
        if ($match !== null) {
            $useDays = $isDayRate($match);
        } elseif ($safeHours >= 24 && fmod($safeHours, 24) == 0) {
            $dayRates = array_values(array_filter($rates, $isDayRate));
            $bookingUnit = strtolower(trim($payment['duration_unit'] ?? ''));
            if ($bookingUnit === 'day' || $bookingUnit === 'days') {
                $useDays = true;
            } elseif (!empty($dayRates)) {
                $originalHours = getReportBookingDurationHours($payment);
                $originalWasDays = false;
                foreach ($dayRates as $r) {
                    if (floatval($r['duration_hours'] ?? 0) === floatval($originalHours)) {
                        $originalWasDays = true;
                        break;
                    }
                }
                $dayRateDividesTotal = false;
                foreach ($dayRates as $r) {
                    $rh = floatval($r['duration_hours'] ?? 0);
                    if ($rh > 0 && fmod($safeHours, $rh) == 0) {
                        $dayRateDividesTotal = true;
                        break;
                    }
                }
                $useDays = $originalWasDays || $dayRateDividesTotal;
            }
        }

        if ($useDays && $safeHours >= 24 && fmod($safeHours, 24) == 0) {
            $days = $safeHours / 24;
            return [
                'amount' => $days,
                'unit' => 'days',
                'text' => $days . ' Day' . ($days != 1 ? 's' : ''),
            ];
        }

        $bookingUnit = strtolower(trim($payment['duration_unit'] ?? ''));
        if ($bookingUnit === 'night' || $bookingUnit === 'nights') {
            $nights = $safeHours / 12;
            return [
                'amount' => $nights,
                'unit' => 'night',
                'text' => $nights . ' Night' . ($nights != 1 ? 's' : ''),
            ];
        }

        return [
            'amount' => $safeHours,
            'unit' => 'hours',
            'text' => $safeHours . ' Hour' . ($safeHours != 1 ? 's' : ''),
        ];
    }

    function computeReportStayHoursFromDates(array $payment): ?array
    {
        $checkIn = trim((string) ($payment['check_in'] ?? ''));
        $checkOut = trim((string) ($payment['checked_out_at'] ?? ''));
        if ($checkIn === '' || $checkOut === '' || $checkOut === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $in = new DateTime($checkIn);
            $out = new DateTime($checkOut);
            $diffMins = (int) round(($out->getTimestamp() - $in->getTimestamp()) / 60);
            if ($diffMins < 0) {
                return null;
            }

            return [
                'wholeHours' => intdiv($diffMins, 60),
                'remainingMinutes' => $diffMins % 60,
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    function formatReportStayDurationDisplay(
        array $payment,
        int $wholeHours,
        int $remainingMinutes = 0,
        int $extendHours = 0,
        int $extendMinutes = 0
    ): string {
        $totalHours = max(0, (int) $wholeHours);
        $remMins = max(0, (int) $remainingMinutes);
        $extH = max(0, (int) $extendHours);
        $extM = max(0, (int) $extendMinutes);
        $hasExtend = $extH > 0 || $extM > 0;

        if ($hasExtend) {
            $baseHours = getReportBookingDurationHours($payment);
            $fromDatesBase = max(0, $totalHours - $extH);
            if ($baseHours <= 0) {
                $baseHours = $fromDatesBase;
            } elseif (abs(($baseHours + $extH) - $totalHours) > 1) {
                $baseHours = $fromDatesBase;
            }

            $baseResolved = resolveReportDurationLabel($payment, $baseHours);
            $totalResolved = resolveReportDurationLabel($payment, (float) $totalHours);

            if ($totalResolved['unit'] === 'days' && $remMins === 0 && $extM === 0) {
                return $totalResolved['text'] . ' (Extended)';
            }

            if ($baseResolved['unit'] === 'days') {
                $parts = [$baseResolved['text']];
                if ($extH > 0) {
                    $extResolved = resolveReportDurationLabel($payment, (float) $extH);
                    if ($extResolved['unit'] === 'days') {
                        $parts[] = $extResolved['text'];
                    } else {
                        $parts[] = $extH . ' Hr' . ($extH !== 1 ? 's' : '');
                    }
                }
                $leftoverMins = $extM > 0 ? $extM : $remMins;
                if ($leftoverMins > 0) {
                    $parts[] = $leftoverMins . ' Min' . ($leftoverMins !== 1 ? 's' : '');
                }
                return implode(' ', $parts) . ' (Extended)';
            }
        }

        if ($remMins > 0) {
            $text = $totalHours . ':' . str_pad((string) $remMins, 2, '0', STR_PAD_LEFT) . ' Hours';
        } else {
            $text = resolveReportDurationLabel($payment, (float) $totalHours)['text'];
        }

        if ($hasExtend) {
            $text .= ' (Extended)';
        }

        return $text;
    }

    function formatReportExtendDurationLabel(array $payment, int $extendHours, int $extendMinutes = 0): string
    {
        $hours = max(0, (int) $extendHours);
        $minutes = max(0, (int) $extendMinutes);

        if ($hours > 0 && $minutes > 0) {
            return $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ' Hours';
        }
        if ($minutes > 0 && $hours <= 0) {
            return $minutes . ' Minute' . ($minutes !== 1 ? 's' : '');
        }
        if ($hours <= 0) {
            return '';
        }

        $rates = isset($payment['rates']) && is_array($payment['rates']) ? $payment['rates'] : [];
        $resolved = resolveReportDurationLabel($payment, (float) $hours);
        if ($resolved['unit'] === 'days') {
            return $resolved['text'];
        }

        if ($hours >= 24 && fmod($hours, 24) == 0) {
            $days = $hours / 24;
            $extPrice = floatval($payment['extend_regular_rate'] ?? $payment['extend_price'] ?? 0);
            $dayRates = array_values(array_filter($rates, function ($r) {
                return is_array($r) && (($r['unit'] ?? '') === 'days' || ($r['duration_unit'] ?? '') === 'days');
            }));

            $exactDayHours = false;
            foreach ($dayRates as $r) {
                if (floatval($r['duration_hours'] ?? 0) === floatval($hours)) {
                    $exactDayHours = true;
                    break;
                }
            }

            $priceMatchedDay = false;
            if ($extPrice > 0) {
                foreach ($dayRates as $r) {
                    if (abs(floatval($r['price'] ?? 0) - $extPrice) < 0.01) {
                        $priceMatchedDay = true;
                        break;
                    }
                }
            }

            $dividesDayPackage = false;
            foreach ($dayRates as $r) {
                $rh = floatval($r['duration_hours'] ?? 0);
                if ($rh > 0 && fmod($hours, $rh) == 0) {
                    $dividesDayPackage = true;
                    break;
                }
            }

            if ($exactDayHours || $priceMatchedDay || $dividesDayPackage) {
                return $days . ' Day' . ($days != 1 ? 's' : '');
            }
        }

        return $resolved['text'];
    }

    function formatBaseDurationDisplay(array $payment): string
    {
        return resolveReportDurationLabel($payment, getReportBookingDurationHours($payment))['text'];
    }

    function buildCustomerDetailsDurationDisplays(array $payment): array
    {
        $promoStr = $payment['promo'] ?? '';
        $isPromo = !empty($promoStr) && !in_array(strtolower(trim($promoStr)), ['', 'none', 'regular', 'select bundle', 'select promo']);

        if ($isPromo && intval($payment['duration'] ?? 0) == 0) {
            $promoMeta = parsePromoSelection($promoStr);
            $promoHours = $promoMeta['hours'] ?? 0;
            if ($promoHours > 0) {
                $durationDisplay = $promoHours . ' Hrs (Promo)';
            } elseif (!empty($payment['hours'])) {
                $durationDisplay = ucwords($payment['hours']) . ' (Promo)';
            } else {
                $durationDisplay = '—';
            }

            return [
                'duration' => $durationDisplay,
                'extension_duration' => '—',
            ];
        }

        $extHours = intval($payment['extend_hours'] ?? 0);
        $extMinutes = intval($payment['extend_minutes'] ?? 0);
        $extPrice = floatval($payment['extend_price'] ?? 0);
        $hasExtend = $extHours > 0 || $extMinutes > 0;

        if (!$hasExtend) {
            return [
                'duration' => formatBaseDurationDisplay($payment),
                'extension_duration' => '—',
            ];
        }

        $stayPayment = $payment;
        if (empty(trim((string) ($stayPayment['checked_out_at'] ?? ''))) && !empty(trim((string) ($stayPayment['check_out'] ?? '')))) {
            $stayPayment['checked_out_at'] = $stayPayment['check_out'];
        }

        $dateStay = computeReportStayHoursFromDates($stayPayment);
        if ($dateStay !== null) {
            $wholeHours = $dateStay['wholeHours'];
            $remainingMinutes = $dateStay['remainingMinutes'];
        } else {
            $baseHours = getReportBookingDurationHours($payment);
            $wholeHours = (int) ($baseHours + $extHours);
            $remainingMinutes = $extMinutes;
        }

        $durationDisplay = formatReportStayDurationDisplay(
            $payment,
            $wholeHours,
            $remainingMinutes,
            $extHours,
            $extMinutes
        );

        $extLabel = formatReportExtendDurationLabel($payment, $extHours, $extMinutes);
        if ($extLabel !== '') {
            $extensionDisplay = $extPrice > 0.005
                ? $extLabel . ' = ' . number_format($extPrice, 0)
                : $extLabel;
        } else {
            $extensionDisplay = '—';
        }

        return [
            'duration' => $durationDisplay,
            'extension_duration' => $extensionDisplay,
        ];
    }

    function buildDurationDisplays(array $payment, int $paymentTimestampIndex = 0, string $paymentRaw = ''): array
    {
        $extHours = intval($payment['extend_hours'] ?? 0);
        $extMinutes = intval($payment['extend_minutes'] ?? 0);
        $extPrice = floatval($payment['extend_price'] ?? 0);

        $hasExtensionData = ($extHours > 0 || $extMinutes > 0 || $extPrice > 0.005);

        // First payment = room rate only, unless book + extend were paid together (single timestamp).
        $bundledExtension = $paymentTimestampIndex === 0 && isBundledExtensionExportPayment($payment);

        if ($paymentTimestampIndex === 0 && !$bundledExtension && !$hasExtensionData) {
            return [
                'duration' => formatBaseDurationDisplay($payment),
                'extension_duration' => '—',
                'extend_date' => '—',
            ];
        }

        if ($extHours > 0 || $extMinutes > 0) {
            $dateStay = computeReportStayHoursFromDates($payment);
            if ($dateStay !== null) {
                $wholeHours = $dateStay['wholeHours'];
                $remainingMinutes = $dateStay['remainingMinutes'];
            } else {
                $baseHours = getReportBookingDurationHours($payment);
                $wholeHours = (int) ($baseHours + $extHours);
                $remainingMinutes = $extMinutes;
            }

            $durationDisplay = formatReportStayDurationDisplay(
                $payment,
                $wholeHours,
                $remainingMinutes,
                $extHours,
                $extMinutes
            );

            $extLabel = formatReportExtendDurationLabel($payment, $extHours, $extMinutes);
            if ($extLabel !== '') {
                $extDurationDisplay = $extLabel . ' = ' . number_format($extPrice, 0);
            } else {
                $extDurationDisplay = $extPrice > 0.005 ? number_format($extPrice, 0) : '—';
            }
        } else {
            $durationDisplay = formatBaseDurationDisplay($payment);
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
        if ($paymentTotal <= 0 || $baseAmount < 0 || $unitPrice <= 0) {
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

    function formatAdditionalSlippersDisplay(int $count, int $unitPrice = 100): string
    {
        if ($count <= 0) {
            return '';
        }

        return $count . ' Slippers = P' . number_format($count * $unitPrice, 0);
    }

    /** Convert stored additional food/items (readable text or JSON) to report display text. */
    function formatAdditionalChargesForDisplay(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $lines = [];
                foreach ($decoded as $it) {
                    $name = trim((string) ($it['selectedItem'] ?? $it['name'] ?? ''));
                    if ($name === '' || $name === 'Select Food' || $name === 'Select Item') {
                        continue;
                    }
                    $qty = intval($it['quantity'] ?? 1);
                    $price = floatval($it['price'] ?? 0);
                    $lines[] = "{$qty} {$name} = ₱" . number_format($price, 2);
                }
                return implode("\n", $lines);
            }
        }
        return $raw;
    }

    function appendAdditionalMissingItemsPart(string $display, string $part): string
    {
        $part = trim($part);
        if ($part === '') {
            return $display;
        }

        if ($display === '—' || $display === '') {
            return $part;
        }

        if (strpos($display, $part) !== false) {
            return $display;
        }

        return $display . ', ' . $part;
    }

    function mergeGroupedAdditionalFees(array $existing, array $incoming): array
    {
        $rawGuestSum = intval($existing['_guest_count'] ?? 0) + intval($incoming['_guest_count'] ?? 0);
        $rawPetSum = intval($existing['_pet_count'] ?? 0) + intval($incoming['_pet_count'] ?? 0);
        $guestCap = max(intval($existing['_guest_cap'] ?? 0), intval($incoming['_guest_cap'] ?? 0));
        $petCap = max(intval($existing['_pet_cap'] ?? 0), intval($incoming['_pet_cap'] ?? 0));
        $guestCount = ($guestCap > 0) ? min($rawGuestSum, $guestCap) : $rawGuestSum;
        $petCount = ($petCap > 0) ? min($rawPetSum, $petCap) : $rawPetSum;

        $existing['_guest_count'] = $guestCount;
        $existing['_pet_count'] = $petCount;
        $existing['_guest_cap'] = $guestCap;
        $existing['_pet_cap'] = $petCap;
        $existing['additional_guest'] = formatGuestPetCountDisplay($guestCount, 300);
        $existing['additional_pet'] = formatGuestPetCountDisplay($petCount, 500);

        $hasNewFeeContent = false;
        foreach (['additional_items', 'additional_foods', 'additional_missing_items', 'additional_penalty'] as $field) {
            $prev = trim((string) ($existing[$field] ?? '—'));
            $next = trim((string) ($incoming[$field] ?? '—'));
            if ($prev === '—' || $prev === '') {
                if ($next !== '—' && $next !== '') {
                    $existing[$field] = $next;
                    $hasNewFeeContent = true;
                }
            } elseif ($next !== '—' && $next !== '') {
                if (strpos($prev, $next) === false) {
                    $existing[$field] = $prev . ' | ' . $next;
                    $hasNewFeeContent = true;
                }
            }
        }

        $incomingGuestPetTotal = (intval($incoming['_guest_count'] ?? 0) * 300) + (intval($incoming['_pet_count'] ?? 0) * 500);
        if ($hasNewFeeContent || $incomingGuestPetTotal > 0) {
            $existing['additional_total_fees'] = floatval($existing['additional_total_fees'] ?? 0)
                + floatval($incoming['additional_total_fees'] ?? 0);
        }
        // Keep fee total aligned if merged guest/pet counts were capped
        $existing['additional_total_fees'] = floatval($existing['additional_total_fees'] ?? 0)
            - (($rawGuestSum - $guestCount) * 300)
            - (($rawPetSum - $petCount) * 500);

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

        if ($paymentAmount > 0) {
            if ($guestBase > 0) {
                $guestCount = inferChargeCountFromPayment($paymentAmount, $guestBase, 300);
                $petBase = $guestBase + ($guestCount * 300);
                $petCount = inferChargeCountFromPayment($paymentAmount, $petBase, 500);
            } else {
                // Pure guest/pet payment (no room/extend base) — e.g. ₱600 = 2 guests.
                // Do NOT treat food-only amounts (e.g. ₱315) as guests.
                $guestCount = inferChargeCountFromPayment($paymentAmount, 0.0, 300);
                $petBase = $guestCount * 300;
                $petCount = inferChargeCountFromPayment($paymentAmount, $petBase, 500);
            }
        }

        // Fallback for single-payment bookings only. Never use additional_*_date entry
        // counts as guest/pet quantity — those timestamps are one-per-save, not one-per-guest,
        // and on multi-payment same-day rows they double-count guests already inferred
        // from the room-rate payment (e.g. 2 guests + food payment → false "3").
        if ($guestCount === 0 && $cumulativeGuest > 0 && $nPaymentTimestamps <= 1) {
            if (shouldIncludeAdditionalOnPaymentRow(
                $payment['additional_guest_date'] ?? null,
                true,
                $paymentDateRaw,
                $paymentTimestampIndex,
                $startDate,
                $endDate,
                $nPaymentTimestamps
            )) {
                $guestCount = $cumulativeGuest;
            }
        }

        if ($petCount === 0 && $cumulativePet > 0 && $nPaymentTimestamps <= 1) {
            if (shouldIncludeAdditionalOnPaymentRow(
                $payment['additional_pet_date'] ?? null,
                true,
                $paymentDateRaw,
                $paymentTimestampIndex,
                $startDate,
                $endDate,
                $nPaymentTimestamps
            )) {
                $petCount = $cumulativePet;
            }
        }

        if ($cumulativeGuest > 0) {
            $guestCount = min($guestCount, $cumulativeGuest);
        }
        if ($cumulativePet > 0) {
            $petCount = min($petCount, $cumulativePet);
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
        string $endDate,
        int $nPaymentTimestamps = 1
    ): bool {
        if (!$hasData) {
            return false;
        }

        if (hasValidAdditionalDate($dateStr) && !isAdditionalDateInReportRange($dateStr, $startDate, $endDate)) {
            return false;
        }

        if (isAdditionalPaidOnPaymentDate($dateStr, $paymentDateRaw)) {
            if ($nPaymentTimestamps > 1) {
                return $paymentTimestampIndex === ($nPaymentTimestamps - 1);
            }
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
            $endDate,
            $nPaymentTimestamps
        );
        $includeItemsFees = shouldIncludeAdditionalOnPaymentRow(
            $payment['additional_items_date'] ?? null,
            $hasItems,
            $paymentDateRaw,
            $paymentTimestampIndex,
            $startDate,
            $endDate,
            $nPaymentTimestamps
        );
        $includeCheckoutFees = shouldIncludeAdditionalOnPaymentRow(
            $payment['additional_fees_paid_date'] ?? null,
            $hasCheckoutFees || !empty($payment['additional_fees_paid_date']),
            $paymentDateRaw,
            $paymentTimestampIndex,
            $startDate,
            $endDate,
            $nPaymentTimestamps
        );

        $additionalItemsRaw = $includeItemsFees
            ? formatAdditionalChargesForDisplay($payment['additional_items'] ?? '')
            : '';
        $additionalFoodRaw = $includeFoodFees
            ? formatAdditionalChargesForDisplay($payment['additional_food'] ?? '')
            : '';

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
        $missingItemsDisplay = buildMissingItemsReportDisplay($payment, $includeCheckoutFees);

        $slipperCount = intval($payment['additional_slippers'] ?? 0);
        $slipperStatus = strtolower(trim((string) ($payment['slipper_status'] ?? '')));
        // Held new_deposit is excluded from payment-row revenue; forfeited amount appears in missing_items_fees at checkout.

        $totalAdditionalFees = $missingItemsFees + $additionalItemsTotal + $additionalFoodTotal + $additionalGuestPrice + $additionalPetPrice + $penaltyAmount;

        $guestCap = intval($payment['additional_guest'] ?? 0) + intval($payment['extend_additional_guest'] ?? 0);
        $petCap = intval($payment['additional_pet'] ?? 0);

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
            '_guest_cap' => $guestCap,
            '_pet_cap' => $petCap,
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

    /**
     * Consolidate all guest names (primary, second, additional) with newline separator.
     * Used for detailed booking reports to display all names in a single cell.
     */
    function consolidateGuestNames(array $payment): string
    {
        $allGuestNames = [];
        
        // Add primary guest name
        if (!empty($payment['guest_name']) && trim($payment['guest_name']) !== '') {
            $allGuestNames[] = trim($payment['guest_name']);
        }
        
        // Add second guest names (pipe-separated)
        $secondGuestNameRaw = trim((string) ($payment['second_guest_name'] ?? ''));
        if ($secondGuestNameRaw !== '') {
            $secondGuestNames = array_filter(array_map('trim', explode('|', $secondGuestNameRaw)));
            foreach ($secondGuestNames as $name) {
                if ($name !== '') {
                    $allGuestNames[] = $name;
                }
            }
        }
        
        // Add additional guest names (pipe-separated)
        $additionalGuestNamesRaw = trim((string) ($payment['additional_guest_names'] ?? ''));
        if ($additionalGuestNamesRaw !== '') {
            $additionalGuestNames = array_filter(array_map('trim', explode('|', $additionalGuestNamesRaw)));
            foreach ($additionalGuestNames as $name) {
                if ($name !== '') {
                    $allGuestNames[] = $name;
                }
            }
        }
        
        // Return consolidated names with newline separator, or '—' if empty
        return !empty($allGuestNames) ? implode("\n", $allGuestNames) : '—';
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

        // Consolidate all guest names (primary, second, additional) with <br> separator
        $consolidatedGuestName = consolidateGuestNames($payment);

        return [
            'modified' => $modifiedIndicator,
            'original_room' => $transferRoomFrom !== '' ? $transferRoomFrom : '—',
            'sales_channel' => $salesChannel !== '' ? $salesChannel : '—',
            'current_room' => $currentRoom !== '' ? $currentRoom : '—',
            'booking_type' => $payment['booking_type'] ?: '—',
            'room_type' => $payment['room_type'] ?? '',
            'guest_type' => $payment['guest_type'] ?: '—',
            'guest_name' => $consolidatedGuestName,
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