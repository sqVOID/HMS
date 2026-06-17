<?php
// Add encoder and encoder_checkout columns to bookings and reports tables

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

echo "Adding encoder columns...\n\n";

// Check and add encoder to bookings table
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'encoder'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN encoder VARCHAR(255) NULL DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added encoder column to bookings table\n";
    } else {
        echo "✗ Error adding encoder to bookings: " . $conn->error . "\n";
    }
} else {
    echo "✓ encoder column already exists in bookings table\n";
}

// Check and add encoder_checkout to bookings table
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'encoder_checkout'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN encoder_checkout VARCHAR(255) NULL DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added encoder_checkout column to bookings table\n";
    } else {
        echo "✗ Error adding encoder_checkout to bookings: " . $conn->error . "\n";
    }
} else {
    echo "✓ encoder_checkout column already exists in bookings table\n";
}

// Check and add encoder to reports table
$result = $conn->query("SHOW COLUMNS FROM reports LIKE 'encoder'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE reports ADD COLUMN encoder VARCHAR(255) NULL DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added encoder column to reports table\n";
    } else {
        echo "✗ Error adding encoder to reports: " . $conn->error . "\n";
    }
} else {
    echo "✓ encoder column already exists in reports table\n";
}

// Check and add encoder_checkout to reports table
$result = $conn->query("SHOW COLUMNS FROM reports LIKE 'encoder_checkout'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE reports ADD COLUMN encoder_checkout VARCHAR(255) NULL DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added encoder_checkout column to reports table\n";
    } else {
        echo "✗ Error adding encoder_checkout to reports: " . $conn->error . "\n";
    }
} else {
    echo "✓ encoder_checkout column already exists in reports table\n";
}

echo "\nDone!\n";

$conn->close();
?>
