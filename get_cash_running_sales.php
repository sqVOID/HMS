<?php
require_once 'config.php';
header('Content-Type: application/json');

if (isset($_GET['shift_start']) && isset($_GET['shift_end'])) {
    $shiftStart = new DateTime($_GET['shift_start']);
    $shiftEnd = new DateTime($_GET['shift_end']);
} else {
    // Determine shift window: 8am today → 8am tomorrow (or yesterday 8am → today 8am)
    $now = new DateTime('now');
    $today8am = (new DateTime('today'))->setTime(8, 0, 0);
    $yesterday8am = (new DateTime('yesterday'))->setTime(8, 0, 0);
    $tomorrow8am = (new DateTime('tomorrow'))->setTime(8, 0, 0);
    if ($now >= $today8am) {
        $shiftStart = $today8am;
        $shiftEnd = $tomorrow8am;
    } else {
        $shiftStart = $yesterday8am;
        $shiftEnd = $today8am;
    }
}

$shiftStartStr = $shiftStart->format('Y-m-d H:i:s');
$shiftEndStr   = $shiftEnd->format('Y-m-d H:i:s');
$shiftDateStr  = $shiftStart->format('Y-m-d');

$yesterdayShiftStart = clone $shiftStart;
$yesterdayShiftStart->modify('-1 day');
$yesterdayShiftEnd = clone $shiftStart;
$yesterdayShiftDateStr = $yesterdayShiftStart->format('Y-m-d');

