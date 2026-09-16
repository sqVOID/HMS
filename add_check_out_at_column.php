<?php
// Migration script to add check_out_at column to bookings table
require_once 'config.php';

try {
    // Check if column already exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'check_out_at'");
    $result = $checkColumn->fetch();
    
    if ($result) {
        echo "Column 'check_out_at' already exists in bookings table.\n";
    } else {
        // Add check_out_at column to bookings table
        $sql = "ALTER TABLE bookings 
                ADD COLUMN check_out_at DATETIME NULL 
                AFTER check_out";
        
        $conn->exec($sql);
        echo "SUCCESS: Column 'check_out_at' has been added to bookings table.\n";
        echo "This column will store the actual check-out date and time.\n";
    }
    
    $conn = null;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
