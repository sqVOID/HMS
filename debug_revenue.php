<?php
/**
 * Debug script to check booking data and payment calculations
 */

require_once 'config.php';
require_once 'report_helpers.php';
require_once 'payment_amount_calculator.php';

echo "Debug Revenue Calculations\n";
echo "==========================\n\n";

try {
    // Get the booking data directly from the database
    $query = "
        SELECT 
            booking_id,
            guest_name,
            payment_date_time,
            total_amount,
            extend_price,
            room_price,
            penalty_amount,
            missing_items_fees,
            payment_status_cash,
            payment_status_g_cash,
            payment_status_maya,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            downpayment_amount,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya
        FROM reports
        WHERE status IN ('Checked Out', 'Confirmed')
          AND payment_date_time IS NOT NULL
        ORDER BY booking_id DESC
        LIMIT 5
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($bookings) . " bookings with payment data:\n\n";

    foreach ($bookings as $booking) {
        echo "Booking ID: " . $booking['booking_id'] . "\n";
        echo "Guest: " . $booking['guest_name'] . "\n";
        echo "Payment Date Time: " . $booking['payment_date_time'] . "\n";
        echo "Total Amount: " . $booking['total_amount'] . "\n";
        echo "Extend Price: " . $booking['extend_price'] . "\n";
        echo "Room Price: " . $booking['room_price'] . "\n";
        echo "Penalty Amount: " . $booking['penalty_amount'] . "\n";
        echo "Missing Items Fees: " . $booking['missing_items_fees'] . "\n";
        
        echo "\nPayment Status:\n";
        echo "- Cash: " . $booking['payment_status_cash'] . "\n";
        echo "- GCash: " . $booking['payment_status_g_cash'] . "\n";
        echo "- Maya: " . $booking['payment_status_maya'] . "\n";
        
        echo "\nDeposit Breakdown:\n";
        echo "- Cash: " . $booking['deposit_cash'] . "\n";
        echo "- GCash: " . $booking['deposit_g_cash'] . "\n";
        echo "- Maya: " . $booking['deposit_maya'] . "\n";
        
        echo "\nDownpayment:\n";
        echo "- Amount: " . $booking['downpayment_amount'] . "\n";
        echo "- Cash: " . $booking['downpayment_cash'] . "\n";
        echo "- GCash: " . $booking['downpayment_gcash'] . "\n";
        echo "- Maya: " . $booking['downpayment_maya'] . "\n";
        
        // Test payment calculation for different date ranges
        echo "\nPayment Calculations:\n";
        
        // Test for 20/04/2026
        $calc1 = formatPaymentAmountsForExport($booking, '2026-04-20', '2026-04-20');
        echo "20/04/2026 only: Amount=" . $calc1['amount'] . ", Total=" . $calc1['total_amount_booking'] . ", Overall=" . $calc1['overall_amount'] . "\n";
        
        // Test for 21/04/2026
        $calc2 = formatPaymentAmountsForExport($booking, '2026-04-21', '2026-04-21');
        echo "21/04/2026 only: Amount=" . $calc2['amount'] . ", Total=" . $calc2['total_amount_booking'] . ", Overall=" . $calc2['overall_amount'] . "\n";
        
        // Test for both dates
        $calc3 = formatPaymentAmountsForExport($booking, '2026-04-20', '2026-04-21');
        echo "Both dates: Amount=" . $calc3['amount'] . ", Total=" . $calc3['total_amount_booking'] . ", Overall=" . $calc3['overall_amount'] . "\n";
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>