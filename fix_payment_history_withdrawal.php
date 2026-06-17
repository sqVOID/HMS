<?php
/**
 * Fix payment history by removing the last payment (extension) after withdrawal
 */

require_once 'config.php';

$booking_id = 'B-05/18/26-2909';

echo "=== FIXING PAYMENT HISTORY AFTER EXTENSION WITHDRAWAL ===\n\n";
echo "Booking ID: $booking_id\n\n";

try {
    // Get current values from bookings table
    $stmt = $conn->prepare("
        SELECT 
            payment_date_time,
            payment_amount_cash_history,
            payment_amount_g_cash_history,
            payment_amount_maya_history,
            payment_amount_instapay_history,
            payment_amount_online_banking_history,
            payment_amount_airbnb_history,
            extension_withdraw
        FROM bookings
        WHERE booking_id = :booking_id
    ");
    $stmt->execute([':booking_id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "ERROR: Booking not found!\n";
        exit(1);
    }
    
    if ($booking['extension_withdraw'] != 1) {
        echo "NOTE: Extension was not withdrawn, no fix needed.\n";
        exit(0);
    }
    
    echo "--- BEFORE FIX ---\n";
    echo "payment_date_time: " . $booking['payment_date_time'] . "\n";
    echo "payment_amount_cash_history: " . $booking['payment_amount_cash_history'] . "\n\n";
    
    // Remove last payment from each history column
    $removeLastSegment = function($historyString) {
        if (empty($historyString)) {
            return null;
        }
        $segments = explode('|', $historyString);
        if (count($segments) <= 1) {
            return null; // Only one payment, removing it leaves nothing
        }
        array_pop($segments); // Remove last segment
        return implode('|', $segments);
    };
    
    $newPaymentDateTime = $removeLastSegment($booking['payment_date_time']);
    $newCashHistory = $removeLastSegment($booking['payment_amount_cash_history']);
    $newGCashHistory = $removeLastSegment($booking['payment_amount_g_cash_history']);
    $newMayaHistory = $removeLastSegment($booking['payment_amount_maya_history']);
    $newInstapayHistory = $removeLastSegment($booking['payment_amount_instapay_history']);
    $newOnlineBankingHistory = $removeLastSegment($booking['payment_amount_online_banking_history']);
    $newAirbnbHistory = $removeLastSegment($booking['payment_amount_airbnb_history']);
    
    echo "--- AFTER FIX (NEW VALUES) ---\n";
    echo "payment_date_time: " . ($newPaymentDateTime ?? 'NULL') . "\n";
    echo "payment_amount_cash_history: " . ($newCashHistory ?? 'NULL') . "\n\n";
    
    // Update bookings table
    $updateBookingStmt = $conn->prepare("
        UPDATE bookings
        SET payment_date_time = :payment_date_time,
            payment_amount_cash_history = :cash_hist,
            payment_amount_g_cash_history = :gcash_hist,
            payment_amount_maya_history = :maya_hist,
            payment_amount_instapay_history = :instapay_hist,
            payment_amount_online_banking_history = :online_banking_hist,
            payment_amount_airbnb_history = :airbnb_hist
        WHERE booking_id = :booking_id
    ");
    
    $updateBookingStmt->execute([
        ':payment_date_time' => $newPaymentDateTime,
        ':cash_hist' => $newCashHistory,
        ':gcash_hist' => $newGCashHistory,
        ':maya_hist' => $newMayaHistory,
        ':instapay_hist' => $newInstapayHistory,
        ':online_banking_hist' => $newOnlineBankingHistory,
        ':airbnb_hist' => $newAirbnbHistory,
        ':booking_id' => $booking_id
    ]);
    
    echo "✓ Bookings table updated\n";
    
    // Update reports table if exists
    $reportCheckStmt = $conn->prepare("SELECT booking_id FROM reports WHERE booking_id = :booking_id");
    $reportCheckStmt->execute([':booking_id' => $booking_id]);
    
    if ($reportCheckStmt->rowCount() > 0) {
        $updateReportStmt = $conn->prepare("
            UPDATE reports
            SET payment_date_time = :payment_date_time,
                payment_amount_cash_history = :cash_hist,
                payment_amount_g_cash_history = :gcash_hist,
                payment_amount_maya_history = :maya_hist,
                payment_amount_instapay_history = :instapay_hist,
                payment_amount_online_banking_history = :online_banking_hist,
                payment_amount_airbnb_history = :airbnb_hist
            WHERE booking_id = :booking_id
        ");
        
        $updateReportStmt->execute([
            ':payment_date_time' => $newPaymentDateTime,
            ':cash_hist' => $newCashHistory,
            ':gcash_hist' => $newGCashHistory,
            ':maya_hist' => $newMayaHistory,
            ':instapay_hist' => $newInstapayHistory,
            ':online_banking_hist' => $newOnlineBankingHistory,
            ':airbnb_hist' => $newAirbnbHistory,
            ':booking_id' => $booking_id
        ]);
        
        echo "✓ Reports table updated\n";
    }
    
    echo "\n=== FIX COMPLETE ===\n";
    echo "The last payment (extension) has been removed from payment history.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
