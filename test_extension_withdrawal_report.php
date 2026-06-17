<?php
// Test script to check extension withdrawal in reports
require_once 'config.php';

// Get the booking ID from the URL parameter
$booking_id = $_GET['booking_id'] ?? null;

if (!$booking_id) {
    die("Please provide a booking_id parameter (e.g., ?booking_id=B-05/18/26-2909)");
}

echo "<h2>Extension Withdrawal Report Test</h2>";
echo "<p>Booking ID: " . htmlspecialchars($booking_id) . "</p>";

try {
    // Fetch from bookings table
    $bookingStmt = $conn->prepare("
        SELECT 
            booking_id,
            deposit,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            downpayment_amount,
            extend_price,
            extension_withdraw,
            refund_amount_extension,
            withdrawn_extend_price,
            total_amount,
            paid_status
        FROM bookings 
        WHERE booking_id = :booking_id
    ");
    $bookingStmt->execute([':booking_id' => $booking_id]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        die("Booking not found in bookings table");
    }
    
    echo "<h3>Bookings Table Data:</h3>";
    echo "<pre>";
    print_r($booking);
    echo "</pre>";
    
    // Fetch from reports table
    $reportStmt = $conn->prepare("
        SELECT 
            booking_id,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            downpayment_amount,
            extend_price,
            extension_withdraw,
            refund_amount_extension,
            withdrawn_extend_price,
            total_amount,
            paid_status
        FROM reports 
        WHERE booking_id = :booking_id
    ");
    $reportStmt->execute([':booking_id' => $booking_id]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        echo "<h3>Reports Table Data:</h3>";
        echo "<pre>";
        print_r($report);
        echo "</pre>";
    } else {
        echo "<p>No record found in reports table (booking might not be checked out yet)</p>";
    }
    
    // Calculate what the total_amount should be
    echo "<h3>Calculated Values:</h3>";
    $totalPaid = floatval($booking['deposit_cash'] ?? 0) + 
                 floatval($booking['deposit_g_cash'] ?? 0) + 
                 floatval($booking['deposit_maya'] ?? 0) + 
                 floatval($booking['downpayment_amount'] ?? 0);
    
    echo "<p>Total Paid (deposit + downpayment): ₱" . number_format($totalPaid, 2) . "</p>";
    echo "<p>Extension Withdraw Flag: " . ($booking['extension_withdraw'] ? 'YES' : 'NO') . "</p>";
    echo "<p>Refund Amount: ₱" . number_format(floatval($booking['refund_amount_extension'] ?? 0), 2) . "</p>";
    echo "<p>Current extend_price: ₱" . number_format(floatval($booking['extend_price'] ?? 0), 2) . "</p>";
    echo "<p>Withdrawn extend_price: ₱" . number_format(floatval($booking['withdrawn_extend_price'] ?? 0), 2) . "</p>";
    echo "<p>Total Amount (from DB): ₱" . number_format(floatval($booking['total_amount'] ?? 0), 2) . "</p>";
    
    if ($report) {
        echo "<p><strong>Reports Table total_amount: ₱" . number_format(floatval($report['total_amount'] ?? 0), 2) . "</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
