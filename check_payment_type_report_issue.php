<?php
require_once 'config.php';

// Check for bookings with withdrawn extensions and their payment history
$stmt = $conn->prepare("
    SELECT 
        booking_id,
        guest_name,
        room_id,
        extension_withdraw,
        withdrawn_extend_price,
        payment_date_time,
        payment_amount_cash_history,
        payment_amount_g_cash_history,
        deposit_cash,
        deposit_g_cash,
        extend_price
    FROM reports
    WHERE extension_withdraw = 1
    ORDER BY booking_id DESC
    LIMIT 5
");

$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Bookings with Withdrawn Extensions</h2>";
echo "<pre>";

foreach ($bookings as $booking) {
    echo "\n========================================\n";
    echo "Booking ID: " . $booking['booking_id'] . "\n";
    echo "Guest: " . $booking['guest_name'] . "\n";
    echo "Room: " . $booking['room_id'] . "\n";
    echo "Extension Withdrawn: " . $booking['extension_withdraw'] . "\n";
    echo "Withdrawn Extension Price: ₱" . number_format($booking['withdrawn_extend_price'], 2) . "\n";
    echo "Current Extension Price: ₱" . number_format($booking['extend_price'], 2) . "\n";
    echo "\nPayment History:\n";
    echo "  payment_date_time: " . $booking['payment_date_time'] . "\n";
    echo "  payment_amount_cash_history: " . $booking['payment_amount_cash_history'] . "\n";
    echo "  payment_amount_g_cash_history: " . $booking['payment_amount_g_cash_history'] . "\n";
    echo "\nDeposit Amounts:\n";
    echo "  deposit_cash: ₱" . number_format($booking['deposit_cash'], 2) . "\n";
    echo "  deposit_g_cash: ₱" . number_format($booking['deposit_g_cash'], 2) . "\n";
    
    // Calculate what the payment type report would show
    $cashHistory = !empty($booking['payment_amount_cash_history']) 
        ? explode('|', $booking['payment_amount_cash_history']) 
        : [];
    $gcashHistory = !empty($booking['payment_amount_g_cash_history']) 
        ? explode('|', $booking['payment_amount_g_cash_history']) 
        : [];
    
    $totalCashFromHistory = array_sum(array_map('floatval', $cashHistory));
    $totalGcashFromHistory = array_sum(array_map('floatval', $gcashHistory));
    $totalFromHistory = $totalCashFromHistory + $totalGcashFromHistory;
    
    echo "\nCalculated from History:\n";
    echo "  Total Cash: ₱" . number_format($totalCashFromHistory, 2) . "\n";
    echo "  Total GCash: ₱" . number_format($totalGcashFromHistory, 2) . "\n";
    echo "  TOTAL: ₱" . number_format($totalFromHistory, 2) . "\n";
    
    echo "\nExpected Total (should NOT include withdrawn extension):\n";
    $expectedTotal = max($booking['deposit_cash'], 0) + max($booking['deposit_g_cash'], 0);
    echo "  ₱" . number_format($expectedTotal, 2) . "\n";
    
    if (abs($totalFromHistory - $expectedTotal) > 0.01) {
        echo "\n⚠️ MISMATCH! History total doesn't match expected total!\n";
        echo "  Difference: ₱" . number_format($totalFromHistory - $expectedTotal, 2) . "\n";
    } else {
        echo "\n✓ OK - History matches expected total\n";
    }
}

echo "</pre>";
?>
