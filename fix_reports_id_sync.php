<?php
/**
 * Fix Reports ID Synchronization
 * 
 * This script syncs the id field in the reports table with the corresponding
 * id from the bookings table based on matching booking_id values.
 */

require_once 'config.php';

echo "<h2>Reports ID Synchronization Fix</h2>\n";
echo "<pre>\n";

try {
    // Check if reports table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkTable->rowCount() == 0) {
        echo "❌ Reports table does not exist!\n";
        exit;
    }
    
    echo "✓ Reports table found\n\n";
    
    // Get all reports that need ID sync
    $stmt = $conn->query("
        SELECT 
            r.id as report_id,
            r.booking_id,
            b.id as booking_id_numeric
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id = b.booking_id
        WHERE b.id IS NOT NULL
        ORDER BY r.id
    ");
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalRecords = count($records);
    
    echo "Found {$totalRecords} records to sync\n\n";
    
    if ($totalRecords == 0) {
        echo "✓ No records need syncing\n";
        exit;
    }
    
    // Show sample of mismatches
    echo "Sample of ID mismatches:\n";
    echo str_repeat("-", 80) . "\n";
    echo sprintf("%-15s | %-20s | %-15s | %-15s\n", 
        "Reports ID", "Booking ID", "Bookings ID", "Match?");
    echo str_repeat("-", 80) . "\n";
    
    $mismatchCount = 0;
    foreach (array_slice($records, 0, 10) as $record) {
        $match = ($record['report_id'] == $record['booking_id_numeric']) ? "✓" : "✗";
        if ($match == "✗") $mismatchCount++;
        
        echo sprintf("%-15s | %-20s | %-15s | %-15s\n",
            $record['report_id'],
            $record['booking_id'],
            $record['booking_id_numeric'],
            $match
        );
    }
    echo str_repeat("-", 80) . "\n\n";
    
    // Count total mismatches
    $totalMismatches = 0;
    foreach ($records as $record) {
        if ($record['report_id'] != $record['booking_id_numeric']) {
            $totalMismatches++;
        }
    }
    
    echo "Total mismatches found: {$totalMismatches} out of {$totalRecords}\n\n";
    
    if ($totalMismatches == 0) {
        echo "✓ All IDs are already in sync!\n";
        exit;
    }
    
    // Ask for confirmation
    echo "⚠️  WARNING: This will update the 'id' field in the reports table\n";
    echo "⚠️  to match the corresponding 'id' from the bookings table.\n\n";
    echo "Do you want to proceed? (yes/no): ";
    
    // For web execution, auto-proceed (comment out for CLI)
    $confirm = 'yes';
    
    // For CLI execution, uncomment this:
    // $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'yes') {
        echo "\n❌ Operation cancelled by user\n";
        exit;
    }
    
    echo "\n🔄 Starting ID synchronization...\n\n";
    
    $conn->beginTransaction();
    
    $updated = 0;
    $errors = 0;
    
    // Temporarily disable foreign key checks if needed
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    foreach ($records as $record) {
        if ($record['report_id'] != $record['booking_id_numeric']) {
            try {
                // Update the reports table id to match bookings table id
                $updateStmt = $conn->prepare("
                    UPDATE reports 
                    SET id = :new_id 
                    WHERE id = :old_id 
                    AND booking_id = :booking_id
                ");
                
                $updateStmt->execute([
                    ':new_id' => $record['booking_id_numeric'],
                    ':old_id' => $record['report_id'],
                    ':booking_id' => $record['booking_id']
                ]);
                
                $updated++;
                
                if ($updated % 10 == 0) {
                    echo "  Updated {$updated} records...\n";
                }
            } catch (PDOException $e) {
                $errors++;
                echo "  ✗ Error updating {$record['booking_id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    $conn->commit();
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "✓ Synchronization complete!\n";
    echo "  - Records updated: {$updated}\n";
    echo "  - Errors: {$errors}\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Verify the fix
    echo "Verifying the fix...\n";
    $verifyStmt = $conn->query("
        SELECT COUNT(*) as mismatch_count
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id = b.booking_id
        WHERE b.id IS NOT NULL AND r.id != b.id
    ");
    
    $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    $remainingMismatches = $verifyResult['mismatch_count'];
    
    if ($remainingMismatches == 0) {
        echo "✓ All IDs are now in sync!\n";
    } else {
        echo "⚠️  Warning: {$remainingMismatches} mismatches still remain\n";
    }
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "\n❌ Database error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>
