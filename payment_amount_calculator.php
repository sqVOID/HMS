<?php
/**
 * Payment Amount Calculator
 * 
 * This helper calculates payment amounts based on payment dates and booking structure
 */

require_once 'payment_history_helpers.php';

/**
 * Calculate the total payment amount that falls within the specified date range.
 * This function handles both payment_date_time timestamps and downpayment_date for reservations.
 * It matches the logic used in export_payment_type_report.php to ensure consistency.
 *
 * @param array $report The booking/report data
 * @param string $filterStart Start date (Y-m-d format)
 * @param string $filterEnd End date (Y-m-d format)
 * @return float Total payment amount within the date range
 */
function calculatePaymentAmountInDateRange(array $report, string $filterStart, string $filterEnd): float
{
    $totalInRange = 0.0;

    // Include BOTH downpayment_date and payment_date_time so reservation check-ins
    // and add-on payments are not dropped when a downpayment timestamp exists.
    $timestampRows = [];
    $seenTs = [];
    $pushUniqueTs = static function (string $rawTs) use (&$timestampRows, &$seenTs): void {
        $rawTs = trim($rawTs);
        if ($rawTs === '') {
            return;
        }
        try {
            $dt = new DateTime($rawTs);
            $key = $dt->format('Y-m-d H:i:s');
            if (isset($seenTs[$key])) {
                return;
            }
            $seenTs[$key] = true;
            $timestampRows[] = $dt;
        } catch (Exception $e) {
            // Skip invalid timestamp
        }
    };

    $downpaymentDate = $report['downpayment_date'] ?? null;
    // Prefer payment_date_time segments — they stay 1:1 with payment_amount_*_history.
    // When reservations also have downpayment_date, prepending it duplicated the first
    // installment and broke history index alignment.
    if (!empty($report['payment_date_time'])) {
        foreach (explode('|', (string) $report['payment_date_time']) as $ts) {
            $pushUniqueTs($ts);
        }
    }
    if (empty($timestampRows) && !empty($downpaymentDate)) {
        $pushUniqueTs($downpaymentDate);
    }

    usort($timestampRows, static function ($a, $b) {
        return $a <=> $b;
    });

    if (empty($timestampRows)) {
        return 0.0;
    }

    $nTimestamps = count($timestampRows);

    // Helper function to parse payment amounts from status strings (same as export_payment_type_report.php)
    $parsePaymentAmountsAll = function ($paymentString) {
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
    };

    // Helper function to allocate amounts (same as export_payment_type_report.php)
    $allocateAmountsToPaymentTimestamps = function ($timestamps, $methodAmounts, $baseAmount) {
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
            // Single reporting bucket: trust canonical totals (deposit/downpayment rollup) when
            // the status string only captured the latest incremental amount (common after add-ons).
            if ($n === 1) {
                if ($base > $total + 0.01) {
                    return [$base];
                }
                return [$total];
            }
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
    };

    // Process each payment method (Cash, G-Cash, Maya, Instapay, Online Banking, Airbnb)
    $paymentMethods = [
        'cash' => [
            'deposit' => floatval($report['deposit_cash'] ?? 0),
            'downpayment' => floatval($report['downpayment_cash'] ?? 0),
            'history' => $report['payment_amount_cash_history'] ?? null,
            'status' => $report['payment_status_cash'] ?? null
        ],
        'gcash' => [
            'deposit' => floatval($report['deposit_g_cash'] ?? 0),
            'downpayment' => floatval($report['downpayment_gcash'] ?? 0),
            'history' => $report['payment_amount_g_cash_history'] ?? null,
            'status' => $report['payment_status_g_cash'] ?? null
        ],
        'maya' => [
            'deposit' => floatval($report['deposit_maya'] ?? 0),
            'downpayment' => floatval($report['downpayment_maya'] ?? 0),
            'history' => $report['payment_amount_maya_history'] ?? null,
            'status' => $report['payment_status_maya'] ?? null
        ],
        'instapay' => [
            'deposit' => floatval($report['deposit_instapay'] ?? 0),
            'downpayment' => floatval($report['downpayment_instapay'] ?? 0),
            'history' => $report['payment_amount_instapay_history'] ?? null,
            'status' => $report['payment_status_instapay'] ?? null
        ],
        'online_banking' => [
            'deposit' => floatval($report['deposit_online_banking'] ?? 0),
            'downpayment' => floatval($report['downpayment_online_banking'] ?? 0),
            'history' => $report['payment_amount_online_banking_history'] ?? null,
            'status' => $report['payment_status_online_banking'] ?? null
        ],
        'airbnb' => [
            'deposit' => floatval($report['deposit_airbnb'] ?? 0),
            'downpayment' => floatval($report['downpayment_airbnb'] ?? 0),
            'history' => $report['payment_amount_airbnb_history'] ?? null,
            'status' => $report['payment_status_airbnb'] ?? null
        ]
    ];

    $allocatedPerMethod = [];

    // Determine if downpayment_date is OUTSIDE the filter range.
    // When it is, the downpayment belongs to a different day and must NOT be merged
    // into the deposit total for this date range (otherwise a 05/14 downpayment of
    // ₱600 would be lumped with the 05/15 checkout deposit of ₱1,699 → ₱2,299).
    $downpaymentDateInRange = false;
    if (!empty($downpaymentDate)) {
        try {
            $dpDateStr = (new DateTime($downpaymentDate))->format('Y-m-d');
            $downpaymentDateInRange = ($dpDateStr >= $filterStart && $dpDateStr <= $filterEnd);
        } catch (Exception $e) {
            // Invalid date — treat as out-of-range (safe default).
        }
    }

    foreach ($paymentMethods as $methodKey => $methodData) {
        // For checked-out bookings, payment_status_* contains the total payment.
        // For reservations/check-ins, BOTH deposit AND downpayment may contain separate
        // payments made at different times (e.g. downpayment=960 at reservation time,
        // deposit=150 as an additional payment later). Sum them so both are counted.
        // Use max() only when both are non-zero but identical (legacy double-store),
        // otherwise accumulate: deposit + downpayment.
        $dep = floatval($methodData['deposit']);
        $down = floatval($methodData['downpayment']);

        // KEY FIX: When downpayment_date is outside the requested filter range the
        // downpayment was collected on a different day.  Including it here would inflate
        // the per-timestamp allocation (e.g. ₱600 05/14 + ₱1,699 05/15 = ₱2,299 all
        // assigned to the single 05/15 checkout timestamp).  Exclude it so only the
        // deposit (checkout payment) contributes to this date-range total.
        if ($down > 0 && !$downpaymentDateInRange) {
            $down = 0.0;
        }

        if ($dep > 0 && $down > 0 && abs($dep - $down) < 0.01) {
            // Same value stored in both fields — keep just one to avoid double-count.
            $totalMethod = $dep;
        } elseif (
            $dep > 0 && $down > 0
            && $dep >= $down - 0.01
            && strcasecmp(trim((string) ($report['booking_type'] ?? '')), 'Reservation') === 0
        ) {
            // Reservation: deposit_cash stores the cumulative collected total
            // (downpayment + room payment already rolled up). Adding downpayment
            // on top would double-count the reservation fee. Use dep (the larger, full value).
            // This applies to plain reservations AND reservation+extension alike.
            $totalMethod = $dep;
        } else {
            // All other cases: sum deposit + downpayment as genuinely separate payments.
            $totalMethod = $dep + $down;
        }

        // If no deposit/downpayment, check payment_status for checked-out bookings
        if ($totalMethod <= 0 && !empty($methodData['status'])) {
            $statusAmounts = $parsePaymentAmountsAll($methodData['status']);
            if (!empty($statusAmounts)) {
                $totalMethod = array_sum($statusAmounts);
            }
        }

        if ($totalMethod <= 0) {
            continue;
        }

        $historyArr = !empty($methodData['history'])
            ? explode('|', (string) $methodData['history'])
            : null;

        $amountsByTimestamp = [];

        // Use the EXACT same logic as export_payment_type_report.php
        if (is_array($historyArr) && count($historyArr) === $nTimestamps) {
            $amountsByTimestamp = array_map(fn($v) => floatval($v), $historyArr);
            // Self-heal: handle when history sum exceeds the canonical total.
            // This happens when an intermediate segment stored a cumulative value
            // (e.g. downpayment=500 then deposit=960 cumulative then extension=200
            // → history [500, 960, 200] sums to 1660 but totalMethod=1160).
            // Strategy: if sum > totalMethod, scale all entries proportionally so
            // they still preserve relative weights but add up to totalMethod.
            $sumAll = array_sum($amountsByTimestamp);
            if ($nTimestamps >= 2 && $sumAll > $totalMethod + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $lastVal = $amountsByTimestamp[$lastIdx];
                // Case 1: last segment is itself the cumulative total (legacy pattern).
                if ($lastVal >= $totalMethod - 0.02) {
                    $sumPrior = array_sum(array_slice($amountsByTimestamp, 0, $lastIdx));
                    $amountsByTimestamp[$lastIdx] = max(0, $totalMethod - $sumPrior);
                } else {
                    // Case 2: an earlier segment is cumulative (e.g. history=[500,960,200]
                    // where 960 already included the 500). Scale all proportionally.
                    $scale = $totalMethod / $sumAll;
                    $amountsByTimestamp = array_map(fn($v) => round($v * $scale, 2), $amountsByTimestamp);
                    // Fix rounding drift on the last entry.
                    $roundingDiff = $totalMethod - array_sum($amountsByTimestamp);
                    if (abs($roundingDiff) > 0) {
                        $amountsByTimestamp[$lastIdx] = round($amountsByTimestamp[$lastIdx] + $roundingDiff, 2);
                    }
                }
            }
        } elseif (is_array($historyArr) && $nTimestamps === count($historyArr) + 1) {
            $sumPrior = 0.0;
            foreach ($historyArr as $seg) {
                $sumPrior += floatval($seg);
            }
            $lastAmount = max(0, $totalMethod - $sumPrior);
            $amountsByTimestamp = array_merge(
                array_map(fn($v) => floatval($v), $historyArr),
                [$lastAmount]
            );
        } elseif (is_array($historyArr) && count($historyArr) === 1 && $nTimestamps === 2) {
            $lastAmount = floatval($historyArr[0] ?? 0);
            $firstAmount = max(0, $totalMethod - $lastAmount);
            $amountsByTimestamp = [$firstAmount, $lastAmount];
        } else {
            // Fallback heuristic: parse payment_status_* to extract individual payment amounts
            $methodAmounts = $parsePaymentAmountsAll($methodData['status']);

            // Special case: if we have 2 timestamps and 1 amount in payment_status,
            // that amount is likely the LAST payment (extension), not the first
            if ($nTimestamps === 2 && count($methodAmounts) === 1) {
                $lastAmount = floatval($methodAmounts[0]);
                // If the single amount is less than total, it's the extension payment
                if ($lastAmount > 0 && $totalMethod > $lastAmount + 0.01) {
                    $amountsByTimestamp = [$totalMethod - $lastAmount, $lastAmount];
                } else {
                    // Otherwise use the allocation function
                    $amountsByTimestamp = $allocateAmountsToPaymentTimestamps($timestampRows, $methodAmounts, $totalMethod);
                }
            } else {
                $amountsByTimestamp = $allocateAmountsToPaymentTimestamps($timestampRows, $methodAmounts, $totalMethod);
            }
        }

        $allocatedPerMethod[$methodKey] = $amountsByTimestamp;
    }

    // Reservation check-in: first installment on downpayment_date may bundle room + reservation fee
    // (e.g. 1990) while downpayment_* columns still show the fee (500). export_detailed_booking_report.php
    // subtracts downpayment_amount from that day's payment total; mirror that here so Daily Sales matches.
    // With extensions, room_price often includes extend charges so (gross - fee) no longer equals room_price;
    // use min(room_price, room_price - extension_total) as the smallest plausible "base" for the guard.
    if (
        !empty($downpaymentDate)
        && strcasecmp(trim((string) ($report['booking_type'] ?? '')), 'Reservation') === 0
        && $allocatedPerMethod !== []
    ) {
        $fee = floatval($report['downpayment_amount'] ?? 0);
        if ($fee <= 0.01) {
            $fee = floatval($report['total_amount_reservation'] ?? 0);
        }
        $roomHint = floatval($report['room_price'] ?? 0);
        $extCharges = getTotalExtensionChargesForBooking($report);
        $roomCandidates = [];
        if ($roomHint > 0.01) {
            $roomCandidates[] = $roomHint;
        }
        // Only treat (room_price - extension) as a hint when room_price likely already includes
        // extension totals (ratio stays high). If room_price is base-only, (room - ext) is junk.
        if ($extCharges > 0.01 && $roomHint > $extCharges + 0.01) {
            $roomCandidates[] = max(0, $roomHint - $extCharges);
        }
        $roomCandidates = array_values(array_unique(array_filter(
            $roomCandidates,
            static fn($v) => $v > 0.01
        )));
        $minPlausibleBase = $roomCandidates !== [] ? min($roomCandidates) : $roomHint;

        try {
            $dpDay = (new DateTime($downpaymentDate))->format('Y-m-d');
        } catch (Exception $e) {
            $dpDay = '';
        }

        if ($fee > 0.01 && $dpDay !== '') {
            foreach ($timestampRows as $histIdx => $dt) {
                if ($dt->format('Y-m-d') !== $dpDay) {
                    continue;
                }
                $totalAtIdx = 0.0;
                foreach ($allocatedPerMethod as $slice) {
                    $totalAtIdx += floatval($slice[$histIdx] ?? 0);
                }
                if ($totalAtIdx <= $fee + 0.01) {
                    continue;
                }

                $tol = $minPlausibleBase > 0.01 ? max(1.0, $minPlausibleBase * 0.06) : 1.0;
                $bundleLikely = $minPlausibleBase > 0.01
                    && ($totalAtIdx > $fee + $minPlausibleBase - $tol);

                if (!$bundleLikely) {
                    if ($minPlausibleBase > 0.01) {
                        // Legacy: single-method cash match when room_price alone explains the slice
                        $cashOnly = floatval(($allocatedPerMethod['cash'] ?? [])[$histIdx] ?? 0);
                        if ($cashOnly > $fee + 0.01 && abs(($cashOnly - $fee) - $roomHint) <= max(1.0, $roomHint * 0.06)) {
                            $allocatedPerMethod['cash'][$histIdx] = max(0, $cashOnly - $fee);
                        }
                    }
                    continue;
                }

                $cashVal = floatval(($allocatedPerMethod['cash'] ?? [])[$histIdx] ?? 0);
                $otherAtIdx = $totalAtIdx - $cashVal;
                $dpCash = floatval($report['downpayment_cash'] ?? 0);

                if ($otherAtIdx < 0.01 && $dpCash >= $fee - 0.02 && $cashVal > $fee + 0.01) {
                    $allocatedPerMethod['cash'][$histIdx] = max(0, $cashVal - $fee);
                } else {
                    $newTotal = max(0, $totalAtIdx - $fee);
                    $den = $totalAtIdx > 0 ? $totalAtIdx : 1.0;
                    $factor = $newTotal / $den;
                    foreach (array_keys($allocatedPerMethod) as $mk) {
                        if (!isset($allocatedPerMethod[$mk][$histIdx])) {
                            continue;
                        }
                        $allocatedPerMethod[$mk][$histIdx] = max(
                            0,
                            floatval($allocatedPerMethod[$mk][$histIdx]) * $factor
                        );
                    }
                }
            }
        }
    }

    foreach ($allocatedPerMethod as $methodKey => $amountsByTimestamp) {
        // Sum amounts that fall within the date range
        foreach ($timestampRows as $idx => $dt) {
            $paymentDate = $dt->format('Y-m-d');
            if ($paymentDate >= $filterStart && $paymentDate <= $filterEnd) {
                $amt = $amountsByTimestamp[$idx] ?? 0;
                $totalInRange += $amt;
            }
        }
    }

    return $totalInRange;
}

