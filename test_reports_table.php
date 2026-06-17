<?php
/**
 * Test script to verify reports table has correct data after extension withdrawal
 */

require_once 'config.php';
require_once 'payment_amount_calculator.php';

// Test booking ID from user's scenario
$booking_id = 'B-05/18/26-2909';

echo "=== TESTING REPORTS TABLE DATA ===\n\n";
echo "Booking ID: $booking_id\n\n";

try {
    // Fetch report data
    $stmt = $conn->prepare("
        SELECT 
            booking_id,
            room_price,
            extend_price,
            extend_regular_rate,
            extend_bundle_rate,
            extension_withdraw,
            refund_amount_extension,
            withdrawn_extend_price,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya,
            total_amount
        FROM reports
        WHERE booking_id = :booking_id
    ");
    $stmt->execute([':booking_id' => $booking_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        echo "NOTE: Booking not found in reports table (may not be checked out yet)\n";
        echo "This is normal if the booking is still active.\n";
        exit(0);
    }
    
    echo "--- REPORTS TABLE VALUES ---\n";
    echo "room_price: " . $report['room_price'] . "\n";
    echo "extend_price: " . $report['extend_price'] . "\n";
    echo "extend_regular_rate: " . $report['extend_regular_rate'] . "\n";
    echo "extend_bundle_rate: " . $report['extend_bundle_rate'] . "\n";
    echo "extension_withdraw: " . $report['extension_withdraw'] . "\n";
    echo "refund_amount_extension: " . $report['refund_amount_extension'] . "\n";
    echo "withdrawn_extend_price: " . $report['withdrawn_extend_price'] . "\n";
    echo "\n";
    
    echo "--- DEPOSIT VALUES ---\n";
    echo "deposit_cash: " . $report['deposit_cash'] . "\n";
    echo "deposit_g_cash: " . $report['deposit_g_cash'] . "\n";
    echo "deposit_maya: " . $report['deposit_maya'] . "\n";
    echo "downpayment_cash: " . $report['downpayment_cash'] . "\n";
    echo "downpayment_gcash: " . $report['downpayment_gcash'] . "\n";
    echo "downpayment_maya: " . $report['downpayment_maya'] . "\n";
    echo "total_amount: " . $report['total_amount'] . "\n";
    echo "\n";
    
    // Test getTotalExtensionChargesForBooking
    $extensionCharges = getTotalExtensionChargesForBooking($report);
    echo "--- CALCULATED VALUES ---\n";
    echo "getTotalExtensionChargesForBooking(): " . $extensionCharges . "\n";
    
    // Test getNetPaidAmountForExport
    $netPaid = getNetPaidAmountForExport($report);
    echo "getNetPaidAmountForExport(): " . $netPaid . "\n";
    echo "\n";
    
    // Expected values
    echo "--- EXPECTED VALUES ---\n";
    echo "Extension charges should be: 0 (withdrawn)\n";
    echo "Net paid amount should be: 960 (room price only)\n";
    echo "\n";
    
    // Verification
    echo "--- VERIFICATION ---\n";
    if ($extensionCharges == 0) {
        echo "✓ Extension charges CORRECT (0)\n";
    } else {
        echo "✗ Extension charges WRONG (expected 0, got $extensionCharges)\n";
    }
    
    if (abs($netPaid - 960) < 0.01) {
        echo "✓ Net paid amount CORRECT (960)\n";
    } else {
        echo "✗ Net paid amount WRONG (expected 960, got $netPaid)\n";
    }
    
    echo "\n=== TEST COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
