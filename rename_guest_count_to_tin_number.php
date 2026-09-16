<?php
require_once 'config.php';

try {
    // Check if the bookings table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkTable->rowCount() == 0) {
        echo "Bookings table does not exist.\n";
        exit;
    }
    
    // Check if guest_count column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'guest_count'");
    if ($checkColumn->rowCount() > 0) {
        // Rename guest_count to tin_number
        $conn->exec("ALTER TABLE bookings CHANGE COLUMN guest_count tin_number VARCHAR(50) NULL DEFAULT NULL");
        echo "Successfully renamed 'guest_count' column to 'tin_number' in bookings table.\n";
    } else {
        // Check if tin_number already exists
        $checkTinNumber = $conn->query("SHOW COLUMNS FROM bookings LIKE 'tin_number'");
        if ($checkTinNumber->rowCount() > 0) {
            echo "'tin_number' column already exists in bookings table.\n";
        } else {
            echo "'guest_count' column does not exist in bookings table.\n";
        }
    }
    
    // Also check and update reports table if it has guest_count
    $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkReportsTable->rowCount() > 0) {
        $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'guest_count'");
        if ($checkReportsColumn->rowCount() > 0) {
            $conn->exec("ALTER TABLE reports CHANGE COLUMN guest_count tin_number VARCHAR(50) NULL DEFAULT NULL");
            echo "Successfully renamed 'guest_count' column to 'tin_number' in reports table.\n";
        } else {
            $checkReportsTinNumber = $conn->query("SHOW COLUMNS FROM reports LIKE 'tin_number'");
            if ($checkReportsTinNumber->rowCount() > 0) {
                echo "'tin_number' column already exists in reports table.\n";
            }
        }
    }
    
    echo "\nColumn rename completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
