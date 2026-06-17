<?php
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DATABASE CLEANUP SCRIPT: FUTURE CHECKOUT DATES ===\n\n";

try {
    // 1. Check reports table
    $selectStmt = $conn->prepare("
        SELECT id, booking_id, room_id, guest_name, checked_out_at, updated_at 
        FROM reports 
        WHERE status = 'Checked Out' AND checked_out_at > updated_at
    ");
    $selectStmt->execute();
    $reportsToFix = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($reportsToFix) . " incorrect records in 'reports' table:\n";
    foreach ($reportsToFix as $row) {
        echo "- Booking ID: {$row['booking_id']}, Room: {$row['room_id']}, Guest: {$row['guest_name']}, Checked Out At: {$row['checked_out_at']} (Target Fix: {$row['updated_at']})\n";
    }

    if (count($reportsToFix) > 0) {
        $updateStmt = $conn->prepare("
            UPDATE reports 
            SET checked_out_at = updated_at 
            WHERE status = 'Checked Out' AND checked_out_at > updated_at
        ");
        $updateStmt->execute();
        echo "\n✓ Successfully repaired " . $updateStmt->rowCount() . " records in 'reports' table!\n";
    } else {
        echo "\nNo records in 'reports' table needed repair.\n";
    }

    echo "\n----------------------------------------\n\n";

    // 2. Check bookings table (just in case)
    // Check if column checked_out_at exists in bookings table first
    $chkCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'checked_out_at'");
    if ($chkCol->rowCount() > 0) {
        $selectBStmt = $conn->prepare("
            SELECT id, booking_id, room_id, guest_name, checked_out_at, updated_at 
            FROM bookings 
            WHERE status = 'Checked Out' AND checked_out_at > updated_at
        ");
        $selectBStmt->execute();
        $bookingsToFix = $selectBStmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Found " . count($bookingsToFix) . " incorrect records in 'bookings' table:\n";
        foreach ($bookingsToFix as $row) {
            echo "- Booking ID: {$row['booking_id']}, Room: {$row['room_id']}, Guest: {$row['guest_name']}, Checked Out At: {$row['checked_out_at']} (Target Fix: {$row['updated_at']})\n";
        }

        if (count($bookingsToFix) > 0) {
            $updateBStmt = $conn->prepare("
                UPDATE bookings 
                SET checked_out_at = updated_at 
                WHERE status = 'Checked Out' AND checked_out_at > updated_at
            ");
            $updateBStmt->execute();
            echo "\n✓ Successfully repaired " . $updateBStmt->rowCount() . " records in 'bookings' table!\n";
        } else {
            echo "\nNo records in 'bookings' table needed repair.\n";
        }
    } else {
        echo "The 'bookings' table does not contain a 'checked_out_at' column.\n";
    }

    echo "\n=== CLEANUP COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "\nERROR running cleanup script: " . $e->getMessage() . "\n";
}
?>
