<?php
/**
 * Cleanup script to remove breakfast_date values where breakfast is empty or "None"
 */

require_once 'config.php';

echo "=== CLEANING UP BREAKFAST_DATE DATA ===\n\n";

try {
    // Find bookings with breakfast_date but no actual breakfast
    echo "1. Finding bookings with incorrect breakfast_date...\n";
    
    $stmt = $conn->query("
        SELECT id, booking_id, guest_name, breakfast, breakfast_date
        FROM bookings
        WHERE breakfast_date IS NOT NULL
        AND (breakfast IS NULL OR breakfast = '' OR breakfast = 'None')
    ");
    
    $incorrectBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($incorrectBookings);
    
    echo "   Found {$count} booking(s) with incorrect breakfast_date\n\n";
    
    if ($count > 0) {
        echo "2. Bookings that will be cleaned:\n";
        foreach ($incorrectBookings as $booking) {
            echo "   - {$booking['booking_id']} ({$booking['guest_name']})\n";
            echo "     Breakfast: " . ($booking['breakfast'] ?: 'NULL/Empty') . "\n";
            echo "     breakfast_date: {$booking['breakfast_date']}\n\n";
        }
        
        // Clean up bookings table
        echo "3. Cleaning bookings table...\n";
        $updateBookings = $conn->prepare("
            UPDATE bookings
            SET breakfast_date = NULL
            WHERE breakfast_date IS NOT NULL
            AND (breakfast IS NULL OR breakfast = '' OR breakfast = 'None')
        ");
        $updateBookings->execute();
        $bookingsUpdated = $updateBookings->rowCount();
        echo "   ✓ Cleaned {$bookingsUpdated} record(s) in bookings table\n\n";
        
        // Clean up reports table
        echo "4. Cleaning reports table...\n";
        $updateReports = $conn->prepare("
            UPDATE reports
            SET breakfast_date = NULL
            WHERE breakfast_date IS NOT NULL
            AND (breakfast IS NULL OR breakfast = '' OR breakfast = 'None')
        ");
        $updateReports->execute();
        $reportsUpdated = $updateReports->rowCount();
        echo "   ✓ Cleaned {$reportsUpdated} record(s) in reports table\n\n";
        
        echo "=== CLEANUP COMPLETED ===\n";
        echo "Summary:\n";
        echo "  - Bookings cleaned: {$bookingsUpdated}\n";
        echo "  - Reports cleaned: {$reportsUpdated}\n";
        echo "  - Now breakfast_date will only be set when breakfast is actually selected\n";
    } else {
        echo "   ✓ No incorrect data found. Database is clean!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
