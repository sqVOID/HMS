<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config.php';

ob_start();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $response['message'] = 'Invalid request data!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        $response['message'] = 'Invalid receive order ID!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    try {
        // Ensure columns exist
        try {
            $conn->exec("ALTER TABLE purchase_orders ADD COLUMN received_status VARCHAR(50) DEFAULT 'Pending'");
        } catch(PDOException $e) {
            // Column might already exist
        }
        try {
            $conn->exec("ALTER TABLE purchase_orders ADD COLUMN date_received DATE DEFAULT NULL");
        } catch(PDOException $e) {
            // Column might already exist
        }
        
        // Get the purchase order
        $stmt = $conn->prepare("SELECT * FROM purchase_orders WHERE id = :id AND status = 'Approved'");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $response['message'] = 'Purchase order not found or not approved!';
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        // Check if already received or declined
        $currentStatus = $order['received_status'] ?? 'Pending';
        if ($currentStatus === 'Received') {
            $response['message'] = 'This order has already been received and cannot be declined!';
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        // Update purchase order status to Declined
        $updateOrderStmt = $conn->prepare("UPDATE purchase_orders SET received_status = 'Declined' WHERE id = :id");
        $updateOrderStmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($updateOrderStmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Receive order declined successfully!';
        } else {
            $response['message'] = 'Failed to decline receive order!';
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

ob_clean();
echo json_encode($response);
exit;
?>

