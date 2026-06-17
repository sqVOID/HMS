<?php
// Add requested_by and requested_at columns to cancellation_requests table
$host = 'localhost';
$dbname = 'hotel_management';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if columns exist and add them if they don't
$checkSql = "SHOW COLUMNS FROM cancellation_requests LIKE 'requested_by'";
$result = $conn->query($checkSql);

if ($result->num_rows == 0) {
    $alterSql = "ALTER TABLE cancellation_requests 
                 ADD COLUMN requested_by VARCHAR(100) AFTER status,
                 ADD COLUMN requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER requested_by";
    
    if ($conn->query($alterSql) === TRUE) {
        echo "Columns 'requested_by' and 'requested_at' added successfully.<br>";
    } else {
        echo "Error adding columns: " . $conn->error . "<br>";
    }
} else {
    echo "Columns already exist.<br>";
}

// Update existing records to populate requested_by from guest_name if empty
$updateSql = "UPDATE cancellation_requests 
              SET requested_by = guest_name 
              WHERE requested_by IS NULL OR requested_by = ''";

if ($conn->query($updateSql) === TRUE) {
    echo "Updated " . $conn->affected_rows . " records with requested_by from guest_name.<br>";
} else {
    echo "Error updating records: " . $conn->error . "<br>";
}

$conn->close();
echo "<br>Done! You can now delete this file.";
?>
