<?php
// Script to sync cancellation_reason and refund_amount from cancellation_requests to bookings/reports
// This is a one-time fix for existing cancelled bookings

header('Content-Type: application/json');

require_once 'config.php';

try {
    $conn->begin_transaction();
    
    // Get all approved cancellation requests
    $query = "SELECT cr.booking_id, cr.reason, cr.refund_amount, b.booking_id as booking_code
              FROM cancellation_requests cr
              LEFT JOIN bookings b ON cr.booking_id = b.id
              WHERE cr.status = 'Approved'";
    
    $result = $conn->query($query);
    
    $updated = 0;
    $errors = [];
    
    while ($row = $result->fetch_assoc()) {
        $bookingId = $row['booking_id'];
        $bookingCode = $row['booking_code'];
        $reason = $row['reason'];
        $refundAmount = $row['refund_amount'];
        
        // Update bookings table
        $stmt = $conn->prepare("UPDATE bookings SET cancellation_reason = ?, refund_amount = ? WHERE id = ?");
        $stmt->bind_param("sdi", $reason, $refundAmount, $bookingId);
        if ($stmt->execute()) {
            $updated++;
        } else {
            $errors[] = "Failed to update booking ID $bookingId: " . $stmt->error;
        }
        $stmt->close();
        
        // Update reports table if exists
        if ($bookingCode) {
            $stmt = $conn->prepare("UPDATE reports SET cancellation_reason = ?, refund_amount = ? WHERE booking_id = ?");
            $stmt->bind_param("sds", $reason, $refundAmount, $bookingCode);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully synced cancellation data for $updated bookings",
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
