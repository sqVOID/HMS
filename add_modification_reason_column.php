


<?php
// Add modification_reason column to bookings and reports tables

header('Content-Type: text/plain');

$host = 'localhost';
$dbname = 'hotel_management';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

echo "Adding modification_reason column...\n\n";

// Check and add to bookings table
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'modification_reason'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN modification_reason TEXT AFTER cancellation_reason";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added modification_reason column to bookings table\n";
    } else {
        echo "✗ Error adding to bookings: " . $conn->error . "\n";
    }
} else {
    echo "✓ modification_reason column already exists in bookings table\n";
}

// Check and add to reports table
$result = $conn->query("SHOW COLUMNS FROM reports LIKE 'modification_reason'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE reports ADD COLUMN modification_reason TEXT AFTER cancellation_reason";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added modification_reason column to reports table\n";
    } else {
        echo "✗ Error adding to reports: " . $conn->error . "\n";
    }
} else {
    echo "✓ modification_reason column already exists in reports table\n";
}

echo "\nDone!\n";

$conn->close();
?>
