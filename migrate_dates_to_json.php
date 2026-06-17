<?php
/**
 * Migration script to convert single date columns to JSON arrays
 * This allows tracking multiple dates for each additional type
 */

require_once 'config.php';

try {
    echo "Converting date columns to JSON format...\n\n";
    
    // Columns to convert
    $columns = [
        'additional_items_date',
        'additional_food_date',
        'additional_guest_date',
        'additional_pet_date',
    ];
    
    // Convert columns in bookings table
    echo "Processing bookings table...\n";
    foreach ($columns as $columnName) {
        // First, get existing data
        $stmt = $conn->query("SELECT id, {$columnName} FROM bookings WHERE {$columnName} IS NOT NULL");
        $existingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Modify column to TEXT type
        $conn->exec("ALTER TABLE bookings MODIFY COLUMN {$columnName} TEXT NULL DEFAULT NULL");
        echo "✓ Modified {$columnName} to TEXT in bookings table\n";
        
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
    
    echo "\n";
    
    // Convert columns in reports table
    echo "Processing reports table...\n";
    foreach ($columns as $columnName) {
        // First, get existing data
        $stmt = $conn->query("SELECT id, {$columnName} FROM reports WHERE {$columnName} IS NOT NULL");
        $existingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Modify column to TEXT type
        $conn->exec("ALTER TABLE reports MODIFY COLUMN {$columnName} TEXT NULL DEFAULT NULL");
        echo "✓ Modified {$columnName} to TEXT in reports table\n";
        
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
    
    echo "\n===========================================\n";
    echo "Migration completed successfully!\n";
    echo "===========================================\n\n";
    
    echo "IMPORTANT NOTES:\n";
    echo "1. Date columns now store JSON arrays of dates\n";
    echo "2. Existing single dates have been converted to arrays\n";
    echo "3. New dates will be appended to the arrays instead of overwriting\n";
    echo "4. Example format: [\"2026-06-04 10:15:23\", \"2026-06-05 14:30:00\"]\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
