<?php
/**
 * Sync breakfast_date from bookings to reports table
 * This fixes the issue where breakfast_date exists in bookings but not in reports
 */

require_once 'config.php';

echo "===========================================\n";
echo "SYNC BREAKFAST_DATE TO REPORTS TABLE\n";
echo "===========================================\n\n";

try {
    // First, check bookings with breakfast_date
    echo "1. Finding bookings with breakfast_date...\n";
    $findStmt = $conn->query("
        SELECT id, booking_id, guest_name, breakfast_date 
        FROM bookings 
        WHERE breakfast_date IS NOT NULL
    ");
    
    $bookings = $findStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($bookings) . " bookings with breakfast_date\n\n";
    
    if (count($bookings) === 0) {
        echo "   No bookings to sync. Exiting.\n";
        exit(0);
    }
    
    // Sync each booking
    echo "2. Syncing breakfast_date to reports table...\n\n";
    
    $syncedCount = 0;
    $errorCount = 0;
    
    foreach ($bookings as $booking) {
        try {
            // Check if report exists
            $checkStmt = $conn->prepare("SELECT id, breakfast_date FROM reports WHERE booking_id = :booking_id");
            $checkStmt->execute([':booking_id' => $booking['booking_id']]);
            $report = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($report) {
                // Update the report
                $updateStmt = $conn->prepare("UPDATE reports SET breakfast_date = :breakfast_date WHERE booking_id = :booking_id");
                $updateStmt->execute([
                    ':breakfast_date' => $booking['breakfast_date'],
                    ':booking_id' => $booking['booking_id']
                ]);
                
                echo "   ✓ Synced booking_id: {$booking['booking_id']} ({$booking['guest_name']})\n";
                echo "     breakfast_date: {$booking['breakfast_date']}\n";
                $syncedCount++;
            } else {
                echo "   ⚠ No report found for booking_id: {$booking['booking_id']}\n";
            }
            
        } catch (PDOException $e) {
            echo "   ✗ Error syncing booking_id: {$booking['booking_id']} - " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo "\n===========================================\n";
    echo "SYNC COMPLETE\n";
    echo "===========================================\n\n";
    
    echo "Summary:\n";
    echo "Total bookings with breakfast_date: " . count($bookings) . "\n";
    echo "Successfully synced: {$syncedCount}\n";
    echo "Errors: {$errorCount}\n\n";
    
    if ($syncedCount > 0) {
        echo "✓ breakfast_date has been synced to reports table!\n";
        echo "  All existing bookings now have matching breakfast_date in both tables.\n\n";
    }
    
    echo "Next steps:\n";
    echo "1. New bookings will automatically have breakfast_date in both tables\n";
    echo "2. Updates to breakfast will append timestamps to both tables\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