/**
 * Parse currency/amount values from strings like "Cash (₱2,990.00)".
 *
 * @param mixed $value
 * @return float
 */
function parseCalculatorAmountValue($value)
{
    if (is_numeric($value)) {
        return floatval($value);
    }
    if (!is_string($value)) {
        return 0.0;
    }
    if (preg_match('/(?:₱|P)?\s*([0-9][0-9,]*(?:\.[0-9]+)?)/u', $value, $m)) {
        return floatval(str_replace(',', '', $m[1]));
    }
    return 0.0;
}

/**
 * Canonical extension tariff stored on the booking.
 *
 * Newer rows use extend_price as the final extension total.
 * Some legacy rows may only have regular/bundle splits populated,
 * so we fallback to the split sum when extend_price is missing/zero.
 * 
 * CRITICAL FIX: Account for withdrawn extensions - when extension_withdraw=1,
 * the extend_price is 0 but extend_regular_rate/extend_bundle_rate still contain
 * the original values. We must return 0 in this case to avoid inflating the cap.
 */
function getTotalExtensionChargesForBooking(array $booking_data): float
{
    // CRITICAL FIX: Check if extension has been withdrawn
    $extensionWithdrawn = intval($booking_data['extension_withdraw'] ?? 0);

    // If extension was withdrawn, return 0 (no extension charges apply)
    if ($extensionWithdrawn === 1) {
        return 0.0;
    }

    $extendPrice = floatval($booking_data['extend_price'] ?? 0);
    $regular = floatval($booking_data['extend_regular_rate'] ?? 0);
    $bundle = floatval($booking_data['extend_bundle_rate'] ?? 0);
    $splitTotal = $regular + $bundle;

    // Use extend_price if available, otherwise use the split total
    if ($extendPrice > 0) {
        // Use the stored extension total to avoid double-counting split fields.
        return round(max(0, max($extendPrice, $splitTotal)), 2);
    }

    return round(max(0, $splitTotal), 2);
}

