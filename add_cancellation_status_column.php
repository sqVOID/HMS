<?php
// Add cancellation_status column to bookings table
$host = 'localhost';
$dbname = 'hotel_management';
$username = 'root';
$password = '';

// Create mysqli connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if column already exists
$checkSql = "SHOW COLUMNS FROM bookings LIKE 'cancellation_status'";
$result = $conn->query($checkSql);

if ($result->num_rows == 0) {
    // Add cancellation_status column
    $alterSql = "ALTER TABLE bookings ADD COLUMN cancellation_status ENUM('None', 'Pending', 'Approved', 'Rejected') DEFAULT 'None' AFTER status";
    if ($conn->query($alterSql) === TRUE) {
        echo "Column 'cancellation_status' added successfully to bookings table.<br>";
    } else {
        echo "Error adding cancellation_status: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'cancellation_status' already exists in bookings table.<br>";
}

$conn->close();
echo "<br>Done! Bookings can now have a cancellation status.";
?>
