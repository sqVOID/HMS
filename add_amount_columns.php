<?php
// Add amount_due and amount_paid columns to cancellation_requests table
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

// Check if columns already exist
$checkSql = "SHOW COLUMNS FROM cancellation_requests LIKE 'amount_due'";
$result = $conn->query($checkSql);

if ($result->num_rows == 0) {
    // Add amount_due column
    $alterSql1 = "ALTER TABLE cancellation_requests ADD COLUMN amount_due DECIMAL(10,2) DEFAULT 0 AFTER refund_amount";
    if ($conn->query($alterSql1) === TRUE) {
        echo "Column 'amount_due' added successfully.<br>";
    } else {
        echo "Error adding amount_due: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'amount_due' already exists.<br>";
}

// Check if amount_paid column exists
$checkSql2 = "SHOW COLUMNS FROM cancellation_requests LIKE 'amount_paid'";
$result2 = $conn->query($checkSql2);

if ($result2->num_rows == 0) {
    // Add amount_paid column
    $alterSql2 = "ALTER TABLE cancellation_requests ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0 AFTER amount_due";
    if ($conn->query($alterSql2) === TRUE) {
        echo "Column 'amount_paid' added successfully.<br>";
    } else {
        echo "Error adding amount_paid: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'amount_paid' already exists.<br>";
}

$conn->close();
echo "<br>Done! You can now submit cancellation requests with amount_due and amount_paid.";
?>
