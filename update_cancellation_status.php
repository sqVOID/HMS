<?php
// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Start session
session_start();

require_once 'config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$requestId = $input['request_id'] ?? null;
$status = $input['status'] ?? null; // 'Approved' or 'Rejected'
$adminNotes = $input['admin_notes'] ?? '';

// Validate inputs
if (!$requestId || !in_array($status, ['Approved', 'Rejected'])) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    ob_end_flush();
    exit;
}

// Get current user
$reviewedBy = $_SESSION['username'] ?? 'Admin';

// Get cancellation request details including reason and refund amount
$stmt = $conn->prepare("SELECT booking_id, reason, refund_amount FROM cancellation_requests WHERE id = :id");
$stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Cancellation request not found']);
    ob_end_flush();
    exit;
}

$bookingId = $request['booking_id'];
$cancellationReason = $request['reason'] ?? '';
$refundAmount = $request['refund_amount'] ?? 0;

// Start transaction
$conn->beginTransaction();

try {
    // Update cancellation request status
    $stmt = $conn->prepare("UPDATE cancellation_requests 
                           SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW(), admin_notes = :admin_notes 
                           WHERE id = :id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':reviewed_by', $reviewedBy);
    $stmt->bindParam(':admin_notes', $adminNotes);
    $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
    $stmt->execute();
    
    // If approved, save to reports then DELETE the booking
    if ($status === 'Approved') {
        // First, get the full booking row before deleting it
        // We need both the numeric primary key (id), the public booking_id/code,
        // and the extension fields so we can correctly update the existing row in reports.
        $getRoomStmt = $conn->prepare("
            SELECT room_id, booking_id, extend_hours, extend_minutes, extend_price 
            FROM bookings 
            WHERE id = :id
        ");
        $getRoomStmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
        $getRoomStmt->execute();
        $roomData = $getRoomStmt->fetch(PDO::FETCH_ASSOC);
        
        $roomId       = $roomData['room_id'] ?? null;
        $bookingCode  = $roomData['booking_id'] ?? null; // e.g. 'B-03/10/26-2077'
        $extendHours  = isset($roomData['extend_hours']) ? (int)$roomData['extend_hours'] : 0;
        $extendMinutes= isset($roomData['extend_minutes']) ? (int)$roomData['extend_minutes'] : 0;
        $extendPrice  = isset($roomData['extend_price']) ? (float)$roomData['extend_price'] : 0.0;
        
        // Ensure there is a corresponding Canceled record in reports table
        // so that Report.php counts cancellations correctly.

        // 1) Check if a reports row already exists for this booking.
        // NOTE: In the reports table, booking_id stores the *public* booking code
        // (e.g. 'B-03/10/26-2077'), not the numeric bookings.id. So we must look
        // it up using the same string code we just fetched from bookings.
        $checkStmt = $conn->prepare("SELECT id FROM reports WHERE booking_id = :booking_id LIMIT 1");
        $checkStmt->bindParam(':booking_id', $bookingCode);
        $checkStmt->execute();
        $existingReport = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingReport) {
            // Existing reports row – update status, canceled_at, and sync extension data from bookings
            $reportId = intval($existingReport['id']);

            // Update the existing reports row for this booking.
            // We avoid joining bookings and reports here to prevent collation
            // issues between the two booking_id columns.
            $updateReport = $conn->prepare("
                UPDATE reports
                SET status = 'Canceled',
                    canceled_at = NOW(),
                    extend_hours = :extend_hours,
                    extend_minutes = :extend_minutes,
                    extend_price = :extend_price,
                    cancellation_reason = :cancellation_reason,
                    refund_amount = :refund_amount
                WHERE booking_id = :booking_id
            ");
            $updateReport->bindParam(':extend_hours', $extendHours, PDO::PARAM_INT);
            $updateReport->bindParam(':extend_minutes', $extendMinutes, PDO::PARAM_INT);
            $updateReport->bindParam(':extend_price', $extendPrice);
            $updateReport->bindParam(':cancellation_reason', $cancellationReason);
            $updateReport->bindParam(':refund_amount', $refundAmount);
            $updateReport->bindParam(':booking_id', $bookingCode);
            $updateReport->execute();
        } else {
            // No reports row yet – insert one based on the current booking data
            $insertReports = $conn->prepare("
                INSERT INTO reports (
                    id, booking_id, room_id, room_type, guest_name, guest_type, contact_person_name, tin_number, request,
                    promo, breakfast, additional_guest, additional_pet, payment_status, reference_no, referral_name,
                    supplier, additional, paid_status,
                    check_in, check_out, duration, duration_unit, hours,
                    status, booking_type, room_image, hygiene_kit_used, hygiene_kit_price, total_amount, room_price, canceled_at,
                    extend_hours, extend_minutes, extend_price, cancellation_reason, refund_amount
                )
                SELECT 
                    id, booking_id, room_id, room_type, guest_name, guest_type, contact_person_name, tin_number, request,
                    promo, breakfast, additional_guest, additional_pet, payment_status, reference_no, referral_name,
                    supplier, additional, paid_status,
                    check_in, check_out, duration, duration_unit, hours,
                    'Canceled', booking_type, room_image, hygiene_kit_used, hygiene_kit_price, total_amount, room_price, NOW(),
                    extend_hours, extend_minutes, extend_price, :cancellation_reason, :refund_amount
                FROM bookings
                WHERE id = :id
            ");
            $insertReports->bindParam(':cancellation_reason', $cancellationReason);
            $insertReports->bindParam(':refund_amount', $refundAmount);
            $insertReports->bindParam(':id', $bookingId, PDO::PARAM_INT);
            $insertReports->execute();
        }
        
        // Update room status to 'Available' if it was occupied
        if ($roomId) {
            $stmt = $conn->prepare("UPDATE rooms SET status = 'Available' WHERE room_id = :room_id");
            $stmt->bindParam(':room_id', $roomId);
            $stmt->execute();
        }
        
        // Update booking status to 'Canceled' and store cancellation reason and refund amount
        $stmt = $conn->prepare("UPDATE bookings SET status = 'Canceled', cancellation_status = 'Approved', cancellation_reason = :cancellation_reason, refund_amount = :refund_amount WHERE id = :id");
        $stmt->bindParam(':cancellation_reason', $cancellationReason);
        $stmt->bindParam(':refund_amount', $refundAmount);
        $stmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // If rejected, restore booking to normal (set cancellation_status back to 'None')
    if ($status === 'Rejected') {
        $stmt = $conn->prepare("UPDATE bookings SET cancellation_status = 'None' WHERE id = :id");
        $stmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    $message = $status === 'Approved' 
        ? 'Cancellation approved. Booking has been cancelled.' 
        : 'Cancellation request rejected.';
    
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
    ob_end_flush();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollBack();
    
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Update cancellation status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    
    ob_end_flush();
}
?>
