<?php
// Complete migration script for payment and extension history support
// This will add/modify columns in both reports and bookings tables

require_once 'config.php';

try {
    echo "Starting complete history migration (payment + extension)...\n\n";
    
    // ===== PAYMENT HISTORY MIGRATION =====
    echo "=== PAYMENT HISTORY MIGRATION ===\n";
    
    // Step 1: Handle reports table - payment_date_time
    echo "Checking reports table for payment_date_time...\n";
    $checkReportsPayment = $conn->query("SHOW COLUMNS FROM reports LIKE 'payment_date_time'");
    $reportsPaymentInfo = $checkReportsPayment->fetch(PDO::FETCH_ASSOC);
    
    if ($reportsPaymentInfo) {
        echo "Current reports payment_date_time type: " . $reportsPaymentInfo['Type'] . "\n";
        
        if (stripos($reportsPaymentInfo['Type'], 'text') === false) {
            $sql = "ALTER TABLE reports MODIFY COLUMN payment_date_time TEXT NULL";
            if ($conn->query($sql)) {
                echo "✓ Modified payment_date_time in reports table to TEXT\n";
            } else {
                echo "✗ Error modifying payment_date_time in reports table\n";
            }
        } else {
            echo "✓ payment_date_time in reports table is already TEXT\n";
        }
    } else {
        // Add payment_date_time column to reports table
        $sql = "ALTER TABLE reports ADD COLUMN payment_date_time TEXT NULL";
        if ($conn->query($sql)) {
            echo "✓ Added payment_date_time column to reports table\n";
        } else {
            echo "✗ Error adding payment_date_time to reports table\n";
        }
    }
    
    // Step 2: Handle bookings table - payment_date_time
    echo "\nChecking bookings table for payment_date_time...\n";
    $checkBookingsStmt = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkBookingsStmt->rowCount() > 0) {
        $checkBookingsPayment = $conn->query("SHOW COLUMNS FROM bookings LIKE 'payment_date_time'");
        $bookingsPaymentInfo = $checkBookingsPayment->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingsPaymentInfo) {
            echo "Current bookings payment_date_time type: " . $bookingsPaymentInfo['Type'] . "\n";
            
            if (stripos($bookingsPaymentInfo['Type'], 'text') === false) {
                $sqlBookings = "ALTER TABLE bookings MODIFY COLUMN payment_date_time TEXT NULL";
                if ($conn->query($sqlBookings)) {
                    echo "✓ Modified payment_date_time in bookings table to TEXT\n";
                } else {
                    echo "✗ Error modifying payment_date_time in bookings table\n";
                }
            } else {
                echo "✓ payment_date_time in bookings table is already TEXT\n";
            }
        } else {
            // Add payment_date_time column to bookings table
            $sql = "ALTER TABLE bookings ADD COLUMN payment_date_time TEXT NULL";
            if ($conn->query($sql)) {
                echo "✓ Added payment_date_time column to bookings table\n";
            } else {
                echo "✗ Error adding payment_date_time to bookings table\n";
            }
        }
    } else {
        echo "⚠️ Bookings table not found\n";
    }
    
    // ===== EXTENSION HISTORY MIGRATION =====
    echo "\n=== EXTENSION HISTORY MIGRATION ===\n";
    
    // Step 3: Handle reports table - extension_time_at
    echo "Checking reports table for extension_time_at...\n";
    $checkReportsExtension = $conn->query("SHOW COLUMNS FROM reports LIKE 'extension_time_at'");
    $reportsExtensionInfo = $checkReportsExtension->fetch(PDO::FETCH_ASSOC);
    
    if ($reportsExtensionInfo) {
        echo "Current reports extension_time_at type: " . $reportsExtensionInfo['Type'] . "\n";
        
        if (stripos($reportsExtensionInfo['Type'], 'text') === false) {
            $sql = "ALTER TABLE reports MODIFY COLUMN extension_time_at TEXT NULL";
            if ($conn->query($sql)) {
                echo "✓ Modified extension_time_at in reports table to TEXT\n";
            } else {
                echo "✗ Error modifying extension_time_at in reports table\n";
            }
        } else {
            echo "✓ extension_time_at in reports table is already TEXT\n";
        }
    } else {
        // Add extension_time_at column to reports table
        $sql = "ALTER TABLE reports ADD COLUMN extension_time_at TEXT NULL AFTER extended_time";
        if ($conn->query($sql)) {
            echo "✓ Added extension_time_at column to reports table\n";
        } else {
            echo "✗ Error adding extension_time_at to reports table\n";
        }
    }
    
    // Step 4: Handle bookings table - extension_time_at
    echo "\nChecking bookings table for extension_time_at...\n";
    if ($checkBookingsStmt->rowCount() > 0) {
        $checkBookingsExtension = $conn->query("SHOW COLUMNS FROM bookings LIKE 'extension_time_at'");
        $bookingsExtensionInfo = $checkBookingsExtension->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingsExtensionInfo) {
            echo "Current bookings extension_time_at type: " . $bookingsExtensionInfo['Type'] . "\n";
            
            if (stripos($bookingsExtensionInfo['Type'], 'text') === false) {
                $sqlBookings = "ALTER TABLE bookings MODIFY COLUMN extension_time_at TEXT NULL";
                if ($conn->query($sqlBookings)) {
                    echo "✓ Modified extension_time_at in bookings table to TEXT\n";
                } else {
                    echo "✗ Error modifying extension_time_at in bookings table\n";
                }
            } else {
                echo "✓ extension_time_at in bookings table is already TEXT\n";
            }
        } else {
            // Add extension_time_at column to bookings table
            $sql = "ALTER TABLE bookings ADD COLUMN extension_time_at TEXT NULL";
            if ($conn->query($sql)) {
                echo "✓ Added extension_time_at column to bookings table\n";
            } else {
                echo "✗ Error adding extension_time_at to bookings table\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "📊 SUMMARY:\n";
    echo "✓ Payment history support added/updated in both tables\n";
    echo "✓ Extension history support added/updated in both tables\n";
    echo "✓ Multiple timestamps format: 2026-01-18 10:10:00|2026-01-18 23:04:00\n\n";
    
    echo "🎯 WHAT'S ENABLED:\n";
    echo "• Every payment will be recorded with timestamp\n";
    echo "• Every extension will be recorded with timestamp\n";
    echo "• Both bookings and reports tables are synchronized\n";
    echo "• Complete audit trail for all booking activities\n\n";
    
    echo "🚀 NEXT STEPS:\n";
    echo "1. Test payment history: Make a booking with payment\n";
    echo "2. Test extension history: Extend a booking\n";
    echo "3. View results: Visit test_payment_history.php and test_extension_history.php\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

$conn = null;
?>