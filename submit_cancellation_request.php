<?php
// Start output buffering first
ob_start();

// Start session before any output
session_start();

// Set error reporting but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendResponse($success, $message) {
    // Clean any output that might have been generated
    if (ob_get_length()) ob_clean();
    
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    
    ob_end_flush();
    exit;
}

try {
    // Include database configuration
    if (!file_exists('config.php')) {
        throw new Exception('Configuration file not found');
    }
    
    require_once 'config.php';
    
    if (!isset($conn)) {
        throw new Exception('Database connection not established');
    }

    // Get JSON input
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        throw new Exception('No input data received');
    }
    
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    // Extract and validate inputs
    $bookingId = isset($input['booking_id']) ? intval($input['booking_id']) : 0;
    $reason = isset($input['reason']) ? trim($input['reason']) : '';
    $refundAmount = isset($input['refund_amount']) ? floatval($input['refund_amount']) : 0;
    $amountDue = isset($input['amount_due']) ? floatval($input['amount_due']) : 0;
    $amountPaid = isset($input['amount_paid']) ? floatval($input['amount_paid']) : 0;
    $totalAmountFromFrontend = isset($input['total_amount']) ? floatval($input['total_amount']) : 0;

    // Validate inputs
    if ($bookingId <= 0) {
        throw new Exception('Invalid booking ID');
    }

    if (empty($reason)) {
        throw new Exception('Cancellation reason is required');
    }

    // Get booking details using PDO
    $stmt = $conn->prepare("SELECT guest_name, room_type, room_id, check_in, check_out, duration, duration_unit, 
                                   total_amount, deposit, downpayment_amount, extended_duration 
                            FROM bookings WHERE id = :booking_id");
    
    $stmt->execute(['booking_id' => $bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    // Calculate duration display (include extended duration if exists)
    $durationUnit = isset($booking['duration_unit']) ? $booking['duration_unit'] : 'hours';
    $baseDuration = intval($booking['duration']);
    $extendedDuration = isset($booking['extended_duration']) ? intval($booking['extended_duration']) : 0;
    $totalDuration = $baseDuration + $extendedDuration;
    $duration = $totalDuration . ' ' . ($durationUnit === 'night' ? 'Night(s)' : 'Hour(s)');

    // Get current user
    $requestedBy = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown';

    // Use the total_amount from frontend (calculated in modal) instead of booking table
    $totalAmount = $totalAmountFromFrontend > 0 ? $totalAmountFromFrontend : floatval($booking['total_amount']);
    $paymentAmount = floatval($booking['deposit']);
    $reservationAmount = floatval($booking['downpayment_amount']);

    // Insert cancellation request using PDO
    $insertStmt = $conn->prepare("INSERT INTO cancellation_requests 
                            (booking_id, guest_name, room_type, room_id, check_in, check_out, duration, 
                             total_amount, payment_amount, reservation_amount, refund_amount, amount_due, amount_paid, reason, requested_by) 
                            VALUES (:booking_id, :guest_name, :room_type, :room_id, :check_in, :check_out, :duration, 
                                    :total_amount, :payment_amount, :reservation_amount, :refund_amount, :amount_due, :amount_paid, :reason, :requested_by)");

    $insertStmt->execute([
        'booking_id' => $bookingId,
        'guest_name' => $booking['guest_name'],
        'room_type' => $booking['room_type'],
        'room_id' => $booking['room_id'],
        'check_in' => $booking['check_in'],
        'check_out' => $booking['check_out'],
        'duration' => $duration,
        'total_amount' => $totalAmount,
        'payment_amount' => $paymentAmount,
        'reservation_amount' => $reservationAmount,
        'refund_amount' => $refundAmount,
        'amount_due' => $amountDue,
        'amount_paid' => $amountPaid,
        'reason' => $reason,
        'requested_by' => $requestedBy
    ]);
    
    // Update booking cancellation_status to "Pending"
    $updateStmt = $conn->prepare("UPDATE bookings SET cancellation_status = 'Pending' WHERE id = :booking_id");
    $updateStmt->execute(['booking_id' => $bookingId]);

    // Send success response
    sendResponse(true, 'Cancellation request submitted successfully. Awaiting admin approval.');

} catch (PDOException $e) {
    // Log the database error
    error_log('Cancellation request database error: ' . $e->getMessage());
    sendResponse(false, 'Database error: ' . $e->getMessage());
    
} catch (Exception $e) {
    // Log the error
    error_log('Cancellation request error: ' . $e->getMessage());
    sendResponse(false, $e->getMessage());
    
} catch (Error $e) {
    // Catch any PHP errors
    error_log('Cancellation request PHP error: ' . $e->getMessage());
    sendResponse(false, 'A system error occurred. Please try again.');
}
?>
