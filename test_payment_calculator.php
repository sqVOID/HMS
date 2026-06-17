<?php
/**
 * Test script for payment amount calculator
 */

require_once 'payment_amount_calculator.php';

// Test data similar to your scenario
$test_booking = [
    'payment_date_time' => '2026-04-20 14:17:33|2026-04-21 13:22:46',
    'total_amount' => 3380.00,
    'extend_price' => 1690.00,
    'room_price' => 1690.00,
    'duration' => 12,
    'duration_unit' => 'hours',
    'extend_hours' => 12,
    'extend_minutes' => 0,
    'penalty_amount' => 0,
    'missing_items_fees' => 0,
    'promo' => 'None'
];

echo "Testing Payment Amount Calculator\n";
echo "================================\n\n";

// Test 1: Export for 20/04/2026 only (first payment - no extension)
echo "Test 1: Export 20/04/2026 only (First Payment - Initial Booking)\n";
$result1 = formatPaymentAmountsForExport($test_booking, '2026-04-20', '2026-04-20');
echo "Amount: " . $result1['amount'] . "\n";
echo "Total Amount Booking: " . $result1['total_amount_booking'] . "\n";
echo "Overall Amount: " . $result1['overall_amount'] . "\n";
echo "Duration: " . $result1['duration'] . "\n";
echo "Extension Duration: " . $result1['extension_duration'] . "\n\n";

// Test 2: Export for 21/04/2026 only (second payment - extension)
echo "Test 2: Export 21/04/2026 only (Second Payment - Extension)\n";
$result2 = formatPaymentAmountsForExport($test_booking, '2026-04-21', '2026-04-21');
echo "Amount: " . $result2['amount'] . "\n";
echo "Total Amount Booking: " . $result2['total_amount_booking'] . "\n";
echo "Overall Amount: " . $result2['overall_amount'] . "\n";
echo "Duration: " . $result2['duration'] . "\n";
echo "Extension Duration: " . $result2['extension_duration'] . "\n\n";

// Test 3: Export for both dates (combined)
echo "Test 3: Export 20/04/2026 to 21/04/2026 (Both Payments)\n";
$result3 = formatPaymentAmountsForExport($test_booking, '2026-04-20', '2026-04-21');
echo "Amount: " . $result3['amount'] . "\n";
echo "Total Amount Booking: " . $result3['total_amount_booking'] . "\n";
echo "Overall Amount: " . $result3['overall_amount'] . "\n";
echo "Duration: " . $result3['duration'] . "\n";
echo "Extension Duration: " . $result3['extension_duration'] . "\n\n";

echo "Expected Results:\n";
echo "- Test 1: Duration = '12 hours', Extension = '—'\n";
echo "- Test 2: Duration = '12:00 hours (Extension Only)', Extension = '12 Hr = 1690'\n";
echo "- Test 3: Duration = '24:00 hours (Extended)', Extension = '12 Hr = 1690'\n\n";

echo "Test completed!\n";
?>