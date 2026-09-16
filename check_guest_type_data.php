<?php
require_once 'config.php';

echo "<h2>Checking guest_type column in reports table</h2>";

try {
    // Check if guest_type column exists in reports table
    $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'guest_type'");
    if ($checkColumn->rowCount() > 0) {
        echo "<p style='color: green;'>✓ guest_type column EXISTS in reports table</p>";
        
        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
        echo "<p>Column details: Type = " . $columnInfo['Type'] . ", Null = " . $columnInfo['Null'] . ", Default = " . $columnInfo['Default'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ guest_type column DOES NOT EXIST in reports table</p>";
        echo "<p>Please run: add_columns_to_reports.php</p>";
        exit;
    }
    
    // Check if guest_type column exists in bookings table
    $checkBookingsColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'guest_type'");
    if ($checkBookingsColumn->rowCount() > 0) {
        echo "<p style='color: green;'>✓ guest_type column EXISTS in bookings table</p>";
    } else {
        echo "<p style='color: orange;'>⚠ guest_type column DOES NOT EXIST in bookings table (this is OK if you only added it to reports)</p>";
    }
    
    // Count total records in reports
    $totalStmt = $conn->query("SELECT COUNT(*) as total FROM reports");
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Total records in reports table: <strong>$total</strong></p>";
    
    // Count records with guest_type data
    $withDataStmt = $conn->query("SELECT COUNT(*) as count FROM reports WHERE guest_type IS NOT NULL AND guest_type != ''");
    $withData = $withDataStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Records with guest_type data: <strong>$withData</strong></p>";
    
    // Count records without guest_type data
    $withoutData = $total - $withData;
    echo "<p>Records without guest_type data: <strong>$withoutData</strong></p>";
    
    // Show sample data
    echo "<h3>Sample records (last 10):</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Booking ID</th><th>Guest Name</th><th>Guest Type</th><th>Check-In</th></tr>";
    
    $sampleStmt = $conn->query("SELECT id, booking_id, guest_name, guest_type, check_in FROM reports ORDER BY id DESC LIMIT 10");
    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($samples as $row) {
        $guestType = $row['guest_type'] ?? 'NULL';
        $color = ($guestType && $guestType != 'NULL') ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['booking_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['guest_name']) . "</td>";
        echo "<td style='color: $color; font-weight: bold;'>" . htmlspecialchars($guestType) . "</td>";
        echo "<td>" . htmlspecialchars($row['check_in']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show guest type distribution
    echo "<h3>Guest Type Distribution:</h3>";
    $distStmt = $conn->query("SELECT guest_type, COUNT(*) as count FROM reports GROUP BY guest_type ORDER BY count DESC");
    $distribution = $distStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Guest Type</th><th>Count</th></tr>";
    foreach ($distribution as $row) {
        $type = $row['guest_type'] ?? 'NULL/Empty';
        echo "<tr><td>" . htmlspecialchars($type) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
