<?php
// Add cancellation_reason and refund_amount columns to bookings and reports tables

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

echo "Adding cancellation columns...\n\n";

// Check and add cancellation_reason to bookings table
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'cancellation_reason'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN cancellation_reason TEXT AFTER id_number";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added cancellation_reason column to bookings table\n";
    } else {
        echo "✗ Error adding cancellation_reason to bookings: " . $conn->error . "\n";
    }
} else {
    echo "✓ cancellation_reason column already exists in bookings table\n";
}

// Check and add refund_amount to bookings table
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'refund_amount'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cancellation_reason";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added refund_amount column to bookings table\n";
    } else {
        echo "✗ Error adding refund_amount to bookings: " . $conn->error . "\n";
    }
} else {
    echo "✓ refund_amount column already exists in bookings table\n";
}

// Check and add cancellation_reason to reports table
$result = $conn->query("SHOW COLUMNS FROM reports LIKE 'cancellation_reason'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE reports ADD COLUMN cancellation_reason TEXT AFTER id_number";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added cancellation_reason column to reports table\n";
    } else {
        echo "✗ Error adding cancellation_reason to reports: " . $conn->error . "\n";
    }
} else {
    echo "✓ cancellation_reason column already exists in reports table\n";
}

// Check and add refund_amount to reports table
$result = $conn->query("SHOW COLUMNS FROM reports LIKE 'refund_amount'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE reports ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cancellation_reason";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added refund_amount column to reports table\n";
    } else {
        echo "✗ Error adding refund_amount to reports: " . $conn->error . "\n";
    }
} else {
    echo "✓ refund_amount column already exists in reports table\n";
}

echo "\nDone!\n";

$conn->close();
?>
