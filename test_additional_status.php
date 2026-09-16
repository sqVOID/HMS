<?php
require_once 'config.php';

// Get the latest booking with additional items
$stmt = $conn->prepare("
    SELECT id, booking_id, additional_food, additional_items, additional_paid_status, paid_status
    FROM bookings 
    WHERE (additional_food IS NOT NULL OR additional_items IS NOT NULL)
    ORDER BY id DESC 
    LIMIT 5
");
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Latest Bookings with Additional Items:</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Booking ID</th><th>Additional Food</th><th>Additional Items</th><th>Additional Paid Status</th><th>Main Paid Status</th></tr>";

foreach ($bookings as $booking) {
    echo "<tr>";
    echo "<td>" . $booking['id'] . "</td>";
    echo "<td>" . $booking['booking_id'] . "</td>";
    echo "<td>" . ($booking['additional_food'] ?? 'NULL') . "</td>";
    echo "<td>" . ($booking['additional_items'] ?? 'NULL') . "</td>";
    echo "<td><strong>" . ($booking['additional_paid_status'] ?? 'NULL') . "</strong></td>";
    echo "<td>" . ($booking['paid_status'] ?? 'NULL') . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
