<?php
/**
 * Check payment history columns to see what needs to be fixed
 */

require_once 'config.php';

$booking_id = 'B-05/18/26-2909';

echo "=== CHECKING PAYMENT HISTORY COLUMNS ===\n\n";
echo "Booking ID: $booking_id\n\n";

try {
    // Check bookings table
    $bookingStmt = $conn->prepare("
        SELECT 
            payment_date_time,
            payment_amount_cash_history,
            payment_amount_g_cash_history,
            payment_amount_maya_history,
            payment_amount_instapay_history,
            payment_amount_online_banking_history,
            payment_amount_airbnb_history,
            deposit_cash,
            extension_withdraw,
            refund_amount_extension
        FROM bookings
        WHERE booking_id = :booking_id
    ");
    $bookingStmt->execute([':booking_id' => $booking_id]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "ERROR: Booking not found!\n";
        exit(1);
    }
    
    echo "--- BOOKINGS TABLE ---\n";
    echo "payment_date_time: " . ($booking['payment_date_time'] ?? 'NULL') . "\n";
    echo "payment_amount_cash_history: " . ($booking['payment_amount_cash_history'] ?? 'NULL') . "\n";
    echo "payment_amount_g_cash_history: " . ($booking['payment_amount_g_cash_history'] ?? 'NULL') . "\n";
    echo "payment_amount_maya_history: " . ($booking['payment_amount_maya_history'] ?? 'NULL') . "\n";
    echo "payment_amount_instapay_history: " . ($booking['payment_amount_instapay_history'] ?? 'NULL') . "\n";
    echo "payment_amount_online_banking_history: " . ($booking['payment_amount_online_banking_history'] ?? 'NULL') . "\n";
    echo "payment_amount_airbnb_history: " . ($booking['payment_amount_airbnb_history'] ?? 'NULL') . "\n";
    echo "deposit_cash: " . $booking['deposit_cash'] . "\n";
    echo "extension_withdraw: " . $booking['extension_withdraw'] . "\n";
    echo "refund_amount_extension: " . $booking['refund_amount_extension'] . "\n";
    echo "\n";
    
    // Check reports table
    $reportStmt = $conn->prepare("
        SELECT 
            payment_date_time,
            payment_amount_cash_history,
            payment_amount_g_cash_history,
            payment_amount_maya_history,
            payment_amount_instapay_history,
            payment_amount_online_banking_history,
            payment_amount_airbnb_history,
            deposit_cash,
            extension_withdraw,
            refund_amount_extension
        FROM reports
        WHERE booking_id = :booking_id
    ");
    $reportStmt->execute([':booking_id' => $booking_id]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        echo "--- REPORTS TABLE ---\n";
        echo "payment_date_time: " . ($report['payment_date_time'] ?? 'NULL') . "\n";
        echo "payment_amount_cash_history: " . ($report['payment_amount_cash_history'] ?? 'NULL') . "\n";
        echo "payment_amount_g_cash_history: " . ($report['payment_amount_g_cash_history'] ?? 'NULL') . "\n";
        echo "payment_amount_maya_history: " . ($report['payment_amount_maya_history'] ?? 'NULL') . "\n";
        echo "payment_amount_instapay_history: " . ($report['payment_amount_instapay_history'] ?? 'NULL') . "\n";
        echo "payment_amount_online_banking_history: " . ($report['payment_amount_online_banking_history'] ?? 'NULL') . "\n";
        echo "payment_amount_airbnb_history: " . ($report['payment_amount_airbnb_history'] ?? 'NULL') . "\n";
        echo "deposit_cash: " . $report['deposit_cash'] . "\n";
        echo "extension_withdraw: " . $report['extension_withdraw'] . "\n";
        echo "refund_amount_extension: " . $report['refund_amount_extension'] . "\n";
        echo "\n";
    } else {
        echo "--- REPORTS TABLE ---\n";
        echo "Not found (booking not checked out yet)\n\n";
    }
    
    // Analyze the history
    if (!empty($booking['payment_amount_cash_history'])) {
        $cashHistory = explode('|', $booking['payment_amount_cash_history']);
        echo "--- CASH HISTORY ANALYSIS ---\n";
        echo "Number of payments: " . count($cashHistory) . "\n";
        foreach ($cashHistory as $idx => $amount) {
            echo "Payment " . ($idx + 1) . ": ₱" . $amount . "\n";
        }
        echo "\n";
        
        if ($booking['extension_withdraw'] == 1) {
            echo "ISSUE: Extension was withdrawn, but payment history still has " . count($cashHistory) . " payments\n";
            echo "The last payment (₱" . end($cashHistory) . ") should be removed\n";
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
