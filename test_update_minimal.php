<?php
// Minimal test version to isolate the issue
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors as HTML
ini_set('log_errors', 1);

session_start();
ob_start();

header('Content-Type: application/json');

try {
    // Test database connection
    $conn = new mysqli('localhost', 'root', '', 'hotel_management');
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Test if columns exist
    $testQuery = "SELECT payment_status_instapay, deposit_instapay, downpayment_instapay FROM bookings LIMIT 1";
    $result = $conn->query($testQuery);
    
    if (!$result) {
        throw new Exception('Column test failed: ' . $conn->error);
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Database connection and columns OK',
        'columns_exist' => true
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>