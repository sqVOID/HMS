<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
$_u_first = trim($_SESSION['first_name'] ?? ''); $_u_last = trim($_SESSION['last_name'] ?? '');
$_actor = ($_u_first !== '' || $_u_last !== '') ? trim($_u_first . ' ' . $_u_last) : trim($_SESSION['username'] ?? 'Unknown');

ob_start();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['id'])) {
        $response['message'] = 'Invalid request data!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    $id = intval($data['id']);
    
    if ($id <= 0) {
        $response['message'] = 'Invalid purchase order ID!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            logActivity($conn, 'delete', 'PURCHASE_ORDER_DELETE',
                "Purchase order #{$id} deleted by {$_actor}",
                ['order_id' => $id, 'deleted_by' => $_actor]
            );
            $response['success'] = true;
            $response['message'] = 'Purchase order deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete purchase order!';
        }
    } catch(PDOException $e) {
        logError($conn, 'delete', 'PURCHASE_ORDER_DELETE', "Failed to delete PO #{$id}: " . $e->getMessage(), ['order_id' => $id, 'deleted_by' => $_actor]);
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

ob_clean();
echo json_encode($response);
exit;
?>

