<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

try {
    require_once 'config.php';

    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    $bookingId = isset($input['booking_id']) ? intval($input['booking_id']) : 0;

    if ($bookingId <= 0) {
        throw new Exception('Invalid booking ID');
    }

    // Update booking cancellation_status back to 'None'
    $updateStmt = $conn->prepare("UPDATE bookings SET cancellation_status = 'None' WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception('Failed to prepare booking update: ' . $conn->error);
    }
    
    $updateStmt->bind_param("i", $bookingId);
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update booking: ' . $updateStmt->error);
    }
    $updateStmt->close();

    // Delete the cancellation request from the database
    $deleteStmt = $conn->prepare("DELETE FROM cancellation_requests WHERE booking_id = ? AND status = 'Pending'");
    if (!$deleteStmt) {
        throw new Exception('Failed to prepare cancellation request deletion: ' . $conn->error);
    }
    
    $deleteStmt->bind_param("i", $bookingId);
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete cancellation request: ' . $deleteStmt->error);
    }
    
    $rowsDeleted = $deleteStmt->affected_rows;
    $deleteStmt->close();
    
    if ($rowsDeleted === 0) {
        throw new Exception('No pending cancellation request found to withdraw');
    }

    $conn->close();

    ob_end_clean();

    echo json_encode([
        'success' => true,
        'message' => 'Cancellation request withdrawn successfully.',
        'rows_deleted' => $rowsDeleted
    ]);

} catch (Exception $e) {
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
