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
        $response['message'] = 'Invalid purchase order ID!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Check if this is just a status update (approve/decline)
    if (isset($data['status']) && !isset($data['requestor'])) {
        $status = $data['status'];
        $dateApproved = null;
        
        if ($status === 'Approved') {
            $dateApproved = date('Y-m-d');
        }
        
        try {
            if ($dateApproved) {
                $stmt = $conn->prepare("
                    UPDATE purchase_orders 
                    SET status = :status, date_approved = :date_approved 
                    WHERE id = :id
                ");
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':date_approved', $dateApproved);
                $stmt->bindParam(':id', $id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE purchase_orders 
                    SET status = :status, date_approved = NULL 
                    WHERE id = :id
                ");
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $id);
            }
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Purchase order status updated successfully!';
            } else {
                $response['message'] = 'Failed to update purchase order!';
            }
        } catch(PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    } else {
        // Full update
        $requestor = trim($data['requestor'] ?? '');
        $po_date = trim($data['po_date'] ?? '');
        $description = trim($data['description'] ?? '');
        $address = trim($data['address'] ?? '');
        $items = $data['items'] ?? [];
        
        // Validate inputs
        if (empty($requestor) || empty($po_date) || empty($description) || empty($items)) {
            $response['message'] = 'All required fields must be filled!';
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        // Calculate total
        $total = 0;
        foreach ($items as $item) {
            $quantity = intval($item['quantity'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            $total += $quantity * $price;
        }
        
        try {
            $itemsJson = json_encode($items);
            
            $stmt = $conn->prepare("
                UPDATE purchase_orders 
                SET requestor = :requestor, 
                    po_date = :po_date, 
                    description = :description, 
                    address = :address, 
                    items = :items, 
                    total = :total
                WHERE id = :id
            ");
            
            $stmt->bindParam(':requestor', $requestor);
            $stmt->bindParam(':po_date', $po_date);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':items', $itemsJson);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Purchase order updated successfully!';
            } else {
                $response['message'] = 'Failed to update purchase order!';
            }
        } catch(PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

ob_clean();
echo json_encode($response);
exit;
?>

