<?php
/**
 * Test script to verify extend_bundle_breakfast_date implementation
 */

require_once 'config.php';

echo "===========================================\n";
echo "EXTEND BUNDLE BREAKFAST DATE TEST\n";
echo "===========================================\n\n";

try {
    // Check bookings table
    echo "1. Checking bookings table...\n";
    $bookingsColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'extend_bundle_breakfast_date'")->fetch(PDO::FETCH_ASSOC);
    if ($bookingsColumn) {
        echo "   ✓ extend_bundle_breakfast_date column exists\n";
        echo "   ✓ Type: {$bookingsColumn['Type']}\n";
        echo "   ✓ Null: {$bookingsColumn['Null']}\n";
        echo "   ✓ Default: " . ($bookingsColumn['Default'] ?? 'NULL') . "\n\n";
    } else {
        echo "   ✗ extend_bundle_breakfast_date column NOT found!\n";
        echo "   → Run add_extend_bundle_breakfast_date.php first\n\n";
        exit(1);
    }
    
    // Check reports table
    echo "2. Checking reports table...\n";
    $reportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'extend_bundle_breakfast_date'")->fetch(PDO::FETCH_ASSOC);
    if ($reportsColumn) {
        echo "   ✓ extend_bundle_breakfast_date column exists\n";
        echo "   ✓ Type: {$reportsColumn['Type']}\n";
        echo "   ✓ Null: {$reportsColumn['Null']}\n";
        echo "   ✓ Default: " . ($reportsColumn['Default'] ?? 'NULL') . "\n\n";
    } else {
        echo "   ✗ extend_bundle_breakfast_date column NOT found!\n";
        echo "   → Run add_extend_bundle_breakfast_date.php first\n\n";
        exit(1);
    }
    
    // Check for any existing extend_bundle_breakfast_date data
    echo "3. Checking existing data...\n";
    $stmt = $conn->query("
        SELECT id, booking_id, guest_name, extend_bundle_breakfast, extend_bundle_breakfast_date 
        FROM bookings 
        WHERE extend_bundle_breakfast IS NOT NULL 
        AND extend_bundle_breakfast != '' 
        AND extend_bundle_breakfast != 'None'
        ORDER BY id DESC 
        LIMIT 5
    ");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        echo "   Found " . count($bookings) . " booking(s) with extend_bundle_breakfast:\n\n";
        foreach ($bookings as $booking) {
            echo "   Booking ID: {$booking['booking_id']} (DB ID: {$booking['id']})\n";
            echo "   Guest: {$booking['guest_name']}\n";
            echo "   Extend Bundle Breakfast: {$booking['extend_bundle_breakfast']}\n";
            echo "   Extend Bundle Breakfast Date: " . ($booking['extend_bundle_breakfast_date'] ?? 'NULL') . "\n";
            
            if ($booking['extend_bundle_breakfast_date']) {
                $dates = json_decode($booking['extend_bundle_breakfast_date'], true);
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
        echo "   No bookings with extend_bundle_breakfast found.\n";
        echo "   → Extend a booking with bundle breakfast to test the functionality\n\n";
    }
    
    // Show how it works
    echo "4. How extend_bundle_breakfast_date works:\n";
    echo "   • When extension with bundle breakfast is added, a timestamp is appended\n";
    echo "   • Format: JSON array [\"2026-06-04 10:15:23\", \"2026-06-05 14:30:00\"]\n";
    echo "   • Each entry represents when the extension bundle breakfast was added/modified\n";
    echo "   • Multiple timestamps = multiple extensions tracked\n\n";
    
    echo "5. Related date tracking columns:\n";
    $dateColumns = [
        'breakfast_date',
        'extend_bundle_breakfast_date',
        'additional_food_date',
        'additional_items_date',
        'additional_guest_date',
        'additional_pet_date'
    ];
    foreach ($dateColumns as $col) {
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
    echo "1. Extend a booking with bundle breakfast to test date tracking\n";
    echo "2. Extend again to see multiple dates appended\n";
    echo "3. Check the extend_bundle_breakfast_date field to verify JSON array format\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
