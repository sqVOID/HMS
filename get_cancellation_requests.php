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

try {
    require_once 'config.php';

    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $date = $_GET['date'] ?? null;

    // Build query based on status filter
    // Also pull base duration and extension fields from bookings so we can
    // display accurate extended duration text in the Cancellation page.
    $sql = "SELECT 
                cr.*,
                b.booking_id AS booking_number,
                b.duration AS base_duration,
                b.duration_unit,
                b.extend_hours,
                b.extend_minutes
            FROM cancellation_requests cr
            LEFT JOIN bookings b ON cr.booking_id = b.id
            WHERE 1=1";

    $params = [];

    if ($status !== 'all') {
        $sql .= " AND cr.status = :status";
        $params['status'] = $status;
    }

    // Add date filter if provided (filter by requested_at date)
    if ($date) {
        $sql .= " AND DATE(cr.requested_at) = :date";
        $params['date'] = $date;
    }

    $sql .= " ORDER BY cr.requested_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $requests = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $requests[] = $row;
    }

    // Clear output buffer
    if (ob_get_length()) ob_clean();

    echo json_encode(['success' => true, 'data' => $requests]);
    
    ob_end_flush();

} catch (PDOException $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Get cancellation requests database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
    ob_end_flush();
    
} catch (Exception $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Get cancellation requests error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    
    ob_end_flush();
}
?>
