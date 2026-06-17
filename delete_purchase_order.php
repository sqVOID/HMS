<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config.php';

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
            $response['success'] = true;
            $response['message'] = 'Purchase order deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete purchase order!';
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

ob_clean();
echo json_encode($response);
exit;
?>

