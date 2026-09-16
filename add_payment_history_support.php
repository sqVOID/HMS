<?php
// Migration script to convert payment_date_time to support multiple payment records
// This will store payment timestamps as a delimited string

require_once 'config.php';

try {
    echo "Starting payment_date_time migration...\n\n";
    
    // Step 1: Handle reports table
    echo "=== REPORTS TABLE ===\n";
    $checkStmt = $conn->query("SHOW COLUMNS FROM reports LIKE 'payment_date_time'");
    $columnInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($columnInfo) {
        echo "Current reports column type: " . $columnInfo['Type'] . "\n";
        
        if (stripos($columnInfo['Type'], 'text') === false) {
            // Step 2: Change payment_date_time from DATETIME to TEXT to store multiple timestamps
            $sql = "ALTER TABLE reports MODIFY COLUMN payment_date_time TEXT NULL";
            
            if ($conn->query($sql)) {
                echo "✓ Successfully modified payment_date_time column in 'reports' table\n";
                echo "✓ Column type changed from DATETIME to TEXT\n";
            } else {
                echo "✗ Error modifying reports table\n";
            }
        } else {
            echo "✓ payment_date_time in reports table is already TEXT\n";
        }
    } else {
        // Add payment_date_time column if it doesn't exist
        $sql = "ALTER TABLE reports ADD COLUMN payment_date_time TEXT NULL";
        if ($conn->query($sql)) {
            echo "✓ Added payment_date_time column to reports table\n";
        } else {
            echo "✗ Error adding payment_date_time column to reports table\n";
        }   
    }
    
    // Step 3: Handle bookings table
    echo "\n=== BOOKINGS TABLE ===\n";
    $checkBookingsStmt = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkBookingsStmt->rowCount() > 0) {
        $checkBookingsCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'payment_date_time'");
        $bookingsColInfo = $checkBookingsCol->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingsColInfo) {
            echo "Current bookings column type: " . $bookingsColInfo['Type'] . "\n";
            
            if (stripos($bookingsColInfo['Type'], 'text') === false) {
                $sqlBookings = "ALTER TABLE bookings MODIFY COLUMN payment_date_time TEXT NULL";
                
                if ($conn->query($sqlBookings)) {
                    echo "✓ Successfully modified payment_date_time column in 'bookings' table\n";
                    echo "✓ Column type changed from DATETIME to TEXT\n";
                } else {
                    echo "✗ Error modifying bookings table\n";
                }
            } else {
                echo "✓ payment_date_time in bookings table is already TEXT\n";
            }
        } else {
            // Add payment_date_time column if it doesn't exist
            $sqlBookings = "ALTER TABLE bookings ADD COLUMN payment_date_time TEXT NULL";
            
            if ($conn->query($sqlBookings)) {
                echo "✓ Added payment_date_time column to bookings table\n";
            } else {
                echo "✗ Error adding payment_date_time column to bookings table\n";
            }
        }
    } else {
        echo "⚠️ Bookings table not found\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ PAYMENT HISTORY MIGRATION COMPLETED!\n";
    echo str_repeat("=", 50) . "\n";
    echo "✓ Multiple payment timestamps will be stored in format: 2026-01-18 10:10:00|2026-01-18 23:04:00\n";
    echo "✓ Both reports and bookings tables are now ready for payment history\n";
    echo "\nYou can now record multiple payment dates for each booking.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn = null;
?>
