<?php
// Script to sync check_out_at from reports table to bookings table
// Only for bookings with status "Checked Out"
require_once 'config.php';

try {
    echo "Starting sync of check_out_at from reports to bookings...\n\n";
    
    // Update bookings.check_out_at with reports.checked_out_at
    // Only for bookings where status = 'Checked Out'
    $sql = "UPDATE bookings b
            INNER JOIN reports r ON b.booking_id COLLATE utf8mb4_unicode_ci = r.booking_id COLLATE utf8mb4_unicode_ci
            SET b.check_out_at = r.checked_out_at
            WHERE b.status = 'Checked Out'
            AND r.checked_out_at IS NOT NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $rowCount = $stmt->rowCount();
    
    echo "SUCCESS: Updated {$rowCount} booking(s) with check_out_at data from reports table.\n";
    echo "Only bookings with status 'Checked Out' were updated.\n\n";
    
    // Display some sample results
    echo "Sample of updated records:\n";
    echo str_repeat("-", 80) . "\n";
    
    $sampleSql = "SELECT 
                    b.id,
                    b.booking_id,
                    b.guest_name,
                    b.status,
                    b.check_out,
                    b.check_out_at,
                    r.checked_out_at as report_checked_out_at
                  FROM bookings b
                  INNER JOIN reports r ON b.booking_id COLLATE utf8mb4_unicode_ci = r.booking_id COLLATE utf8mb4_unicode_ci
                  WHERE b.status = 'Checked Out'
                  AND b.check_out_at IS NOT NULL
                  ORDER BY b.id DESC
                  LIMIT 10";
    
    $sampleStmt = $conn->prepare($sampleSql);
    $sampleStmt->execute();
    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($samples) > 0) {
        printf("%-8s %-20s %-30s %-15s %-20s %-20s\n", 
            "ID", "Booking ID", "Guest Name", "Status", "Planned Check-Out", "Actual Check-Out");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($samples as $row) {
            printf("%-8s %-20s %-30s %-15s %-20s %-20s\n",
                $row['id'],
                $row['booking_id'],
                substr($row['guest_name'], 0, 28),
                $row['status'],
                $row['check_out'] ?? 'N/A',
                $row['check_out_at'] ?? 'N/A'
            );
        }
    } else {
        echo "No records found with check_out_at data.\n";
    }
    
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "Sync completed successfully!\n";
    
    $conn = null;
    
} catch (PDOException $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