try {
    // Ensure cash history column exists
    try {
        $chk = $conn->query("SHOW COLUMNS FROM reports LIKE 'payment_amount_cash_history'");
        if ($chk && $chk->rowCount() == 0) {
            $conn->exec("ALTER TABLE reports ADD COLUMN payment_amount_cash_history TEXT NULL DEFAULT NULL");
        }
    } catch (PDOException $e) {}

    // Check if there's already a deposit recorded for the current shift
    // For custom date ranges, we need to get ALL deposits that overlap with the range
    // A deposit overlaps if: deposit_start < range_end AND deposit_end > range_start
    $depositStmt = $conn->prepare("
        SELECT id, shift_date, cash_deposited, shift_start, shift_end, created_at 
        FROM cash_deposits 
        WHERE shift_start < :shift_end AND shift_end > :shift_start
        ORDER BY created_at ASC
    ");
    $depositStmt->execute([
        ':shift_start' => $shiftStartStr,
        ':shift_end' => $shiftEndStr
    ]);
    $existingDeposits = $depositStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build array of deposit time ranges to exclude transactions
    // We'll exclude transactions that fall within any deposited shift period
    $depositedRanges = [];
    foreach ($existingDeposits as $deposit) {
        $depositedRanges[] = [
            'start' => new DateTime($deposit['shift_start']),
            'end' => new DateTime($deposit['shift_end']),
            'created_at' => new DateTime($deposit['created_at'])
        ];
    }

    // Also check for single-day deposit (legacy compatibility)
    $singleDayDepositStmt = $conn->prepare("
        SELECT id, cash_deposited, shift_start, shift_end, created_at 
        FROM cash_deposits 
        WHERE shift_date = :shift_date 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $singleDayDepositStmt->execute([':shift_date' => $shiftDateStr]);
    $existingDeposit = $singleDayDepositStmt->fetch(PDO::FETCH_ASSOC);

    // If single-day deposit exists and not already in ranges, add it
    if ($existingDeposit) {
        $found = false;
        foreach ($depositedRanges as $range) {
            if ($range['created_at']->format('Y-m-d H:i:s') === $existingDeposit['created_at']) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $depositedRanges[] = [
                'start' => new DateTime($existingDeposit['shift_start']),
                'end' => new DateTime($existingDeposit['shift_end']),
                'created_at' => new DateTime($existingDeposit['created_at'])
            ];
        }
    }

    // Check if there's already a deposit recorded for yesterday's shift
    $yesterdayDepositStmt = $conn->prepare("
        SELECT id, cash_deposited, created_at 
        FROM cash_deposits 
        WHERE shift_date = :shift_date 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $yesterdayDepositStmt->execute([':shift_date' => $yesterdayShiftDateStr]);
    $yesterdayDeposit = $yesterdayDepositStmt->fetch(PDO::FETCH_ASSOC);

    $yesterdayDepositedCash = $yesterdayDeposit ? floatval($yesterdayDeposit['cash_deposited']) : 0.0;

    // Fetch all rows that could have cash payments
    $stmt = $conn->prepare("
        SELECT
            r.booking_id,
            r.payment_date_time,
            r.downpayment_date,
            r.payment_amount_cash_history,
            r.payment_status_cash,
            r.deposit_cash,
            r.downpayment_cash,
            r.paid_status,
            r.created_at,
            r.confirmed_at
        FROM reports r
        WHERE (
            (r.payment_date_time IS NOT NULL AND TRIM(r.payment_date_time) <> '')
            OR (r.downpayment_date IS NOT NULL AND r.downpayment_date <> '')
        )
        AND (
            r.paid_status = 'Paid'
            OR COALESCE(r.deposit_cash, 0) > 0.005
            OR COALESCE(r.downpayment_cash, 0) > 0.005
        )
        ORDER BY r.booking_id ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cashTotal      = 0.0;
    $txCount        = 0;
    $transactions   = [];
    $runningCash    = 0.0;
    $runningTxCount = 0;
    $yesterdayRunningCash = 0.0;

    foreach ($rows as $row) {
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
                if ($ts !== '' && $ts !== '0000-00-00 00:00:00') $timestamps[] = $ts;
            }
        }
        if (empty($timestamps) && !empty($row['downpayment_date'])) {
            $dp = trim((string)$row['downpayment_date']);
            if ($dp !== '' && $dp !== '0000-00-00 00:00:00') $timestamps[] = $dp;
        }
        if (empty($timestamps)) continue;

        $nTs       = count($timestamps);
        $depCash   = floatval($row['deposit_cash'] ?? 0);
        $downCash  = floatval($row['downpayment_cash'] ?? 0);
        $totalCash = max($depCash, $downCash);

        // Build per-timestamp cash amounts using history column
        $cashAmounts = [];
        $histArr = !empty($row['payment_amount_cash_history'])
            ? explode('|', (string)$row['payment_amount_cash_history'])
            : null;

        if (is_array($histArr) && count($histArr) === $nTs) {
            // Exact match: align 1:1
            $cashAmounts = array_map('floatval', $histArr);
            // Sanity: if sum > totalCash significantly, recalculate last slot
            if ($nTs >= 2 && $totalCash > 0) {
                $sum = array_sum($cashAmounts);
                if ($sum > $totalCash + 0.02) {
                    $lastIdx  = $nTs - 1;
                    $sumPrior = array_sum(array_slice($cashAmounts, 0, $lastIdx));
                    if ($cashAmounts[$lastIdx] >= $totalCash - 0.02) {
                        $cashAmounts[$lastIdx] = max(0, $totalCash - $sumPrior);
                    }
                }
            }
        } elseif (is_array($histArr) && $nTs === count($histArr) + 1) {
            // History is one behind — last payment not yet in history
            $sumPrior    = array_sum(array_map('floatval', $histArr));
            $lastAmt     = max(0, $totalCash - $sumPrior);
            $cashAmounts = array_merge(array_map('floatval', $histArr), [$lastAmt]);
        } elseif ($totalCash > 0) {
            // No precise history: put all on first timestamp
            $cashAmounts = array_fill(0, $nTs, 0);
            $cashAmounts[0] = $totalCash;
        } else {
            continue; // No cash at all
        }

        // Sum only amounts within shift window
        foreach ($timestamps as $idx => $ts) {
            $amt = floatval($cashAmounts[$idx] ?? 0);
            if ($amt <= 0.005) continue;
            try {
                $dt = new DateTime($ts);
                if ($dt->format('Y') < 2020) {
                    if (!empty($row['confirmed_at']) && $row['confirmed_at'] !== '0000-00-00 00:00:00') {
                        $dt = new DateTime($row['confirmed_at']);
                    } elseif (!empty($row['created_at']) && $row['created_at'] !== '0000-00-00 00:00:00') {
                        $dt = new DateTime($row['created_at']);
                    }
                }
                if ($dt >= $shiftStart && $dt < $shiftEnd) {
                    // Check if this transaction falls within any already-deposited shift range
                    $alreadyDeposited = false;
                    foreach ($depositedRanges as $range) {
                        // Transaction is deposited if it's within the deposited shift range
                        // AND it occurred before or at the deposit creation time
                        if ($dt >= $range['start'] && $dt < $range['end'] && $dt <= $range['created_at']) {
                            $alreadyDeposited = true;
                            break;
                        }
                    }
                    
                    if ($alreadyDeposited) {
                        continue; // Skip transactions that were already deposited
                    }
                    
                    $runningCash += $amt;
                    $runningTxCount++;
                    $cashTotal += $amt;
                    $txCount++;
                    $transactions[] = [
                        'booking_id' => $row['booking_id'],
                        'timestamp'  => $dt->format('m/d/Y h:i A'),
                        'amount'     => round($amt, 2),
                    ];
                } elseif ($dt >= $yesterdayShiftStart && $dt < $yesterdayShiftEnd) {
                    $yesterdayRunningCash += $amt;
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    $runningCash = round($runningCash, 0); // Round to nearest whole number
    $depositedCash = $existingDeposit ? floatval($existingDeposit['cash_deposited']) : 0.0;

    $yesterdayRunningCash = round($yesterdayRunningCash, 0); // Round to nearest whole number
    $yesterdayPendingDeposit = max(0.0, round($yesterdayRunningCash - $yesterdayDepositedCash, 2));
    $cashTotalRounded = round($cashTotal, 0);

    // Pending Deposit = undeposited cash from Jan 1 8:00 AM of the shift year through now
    // (same idea as modal Expected Sales when Shift Start is set to Jan 1)
    $pendingFrom = new DateTime($shiftStart->format('Y') . '-01-01 08:00:00');
    $pendingFromStr = $pendingFrom->format('Y-m-d');

    $allDepositsStmt = $conn->prepare("
        SELECT shift_start, shift_end, created_at
        FROM cash_deposits
        ORDER BY created_at ASC
    ");
    $allDepositsStmt->execute();
    $allDeposits = $allDepositsStmt->fetchAll(PDO::FETCH_ASSOC);

    $allDepositedRanges = [];
    foreach ($allDeposits as $dep) {
        $allDepositedRanges[] = [
            'start' => new DateTime($dep['shift_start']),
            'end' => new DateTime($dep['shift_end']),
            'created_at' => new DateTime($dep['created_at'])
        ];
    }

    $totalPendingDeposit = 0.0;
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
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

        $timestamps = [];
        if (!empty($row['payment_date_time'])) {
            foreach (explode('|', (string)$row['payment_date_time']) as $ts) {
                $ts = trim($ts);
                if ($ts !== '' && $ts !== '0000-00-00 00:00:00') $timestamps[] = $ts;
            }
        }
        if (empty($timestamps) && !empty($row['downpayment_date'])) {
            $dp = trim((string)$row['downpayment_date']);
            if ($dp !== '' && $dp !== '0000-00-00 00:00:00') $timestamps[] = $dp;
        }
        if (empty($timestamps)) continue;

        $nTs       = count($timestamps);
        $depCash   = floatval($row['deposit_cash'] ?? 0);
        $downCash  = floatval($row['downpayment_cash'] ?? 0);
        $totalCash = max($depCash, $downCash);

        $cashAmounts = [];
        $histArr = !empty($row['payment_amount_cash_history'])
            ? explode('|', (string)$row['payment_amount_cash_history'])
            : null;

        if (is_array($histArr) && count($histArr) === $nTs) {
            $cashAmounts = array_map('floatval', $histArr);
            if ($nTs >= 2 && $totalCash > 0) {
                $sum = array_sum($cashAmounts);
                if ($sum > $totalCash + 0.02) {
                    $lastIdx  = $nTs - 1;
                    $sumPrior = array_sum(array_slice($cashAmounts, 0, $lastIdx));
                    if ($cashAmounts[$lastIdx] >= $totalCash - 0.02) {
                        $cashAmounts[$lastIdx] = max(0, $totalCash - $sumPrior);
                    }
                }
            }
        } elseif (is_array($histArr) && $nTs === count($histArr) + 1) {
            $sumPrior    = array_sum(array_map('floatval', $histArr));
            $lastAmt     = max(0, $totalCash - $sumPrior);
            $cashAmounts = array_merge(array_map('floatval', $histArr), [$lastAmt]);
        } elseif ($totalCash > 0) {
            $cashAmounts = array_fill(0, $nTs, 0);
            $cashAmounts[0] = $totalCash;
        } else {
            continue;
        }

        foreach ($timestamps as $idx => $ts) {
            $amt = floatval($cashAmounts[$idx] ?? 0);
            if ($amt <= 0.005) continue;
            try {
                $dt = new DateTime($ts);
                if ($dt->format('Y') < 2020) {
                    if (!empty($row['confirmed_at']) && $row['confirmed_at'] !== '0000-00-00 00:00:00') {
                        $dt = new DateTime($row['confirmed_at']);
                    } elseif (!empty($row['created_at']) && $row['created_at'] !== '0000-00-00 00:00:00') {
                        $dt = new DateTime($row['created_at']);
                    }
                }

                // Only count cash from Jan 1 8AM of this year up to (not past) current shift end
                if ($dt < $pendingFrom || $dt >= $shiftEnd) {
                    continue;
                }

                $alreadyDeposited = false;
                foreach ($allDepositedRanges as $range) {
                    if ($dt >= $range['start'] && $dt < $range['end'] && $dt <= $range['created_at']) {
                        $alreadyDeposited = true;
                        break;
                    }
                }

                if (!$alreadyDeposited) {
                    $totalPendingDeposit += $amt;
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    $totalPendingDeposit = round($totalPendingDeposit, 0);

    echo json_encode([
        'success'                  => true,
        'shift_date'               => $shiftDateStr,
        'shift_start'              => $shiftStartStr,
        'shift_end'                => $shiftEndStr,
        'running_cash'             => $runningCash,
        'running_tx_count'         => $runningTxCount,
        'cash_total'               => $cashTotalRounded,
        'transaction_count'        => $txCount,
        'transactions'             => $transactions,
        'deposit_recorded'         => $existingDeposit ? true : false,
        'deposit_id'               => $existingDeposit ? (int)$existingDeposit['id'] : null,
        'deposited_cash'           => $depositedCash,
        'yesterday_shift_start'    => $yesterdayShiftStart->format('Y-m-d H:i:s'),
        'yesterday_shift_end'      => $yesterdayShiftEnd->format('Y-m-d H:i:s'),
        'yesterday_running_cash'   => $yesterdayRunningCash,
        'yesterday_deposited_cash' => $yesterdayDepositedCash,
        'pending_deposit'          => $totalPendingDeposit,
        'pending_from'             => $pendingFrom->format('Y-m-d H:i:s'),
        'pending_from_label'       => $pendingFromStr,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'cash_total' => 0]);
}
