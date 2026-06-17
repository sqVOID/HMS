<?php
require_once 'config.php';
header('Content-Type: application/json');

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

$shiftStartStr = $shiftStart->format('Y-m-d H:i:s');
$shiftEndStr   = $shiftEnd->format('Y-m-d H:i:s');
$shiftDateStr  = $shiftStart->format('Y-m-d');

try {
    // Ensure cash history column exists
    try {
        $chk = $conn->query("SHOW COLUMNS FROM reports LIKE 'payment_amount_cash_history'");
        if ($chk && $chk->rowCount() == 0) {
            $conn->exec("ALTER TABLE reports ADD COLUMN payment_amount_cash_history TEXT NULL DEFAULT NULL");
        }
    } catch (PDOException $e) {}

    // Check if there's already a deposit recorded for the current shift
    $depositStmt = $conn->prepare("
        SELECT id, cash_deposited, shift_start, shift_end, created_at 
        FROM cash_deposits 
        WHERE shift_date = :shift_date 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $depositStmt->execute([':shift_date' => $shiftDateStr]);
    $existingDeposit = $depositStmt->fetch(PDO::FETCH_ASSOC);

    // If deposit exists, we'll only count transactions AFTER the deposit was created
    $depositCreatedAt = null;
    if ($existingDeposit) {
        $depositCreatedAt = new DateTime($existingDeposit['created_at']);
    }

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
            r.paid_status
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

    $cashTotal    = 0.0;
    $txCount      = 0;
    $transactions = [];

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
                if ($ts !== '') $timestamps[] = $ts;
            }
        }
        if (empty($timestamps) && !empty($row['downpayment_date'])) {
            $dp = trim((string)$row['downpayment_date']);
            if ($dp !== '') $timestamps[] = $dp;
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
                if ($dt >= $shiftStart && $dt < $shiftEnd) {
                    // If deposit exists, only count transactions AFTER the deposit was created
                    if ($depositCreatedAt && $dt <= $depositCreatedAt) {
                        continue; // Skip transactions before/at deposit time
                    }
                    
                    $cashTotal += $amt;
                    $txCount++;
                    $transactions[] = [
                        'booking_id' => $row['booking_id'],
                        'timestamp'  => $dt->format('m/d/Y h:i A'),
                        'amount'     => round($amt, 2),
                    ];
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    echo json_encode([
        'success'           => true,
        'shift_date'        => $shiftDateStr,
        'shift_start'       => $shiftStartStr,
        'shift_end'         => $shiftEndStr,
        'cash_total'        => round($cashTotal, 2),
        'transaction_count' => $txCount,
        'transactions'      => $transactions,
        'deposit_recorded'  => $existingDeposit ? true : false,
        'deposit_id'        => $existingDeposit ? (int)$existingDeposit['id'] : null,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'cash_total' => 0]);
}
