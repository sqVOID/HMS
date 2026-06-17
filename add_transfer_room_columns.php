<?php
require_once 'config.php';

try {
    $columnsAdded = [];
    $columnsExist = [];
    
    // ===== BOOKINGS TABLE =====
    echo "=== UPDATING BOOKINGS TABLE ===\n";
    
    // Check if the bookings table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkTable->rowCount() == 0) {
        echo "Bookings table does not exist.\n";
    } else {
        // Add transfer_room_from column to bookings
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'transfer_room_from'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN transfer_room_from VARCHAR(100) NULL DEFAULT NULL AFTER room_id");
            $columnsAdded[] = 'bookings.transfer_room_from';
            echo "Successfully added 'transfer_room_from' column to bookings table.\n";
        } else {
            $columnsExist[] = 'bookings.transfer_room_from';
            echo "'transfer_room_from' column already exists in bookings table.\n";
        }
        
        // Add transfer_at column to bookings
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'transfer_at'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN transfer_at DATETIME NULL DEFAULT NULL AFTER transfer_room_from");
            $columnsAdded[] = 'bookings.transfer_at';
            echo "Successfully added 'transfer_at' column to bookings table.\n";
        } else {
            $columnsExist[] = 'bookings.transfer_at';
            echo "'transfer_at' column already exists in bookings table.\n";
        }
    }
    
    echo "\n";
    
    // ===== REPORTS TABLE =====
    echo "=== UPDATING REPORTS TABLE ===\n";
    
    // Check if the reports table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkTable->rowCount() == 0) {
        echo "Reports table does not exist.\n";
    } else {
        // Add transfer_room_from column to reports
        $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'transfer_room_from'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE reports ADD COLUMN transfer_room_from VARCHAR(100) NULL DEFAULT NULL AFTER room_id");
            $columnsAdded[] = 'reports.transfer_room_from';
            echo "Successfully added 'transfer_room_from' column to reports table.\n";
        } else {
            $columnsExist[] = 'reports.transfer_room_from';
            echo "'transfer_room_from' column already exists in reports table.\n";
        }
        
        // Add transfer_at column to reports
        $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'transfer_at'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE reports ADD COLUMN transfer_at DATETIME NULL DEFAULT NULL AFTER transfer_room_from");
            $columnsAdded[] = 'reports.transfer_at';
            echo "Successfully added 'transfer_at' column to reports table.\n";
        } else {
            $columnsExist[] = 'reports.transfer_at';
            echo "'transfer_at' column already exists in reports table.\n";
        }
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
