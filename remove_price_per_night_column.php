<?php
/**
 * Optional script to remove price_per_night column from rooms table
 * This column is no longer needed since we're using duration-based pricing
 * 
 * WARNING: Run this only if you're sure you want to remove the column permanently
 * The system will continue to work without it, but this is irreversible.
 */
require_once 'config.php';

try {
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM rooms LIKE 'price_per_night'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "Column 'price_per_night' exists.\n";
        echo "Do you want to remove it? This action cannot be undone.\n";
        echo "To proceed, uncomment the line below in this file and run again.\n\n";
        
        // Uncomment the line below to actually remove the column
        // $conn->exec("ALTER TABLE rooms DROP COLUMN price_per_night");
        // echo "Column 'price_per_night' has been removed successfully!\n";
        
        echo "Column removal is currently disabled for safety.\n";
        echo "To enable, edit this file and uncomment the ALTER TABLE line.\n";
    } else {
        echo "Column 'price_per_night' does not exist in the rooms table.\n";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

