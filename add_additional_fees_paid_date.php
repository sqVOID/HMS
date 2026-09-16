<?php
/**
 * Migration script to add additional_fees_paid_date column
 * This tracks when additional fees (items, food, penalties, etc.) were actually paid
 */

require_once 'config.php';

try {
    echo "Adding additional_fees_paid_date column to bookings table...\n";
    
    // Check if column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_fees_paid_date'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN additional_fees_paid_date DATETIME NULL DEFAULT NULL AFTER additional_fees_reference_no");
        echo "✓ Added additional_fees_paid_date column to bookings table\n";
    } else {
        echo "✓ additional_fees_paid_date column already exists in bookings table\n";
    }
    
    // Check if column exists in reports table
    $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'additional_fees_paid_date'");
    if ($checkReportsColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN additional_fees_paid_date DATETIME NULL DEFAULT NULL");
        echo "✓ Added additional_fees_paid_date column to reports table\n";
    } else {
        echo "✓ additional_fees_paid_date column already exists in reports table\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
