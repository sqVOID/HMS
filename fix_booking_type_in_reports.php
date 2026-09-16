<?php
/**
 * Fix booking_type in reports table
 * This script updates reports where booking_type is 'Walk-in' but should be 'Reservation'
 * by checking if the corresponding booking in the bookings table has booking_type = 'Reservation'
 */

require_once 'config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'updated_count' => 0,
    'details' => []
];

try {
    // Check if reports table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkTable->rowCount() === 0) {
        $response['message'] = 'Reports table does not exist.';
        echo json_encode($response);
        exit;
    }

    // Check if bookings table exists
    $checkBookings = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkBookings->rowCount() === 0) {
        $response['message'] = 'Bookings table does not exist.';
        echo json_encode($response);
        exit;
    }

    // Check if booking_type column exists in both tables
    $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'booking_type'");
    $checkBookingsColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'booking_type'");
    
    if ($checkReportsColumn->rowCount() === 0 || $checkBookingsColumn->rowCount() === 0) {
        $response['message'] = 'booking_type column does not exist in one or both tables.';
        echo json_encode($response);
        exit;
    }

    // Find reports with mismatched booking_type
    // This query finds reports where the booking_type doesn't match the bookings table
    $findMismatchStmt = $conn->prepare("
        SELECT r.id, r.booking_id, r.booking_type as report_type, b.booking_type as booking_type
        FROM reports r
        INNER JOIN bookings b ON r.booking_id = b.booking_id
        WHERE r.booking_type != b.booking_type
    ");
    $findMismatchStmt->execute();
    $mismatches = $findMismatchStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($mismatches)) {
        $response['success'] = true;
        $response['message'] = 'No mismatches found. All booking_type values are correct.';
        echo json_encode($response);
        exit;
    }

    // Update each mismatched record
    $updateStmt = $conn->prepare("
        UPDATE reports 
        SET booking_type = :booking_type 
        WHERE id = :id
    ");

    foreach ($mismatches as $mismatch) {
        $updateStmt->bindParam(':booking_type', $mismatch['booking_type']);
        $updateStmt->bindParam(':id', $mismatch['id']);
        
        if ($updateStmt->execute()) {
            $response['updated_count']++;
            $response['details'][] = [
                'report_id' => $mismatch['id'],
                'booking_id' => $mismatch['booking_id'],
                'old_type' => $mismatch['report_type'],
                'new_type' => $mismatch['booking_type']
            ];
        }
    }

    $response['success'] = true;
    $response['message'] = "Successfully updated {$response['updated_count']} record(s) in reports table.";

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
