<?php
/**
 * Add extension_stack column to bookings and reports tables
 * This script ensures the extension_stack column exists and is properly configured
 */

require_once 'config.php';

try {
    echo "Adding/Updating extension_stack column to bookings and reports tables...\n\n";
    
    // Add extension_stack column to bookings table if it doesn't exist
    $sql = "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS extension_stack TEXT NULL DEFAULT NULL 
            COMMENT 'JSON array storing individual extension segments for tracking and withdrawal'";
    $conn->exec($sql);
    echo "✓ Added/Updated extension_stack column to bookings table\n";
    
    // Add extension_stack column to reports table if it doesn't exist
    $sql = "ALTER TABLE reports ADD COLUMN IF NOT EXISTS extension_stack TEXT NULL DEFAULT NULL 
            COMMENT 'JSON array storing individual extension segments for tracking and withdrawal'";
    $conn->exec($sql);
    echo "✓ Added/Updated extension_stack column to reports table\n";
    
    // Create index for better performance on extension_stack queries
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_bookings_extension_stack ON bookings(extension_stack(255))");
        echo "✓ Created index on bookings.extension_stack\n";
    } catch (PDOException $e) {
        echo "⚠ Index creation skipped (may already exist): " . $e->getMessage() . "\n";
    }
    
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_reports_extension_stack ON reports(extension_stack(255))");
        echo "✓ Created index on reports.extension_stack\n";
    } catch (PDOException $e) {
        echo "⚠ Index creation skipped (may already exist): " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Extension stack migration completed successfully!\n";
    echo "\nThe extension_stack column stores individual extension records as JSON:\n";
    echo "[\n";
    echo "  {\"h\": 12, \"m\": 0, \"price\": 960, \"reg\": 800, \"bun\": 160, \"bf\": \"2 Rice (Promo)\"},\n";
    echo "  {\"h\": 24, \"m\": 0, \"price\": 1920, \"reg\": 1600, \"bun\": 320, \"bf\": null}\n";
    echo "]\n\n";
    echo "Where:\n";
    echo "- h: hours extended\n";
    echo "- m: minutes extended\n";
    echo "- price: total price for this extension segment\n";
    echo "- reg: regular rate portion\n";
    echo "- bun: bundle rate portion\n";
    echo "- bf: breakfast items for this extension (null if none)\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>