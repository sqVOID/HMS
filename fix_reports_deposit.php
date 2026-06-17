<?php
/**
 * Fix reports table deposit values to match bookings table after extension withdrawal
 */

require_once 'config.php';

$booking_id = 'B-05/18/26-2909';

echo "=== FIXING REPORTS TABLE DEPOSIT VALUES ===\n\n";
echo "Booking ID: $booking_id\n\n";

try {
    // Get current values from bookings table
    $bookingStmt = $conn->prepare("
        SELECT 
            deposit,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            change_amount,
            extension_withdraw,
            refund_amount_extension
        FROM bookings
        WHERE booking_id = :booking_id
    ");
    $bookingStmt->execute([':booking_id' => $booking_id]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "ERROR: Booking not found in bookings table!\n";
        exit(1);
    }
    
    echo "--- BOOKINGS TABLE (SOURCE) ---\n";
    echo "deposit: " . $booking['deposit'] . "\n";
    echo "deposit_cash: " . $booking['deposit_cash'] . "\n";
    echo "deposit_g_cash: " . $booking['deposit_g_cash'] . "\n";
    echo "deposit_maya: " . $booking['deposit_maya'] . "\n";
    echo "change_amount: " . $booking['change_amount'] . "\n";
    echo "extension_withdraw: " . $booking['extension_withdraw'] . "\n";
    echo "refund_amount_extension: " . $booking['refund_amount_extension'] . "\n";
    echo "\n";
    
    // Get current values from reports table
    $reportStmt = $conn->prepare("
        SELECT 
            deposit,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            change_amount
        FROM reports
        WHERE booking_id = :booking_id
    ");
    $reportStmt->execute([':booking_id' => $booking_id]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        echo "NOTE: Booking not found in reports table (not checked out yet)\n";
        exit(0);
    }
    
    echo "--- REPORTS TABLE (BEFORE FIX) ---\n";
    echo "deposit: " . $report['deposit'] . "\n";
    echo "deposit_cash: " . $report['deposit_cash'] . "\n";
    echo "deposit_g_cash: " . $report['deposit_g_cash'] . "\n";
    echo "deposit_maya: " . $report['deposit_maya'] . "\n";
    echo "change_amount: " . $report['change_amount'] . "\n";
    echo "\n";
    
    // Update reports table to match bookings table
    $updateStmt = $conn->prepare("
        UPDATE reports
        SET deposit = :deposit,
            deposit_cash = :deposit_cash,
            deposit_g_cash = :deposit_g_cash,
            deposit_maya = :deposit_maya,
            change_amount = :change_amount
        WHERE booking_id = :booking_id
    ");
    
    $updateStmt->execute([
        ':deposit' => $booking['deposit'],
        ':deposit_cash' => $booking['deposit_cash'],
        ':deposit_g_cash' => $booking['deposit_g_cash'],
        ':deposit_maya' => $booking['deposit_maya'],
        ':change_amount' => $booking['change_amount'],
        ':booking_id' => $booking_id
    ]);
    
    echo "✓ Reports table updated successfully!\n\n";
    
    // Verify the update
    $verifyStmt = $conn->prepare("
        SELECT 
            deposit,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            change_amount
        FROM reports
        WHERE booking_id = :booking_id
    ");
    $verifyStmt->execute([':booking_id' => $booking_id]);
    $verifiedReport = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "--- REPORTS TABLE (AFTER FIX) ---\n";
    echo "deposit: " . $verifiedReport['deposit'] . "\n";
    echo "deposit_cash: " . $verifiedReport['deposit_cash'] . "\n";
    echo "deposit_g_cash: " . $verifiedReport['deposit_g_cash'] . "\n";
    echo "deposit_maya: " . $verifiedReport['deposit_maya'] . "\n";
    echo "change_amount: " . $verifiedReport['change_amount'] . "\n";
    echo "\n";
    
    echo "=== FIX COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