/**
 * Get net paid amount from stored payment columns.
 *
 * @param array $booking_data
 * @return float
 */
function getNetPaidAmountForExport($booking_data)
{
    $payCash = parseCalculatorAmountValue($booking_data['payment_status_cash'] ?? 0);
    $payGcash = parseCalculatorAmountValue($booking_data['payment_status_g_cash'] ?? 0);
    $payMaya = parseCalculatorAmountValue($booking_data['payment_status_maya'] ?? 0);
    $checkoutPaid = $payCash + $payGcash + $payMaya;

    $changeAmount = floatval($booking_data['change_amount'] ?? 0);
    $transferRefund = floatval($booking_data['transfer_refund_amount'] ?? 0);

    $isReservation = strcasecmp(trim((string) ($booking_data['booking_type'] ?? '')), 'Reservation') === 0;

    $depCash = floatval($booking_data['deposit_cash'] ?? 0);
    $dpCash = floatval($booking_data['downpayment_cash'] ?? 0);
    $totalCash = ($isReservation && $depCash >= $dpCash - 0.01) ? $depCash : ($depCash + $dpCash);

    $depGcash = floatval($booking_data['deposit_g_cash'] ?? 0);
    $dpGcash = floatval($booking_data['downpayment_gcash'] ?? 0);
    $totalGcash = ($isReservation && $depGcash >= $dpGcash - 0.01) ? $depGcash : ($depGcash + $dpGcash);

    $depMaya = floatval($booking_data['deposit_maya'] ?? 0);
    $dpMaya = floatval($booking_data['downpayment_maya'] ?? 0);
    $totalMaya = ($isReservation && $depMaya >= $dpMaya - 0.01) ? $depMaya : ($depMaya + $dpMaya);

    // Include other methods that are primarily used in downpayments currently
    $totalInstapay = floatval($booking_data['downpayment_instapay'] ?? 0);
    $totalOnlineBanking = floatval($booking_data['downpayment_online_banking'] ?? 0);
    $totalAirbnb = floatval($booking_data['downpayment_airbnb'] ?? 0);

    $depositPaid = $totalCash + $totalGcash + $totalMaya + $totalInstapay + $totalOnlineBanking + $totalAirbnb;

    // If payment_status_* exists, it may represent the latest incremental payment
    // (e.g. extension payment), so add it on top of deposits.
    if ($checkoutPaid > 0) {
        $checkoutNet = max(0, $checkoutPaid - max(0, $changeAmount) - max(0, $transferRefund));

        if ($depositPaid > 0) {
            // Heuristic anti-double-count:
            // cap = room base + known extension charges.
            $extensionCharges = getTotalExtensionChargesForBooking($booking_data);
            $cap = floatval($booking_data['room_price'] ?? 0);
            if ($extensionCharges > 0) {
                $cap += $extensionCharges;
            }

            $sum = $depositPaid + $checkoutNet;
            if ($cap > 0) {
                $normalizedDeposit = min(max(0, $depositPaid), $cap);
                $normalizedCheckout = min(max(0, $checkoutNet), $cap);

                // Mixed-payment flow can store cumulative checkout payment strings
                // (e.g. payment_status_cash = "Cash (₱1760.00)") while deposit_*
                // still contains the earlier edit-modal payment (e.g. 800).
                // In that case, using deposit+checkout overcounts; prefer checkout total.
                if ($sum > $cap + 0.05) {
                    return max($normalizedDeposit, $normalizedCheckout);
                }

                // Normal incremental case: deposit + checkout should be within cap.
                return max(0, $sum);
            }

            // No reliable cap available; keep the larger safe value.
            return max(0, max($depositPaid, $checkoutNet));
        }

        return max(0, $checkoutNet);
    }

    // Fallback: no payment_status_* recorded, use deposits as the paid totals.
    return max(0, $depositPaid - max(0, $changeAmount) - max(0, $transferRefund));
}

