<?php
/**
 * Verify Reports ID Synchronization
 * 
 * This script checks if the id fields in bookings and reports tables are synchronized
 */

require_once 'config.php';

echo "<h2>Reports ID Verification</h2>\n";
echo "<pre>\n";

try {
    // Check if both tables exist
    $checkBookings = $conn->query("SHOW TABLES LIKE 'bookings'");
    $checkReports = $conn->query("SHOW TABLES LIKE 'reports'");
    
    if ($checkBookings->rowCount() == 0) {
        echo "❌ Bookings table does not exist!\n";
        exit;
    }
    
    if ($checkReports->rowCount() == 0) {
        echo "❌ Reports table does not exist!\n";
        exit;
    }
    
    echo "✓ Both tables exist\n\n";
    
    // Count total records in each table
    $bookingsCount = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch(PDO::FETCH_ASSOC)['count'];
    $reportsCount = $conn->query("SELECT COUNT(*) as count FROM reports")->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Total records:\n";
    echo "  - Bookings: {$bookingsCount}\n";
    echo "  - Reports: {$reportsCount}\n\n";
    
    // Find mismatches
    $mismatchStmt = $conn->query("
        SELECT 
            r.id as report_id,
            r.booking_id,
            b.id as booking_id_numeric,
            b.guest_name,
            b.status as booking_status,
            r.status as report_status
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id = b.booking_id
        WHERE b.id IS NOT NULL AND r.id != b.id
        ORDER BY r.id
        LIMIT 20
    ");
    
    $mismatches = $mismatchStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalMismatches = count($mismatches);
    
    // Get total count of mismatches
    $totalMismatchCount = $conn->query("
        SELECT COUNT(*) as count
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id = b.booking_id
        WHERE b.id IS NOT NULL AND r.id != b.id
    ")->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($totalMismatchCount == 0) {
        echo "✅ SUCCESS! All IDs are synchronized!\n";
        echo "   No mismatches found between bookings and reports tables.\n\n";
        
        // Show sample of matching records
        echo "Sample of correctly synchronized records:\n";
        echo str_repeat("-", 100) . "\n";
        echo sprintf("%-10s | %-20s | %-10s | %-25s | %-15s\n", 
            "ID", "Booking ID", "Match?", "Guest Name", "Status");
        echo str_repeat("-", 100) . "\n";
        
        $sampleStmt = $conn->query("
            SELECT 
                r.id,
                r.booking_id,
                b.id as booking_id_numeric,
                b.guest_name,
                r.status
            FROM reports r
            LEFT JOIN bookings b ON r.booking_id = b.booking_id
            WHERE b.id IS NOT NULL
            ORDER BY r.id DESC
            LIMIT 10
        ");
        
        $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($samples as $sample) {
            $match = ($sample['id'] == $sample['booking_id_numeric']) ? "✓ Yes" : "✗ No";
            echo sprintf("%-10s | %-20s | %-10s | %-25s | %-15s\n",
                $sample['id'],
                $sample['booking_id'],
                $match,
                substr($sample['guest_name'], 0, 25),
                $sample['status']
            );
        }
        echo str_repeat("-", 100) . "\n";
        
    } else {
        echo "⚠️  WARNING: Found {$totalMismatchCount} mismatched IDs!\n\n";
        
        echo "Sample of mismatched records (showing first 20):\n";
        echo str_repeat("-", 120) . "\n";
        echo sprintf("%-12s | %-20s | %-12s | %-25s | %-15s | %-15s\n", 
            "Reports ID", "Booking ID", "Bookings ID", "Guest Name", "Booking Status", "Report Status");
        echo str_repeat("-", 120) . "\n";
        
        foreach ($mismatches as $mismatch) {
            echo sprintf("%-12s | %-20s | %-12s | %-25s | %-15s | %-15s\n",
                $mismatch['report_id'],
                $mismatch['booking_id'],
                $mismatch['booking_id_numeric'],
                substr($mismatch['guest_name'], 0, 25),
                $mismatch['booking_status'],
                $mismatch['report_status']
            );
        }
        echo str_repeat("-", 120) . "\n\n";
        
        echo "📋 Next Steps:\n";
        echo "   1. Run fix_reports_id_sync.php to synchronize the IDs\n";
        echo "   2. Run this script again to verify the fix\n\n";
    }
    
    // Check for orphaned records (in reports but not in bookings)
    $orphanedStmt = $conn->query("
        SELECT COUNT(*) as count
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id = b.booking_id
        WHERE b.id IS NULL
    ");
    
    $orphanedCount = $orphanedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($orphanedCount > 0) {
        echo "\n📌 Note: Found {$orphanedCount} records in reports table that don't exist in bookings table.\n";
        echo "   This is normal for checked-out or deleted bookings.\n";
    }
    
} catch (PDOException $e) {
    echo "\n❌ Database error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>
