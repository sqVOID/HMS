<?php
require_once 'config.php';

echo "CHECKING BREAKFAST_DATE IN BOTH TABLES\n";
echo "=======================================\n\n";

// Get from bookings
$stmt1 = $conn->query("SELECT booking_id, guest_name, breakfast_date FROM bookings WHERE breakfast_date IS NOT NULL ORDER BY id DESC LIMIT 1");
$booking = $stmt1->fetch(PDO::FETCH_ASSOC);

if ($booking) {
    echo "BOOKINGS TABLE:\n";
    echo "  Booking ID: {$booking['booking_id']}\n";
    echo "  Guest: {$booking['guest_name']}\n";
    echo "  breakfast_date: {$booking['breakfast_date']}\n\n";
    
    // Get from reports using the same booking_id
    $stmt2 = $conn->prepare("SELECT booking_id, guest_name, breakfast_date FROM reports WHERE booking_id = :bid");
    $stmt2->execute([':bid' => $booking['booking_id']]);
    $report = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        echo "REPORTS TABLE:\n";
        echo "  Booking ID: {$report['booking_id']}\n";
        echo "  Guest: {$report['guest_name']}\n";
        echo "  breakfast_date: " . ($report['breakfast_date'] ?? 'NULL') . "\n\n";
        
        if ($booking['breakfast_date'] === $report['breakfast_date']) {
            echo "✓✓✓ SUCCESS! ✓✓✓\n";
            echo "breakfast_date matches in both tables!\n";
        } else {
            echo "✗ MISMATCH!\n";
            echo "bookings: {$booking['breakfast_date']}\n";
            echo "reports: " . ($report['breakfast_date'] ?? 'NULL') . "\n";
        }
    } else {
        echo "✗ No matching report found\n";
    }
} else {
    echo "No bookings with breakfast_date found\n";
}
?>