/**
 * Total collected for a Reservation booking: downpayment/reservation fee + checkout net,
 * capped by room_price / total_amount when present (avoids double-count if values were merged).
 *
 * @param array $booking_data
 * @return float
 */
function getReservationFullCollectedAmount(array $booking_data): float
{
    if (strcasecmp(trim((string) ($booking_data['booking_type'] ?? '')), 'Reservation') !== 0) {
        return 0.0;
    }

    // For reservation exports, the most reliable way to include multiple incremental
    // payments (initial + extension) is to use the same "net paid" logic as other
    // booking types (deposit_* + downpayment_* plus payment_status_* when present),
    // then cap by room base + extension charges.
    $netAll = getNetPaidAmountForExport($booking_data);
    if ($netAll <= 0) {
        return 0.0;
    }

    $extensionCharges = getTotalExtensionChargesForBooking($booking_data);
    $cap = floatval($booking_data['room_price'] ?? 0);
    if ($extensionCharges > 0) {
        $cap += $extensionCharges;
    }
    if ($cap <= 0) {
        $cap = floatval($booking_data['total_amount'] ?? 0);
    }

    return $cap > 0 ? min($cap, $netAll) : $netAll;
}

/**
 * Full booking cash collected for exports (reservation fee + balance; walk-in uses net paid).
 *
 * @param array $booking_data
 * @return float
 */
