<?php
// Migration script to add/modify extension_time_at to support multiple extension records
// This will store extension timestamps as a delimited string

require_once 'config.php';

try {
    echo "Starting extension_time_at migration...\n\n";
    
    // Step 1: Handle reports table
    echo "=== REPORTS TABLE ===\n";
    $checkReportsStmt = $conn->query("SHOW COLUMNS FROM reports LIKE 'extension_time_at'");
    $reportsColumnInfo = $checkReportsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reportsColumnInfo) {
        echo "Current reports column type: " . $reportsColumnInfo['Type'] . "\n";
        
        if (stripos($reportsColumnInfo['Type'], 'text') === false) {
            // Modify existing column to TEXT
            $sql = "ALTER TABLE reports MODIFY COLUMN extension_time_at TEXT NULL";
            
            if ($conn->query($sql)) {
                echo "✓ Successfully modified extension_time_at column in 'reports' table\n";
                echo "✓ Column type changed to TEXT\n";
            } else {
                echo "✗ Error modifying reports table\n";
            }
        } else {
            echo "✓ extension_time_at in reports table is already TEXT\n";
        }
    } else {
        // Add new column as TEXT
        $sql = "ALTER TABLE reports ADD COLUMN extension_time_at TEXT NULL";
        
        if ($conn->query($sql)) {
            echo "✓ Successfully added extension_time_at column to 'reports' table\n";
        } else {
            echo "✗ Error adding column to reports table\n";
        }
    }
    
    // Step 2: Handle bookings table
    echo "\n=== BOOKINGS TABLE ===\n";
    $checkBookingsStmt = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkBookingsStmt->rowCount() > 0) {
        $checkBookingsCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'extension_time_at'");
        $bookingsColInfo = $checkBookingsCol->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingsColInfo) {
            echo "Current bookings column type: " . $bookingsColInfo['Type'] . "\n";
            
            if (stripos($bookingsColInfo['Type'], 'text') === false) {
                // Modify existing column to TEXT
                $sqlBookings = "ALTER TABLE bookings MODIFY COLUMN extension_time_at TEXT NULL";
                
                if ($conn->query($sqlBookings)) {
                    echo "✓ Successfully modified extension_time_at column in 'bookings' table\n";
                    echo "✓ Column type changed to TEXT\n";
                } else {
                    echo "✗ Error modifying bookings table\n";
                }
            } else {
                echo "✓ extension_time_at in bookings table is already TEXT\n";
            }
        } else {
            // Add new column as TEXT
            $sqlBookings = "ALTER TABLE bookings ADD COLUMN extension_time_at TEXT NULL";
            
            if ($conn->query($sqlBookings)) {
                echo "✓ Successfully added extension_time_at column to 'bookings' table\n";
            } else {
                echo "✗ Error adding column to bookings table\n";
            }
        }
    } else {
        echo "⚠️ Bookings table not found\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ EXTENSION HISTORY MIGRATION COMPLETED!\n";
    echo str_repeat("=", 50) . "\n";
    echo "✓ Multiple extension timestamps will be stored in format: 2026-01-18 10:10:00|2026-01-18 23:04:00\n";
    echo "✓ Both reports and bookings tables are now ready for extension history\n";
    echo "\nYou can now record multiple extension dates for each booking.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn = null;
?>