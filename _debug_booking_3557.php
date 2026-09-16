<?php
require 'config.php';
require 'detailed_booking_report_logic.php';

$startDate = '2026-07-17';
$endDate = '2026-07-18';

function testScenario($label, $history, $timestamps, $deposit, $status) {
    $payment = [
        'payment_date_time' => implode('|', $timestamps),
        'payment_amount_cash_history' => $history,
        'deposit_cash' => $deposit,
        'downpayment_cash' => 0,
        'payment_status_cash' => $status,
        'new_deposit' => 0,
        'slipper_status' => '',
    ];
    $timestampRows = buildPaymentExportTimestampRows($payment);
    $totalCash = resolveDetailedBookingMethodTotal($payment, 'deposit_cash', 'downpayment_cash', 'cash');
    $amounts = allocatePaymentMethodAmountsByHistory($payment, $timestampRows, 'payment_amount_cash_history', 'payment_status_cash', $totalCash);
    echo "$label => " . json_encode($amounts) . " sum=" . array_sum($amounts) . "\n";
}

echo "=== ALLOCATION TESTS ===\n";
testScenario('correct history', '1265.00|1265.00', ['2026-07-17 17:33:49','2026-07-18 17:34:35'], 2530, 'Cash (₱2530.00)');
testScenario('single history entry', '1265.00', ['2026-07-17 17:33:49','2026-07-18 17:34:35'], 2530, 'Cash (₱2530.00)');
testScenario('partial history 0|1265', '0|1265.00', ['2026-07-17 17:33:49','2026-07-18 17:34:35'], 2530, 'Cash (₱2530.00)');
testScenario('cumulative first 2530|0', '2530.00|0', ['2026-07-17 17:33:49','2026-07-18 17:34:35'], 2530, 'Cash (₱2530.00)');

$result = buildDetailedBookingReportData($conn, $startDate, $endDate);
echo "\n=== BOOKING 3557 ROWS ===\n";
$bookingTotal = 0;
foreach ($result['dataRows'] as $row) {
    if (strpos($row['booking_id'], '3557') !== false) {
        echo $row['booking_id'] . ' | ' . $row['date'] . ' | amount=' . $row['amount'] . "\n";
        $bookingTotal += floatval($row['amount']);
    }
}
echo "Booking 3557 total in range: $bookingTotal (expected 2530)\n";
echo "Grand total: " . $result['grandTotal'] . "\n";