function getFullBookingCollectedForExport(array $booking_data): float
{
    $res = getReservationFullCollectedAmount($booking_data);
    if ($res > 0) {
        return $res;
    }
    return getNetPaidAmountForExport($booking_data);
}

/**
 * Calculate payment amounts for each payment date based on booking structure
 * 
 * @param array $booking_data Booking data from database
 * @param string $filter_start Start date for filtering (Y-m-d format)
 * @param string $filter_end End date for filtering (Y-m-d format)
 * @return array Array with payment breakdown
 */
function calculatePaymentAmountsByDate($booking_data, $filter_start, $filter_end)
{
    $payment_date_time = $booking_data['payment_date_time'] ?? '';
    $extend_total = getTotalExtensionChargesForBooking($booking_data);
    $room_price = floatval($booking_data['room_price'] ?? 0);
    $stored_total_amount = floatval($booking_data['total_amount'] ?? 0);

    // IMPORTANT:
    // In this codebase, `total_amount` is frequently used as the *remaining balance* after deposits
    // (edit-payment flow stores running totals in deposit/deposit_*), NOT the full booking charge.
    // For payment-history splitting we must instead use the full charge total:
    // prefer room_price + extension charges; fall back to stored totals when larger.
    $full_charge_total = 0.0;
    if ($room_price > 0 || $extend_total > 0) {
        $full_charge_total = max(0, $room_price) + max(0, $extend_total);
    }
    if ($stored_total_amount > 0) {
        $full_charge_total = $full_charge_total > 0 ? max($full_charge_total, $stored_total_amount) : $stored_total_amount;
    }
    $total_amount = max(0, $full_charge_total);

    // Parse payment timestamps
    $payment_timestamps = parsePaymentHistory($payment_date_time);

    if (empty($payment_timestamps)) {
        return [
            'total_amount_in_range' => 0,
            'payment_count_in_range' => 0,
            'has_initial_payment' => false,
            'has_extension_payment' => false,
            'payments_in_range' => []
        ];
    }

    // Calculate base booking amount (without extension)
    $base_booking_amount = $total_amount;
    if ($extend_total > 0) {
        $base_booking_amount = $total_amount - $extend_total;
    }

    // If we can't determine base amount, use room_price or split evenly
    if ($base_booking_amount <= 0) {
        if ($room_price > 0) {
            $base_booking_amount = $room_price;
        } else {
            // Split total amount evenly among payments
            $base_booking_amount = count($payment_timestamps) > 0 ? $total_amount / count($payment_timestamps) : $total_amount;
        }
    }

    $payments_in_range = [];
    $total_amount_in_range = 0;
    $has_initial_payment = false;
    $has_extension_payment = false;

    foreach ($payment_timestamps as $index => $payment_dt) {
        $payment_date = $payment_dt->format('Y-m-d');

        // Determine payment amount based on payment order
        $payment_amount = 0;
        if ($index === 0) {
            // First payment is usually the base booking amount
            $payment_amount = $base_booking_amount;
            $payment_type = 'initial';
        } else {
            // Subsequent payments are usually extensions or additional charges
            if ($extend_total > 0 && $index === 1) {
                // Second payment is likely the extension
                $payment_amount = $extend_total;
                $payment_type = 'extension';
            } else {
                // Additional payments - split remaining amount
                $remaining_payments = count($payment_timestamps) - 1;
                $remaining_amount = $total_amount - $base_booking_amount;
                $payment_amount = $remaining_payments > 0 ? $remaining_amount / $remaining_payments : 0;
                $payment_type = 'additional';
            }
        }

        // Check if payment date is in the selected range
        if ($payment_date >= $filter_start && $payment_date <= $filter_end) {
            $payments_in_range[] = [
                'date' => $payment_date,
                'datetime' => $payment_dt->format('Y-m-d H:i:s'),
                'amount' => $payment_amount,
                'type' => $payment_type,
                'formatted_date' => $payment_dt->format('d/m/Y h:i A')
            ];

            $total_amount_in_range += $payment_amount;

            if ($payment_type === 'initial') {
                $has_initial_payment = true;
            } elseif ($payment_type === 'extension') {
                $has_extension_payment = true;
            }
        }
    }

    return [
        'total_amount_in_range' => $total_amount_in_range,
        'payment_count_in_range' => count($payments_in_range),
        'has_initial_payment' => $has_initial_payment,
        'has_extension_payment' => $has_extension_payment,
        'payments_in_range' => $payments_in_range,
        'base_booking_amount' => $base_booking_amount,
        'extension_amount' => $extend_total
    ];
}

