<?php
/**
 * Test script for revenue calculations with payment-based logic
 */

require_once 'config.php';
require_once 'report_helpers.php';

echo "Testing Revenue Calculations with Payment-Based Logic\n";
echo "===================================================\n\n";

// Test the fetchCheckoutRevenueData function with different date ranges
try {
    // Test 1: Revenue for 20/04/2026 only (first payment)
    echo "Test 1: Revenue for 20/04/2026 only\n";
    $revenue1 = fetchCheckoutRevenueData($conn, '2026-04-20', '2026-04-20', true);
    echo "Total Revenue: ₱" . number_format($revenue1['total'], 2) . "\n";
    echo "Number of Records: " . count($revenue1['records']) . "\n";
    if (!empty($revenue1['records'])) {
        foreach ($revenue1['records'] as $record) {
            echo "- Booking {$record['booking_id']}: ₱" . number_format($record['total_amount'], 2) . "\n";
        }
    }
    echo "\n";

    // Test 2: Revenue for 21/04/2026 only (extension payment)
    echo "Test 2: Revenue for 21/04/2026 only\n";
    $revenue2 = fetchCheckoutRevenueData($conn, '2026-04-21', '2026-04-21', true);
    echo "Total Revenue: ₱" . number_format($revenue2['total'], 2) . "\n";
    echo "Number of Records: " . count($revenue2['records']) . "\n";
    if (!empty($revenue2['records'])) {
        foreach ($revenue2['records'] as $record) {
            echo "- Booking {$record['booking_id']}: ₱" . number_format($record['total_amount'], 2) . "\n";
        }
    }
    echo "\n";

    // Test 3: Revenue for both dates (combined)
    echo "Test 3: Revenue for 20/04/2026 to 21/04/2026\n";
    $revenue3 = fetchCheckoutRevenueData($conn, '2026-04-20', '2026-04-21', true);
    echo "Total Revenue: ₱" . number_format($revenue3['total'], 2) . "\n";
    echo "Number of Records: " . count($revenue3['records']) . "\n";
    if (!empty($revenue3['records'])) {
        foreach ($revenue3['records'] as $record) {
            echo "- Booking {$record['booking_id']}: ₱" . number_format($record['total_amount'], 2) . "\n";
        }
    }
    echo "\n";

    echo "Expected Results (based on actual payment dates in database):\n";
    echo "- Test 1 (20/04/2026): Should show ₱1,090.00 for extension payment on that date\n";
    echo "- Test 2 (21/04/2026): Should show ₱0.00 (no payments on this date)\n";
    echo "- Test 3 (20-21/04/2026): Should show ₱1,090.00 for extension payment\n";
    echo "\nNote: The first payment was on 19/04/2026 (₱1,490.00), not 20/04/2026\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Test completed!\n";
?>