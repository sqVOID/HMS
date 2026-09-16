<?php
// Simple test file to check database connection and query
header('Content-Type: application/json');

try {
    require_once 'config.php';

    // Test query to get all booking IDs
    $query = "SELECT booking_id, room_id, guest_name FROM reports LIMIT 5";
    $stmt = $conn->query($query);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Database connected successfully',
        'sample_bookings' => $bookings
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
