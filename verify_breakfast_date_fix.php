<?php
/**
 * Quick verification that breakfast_date is now in both tables
 */

require_once 'config.php';

echo "===========================================\n";
echo "BREAKFAST DATE FIX VERIFICATION\n";
echo "===========================================\n\n";

try {
    // Check the most recent booking with breakfast
    $stmt = $conn->query("
        SELECT 
            b.id,
            b.booking_id,
            b.guest_name,
            b.breakfast,
            b.breakfast_date as bookings_date,
            r.breakfast_date as reports_date
        FROM bookings b
        LEFT JOIN reports r ON b.booking_id = r.booking_id
        WHERE b.breakfast IS NOT NULL 
        AND b.breakfast != '' 
        AND b.breakfast != 'None'
        ORDER BY b.id DESC
        LIMIT 5
    ");
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Checking last 5 bookings with breakfast...\n\n";
    
    $allSynced = true;
    
    foreach ($bookings as $booking) {
        $bookings_date = $booking['bookings_date'];
        $reports_date = $booking['reports_date'];
        $isSynced = ($bookings_date === $reports_date);
        
        echo "Booking ID: {$booking['booking_id']}\n";
        echo "Guest: {$booking['guest_name']}\n";
        echo "Breakfast: {$booking['breakfast']}\n";
        echo "bookings.breakfast_date: " . ($bookings_date ?? 'NULL') . "\n";
        echo "reports.breakfast_date:  " . ($reports_date ?? 'NULL') . "\n";
        
        if ($isSynced) {
            echo "Status: ✓ SYNCED\n";
        } else {
            echo "Status: ✗ NOT SYNCED\n";
            $allSynced = false;
        }
        echo "---\n\n";
    }
    
    if ($allSynced) {
        echo "===========================================\n";
        echo "✓✓✓ ALL BOOKINGS ARE SYNCED! ✓✓✓\n";
        echo "===========================================\n\n";
        echo "The breakfast_date fix is working correctly.\n";
        echo "Both bookings and reports tables have matching data.\n\n";
    } else {
        echo "===========================================\n";
        echo "⚠ SOME BOOKINGS ARE NOT SYNCED\n";
        echo "===========================================\n\n";
        echo "Please run: php sync_breakfast_date_to_reports.php\n\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
