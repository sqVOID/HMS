<?php
/**
 * Add extension_details column to bookings and reports tables
 * This column stores individual extension details as JSON array
 * Format: [{"hours": 12, "minutes": 0, "price": 960, "timestamp": "2026-01-18 10:10:00"}, ...]
 */

require_once 'config.php';

try {
    echo "Adding extension_details column to bookings and reports tables...\n\n";
    
    // Add to bookings table
    $sql = "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS extension_details TEXT NULL AFTER extension_time_at";
    $conn->exec($sql);
    echo "✓ Added extension_details column to bookings table\n";
    
    // Add to reports table
    $sql = "ALTER TABLE reports ADD COLUMN IF NOT EXISTS extension_details TEXT NULL AFTER extension_time_at";
    $conn->exec($sql);
    echo "✓ Added extension_details column to reports table\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nThe extension_details column will store individual extension records as JSON:\n";
    echo "[\n";
    echo "  {\"hours\": 12, \"minutes\": 0, \"price\": 960, \"timestamp\": \"2026-01-18 10:10:00\"},\n";
    echo "  {\"hours\": 24, \"minutes\": 0, \"price\": 1920, \"timestamp\": \"2026-01-18 23:04:00\"}\n";
    echo "]\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
