<?php
require_once 'config.php';

try {
    // Check if the reports table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkTable->rowCount() == 0) {
        echo "Reports table does not exist.\n";
        exit;
    }
    
    $columnsAdded = [];
    $columnsExist = [];
    
    // Add guest_type column
    $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'guest_type'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN guest_type VARCHAR(50) NULL DEFAULT NULL AFTER guest_name");
        $columnsAdded[] = 'guest_type';
        echo "Successfully added 'guest_type' column to reports table.\n";
    } else {
        $columnsExist[] = 'guest_type';
        echo "'guest_type' column already exists in reports table.\n";
    }
    
    // Add contact_person_name column
    $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'contact_person_name'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN contact_person_name VARCHAR(255) NULL DEFAULT NULL AFTER guest_type");
        $columnsAdded[] = 'contact_person_name';
        echo "Successfully added 'contact_person_name' column to reports table.\n";
    } else {
        $columnsExist[] = 'contact_person_name';
        echo "'contact_person_name' column already exists in reports table.\n";
    }
    
    // Add tin_number column
    $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'tin_number'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN tin_number VARCHAR(50) NULL DEFAULT NULL AFTER contact_person_name");
        $columnsAdded[] = 'tin_number';
        echo "Successfully added 'tin_number' column to reports table.\n";
    } else {
        $columnsExist[] = 'tin_number';
        echo "'tin_number' column already exists in reports table.\n";
    }
    
    echo "\n=== SUMMARY ===\n";
    if (!empty($columnsAdded)) {
        echo "Columns added: " . implode(', ', $columnsAdded) . "\n";
    }
    if (!empty($columnsExist)) {
        echo "Columns already exist: " . implode(', ', $columnsExist) . "\n";
    }
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
