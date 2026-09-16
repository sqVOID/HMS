<?php
// Test script to verify cancellation_status column exists and works
require_once "config.php";

try {
    // Check if cancellation_status column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'cancellation_status'");
    
    if ($checkColumn->rowCount() > 0) {
        echo "✓ cancellation_status column exists in bookings table<br>";
        
        // Get column details
        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
        echo "Column Type: " . $columnInfo['Type'] . "<br>";
        echo "Default: " . $columnInfo['Default'] . "<br><br>";
        
        // Test query to get bookings with cancellation_status
        $stmt = $conn->prepare("SELECT id, booking_id, guest_name, status, cancellation_status FROM bookings LIMIT 5");
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Sample bookings:<br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Booking ID</th><th>Guest</th><th>Status</th><th>Cancellation Status</th></tr>";
        foreach ($bookings as $booking) {
            echo "<tr>";
            echo "<td>" . $booking['id'] . "</td>";
            echo "<td>" . ($booking['booking_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($booking['guest_name'] ?? 'N/A') . "</td>";
            echo "<td>" . ($booking['status'] ?? 'N/A') . "</td>";
            echo "<td>" . ($booking['cancellation_status'] ?? 'None') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "✗ cancellation_status column does NOT exist in bookings table<br>";
        echo "Please run add_cancellation_status_column.php to create it.";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
