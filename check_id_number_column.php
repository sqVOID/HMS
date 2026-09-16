<?php
/**
 * Diagnostic script to check if id_number column exists in both tables
 */

require_once 'config.php';

echo "<h2>ID Number Column Status</h2>";

try {
    // Check bookings table
    echo "<h3>Bookings Table:</h3>";
    $checkBookings = $conn->query("SHOW COLUMNS FROM bookings LIKE 'id_number'");
    if ($checkBookings->rowCount() > 0) {
        $column = $checkBookings->fetch(PDO::FETCH_ASSOC);
        echo "✓ id_number column EXISTS<br>";
        echo "Type: " . $column['Type'] . "<br>";
        echo "Null: " . $column['Null'] . "<br>";
        echo "Default: " . ($column['Default'] ?? 'NULL') . "<br>";
    } else {
        echo "✗ id_number column DOES NOT EXIST<br>";
        echo "<strong>Action needed:</strong> Run add_id_number_column.php<br>";
    }
    
    echo "<br>";
    
    // Check reports table
    echo "<h3>Reports Table:</h3>";
    $checkReports = $conn->query("SHOW COLUMNS FROM reports LIKE 'id_number'");
    if ($checkReports->rowCount() > 0) {
        $column = $checkReports->fetch(PDO::FETCH_ASSOC);
        echo "✓ id_number column EXISTS<br>";
        echo "Type: " . $column['Type'] . "<br>";
        echo "Null: " . $column['Null'] . "<br>";
        echo "Default: " . ($column['Default'] ?? 'NULL') . "<br>";
    } else {
        echo "✗ id_number column DOES NOT EXIST<br>";
        echo "<strong>Action needed:</strong> Run add_id_number_column.php<br>";
    }
    
    echo "<br><hr><br>";
    
    // Check if there's any data
    echo "<h3>Sample Data:</h3>";
    $sampleBookings = $conn->query("SELECT booking_id, id_number FROM bookings WHERE id_number IS NOT NULL AND id_number != '' LIMIT 5");
    $bookingsWithId = $sampleBookings->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookingsWithId) > 0) {
        echo "<strong>Bookings with ID numbers:</strong><br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Booking ID</th><th>ID Number</th></tr>";
        foreach ($bookingsWithId as $row) {
            echo "<tr><td>" . htmlspecialchars($row['booking_id']) . "</td><td>" . htmlspecialchars($row['id_number']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "No bookings with ID numbers found yet.<br>";
    }
    
    echo "<br>";
    
    $sampleReports = $conn->query("SELECT booking_id, id_number FROM reports WHERE id_number IS NOT NULL AND id_number != '' LIMIT 5");
    $reportsWithId = $sampleReports->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($reportsWithId) > 0) {
        echo "<strong>Reports with ID numbers:</strong><br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Booking ID</th><th>ID Number</th></tr>";
        foreach ($reportsWithId as $row) {
            echo "<tr><td>" . htmlspecialchars($row['booking_id']) . "</td><td>" . htmlspecialchars($row['id_number']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "No reports with ID numbers found yet.<br>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
