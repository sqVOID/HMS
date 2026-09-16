<?php
/**
 * Migration script to add extend_bundle_breakfast_date column for tracking when extension bundle breakfast was added/modified
 * Uses JSON format to store multiple dates (bulk format)
 */

require_once 'config.php';

try {
    echo "Adding extend_bundle_breakfast_date column...\n\n";
    
    $columnName = 'extend_bundle_breakfast_date';
    $definition = "TEXT NULL DEFAULT NULL COMMENT 'When extension bundle breakfast was added/modified (JSON array)'";
    
    // Add column to bookings table
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE '{$columnName}'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN {$columnName} {$definition}");
        echo "✓ Added {$columnName} column to bookings table\n";
    } else {
        echo "✓ {$columnName} column already exists in bookings table\n";
        
        // Check if it's TEXT type, if not, convert it
        $columnInfo = $conn->query("SHOW COLUMNS FROM bookings LIKE '{$columnName}'")->fetch(PDO::FETCH_ASSOC);
        if (strpos(strtolower($columnInfo['Type']), 'text') === false) {
            // Convert existing data to JSON array format
            $stmt = $conn->query("SELECT id, {$columnName} FROM bookings WHERE {$columnName} IS NOT NULL");
            $existingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Modify column to TEXT type
            $conn->exec("ALTER TABLE bookings MODIFY COLUMN {$columnName} {$definition}");
            echo "✓ Converted {$columnName} to TEXT type in bookings table\n";
            
            // Convert existing single dates to JSON arrays
            foreach ($existingData as $row) {
                if ($row[$columnName]) {
                    $jsonArray = json_encode([$row[$columnName]]);
                    $updateStmt = $conn->prepare("UPDATE bookings SET {$columnName} = :json WHERE id = :id");
                    $updateStmt->execute([':json' => $jsonArray, ':id' => $row['id']]);
                }
            }
            echo "✓ Converted existing {$columnName} data to JSON arrays\n";
        }
    }
    
    echo "\n";
    
    // Add column to reports table
    $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE '{$columnName}'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN {$columnName} {$definition}");
        echo "✓ Added {$columnName} column to reports table\n";
    } else {
        echo "✓ {$columnName} column already exists in reports table\n";
        
        // Check if it's TEXT type, if not, convert it
        $columnInfo = $conn->query("SHOW COLUMNS FROM reports LIKE '{$columnName}'")->fetch(PDO::FETCH_ASSOC);
        if (strpos(strtolower($columnInfo['Type']), 'text') === false) {
            // Convert existing data to JSON array format
            $stmt = $conn->query("SELECT id, {$columnName} FROM reports WHERE {$columnName} IS NOT NULL");
            $existingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Modify column to TEXT type
            $conn->exec("ALTER TABLE reports MODIFY COLUMN {$columnName} {$definition}");
            echo "✓ Converted {$columnName} to TEXT type in reports table\n";
            
            // Convert existing single dates to JSON arrays
            foreach ($existingData as $row) {
                if ($row[$columnName]) {
                    $jsonArray = json_encode([$row[$columnName]]);
                    $updateStmt = $conn->prepare("UPDATE reports SET {$columnName} = :json WHERE id = :id");
                    $updateStmt->execute([':json' => $jsonArray, ':id' => $row['id']]);
                }
            }
            echo "✓ Converted existing {$columnName} data to JSON arrays\n";
        }
    }
    
    echo "\n===========================================\n";
    echo "Migration completed successfully!\n";
    echo "===========================================\n\n";
    
    echo "IMPORTANT NOTES:\n";
    echo "1. extend_bundle_breakfast_date column now stores JSON arrays of dates\n";
    echo "2. Each time extension bundle breakfast is added/modified, a new timestamp is appended\n";
    echo "3. Example format: [\"2026-06-04 10:15:23\", \"2026-06-05 14:30:00\"]\n";
    echo "4. This allows tracking multiple extension bundle breakfast additions over time\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
