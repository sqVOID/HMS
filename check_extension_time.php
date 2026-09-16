<?php
require_once 'config.php';

$stmt = $conn->query("
    SELECT booking_id, guest_name, extension_time_at, extend_bundle_breakfast 
    FROM bookings 
    WHERE extension_time_at IS NOT NULL 
    ORDER BY id DESC 
    LIMIT 1
");

$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Booking: {$row['booking_id']}\n";
echo "Guest: {$row['guest_name']}\n";
echo "Extension Times (all extensions): {$row['extension_time_at']}\n";
echo "Bundle Breakfast: " . ($row['extend_bundle_breakfast'] ?? 'NULL') . "\n";

echo "\n---\n";
echo "NOTE: extension_time_at tracks ALL extensions (with or without breakfast)\n";
echo "extend_bundle_breakfast_date only tracks extensions WITH bundle breakfast\n";
?>