/**
 * Get extension duration display based on payment dates in range
 * 
 * @param array $booking_data Booking data from database
 * @param string $filter_start Start date for filtering (Y-m-d format)
 * @param string $filter_end End date for filtering (Y-m-d format)
 * @return string Extension duration display
 */
function getExtensionDurationForDateRange($booking_data, $filter_start, $filter_end)
{
    $payment_calc = calculatePaymentAmountsByDate($booking_data, $filter_start, $filter_end);

    // Only show extension duration if extension payment is in the date range
    if ($payment_calc['has_extension_payment']) {
        $extend_hours = intval($booking_data['extend_hours'] ?? 0);
        $extend_minutes = intval($booking_data['extend_minutes'] ?? 0);
        $extendMoney = getTotalExtensionChargesForBooking($booking_data);

        if ($extend_hours > 0 && $extend_minutes > 0) {
            return $extend_hours . ':' . str_pad($extend_minutes, 2, '0', STR_PAD_LEFT) . ' Hr = ' . number_format($extendMoney, 0);
        }
        if ($extend_hours > 0) {
            return $extend_hours . ' Hr = ' . number_format($extendMoney, 0);
        }
        if ($extend_minutes > 0) {
            return $extend_minutes . ' Mins = ' . number_format($extendMoney, 0);
        }
        if ($extendMoney > 0) {
            return 'Extension = ' . number_format($extendMoney, 0);
        }
    }

    return '—';
}

