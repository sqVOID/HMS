<?php
/**
 * Test script to verify extension withdrawal fix
 */

require_once 'config.php';
require_once 'payment_amount_calculator.php';

// Test booking ID from user's scenario
$booking_id = 'B-05/18/26-2909';

echo "=== TESTING EXTENSION WITHDRAWAL FIX ===\n\n";
echo "Booking ID: $booking_id\n\n";

try {
    // Fetch booking data
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
        FROM bookings
        WHERE booking_id = :booking_id
    ");
    $stmt->execute([':booking_id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "ERROR: Booking not found!\n";
        exit(1);
    }
    
    echo "--- DATABASE VALUES ---\n";
    echo "room_price: " . $booking['room_price'] . "\n";
    echo "extend_price: " . $booking['extend_price'] . "\n";
    echo "extend_regular_rate: " . $booking['extend_regular_rate'] . "\n";
    echo "extend_bundle_rate: " . $booking['extend_bundle_rate'] . "\n";
    echo "extension_withdraw: " . $booking['extension_withdraw'] . "\n";
    echo "refund_amount_extension: " . $booking['refund_amount_extension'] . "\n";
    echo "withdrawn_extend_price: " . $booking['withdrawn_extend_price'] . "\n";
    echo "\n";
    
    echo "--- DEPOSIT VALUES ---\n";
    echo "deposit_cash: " . $booking['deposit_cash'] . "\n";
    echo "deposit_g_cash: " . $booking['deposit_g_cash'] . "\n";
    echo "deposit_maya: " . $booking['deposit_maya'] . "\n";
    echo "downpayment_cash: " . $booking['downpayment_cash'] . "\n";
    echo "downpayment_gcash: " . $booking['downpayment_gcash'] . "\n";
    echo "downpayment_maya: " . $booking['downpayment_maya'] . "\n";
    echo "total_amount: " . $booking['total_amount'] . "\n";
    echo "\n";
    
    // Test getTotalExtensionChargesForBooking
    $extensionCharges = getTotalExtensionChargesForBooking($booking);
    echo "--- CALCULATED VALUES ---\n";
    echo "getTotalExtensionChargesForBooking(): " . $extensionCharges . "\n";
    
    // Test getNetPaidAmountForExport
    $netPaid = getNetPaidAmountForExport($booking);
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
