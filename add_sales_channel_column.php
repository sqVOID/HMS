<?php
// Add sales_channel column to bookings table
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting migration...\n";

require_once 'config.php';

try {
    echo "Connected to database successfully.\n";
    
    // Check if column already exists
    echo "Checking if sales_channel column exists in bookings table...\n";
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'sales_channel'");
    echo "Query executed. Row count: " . $checkColumn->rowCount() . "\n";
    
    if ($checkColumn->rowCount() == 0) {
        echo "Column does not exist. Adding it now...\n";
        // Add the column
        $conn->exec("ALTER TABLE bookings ADD COLUMN sales_channel VARCHAR(50) NULL DEFAULT NULL");
        echo "Successfully added sales_channel column to bookings table.\n";
    } else {
        echo "sales_channel column already exists in bookings table.\n";
    }
    
    // Also add to reports table if it exists
    echo "Checking if reports table exists...\n";
    $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
    echo "Reports table check - Row count: " . $checkReportsTable->rowCount() . "\n";
    
    if ($checkReportsTable->rowCount() > 0) {
        echo "Reports table exists. Checking for sales_channel column...\n";
        $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'sales_channel'");
        
        if ($checkReportsColumn->rowCount() == 0) {
            echo "Column does not exist in reports. Adding it now...\n";
            $conn->exec("ALTER TABLE reports ADD COLUMN sales_channel VARCHAR(50) NULL DEFAULT NULL");
            echo "Successfully added sales_channel column to reports table.\n";
        } else {
            echo "sales_channel column already exists in reports table.\n";
        }
    } else {
        echo "Reports table does not exist.\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
