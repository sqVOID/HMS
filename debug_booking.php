<?php
require_once 'config.php';
require_once 'payment_amount_calculator.php';
header('Content-Type: text/plain');

$stmt = $conn->query("SELECT booking_id, guest_name, booking_type, deposit, deposit_cash, deposit_g_cash, deposit_maya,
    downpayment_amount, downpayment_cash, downpayment_gcash, downpayment_maya,
    downpayment_date, room_price, extend_price, extend_regular_rate, extend_bundle_rate,
    total_amount, paid_status, payment_date_time, payment_amount_cash_history,
    payment_amount_g_cash_history, booking_type
FROM bookings WHERE booking_id = 'B-05/20/26-2940'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== RAW BOOKING DATA: B-05/20/26-2940 ===\n";
foreach ($row as $k => $v) {
    echo "  $k: $v\n";
}

$today = date('Y-m-d');
echo "\n=== calculatePaymentAmountInDateRange (today=$today) ===\n";
$amount = calculatePaymentAmountInDateRange($row, $today, $today);
echo "Result: $amount (expected: 1160)\n";

echo "\n=== getNetPaidAmountForExport ===\n";
echo "Result: " . getNetPaidAmountForExport($row) . "\n";

echo "\n=== getReservationFullCollectedAmount ===\n";
echo "Result: " . getReservationFullCollectedAmount($row) . "\n";

echo "\n=== Manual trace ===\n";
$dep = floatval($row['deposit_cash']);
$down = floatval($row['downpayment_cash']);
$history = $row['payment_amount_cash_history'];
echo "deposit_cash: $dep\n";
echo "downpayment_cash: $down\n";
echo "payment_amount_cash_history: $history\n";
$histArr = explode('|', $history);
echo "history sum: " . array_sum(array_map('floatval', $histArr)) . "\n";
echo "deposit_cash (totalMethod): $dep\n";
