<?php
/**
 * Diagnostic script to check breakfast_date synchronization between bookings and reports
 */

require_once 'config.php';

echo "===========================================\n";
echo "BREAKFAST DATE SYNC DIAGNOSTIC\n";
echo "===========================================\n\n";

try {
    // Check recent bookings with breakfast
    echo "1. Checking recent bookings with breakfast...\n";
    $stmt = $conn->query("
        SELECT 
            b.id,
            b.booking_id,
            b.guest_name,
            b.breakfast,
            b.breakfast_date as booking_breakfast_date,
            r.breakfast_date as report_breakfast_date
        FROM bookings b
        LEFT JOIN reports r ON b.booking_id = r.booking_id
        WHERE b.breakfast IS NOT NULL 
        AND b.breakfast != '' 
        AND b.breakfast != 'None'
        ORDER BY b.id DESC
        LIMIT 10
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        echo "\nFound " . count($results) . " recent bookings with breakfast:\n\n";
        
        $syncedCount = 0;
        $missingInReports = 0;
        
        foreach ($results as $row) {
            $booking_date = $row['booking_breakfast_date'];
            $report_date = $row['report_breakfast_date'];
            
            // Determine sync status
            if ($booking_date === $report_date) {
                $sync_status = ($booking_date === null) ? 'BOTH NULL' : 'SYNCED';
            } elseif ($booking_date !== null && $report_date === null) {
                $sync_status = 'MISSING IN REPORTS';
            } elseif ($booking_date === null && $report_date !== null) {
                $sync_status = 'MISSING IN BOOKINGS';
            } else {
                $sync_status = 'DIFFERENT VALUES';
            }
            
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Booking ID: {$row['booking_id']} (DB ID: {$row['id']})\n";
            echo "Guest: {$row['guest_name']}\n";
            echo "Breakfast: {$row['breakfast']}\n";
            echo "Booking breakfast_date: " . ($booking_date ?? 'NULL') . "\n";
            echo "Report breakfast_date:  " . ($report_date ?? 'NULL') . "\n";
            echo "Status: {$sync_status}\n";
            
            if ($sync_status === 'SYNCED' || $sync_status === 'BOTH NULL') {
                echo "✓ OK\n";
                $syncedCount++;
            } elseif ($sync_status === 'MISSING IN REPORTS') {
                echo "✗ ISSUE: breakfast_date missing in reports table!\n";
                $missingInReports++;
            } else {
                echo "✗ ISSUE: breakfast_date values don't match!\n";
            }
        }
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Summary
        echo "SUMMARY:\n";
        echo "Total checked: " . count($results) . "\n";
        echo "Synced: {$syncedCount}\n";
        echo "Missing in reports: {$missingInReports}\n";
        
        if ($missingInReports > 0) {
            echo "\n⚠️  WARNING: {$missingInReports} booking(s) have breakfast_date in bookings but NOT in reports!\n";
            echo "\nPossible causes:\n";
            echo "1. Old bookings created before breakfast_date was added to reports INSERT\n";
            echo "2. Reports INSERT is failing silently\n";
            echo "3. breakfast_date binding in reports INSERT is missing\n";
            
            echo "\n\nDo you want to sync these records? (This will copy breakfast_date from bookings to reports)\n";
            echo "Run: php sync_breakfast_date_to_reports.php\n";
        } else {
            echo "\n✓ All records are properly synced!\n";
        }
        
    } else {
        echo "No bookings with breakfast found.\n";
    }
    
    echo "\n";
    
    // Check if breakfast_date column exists in both tables
    echo "2. Verifying column existence...\n";
    $bookingsColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'breakfast_date'")->fetch();
    $reportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'breakfast_date'")->fetch();
    
    echo "   Bookings table: " . ($bookingsColumn ? "✓ breakfast_date exists" : "✗ breakfast_date missing") . "\n";
    echo "   Reports table:  " . ($reportsColumn ? "✓ breakfast_date exists" : "✗ breakfast_date missing") . "\n";
    
    echo "\n";
    
    // Check most recent booking
    echo "3. Checking most recent booking created...\n";
    $recentStmt = $conn->query("
        SELECT 
            b.id,
            b.booking_id,
            b.guest_name,
            b.breakfast,
            b.breakfast_date as booking_breakfast_date,
            r.breakfast_date as report_breakfast_date,
            b.created_at
        FROM bookings b
        LEFT JOIN reports r ON b.booking_id = r.booking_id
        ORDER BY b.id DESC
        LIMIT 1
    ");
    
    $recent = $recentStmt->fetch(PDO::FETCH_ASSOC);
    if ($recent) {
        echo "   Most recent booking ID: {$recent['booking_id']}\n";
        echo "   Guest: {$recent['guest_name']}\n";
        echo "   Breakfast: " . ($recent['breakfast'] ?? 'None') . "\n";
        echo "   Booking breakfast_date: " . ($recent['booking_breakfast_date'] ?? 'NULL') . "\n";
        echo "   Report breakfast_date: " . ($recent['report_breakfast_date'] ?? 'NULL') . "\n";
        
        if ($recent['breakfast'] && $recent['breakfast'] !== 'None') {
            if ($recent['booking_breakfast_date'] && $recent['report_breakfast_date']) {
                echo "   ✓ Both tables have breakfast_date\n";
            } elseif ($recent['booking_breakfast_date'] && !$recent['report_breakfast_date']) {
                echo "   ✗ Reports table is MISSING breakfast_date\n";
            } elseif (!$recent['booking_breakfast_date'] && !$recent['report_breakfast_date']) {
                echo "   ✓ Both tables correctly have NULL (old booking before implementation)\n";
            }
        }
    }
    
    echo "\n===========================================\n";
    echo "DIAGNOSTIC COMPLETE\n";
    echo "===========================================\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