/**
 * Get duration display based on payment dates in range
 * Shows base duration only when initial payment is in range, extended duration when extension payment is in range
 * 
 * @param array $booking_data Booking data from database
 * @param string $filter_start Start date for filtering (Y-m-d format)
 * @param string $filter_end End date for filtering (Y-m-d format)
 * @return string Duration display
 */
function getDurationForDateRange($booking_data, $filter_start, $filter_end)
{
    $payment_calc = calculatePaymentAmountsByDate($booking_data, $filter_start, $filter_end);

    $baseDuration = intval($booking_data['duration'] ?? 0);
    $baseUnit = $booking_data['duration_unit'] ?? 'hours';
    $extHours = intval($booking_data['extend_hours'] ?? 0);
    $extMinutes = intval($booking_data['extend_minutes'] ?? 0);

    // Check if promo is selected - extract duration from promo string
    $promoValue = $booking_data['promo'] ?? '';
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

    // If both initial and extension payments are in range, show extended duration
    if ($payment_calc['has_initial_payment'] && $payment_calc['has_extension_payment']) {
        if ($extHours > 0 || $extMinutes > 0) {
            // Total minutes from base (hours only) + extension
            $baseTotalMinutes = $baseDuration * 60;
            $extTotalMinutes = ($extHours * 60) + $extMinutes;
            $grandTotalMinutes = $baseTotalMinutes + $extTotalMinutes;
            $displayHours = intdiv($grandTotalMinutes, 60);
            $displayMinutes = $grandTotalMinutes % 60;
            return $displayHours . ':' . str_pad($displayMinutes, 2, '0', STR_PAD_LEFT) . ' ' . $baseUnit . ' (Extended)';
        }
    }

    // If only initial payment is in range, show base duration only
    if ($payment_calc['has_initial_payment'] && !$payment_calc['has_extension_payment']) {
        return $baseDuration . ' ' . $baseUnit;
    }

    // If only extension payment is in range, show extension duration only
    if (!$payment_calc['has_initial_payment'] && $payment_calc['has_extension_payment']) {
        if ($extHours > 0 || $extMinutes > 0) {
            $extTotalMinutes = ($extHours * 60) + $extMinutes;
            $displayHours = intdiv($extTotalMinutes, 60);
            $displayMinutes = $extTotalMinutes % 60;
            return $displayHours . ':' . str_pad($displayMinutes, 2, '0', STR_PAD_LEFT) . ' ' . $baseUnit . ' (Extension Only)';
        }
    }

    // Fallback to base duration
    return $baseDuration . ' ' . $baseUnit;
}

/**
 * Format payment amounts for display in export report
 * 
 * @param array $booking_data Booking data from database
 * @param string $filter_start Start date for filtering (Y-m-d format)
 * @param string $filter_end End date for filtering (Y-m-d format)
 * @return array Formatted amounts for display
 */
