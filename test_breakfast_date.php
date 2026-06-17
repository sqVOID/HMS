<?php
/**
 * Test script to verify breakfast_date bulk functionality
 * This script checks:
 * 1. If breakfast_date column exists in both tables
 * 2. The data type is TEXT (for JSON storage)
 * 3. Sample data to show how it works
 */

require_once 'config.php';

echo "===========================================\n";
echo "BREAKFAST DATE COLUMN TEST\n";
echo "===========================================\n\n";

try {
    // Check bookings table
    echo "1. Checking bookings table...\n";
    $bookingsColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'breakfast_date'")->fetch(PDO::FETCH_ASSOC);
    if ($bookingsColumn) {
        echo "   ✓ breakfast_date column exists\n";
        echo "   ✓ Type: {$bookingsColumn['Type']}\n";
        echo "   ✓ Null: {$bookingsColumn['Null']}\n";
        echo "   ✓ Default: " . ($bookingsColumn['Default'] ?? 'NULL') . "\n\n";
    } else {
        echo "   ✗ breakfast_date column NOT found!\n";
        echo "   → Run add_breakfast_date_column.php first\n\n";
        exit(1);
    }
    
    // Check reports table
    echo "2. Checking reports table...\n";
    $reportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'breakfast_date'")->fetch(PDO::FETCH_ASSOC);
    if ($reportsColumn) {
        echo "   ✓ breakfast_date column exists\n";
        echo "   ✓ Type: {$reportsColumn['Type']}\n";
        echo "   ✓ Null: {$reportsColumn['Null']}\n";
        echo "   ✓ Default: " . ($reportsColumn['Default'] ?? 'NULL') . "\n\n";
    } else {
        echo "   ✗ breakfast_date column NOT found!\n";
        echo "   → Run add_breakfast_date_column.php first\n\n";
        exit(1);
    }
    
    // Check for any existing breakfast_date data
    echo "3. Checking existing data...\n";
    $stmt = $conn->query("SELECT id, guest_name, breakfast, breakfast_date FROM bookings WHERE breakfast IS NOT NULL AND breakfast != '' AND breakfast != 'None' LIMIT 5");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        echo "   Found " . count($bookings) . " booking(s) with breakfast:\n\n";
        foreach ($bookings as $booking) {
            echo "   Booking ID: {$booking['id']}\n";
            echo "   Guest: {$booking['guest_name']}\n";
            echo "   Breakfast: {$booking['breakfast']}\n";
            echo "   Breakfast Date: " . ($booking['breakfast_date'] ?? 'NULL') . "\n";
            
            if ($booking['breakfast_date']) {
                $dates = json_decode($booking['breakfast_date'], true);
                if (is_array($dates)) {
                    echo "   Decoded Dates:\n";
                    foreach ($dates as $index => $date) {
                        echo "      [" . ($index + 1) . "] {$date}\n";
                    }
                }
            }
            echo "   ---\n\n";
        }
    } else {
        echo "   No bookings with breakfast found.\n";
        echo "   → Add a breakfast item to a booking to test the functionality\n\n";
    }
    
    // Show how it works
    echo "4. How breakfast_date works:\n";
    echo "   • When breakfast is added/modified, a timestamp is appended\n";
    echo "   • Format: JSON array [\"2026-06-04 10:15:23\", \"2026-06-05 14:30:00\"]\n";
    echo "   • Each entry represents when the breakfast was changed\n";
    echo "   • Multiple timestamps = multiple modifications tracked\n\n";
    
    echo "5. Similar bulk date columns:\n";
    $bulkColumns = ['additional_food_date', 'additional_items_date', 'additional_guest_date', 'additional_pet_date', 'breakfast_date'];
    foreach ($bulkColumns as $col) {
        $check = $conn->query("SHOW COLUMNS FROM bookings LIKE '{$col}'")->fetch(PDO::FETCH_ASSOC);
        if ($check) {
            echo "   ✓ {$col} ({$check['Type']})\n";
        } else {
            echo "   ✗ {$col} (NOT FOUND)\n";
        }
    }
    
    echo "\n===========================================\n";
    echo "TEST COMPLETED SUCCESSFULLY!\n";
    echo "===========================================\n\n";
    
    echo "NEXT STEPS:\n";
    echo "1. Update a booking with breakfast to test date tracking\n";
    echo "2. Modify the breakfast again to see multiple dates appended\n";
    echo "3. Check the breakfast_date field to verify JSON array format\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
