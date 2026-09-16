<?php
// Sync additional_pet values from bookings to reports table for existing records

require_once 'config.php';

echo "Connected successfully\n\n";

// Find all reports where additional_pet is 0 or NULL but the booking has additional_pet > 0
$query = "
    SELECT r.id, r.booking_id, r.additional_pet as report_pet, b.additional_pet as booking_pet
    FROM reports r
    INNER JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
    WHERE (r.additional_pet IS NULL OR r.additional_pet = 0)
    AND b.additional_pet > 0
";

$stmt = $conn->query($query);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($result) > 0) {
    echo "Found " . count($result) . " reports that need to be updated:\n\n";
    
    $updateCount = 0;
    foreach ($result as $row) {
        echo "Report ID: " . $row['id'] . " | Booking ID: " . $row['booking_id'] . "\n";
        echo "  Current additional_pet in reports: " . ($row['report_pet'] ?? 'NULL') . "\n";
        echo "  Should be (from bookings): " . $row['booking_pet'] . "\n";
        
        // Update the report
        $updateStmt = $conn->prepare("UPDATE reports SET additional_pet = :booking_pet WHERE id = :id");
        $updateStmt->bindParam(':booking_pet', $row['booking_pet'], PDO::PARAM_INT);
        $updateStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            echo "  ✓ Updated successfully!\n\n";
            $updateCount++;
        } else {
            echo "  ✗ Error updating\n\n";
        }
    }
    
    echo "\n=================================\n";
    echo "Summary: Updated $updateCount out of " . count($result) . " reports\n";
    echo "=================================\n";
} else {
    echo "No reports found that need updating. All additional_pet values are already in sync!\n";
}

echo "\nDone!\n";
?>