function formatPaymentAmountsForExport($booking_data, $filter_start, $filter_end)
{
    $payment_calc = calculatePaymentAmountsByDate($booking_data, $filter_start, $filter_end);

    $gross_amount = $payment_calc['total_amount_in_range'];
    $netPaidAmount = getNetPaidAmountForExport($booking_data);

    // "Total Amount Booking" / "Overall Amount" must reflect the full booking charge,
    // not the filtered (per-day) slice. In extension flows, legacy rows may keep
    // total_amount at the original room rate (e.g. 600) while extension charges live
    // in extend_* fields (e.g. 960). Prefer room base + extension charges when larger.
    $room_price = floatval($booking_data['room_price'] ?? 0);
    $extend_total = getTotalExtensionChargesForBooking($booking_data);
    $storedTotal = floatval($booking_data['total_amount'] ?? 0);
    $fullChargeTotal = 0.0;
    if ($room_price > 0 || $extend_total > 0) {
        $fullChargeTotal = max(0, $room_price) + max(0, $extend_total);
    }
    if ($storedTotal > 0) {
        $fullChargeTotal = $fullChargeTotal > 0 ? max($fullChargeTotal, $storedTotal) : $storedTotal;
    }
    $fullChargeTotal = max(0, $fullChargeTotal);

    // Prefer paid-based values when available so exports match checkout totals.
    // Keep per-day split behavior for multiple timestamps to avoid double-counting.
    if ($netPaidAmount > 0) {
        $allPayments = parsePaymentHistory($booking_data['payment_date_time'] ?? '');
        $allPaymentCount = count($allPayments);
        $inRangeCount = intval($payment_calc['payment_count_in_range'] ?? 0);

        if ($inRangeCount <= 0) {
            $gross_amount = 0.0;
        } elseif ($allPaymentCount <= 1) {
            $gross_amount = $netPaidAmount;
        } else {
            $baseTotal = floatval($booking_data['total_amount'] ?? 0);
            if ($baseTotal > 0 && $payment_calc['total_amount_in_range'] > 0) {
                $ratio = $payment_calc['total_amount_in_range'] / $baseTotal;
                $ratio = max(0, min(1, $ratio));
                $gross_amount = $netPaidAmount * $ratio;
            } else {
                $gross_amount = $netPaidAmount * ($inRangeCount / $allPaymentCount);
            }
        }
    }

    // Keep booking/detailed exports aligned with checkout totals.
    // Missing-item and penalty values are shown in their own columns and should
    // not be subtracted from the booking amount fields.
    $amount_for_export = max(0, $gross_amount);

    $bookingTypeRes = strcasecmp(trim((string) ($booking_data['booking_type'] ?? '')), 'Reservation') === 0;
    $totalBookingVal = $fullChargeTotal > 0 ? $fullChargeTotal : $amount_for_export;
    $overallVal = $totalBookingVal;

    if ($bookingTypeRes) {
        // Check if reservation has been checked in yet
        $status = (string) ($booking_data['status'] ?? '');
        $isNotCheckedInYet = ($status === 'Reserved' || $status === 'Pending' || $status === 'Confirming');

        if ($isNotCheckedInYet) {
            // For reservations not yet checked in, only show the downpayment amount
            $dpAmount = floatval($booking_data['downpayment_amount'] ?? 0);
            if ($dpAmount <= 0) {
                $dpAmount = floatval($booking_data['total_amount_reservation'] ?? 0);
            }
            if ($dpAmount <= 0) {
                $dpAmount = floatval($booking_data['downpayment_cash'] ?? 0) +
                    floatval($booking_data['downpayment_gcash'] ?? 0) +
                    floatval($booking_data['downpayment_maya'] ?? 0);
            }
            $totalBookingVal = $dpAmount;
            $overallVal = $dpAmount;
        } else {
            // For checked-in/checked-out reservations, use full collected amount
            $fullCollected = getReservationFullCollectedAmount($booking_data);
            if ($fullCollected > 0) {
                $totalBookingVal = $fullCollected;
                $overallVal = $fullCollected;
            }
        }
    }

    return [
        'amount' => number_format($amount_for_export, 2),
        'total_amount_booking' => number_format($totalBookingVal, 2),
        'overall_amount' => number_format($overallVal, 2),
        'extension_duration' => getExtensionDurationForDateRange($booking_data, $filter_start, $filter_end),
        'duration' => getDurationForDateRange($booking_data, $filter_start, $filter_end),
        'payment_breakdown' => $payment_calc['payments_in_range']
    ];
}

/**
 * Full booking charge after discount (room + extension + guest/pet fees - discount).
 */
function resolveBookingChargeAfterDiscount(array $report): float
{
    $discount = floatval($report['discount_amount'] ?? 0);
    $roomPrice = floatval($report['room_price'] ?? 0);
    $extend = getTotalExtensionChargesForBooking($report);
    $guest = (intval($report['additional_guest'] ?? 0) + intval($report['extend_additional_guest'] ?? 0)) * 300;
    $pet = intval($report['additional_pet'] ?? 0) * 500;
    $stored = floatval($report['total_amount'] ?? 0);

    $fromComponents = max(0, $roomPrice + $extend + $guest + $pet - $discount);
    if ($fromComponents > 0 && $stored > 0 && $stored < $fromComponents - 0.01) {
        // DB total_amount is the settled charge (e.g. after discount); do not use gross room_price.
        return round($stored, 2);
    }
    if ($fromComponents > 0) {
        return round($fromComponents, 2);
    }
    return round(max(0, $stored), 2);
}

/**
 * Total Amount for checkout revenue reports: payments in range, stored booking total,
 * and full charge after discount — capped by actual cash collected so gross room_rate
 * (e.g. ₱1490) is not shown when the guest paid ₱1400 after discount/downpayment.
 */
function resolveReportRevenueTotalAmount(array $report, string $filterStart, string $filterEnd): float
{
    $paymentsInRange = calculatePaymentAmountInDateRange($report, $filterStart, $filterEnd);
    $storedTotal = floatval($report['total_amount'] ?? 0);
    $fullCharge = resolveBookingChargeAfterDiscount($report);

    $collected = 0.0;
    if (strcasecmp(trim((string) ($report['booking_type'] ?? '')), 'Reservation') === 0) {
        $collected = getReservationFullCollectedAmount($report);
    }
    if ($collected <= 0) {
        $collected = getNetPaidAmountForExport($report);
    }

    $final = $paymentsInRange;
    if ($storedTotal > 0) {
        $final = max($final, $storedTotal);
    }
    if ($fullCharge > 0) {
        $final = max($final, $fullCharge);
    }
    if ($collected > 0) {
        // Do not exceed money actually collected (matches export_report Overall Amount).
        if ($final > $collected + 0.01) {
            $final = $collected;
        } else {
            $final = max($final, $collected);
        }
    }

    return round(max(0, $final), 2);
}
?>