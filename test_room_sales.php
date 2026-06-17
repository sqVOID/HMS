<?php
// Mock session to bypass authentication for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';

$_GET['range'] = 'custom';
$_GET['start_date'] = '2026-06-04';
$_GET['end_date'] = '2026-06-04';

echo "=== MOCK CALL TO get_room_sales_tracking.php FOR TODAY ===\n";
ob_start();
include 'get_room_sales_tracking.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Decode Error: " . json_last_error_msg() . "\n";
    echo "Raw output:\n" . $output . "\n";
} else {
    print_r($response);
}
?>
