<?php
/**
 * Migration script to add date tracking for different types of additional charges
 * This allows accurate date-based filtering in reports
 */

require_once 'config.php';

try {
    echo "Adding date tracking columns for additional charges...\n\n";
    
    // Columns to add to bookings table
    $columns = [
        'additional_items_date' => "DATETIME NULL DEFAULT NULL COMMENT 'When additional items were added/paid'",
        'additional_food_date' => "DATETIME NULL DEFAULT NULL COMMENT 'When additional food was added/paid'",
        'additional_guest_date' => "DATETIME NULL DEFAULT NULL COMMENT 'When additional guests were added/paid'",
        'additional_pet_date' => "DATETIME NULL DEFAULT NULL COMMENT 'When additional pets were added/paid'",
    ];
    
    // Add columns to bookings table
    foreach ($columns as $columnName => $definition) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE '{$columnName}'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN {$columnName} {$definition}");
            echo "✓ Added {$columnName} column to bookings table\n";
        } else {
            echo "✓ {$columnName} column already exists in bookings table\n";
        }
    }
    
    echo "\n";
    
    // Add columns to reports table
    foreach ($columns as $columnName => $definition) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE '{$columnName}'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE reports ADD COLUMN {$columnName} {$definition}");
            echo "✓ Added {$columnName} column to reports table\n";
        } else {
            echo "✓ {$columnName} column already exists in reports table\n";
        }
    }
    
    echo "\n===========================================\n";
    echo "Migration completed successfully!\n";
    echo "===========================================\n\n";
    
    echo "IMPORTANT NOTES:\n";
    echo "1. These columns track WHEN each type of additional was added/paid\n";
    echo "2. You need to update the booking/modification forms to set these dates\n";
    echo "3. For existing bookings, dates will be NULL (won't appear in date-filtered reports)\n";
    echo "4. Each additional type can now have its own payment date\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
