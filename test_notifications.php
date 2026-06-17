<?php
// Test file to debug notification query
header('Content-Type: text/html; charset=utf-8');

session_start();

echo "<h2>Notification Debug Test</h2>";

// Database connection
$host = 'localhost';
$dbname = 'hotel_management';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>Session Info:</h3>";
echo "Access Level: " . ($_SESSION['access_level'] ?? 'not set') . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'not set') . "<br><br>";

// Check if columns exist
echo "<h3>Column Check:</h3>";
$checkColumns = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'requested_by'");
echo "requested_by exists: " . ($checkColumns && $checkColumns->num_rows > 0 ? 'YES' : 'NO') . "<br>";

$checkColumns2 = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'requested_at'");
echo "requested_at exists: " . ($checkColumns2 && $checkColumns2->num_rows > 0 ? 'YES' : 'NO') . "<br><br>";

// Test query
echo "<h3>Query Test:</h3>";
$sql = "SELECT 
            cr.id,
            cr.booking_id,
            cr.guest_name,
            cr.status,
            cr.requested_by,
            cr.requested_at,
            b.booking_id as booking_number
        FROM cancellation_requests cr
        LEFT JOIN bookings b ON cr.booking_id = b.id
        WHERE cr.status = 'Pending'
        ORDER BY cr.requested_at DESC
        LIMIT 10";

echo "SQL: <pre>" . $sql . "</pre><br>";

$result = $conn->query($sql);

if (!$result) {
    echo "Query Error: " . $conn->error . "<br>";
} else {
    echo "Rows found: " . $result->num_rows . "<br><br>";
    
    if ($result->num_rows > 0) {
        echo "<h3>Results:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Booking ID</th><th>Guest Name</th><th>Requested By</th><th>Requested At</th><th>Status</th><th>Booking Number</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['booking_id'] . "</td>";
            echo "<td>" . $row['guest_name'] . "</td>";
            echo "<td>" . ($row['requested_by'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['requested_at'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . ($row['booking_number'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No pending cancellation requests found.</p>";
    }
}

$conn->close();

echo "<br><br><a href='get_cancellation_notifications.php'>Test JSON API</a>";
?>
