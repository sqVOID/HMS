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
        'payment_amount_airbnb_history'
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

    // See export_payment_type_report.php: include partial payments; date filter in PHP for pipe-separated timestamps.
    $stmt = $conn->prepare("
        SELECT 
            r.booking_id,
            r.payment_date_time,
            DATE(COALESCE(NULLIF(TRIM(SUBSTRING_INDEX(r.payment_date_time, '|', 1)), ''), r.check_in)) as payment_date,
            r.check_in,
            r.guest_name,
            r.room_id,
            r.status,
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
            r.booking_type,
            r.extension_withdraw,
            r.withdrawn_extend_price
        FROM reports r
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

    // Parse all ₱ amounts found in a payment status string.
    // Examples:
    //  - "Cash (₱800.00)" => [800]
    //  - "Cash (₱800.00), Cash (₱300.00)" => [800, 300]
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

    // Convert a raw timestamp into the formats used in the report.
    function formatTimestampForExport($rawTimestamp): array
    {
        $rawTimestamp = is_string($rawTimestamp) ? trim($rawTimestamp) : '';
        if ($rawTimestamp === '')
            return ['date' => 'N/A', 'payment_date_time' => 'N/A'];

        try {
            $dt = new DateTime($rawTimestamp);
            return [
                'date' => $dt->format('m/d/Y'),
                'payment_date_time' => $dt->format('m/d/Y h:i a')
            ];
        } catch (Exception $e) {
            // If we can't parse it, keep raw for display.
            return ['date' => 'N/A', 'payment_date_time' => $rawTimestamp];
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

        $candidates = [];
        if (!empty($row['downpayment_date'])) {
            $candidates[] = trim((string) $row['downpayment_date']);
        }
        if (!empty($row['payment_date_time'])) {
            foreach (explode('|', (string) $row['payment_date_time']) as $seg) {
                $s = trim($seg);
                if ($s !== '') {
                    $candidates[] = $s;
                }
            }
        }
        if (!empty($row['check_in'])) {
            $candidates[] = trim((string) $row['check_in']);
        }

        foreach ($candidates as $raw) {
            try {
                $dt = new DateTime($raw);
                if ($dt >= $start && $dt <= $end) {
                    return true;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return false;
    }

    $payments = array_values(array_filter($payments, function ($r) use ($startDate, $endDate) {
        return perPaymentReportRowInDateRange($r, $startDate, $endDate);
    }));

    /**
     * Allocate method amounts across the `payment_date_time` timestamps.
     *
     * Supported storage patterns in this codebase:
     *  - method status contains per-payment amounts (multiple ₱ occurrences)
     *  - method status contains a single cumulative total (one ₱ occurrence)
     * We then use deposit/downpayment as the "base" for the first timestamp,
     * and remainder for the last timestamp (or evenly distribute if > 2 timestamps).
     */
    function allocateAmountsToPaymentTimestamps(array $timestamps, array $methodAmounts, float $baseAmount): array
    {
        $n = count($timestamps);
        if ($n === 0)
            return [];

        // No parsed amounts: only base (if any) is known.
        if (count($methodAmounts) === 0) {
            if ($baseAmount > 0) {
                return array_merge([$baseAmount], array_fill(0, $n - 1, 0));
            }
            return array_fill(0, $n, 0);
        }

        // If we already have per-payment amounts (same count), align 1:1 by index.
        if (count($methodAmounts) === $n) {
            return array_values(array_map(fn($v) => max(0, floatval($v)), $methodAmounts));
        }

        // If storage is a single cumulative total, we can only split it with a base hint.
        if (count($methodAmounts) === 1) {
            $total = max(0, floatval($methodAmounts[0]));
            $base = max(0, floatval($baseAmount));

            // Prefer base as the first payment, if it doesn't exceed total.
            $first = ($base > 0 && $base <= $total) ? $base : (($base > 0) ? min($base, $total) : 0);
            if ($n === 1)
                return [$total];
            if ($first <= 0) {
                // No base hint: split equally.
                $per = $n > 0 ? $total / $n : 0;
                return array_fill(0, $n, $per);
            }

            $remainder = max(0, $total - $first);
            $restCount = $n - 1;
            $perRest = $restCount > 0 ? ($remainder / $restCount) : 0;
            return array_merge([$first], array_fill(0, $restCount, $perRest));
        }

        // Fallback: map what we have to the earliest timestamps, remainder is 0.
        $out = array_fill(0, $n, 0);
        $limit = min(count($methodAmounts), $n);
        for ($i = 0; $i < $limit; $i++) {
            $out[$i] = max(0, floatval($methodAmounts[$i]));
        }
        return $out;
    }

    // Prepare data rows with individual payment methods
    $dataRows = [];
    $grandTotal = 0;

    foreach ($payments as $payment) {
        $bookingId = $payment['booking_id'] ?: 'N/A';
        $guestName = $payment['guest_name'] ?: 'N/A';
        $roomId = $payment['room_id'] ?: 'N/A';
        $status = resolvePaymentExportDisplayStatus($payment);
        $timestampRows = buildPaymentExportTimestampRows($payment);

        $nTimestamps = count($timestampRows);

        // -----------------------------
        // Process Cash payments per timestamp
        // -----------------------------
        $depositCash = floatval($payment['deposit_cash'] ?? 0);
        $downpaymentCash = floatval($payment['downpayment_cash'] ?? 0);
        $totalCash = paymentExportMethodTotal($payment, 'deposit_cash', 'downpayment_cash');

        $cashHistoryArr = !empty($payment['payment_amount_cash_history'])
            ? explode('|', (string) $payment['payment_amount_cash_history'])
            : null;

        if (is_array($cashHistoryArr) && count($cashHistoryArr) === $nTimestamps) {
            $cashAmountsByTimestamp = array_map(fn($v) => floatval($v), $cashHistoryArr);
            $sumAll = array_sum($cashAmountsByTimestamp);
            if ($nTimestamps >= 2 && $totalCash > 0 && $sumAll > $totalCash + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $sumPrior = array_sum(array_slice($cashAmountsByTimestamp, 0, $lastIdx));
                $lastVal = $cashAmountsByTimestamp[$lastIdx];
                if ($lastVal >= $totalCash - 0.02) {
                    $cashAmountsByTimestamp[$lastIdx] = max(0, $totalCash - $sumPrior);
                }
            }
        } elseif (is_array($cashHistoryArr) && $nTimestamps === count($cashHistoryArr) + 1) {
            $sumPrior = 0.0;
            foreach ($cashHistoryArr as $seg) {
                $sumPrior += floatval($seg);
            }
            $lastCash = max(0, $totalCash - $sumPrior);
            $cashAmountsByTimestamp = array_merge(
                array_map(fn($v) => floatval($v), $cashHistoryArr),
                [$lastCash]
            );
        } elseif (is_array($cashHistoryArr) && count($cashHistoryArr) === 1 && $nTimestamps === 2) {
            $lastCash = floatval($cashHistoryArr[0] ?? 0);
            $firstCash = max(0, $totalCash - $lastCash);
            $cashAmountsByTimestamp = [$firstCash, $lastCash];
        } else {
            $cashMethodAmounts = parsePaymentAmountsAll($payment['payment_status_cash']);

            if ($nTimestamps === 2 && count($cashMethodAmounts) === 1) {
                $lastCash = floatval($cashMethodAmounts[0]);
                if ($lastCash > 0 && $totalCash > $lastCash + 0.01) {
                    $cashAmountsByTimestamp = [$totalCash - $lastCash, $lastCash];
                } else {
                    $cashAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $cashMethodAmounts, $totalCash);
                }
            } else {
                $cashAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $cashMethodAmounts, $totalCash);
            }
        }

        // CRITICAL FIX: Only check payment_status_cash (per-method column), NOT the global
        // payment_status field. The global field stores the reservation downpayment method
        // (e.g. "Airbnb") and must never override the label of a subsequent Cash payment.
        $cashLikeMethodLabel = resolvePaymentExportCashLikeLabel($payment);

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $cashAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0) {
                $dataRows[] = [
                    'booking_id' => $bookingId,
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'guest_name' => $guestName,
                    'room_id' => $roomId,
                    'status' => $status,
                    'payment_method' => $cashLikeMethodLabel,
                    'amount' => $amt
                ];
                $grandTotal += $amt;
            }
        }

        // -----------------------------
        // Process G-Cash payments per timestamp
        // -----------------------------
        $depositGcash = floatval($payment['deposit_g_cash'] ?? 0);
        $downpaymentGcash = floatval($payment['downpayment_gcash'] ?? 0);
        $totalGcash = paymentExportMethodTotal($payment, 'deposit_g_cash', 'downpayment_gcash');

        $gcashHistoryArr = !empty($payment['payment_amount_g_cash_history'])
            ? explode('|', (string) $payment['payment_amount_g_cash_history'])
            : null;

        if (is_array($gcashHistoryArr) && count($gcashHistoryArr) === $nTimestamps) {
            $gcashAmountsByTimestamp = array_map(fn($v) => floatval($v), $gcashHistoryArr);
            $sumAll = array_sum($gcashAmountsByTimestamp);
            if ($nTimestamps >= 2 && $totalGcash > 0 && $sumAll > $totalGcash + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $sumPrior = array_sum(array_slice($gcashAmountsByTimestamp, 0, $lastIdx));
                if ($gcashAmountsByTimestamp[$lastIdx] >= $totalGcash - 0.02) {
                    $gcashAmountsByTimestamp[$lastIdx] = max(0, $totalGcash - $sumPrior);
                }
            }
        } elseif (is_array($gcashHistoryArr) && $nTimestamps === count($gcashHistoryArr) + 1) {
            $sumPrior = 0.0;
            foreach ($gcashHistoryArr as $seg) {
                $sumPrior += floatval($seg);
            }
            $gcashAmountsByTimestamp = array_merge(
                array_map(fn($v) => floatval($v), $gcashHistoryArr),
                [max(0, $totalGcash - $sumPrior)]
            );
        } elseif (is_array($gcashHistoryArr) && count($gcashHistoryArr) === 1 && $nTimestamps === 2) {
            $lastGcash = floatval($gcashHistoryArr[0] ?? 0);
            $firstGcash = max(0, $totalGcash - $lastGcash);
            $gcashAmountsByTimestamp = [$firstGcash, $lastGcash];
        } else {
            $gcashMethodAmounts = parsePaymentAmountsAll($payment['payment_status_g_cash']);

            if ($nTimestamps === 2 && count($gcashMethodAmounts) === 1) {
                $lastGcash = floatval($gcashMethodAmounts[0]);
                if ($lastGcash > 0 && $totalGcash > $lastGcash + 0.01) {
                    $gcashAmountsByTimestamp = [$totalGcash - $lastGcash, $lastGcash];
                } else {
                    $gcashAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $gcashMethodAmounts, $totalGcash);
                }
            } else {
                $gcashAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $gcashMethodAmounts, $totalGcash);
            }
        }

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $gcashAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0) {
                $dataRows[] = [
                    'booking_id' => $bookingId,
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'guest_name' => $guestName,
                    'room_id' => $roomId,
                    'status' => $status,
                    'payment_method' => 'G-Cash',
                    'amount' => $amt
                ];
                $grandTotal += $amt;
            }
        }

        // -----------------------------
        // Process Maya payments per timestamp
        // -----------------------------
        $depositMaya = floatval($payment['deposit_maya'] ?? 0);
        $downpaymentMaya = floatval($payment['downpayment_maya'] ?? 0);
        $totalMaya = paymentExportMethodTotal($payment, 'deposit_maya', 'downpayment_maya');

        $mayaHistoryArr = !empty($payment['payment_amount_maya_history'])
            ? explode('|', (string) $payment['payment_amount_maya_history'])
            : null;

        if (is_array($mayaHistoryArr) && count($mayaHistoryArr) === $nTimestamps) {
            $mayaAmountsByTimestamp = array_map(fn($v) => floatval($v), $mayaHistoryArr);
            $sumAll = array_sum($mayaAmountsByTimestamp);
            if ($nTimestamps >= 2 && $totalMaya > 0 && $sumAll > $totalMaya + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $sumPrior = array_sum(array_slice($mayaAmountsByTimestamp, 0, $lastIdx));
                if ($mayaAmountsByTimestamp[$lastIdx] >= $totalMaya - 0.02) {
                    $mayaAmountsByTimestamp[$lastIdx] = max(0, $totalMaya - $sumPrior);
                }
            }
        } elseif (is_array($mayaHistoryArr) && $nTimestamps === count($mayaHistoryArr) + 1) {
            $sumPrior = 0.0;
            foreach ($mayaHistoryArr as $seg) {
                $sumPrior += floatval($seg);
            }
            $mayaAmountsByTimestamp = array_merge(
                array_map(fn($v) => floatval($v), $mayaHistoryArr),
                [max(0, $totalMaya - $sumPrior)]
            );
        } elseif (is_array($mayaHistoryArr) && count($mayaHistoryArr) === 1 && $nTimestamps === 2) {
            $lastMaya = floatval($mayaHistoryArr[0] ?? 0);
            $firstMaya = max(0, $totalMaya - $lastMaya);
            $mayaAmountsByTimestamp = [$firstMaya, $lastMaya];
        } else {
            $mayaMethodAmounts = parsePaymentAmountsAll($payment['payment_status_maya']);

            if ($nTimestamps === 2 && count($mayaMethodAmounts) === 1) {
                $lastMaya = floatval($mayaMethodAmounts[0]);
                if ($lastMaya > 0 && $totalMaya > $lastMaya + 0.01) {
                    $mayaAmountsByTimestamp = [$totalMaya - $lastMaya, $lastMaya];
                } else {
                    $mayaAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $mayaMethodAmounts, $totalMaya);
                }
            } else {
                $mayaAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $mayaMethodAmounts, $totalMaya);
            }
        }

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $mayaAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0) {
                $dataRows[] = [
                    'booking_id' => $bookingId,
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'guest_name' => $guestName,
                    'room_id' => $roomId,
                    'status' => $status,
                    'payment_method' => 'Maya',
                    'amount' => $amt
                ];
                $grandTotal += $amt;
            }
        }

        // -----------------------------
        // Process Instapay payments per timestamp
        // -----------------------------
        $depositInstapay = floatval($payment['deposit_instapay'] ?? 0);
        $downpaymentInstapay = floatval($payment['downpayment_instapay'] ?? 0);
        $totalInstapay = paymentExportMethodTotal($payment, 'deposit_instapay', 'downpayment_instapay');

        $instapayHistoryArr = !empty($payment['payment_amount_instapay_history'])
            ? explode('|', (string) $payment['payment_amount_instapay_history'])
            : null;

        if (is_array($instapayHistoryArr) && count($instapayHistoryArr) === $nTimestamps) {
            $instapayAmountsByTimestamp = array_map(fn($v) => floatval($v), $instapayHistoryArr);
            $sumAll = array_sum($instapayAmountsByTimestamp);
            if ($nTimestamps >= 2 && $totalInstapay > 0 && $sumAll > $totalInstapay + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $sumPrior = array_sum(array_slice($instapayAmountsByTimestamp, 0, $lastIdx));
                if ($instapayAmountsByTimestamp[$lastIdx] >= $totalInstapay - 0.02) {
                    $instapayAmountsByTimestamp[$lastIdx] = max(0, $totalInstapay - $sumPrior);
                }
            }
        } elseif (is_array($instapayHistoryArr) && $nTimestamps === count($instapayHistoryArr) + 1) {
            $sumPrior = 0.0;
            foreach ($instapayHistoryArr as $seg) {
                $sumPrior += floatval($seg);
            }
            $instapayAmountsByTimestamp = array_merge(
                array_map(fn($v) => floatval($v), $instapayHistoryArr),
                [max(0, $totalInstapay - $sumPrior)]
            );
        } elseif (is_array($instapayHistoryArr) && count($instapayHistoryArr) === 1 && $nTimestamps === 2) {
            $lastInstapay = floatval($instapayHistoryArr[0] ?? 0);
            $firstInstapay = max(0, $totalInstapay - $lastInstapay);
            $instapayAmountsByTimestamp = [$firstInstapay, $lastInstapay];
        } else {
            $instapayMethodAmounts = parsePaymentAmountsAll($payment['payment_status_instapay']);
            if ($nTimestamps === 2 && count($instapayMethodAmounts) === 1) {
                $lastInstapay = floatval($instapayMethodAmounts[0]);
                if ($lastInstapay > 0 && $totalInstapay > $lastInstapay + 0.01) {
                    $instapayAmountsByTimestamp = [$totalInstapay - $lastInstapay, $lastInstapay];
                } else {
                    $instapayAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $instapayMethodAmounts, $totalInstapay);
                }
            } else {
                $instapayAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $instapayMethodAmounts, $totalInstapay);
            }
        }

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $instapayAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0) {
                $dataRows[] = [
                    'booking_id' => $bookingId,
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'guest_name' => $guestName,
                    'room_id' => $roomId,
                    'status' => $status,
                    'payment_method' => 'Instapay',
                    'amount' => $amt
                ];
                $grandTotal += $amt;
            }
        }

        // -----------------------------
        // Process Online Banking payments per timestamp
        // -----------------------------
        $depositOnlineBanking = floatval($payment['deposit_online_banking'] ?? 0);
        $downpaymentOnlineBanking = floatval($payment['downpayment_online_banking'] ?? 0);
        $totalOnlineBanking = paymentExportMethodTotal($payment, 'deposit_online_banking', 'downpayment_online_banking');

        $onlineBankingHistoryArr = !empty($payment['payment_amount_online_banking_history'])
            ? explode('|', (string) $payment['payment_amount_online_banking_history'])
            : null;

        if (is_array($onlineBankingHistoryArr) && count($onlineBankingHistoryArr) === $nTimestamps) {
            $onlineBankingAmountsByTimestamp = array_map(fn($v) => floatval($v), $onlineBankingHistoryArr);
            $sumAll = array_sum($onlineBankingAmountsByTimestamp);
            if ($nTimestamps >= 2 && $totalOnlineBanking > 0 && $sumAll > $totalOnlineBanking + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $sumPrior = array_sum(array_slice($onlineBankingAmountsByTimestamp, 0, $lastIdx));
                if ($onlineBankingAmountsByTimestamp[$lastIdx] >= $totalOnlineBanking - 0.02) {
                    $onlineBankingAmountsByTimestamp[$lastIdx] = max(0, $totalOnlineBanking - $sumPrior);
                }
            }
        } elseif (is_array($onlineBankingHistoryArr) && $nTimestamps === count($onlineBankingHistoryArr) + 1) {
            $sumPrior = 0.0;
            foreach ($onlineBankingHistoryArr as $seg) {
                $sumPrior += floatval($seg);
            }
            $onlineBankingAmountsByTimestamp = array_merge(
                array_map(fn($v) => floatval($v), $onlineBankingHistoryArr),
                [max(0, $totalOnlineBanking - $sumPrior)]
            );
        } elseif (is_array($onlineBankingHistoryArr) && count($onlineBankingHistoryArr) === 1 && $nTimestamps === 2) {
            $lastOnlineBanking = floatval($onlineBankingHistoryArr[0] ?? 0);
            $firstOnlineBanking = max(0, $totalOnlineBanking - $lastOnlineBanking);
            $onlineBankingAmountsByTimestamp = [$firstOnlineBanking, $lastOnlineBanking];
        } else {
            $onlineBankingMethodAmounts = parsePaymentAmountsAll($payment['payment_status_online_banking']);
            if ($nTimestamps === 2 && count($onlineBankingMethodAmounts) === 1) {
                $lastOnlineBanking = floatval($onlineBankingMethodAmounts[0]);
                if ($lastOnlineBanking > 0 && $totalOnlineBanking > $lastOnlineBanking + 0.01) {
                    $onlineBankingAmountsByTimestamp = [$totalOnlineBanking - $lastOnlineBanking, $lastOnlineBanking];
                } else {
                    $onlineBankingAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $onlineBankingMethodAmounts, $totalOnlineBanking);
                }
            } else {
                $onlineBankingAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $onlineBankingMethodAmounts, $totalOnlineBanking);
            }
        }

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $onlineBankingAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0) {
                $dataRows[] = [
                    'booking_id' => $bookingId,
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'guest_name' => $guestName,
                    'room_id' => $roomId,
                    'status' => $status,
                    'payment_method' => 'Online Banking',
                    'amount' => $amt
                ];
                $grandTotal += $amt;
            }
        }

        // -----------------------------
        // Process Airbnb payments per timestamp
        // -----------------------------
        $depositAirbnb = floatval($payment['deposit_airbnb'] ?? 0);
        $downpaymentAirbnb = floatval($payment['downpayment_airbnb'] ?? 0);
        $totalAirbnb = paymentExportMethodTotal($payment, 'deposit_airbnb', 'downpayment_airbnb');

        $airbnbHistoryArr = !empty($payment['payment_amount_airbnb_history'])
            ? explode('|', (string) $payment['payment_amount_airbnb_history'])
            : null;

        if (is_array($airbnbHistoryArr) && count($airbnbHistoryArr) === $nTimestamps) {
            $airbnbAmountsByTimestamp = array_map(fn($v) => floatval($v), $airbnbHistoryArr);
            $sumAll = array_sum($airbnbAmountsByTimestamp);
            if ($nTimestamps >= 2 && $totalAirbnb > 0 && $sumAll > $totalAirbnb + 0.02) {
                $lastIdx = $nTimestamps - 1;
                $sumPrior = array_sum(array_slice($airbnbAmountsByTimestamp, 0, $lastIdx));
                if ($airbnbAmountsByTimestamp[$lastIdx] >= $totalAirbnb - 0.02) {
                    $airbnbAmountsByTimestamp[$lastIdx] = max(0, $totalAirbnb - $sumPrior);
                }
            }
        } elseif (is_array($airbnbHistoryArr) && $nTimestamps === count($airbnbHistoryArr) + 1) {
            $sumPrior = 0.0;
            foreach ($airbnbHistoryArr as $seg) {
                $sumPrior += floatval($seg);
            }
            $airbnbAmountsByTimestamp = array_merge(
                array_map(fn($v) => floatval($v), $airbnbHistoryArr),
                [max(0, $totalAirbnb - $sumPrior)]
            );
        } elseif (is_array($airbnbHistoryArr) && count($airbnbHistoryArr) === 1 && $nTimestamps === 2) {
            $lastAirbnb = floatval($airbnbHistoryArr[0] ?? 0);
            $firstAirbnb = max(0, $totalAirbnb - $lastAirbnb);
            $airbnbAmountsByTimestamp = [$firstAirbnb, $lastAirbnb];
        } else {
            $airbnbMethodAmounts = parsePaymentAmountsAll($payment['payment_status_airbnb']);
            if ($nTimestamps === 2 && count($airbnbMethodAmounts) === 1) {
                $lastAirbnb = floatval($airbnbMethodAmounts[0]);
                if ($lastAirbnb > 0 && $totalAirbnb > $lastAirbnb + 0.01) {
                    $airbnbAmountsByTimestamp = [$totalAirbnb - $lastAirbnb, $lastAirbnb];
                } else {
                    $airbnbAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $airbnbMethodAmounts, $totalAirbnb);
                }
            } else {
                $airbnbAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $airbnbMethodAmounts, $totalAirbnb);
            }
        }

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $airbnbAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0) {
                $dataRows[] = [
                    'booking_id' => $bookingId,
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'guest_name' => $guestName,
                    'room_id' => $roomId,
                    'status' => $status,
                    'payment_method' => 'Airbnb',
                    'amount' => $amt
                ];
                $grandTotal += $amt;
            }
        }

    }

    // Mark withdrawn rows before date filtering, so we correctly identify the latest payments across all dates
    $withdrawnBookings = [];
    foreach ($payments as $payment) {
        $ew = intval($payment['extension_withdraw'] ?? 0);
        $wep = floatval($payment['withdrawn_extend_price'] ?? 0);
        if ($ew === 1 && $wep > 0) {
            $withdrawnBookings[$payment['booking_id']] = $wep;
        }
    }

    // Pass 1: Try to find exact matches for the remaining withdrawn amount
    for ($i = count($dataRows) - 1; $i >= 0; $i--) {
        $bid = $dataRows[$i]['booking_id'];
        if (isset($withdrawnBookings[$bid]) && $withdrawnBookings[$bid] > 0) {
            $wep = $withdrawnBookings[$bid];
            $amt = $dataRows[$i]['amount'];
            if (abs($amt - $wep) < 0.01 && $dataRows[$i]['status'] !== 'Withdrawn!') {
                $dataRows[$i]['status'] = 'Withdrawn!';
                $withdrawnBookings[$bid] = 0; // Fully consumed
            }
        }
    }

    // Pass 2: For any remaining withdrawn amount, consume from latest payments
    for ($i = count($dataRows) - 1; $i >= 0; $i--) {
        $bid = $dataRows[$i]['booking_id'];
        if (isset($withdrawnBookings[$bid]) && $withdrawnBookings[$bid] > 0.01) {
            $amt = $dataRows[$i]['amount'];
            if ($dataRows[$i]['status'] !== 'Withdrawn!' && $amt <= $withdrawnBookings[$bid] + 0.01) {
                $dataRows[$i]['status'] = 'Withdrawn!';
                $withdrawnBookings[$bid] -= $amt;
            }
        }
    }

    // Normalize per-payment rows before aggregating (duplicate downpayment must not inflate totals).
    $dataRows = normalizeAllReservationPaymentExportRows($dataRows, $payments);

    // Aggregate rows with same booking_id, date, and payment_method
    $aggregatedRows = [];
    foreach ($dataRows as $row) {
        // Create a unique key for aggregation: booking_id + date + payment_method
        $key = $row['booking_id'] . '|' . $row['date'] . '|' . $row['payment_method'];

        if (isset($aggregatedRows[$key])) {
            if ($row['status'] === 'Withdrawn!') {
                if (strpos($aggregatedRows[$key]['status'], ' + Withdrawn!') === false && $aggregatedRows[$key]['status'] !== 'Withdrawn!') {
                    $aggregatedRows[$key]['status'] .= ' + Withdrawn!';
                }
                // Do not add the withdrawn amount to the aggregated amount
            } else {
                $aggregatedRows[$key]['amount'] += $row['amount'];
                if ($aggregatedRows[$key]['status'] === 'Withdrawn!') {
                    $aggregatedRows[$key]['status'] = $row['status'] . ' + Withdrawn!';
                }
            }
        } else {
            // Create new row
            if ($row['status'] === 'Withdrawn!') {
                $row['amount'] = 0; // Will output 0 if it's the only payment that day
            }
            $aggregatedRows[$key] = $row;
        }
    }

    // Convert back to indexed array
    $dataRows = array_values($aggregatedRows);
    $dataRows = applyCanceledBookingFinancialsToRows($dataRows);

    // Filter rows by the Date column to match the selected date range
    $filteredRows = [];
    try {
        $start = new DateTime($startDate . ' 00:00:00');
        $end = new DateTime($endDate . ' 23:59:59');

        foreach ($dataRows as $row) {
            $rowDate = $row['date'];
            // Parse the date in m/d/Y format
            try {
                $dt = DateTime::createFromFormat('m/d/Y', $rowDate);
                if ($dt && $dt >= $start && $dt <= $end) {
                    $filteredRows[] = $row;
                }
            } catch (Exception $e) {
                // If date parsing fails, include the row
                $filteredRows[] = $row;
            }
        }
        $dataRows = $filteredRows;
    } catch (Exception $e) {
        // If date range parsing fails, keep all rows
    }

    // Recalculate grand total
    $grandTotal = sumPaymentRowAmounts($dataRows);



} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Type Report</title>
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
            <h1>Payment Type Report</h1>
            <div class="info" style="color: #000000ff; font-weight: bold;">

                <?php echo htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Date</th>
                    <th>Customer Name</th>
                    <th>Room Number</th>
                    <th>Status</th>
                    <th>Payment Method</th>
                    <th>Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dataRows)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">
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
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['room_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                            <?php
                        endforeach;
                    endforeach;
                    ?>
                    <tr class="total-row">
                        <td colspan="6" style="text-align: right;">GRAND TOTAL:</td>
                        <td>₱<?php echo number_format($grandTotal, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>