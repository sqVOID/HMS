<?php
/**
 * Migration script to add id_number column to bookings and reports tables
 * This column stores the ID numbers for SC/PWD discount eligibility (can store multiple IDs)
 */

require_once 'config.php';

try {
    // Add id_number column to bookings table
    $checkBookingsColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'id_number'");
    if ($checkBookingsColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN id_number VARCHAR(500) NULL DEFAULT NULL AFTER discount_amount");
        echo "✓ Added id_number column to bookings table\n";
    } else {
        // Update column size if it exists but is too small
        $conn->exec("ALTER TABLE bookings MODIFY COLUMN id_number VARCHAR(500) NULL DEFAULT NULL");
        echo "✓ Updated id_number column in bookings table to VARCHAR(500)\n";
    }
    
    // Add id_number column to reports table
    $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'id_number'");
    if ($checkReportsColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN id_number VARCHAR(500) NULL DEFAULT NULL AFTER discount_amount");
        echo "✓ Added id_number column to reports table\n";
    } else {
        // Update column size if it exists but is too small
        $conn->exec("ALTER TABLE reports MODIFY COLUMN id_number VARCHAR(500) NULL DEFAULT NULL");
        echo "✓ Updated id_number column in reports table to VARCHAR(500)\n";
    }
    
    echo "\n✓ Migration completed successfully!\n";
    echo "Note: id_number can now store multiple IDs separated by commas (e.g., 'ID123, ID456, ID789')\n";
    
} catch(PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
