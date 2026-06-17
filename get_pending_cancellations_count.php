<?php
// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();

// Check if user is admin or super_admin
$access_level = $_SESSION['access_level'] ?? 'user';
if ($access_level !== 'admin' && $access_level !== 'super_admin') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'count' => 0]);
    ob_end_flush();
    exit;
}

try {
    require_once 'config.php';

    // Get count of pending cancellation requests using PDO
    $sql = "SELECT COUNT(*) as count FROM cancellation_requests WHERE status = 'Pending'";
    $stmt = $conn->query($sql);

    $count = 0;
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $count = (int)$row['count'];
        }
    }

    // Clear output buffer
    if (ob_get_length()) ob_clean();

    echo json_encode(['success' => true, 'count' => $count]);
    
    ob_end_flush();

} catch (PDOException $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Get pending cancellations count error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0, 'message' => 'Database error']);
    
    ob_end_flush();
    
} catch (Exception $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Get pending cancellations count error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0, 'message' => $e->getMessage()]);
    
    ob_end_flush();
}
?>
