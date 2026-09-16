<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once 'config.php';

    $bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

    if ($bookingId <= 0) {
        throw new Exception('Invalid booking ID: ' . ($_GET['booking_id'] ?? 'not provided'));
    }

    // First, check if the cancellation_requests table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'cancellation_requests'");
    if ($tableCheck->num_rows === 0) {
        throw new Exception('cancellation_requests table does not exist');
    }

    // Get the cancellation request for this booking - using id instead of created_at
    $stmt = $conn->prepare("SELECT * FROM cancellation_requests WHERE booking_id = ? AND status = 'Pending' ORDER BY id DESC LIMIT 1");
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $bookingId);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'request' => $request,
            'debug' => [
                'booking_id' => $bookingId,
                'rows_found' => $result->num_rows
            ]
        ]);
    } else {
        // Check if there are ANY cancellation requests for this booking (regardless of status)
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count, GROUP_CONCAT(DISTINCT status) as statuses FROM cancellation_requests WHERE booking_id = ?");
        $checkStmt->bind_param("i", $bookingId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkData = $checkResult->fetch_assoc();
        
        echo json_encode([
            'success' => false,
            'message' => 'No pending cancellation request found',
            'debug' => [
                'booking_id' => $bookingId,
                'total_requests_for_booking' => $checkData['count'],
                'statuses_found' => $checkData['statuses']
            ]
        ]);
        $checkStmt->close();
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'booking_id_param' => $_GET['booking_id'] ?? 'not set',
            'error_line' => $e->getLine(),
            'file' => basename(__FILE__)
        ]
    ]);
}
?>
