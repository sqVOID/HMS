<?php
require_once 'config.php';

echo "Checking most recent extension...\n\n";

$stmt = $conn->query("
    SELECT id, booking_id, guest_name, 
           extend_hours, extend_minutes, extend_price,
           extend_bundle_rate, extend_bundle_breakfast, extend_bundle_breakfast_date,
           extension_time_at
    FROM bookings 
    WHERE extend_price > 0 
    ORDER BY id DESC 
    LIMIT 1
");

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if ($booking) {
    echo "Booking ID: {$booking['booking_id']}\n";
    echo "Guest: {$booking['guest_name']}\n";
    echo "Extended Hours: {$booking['extend_hours']}\n";
    echo "Extended Minutes: {$booking['extend_minutes']}\n";
    echo "Extension Price: {$booking['extend_price']}\n";
    echo "Bundle Rate: {$booking['extend_bundle_rate']}\n";
    echo "Bundle Breakfast: " . ($booking['extend_bundle_breakfast'] ?? 'NULL') . "\n";
    echo "Bundle Breakfast Date: " . ($booking['extend_bundle_breakfast_date'] ?? 'NULL') . "\n";
    echo "Extension Time: " . ($booking['extension_time_at'] ?? 'NULL') . "\n";
} else {
    echo "No extensions found\n";
}
?>
