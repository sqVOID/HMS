<?php
// Add additional_pet column to reports table to match bookings table

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hotel_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully\n";

// First, check the structure of additional_pet in bookings table
$check_bookings = "SHOW COLUMNS FROM bookings LIKE 'additional_pet'";
$result = $conn->query($check_bookings);

if ($result->num_rows > 0) {
    $column_info = $result->fetch_assoc();
    echo "Found additional_pet in bookings table:\n";
    echo "Type: " . $column_info['Type'] . "\n";
    echo "Null: " . $column_info['Null'] . "\n";
    echo "Default: " . $column_info['Default'] . "\n\n";
    
    // Now add the same column to reports table
    $add_column = "ALTER TABLE reports ADD COLUMN additional_pet " . $column_info['Type'];
    
    if ($column_info['Null'] == 'YES') {
        $add_column .= " NULL";
    } else {
        $add_column .= " NOT NULL";
    }
    
    if ($column_info['Default'] !== null) {
        $add_column .= " DEFAULT '" . $column_info['Default'] . "'";
    }
    
    echo "Executing: $add_column\n";
    
    if ($conn->query($add_column) === TRUE) {
        echo "Column additional_pet added to reports table successfully!\n";
    } else {
        if ($conn->errno == 1060) {
            echo "Column additional_pet already exists in reports table.\n";
        } else {
            echo "Error adding column: " . $conn->error . "\n";
        }
    }
} else {
    echo "Column additional_pet not found in bookings table.\n";
}

// Verify the column was added
$verify = "SHOW COLUMNS FROM reports LIKE 'additional_pet'";
$result = $conn->query($verify);

if ($result->num_rows > 0) {
    $column_info = $result->fetch_assoc();
    echo "\nVerification - additional_pet in reports table:\n";
    echo "Type: " . $column_info['Type'] . "\n";
    echo "Null: " . $column_info['Null'] . "\n";
    echo "Default: " . $column_info['Default'] . "\n";
}

$conn->close();
echo "\nDone!\n";
?>
