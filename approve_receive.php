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
        $requestedItems = $data['items'] ?? [];
        if (!is_array($requestedItems) || empty($requestedItems)) {
            $response['message'] = 'Please select at least one item to receive!';
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
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
        try {
            $conn->exec("ALTER TABLE purchase_orders ADD COLUMN received_items TEXT DEFAULT NULL");
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
        
        // Decode items
        $orderItems = json_decode($order['items'], true);
        if (!is_array($orderItems) || empty($orderItems)) {
            $response['message'] = 'No items found in purchase order!';
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        // Index purchase order items by name for quick validation
        $orderItemsByName = [];
        foreach ($orderItems as $item) {
            $name = trim($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $orderItemsByName[$name] = $item;
        }
        
        // Sanitize requested items and make sure they exist in PO
        $itemsToReceive = [];
        foreach ($requestedItems as $reqItem) {
            $name = trim($reqItem['name'] ?? '');
            $quantity = intval($reqItem['quantity'] ?? 0);
            $price = floatval($reqItem['price'] ?? 0);
            
            if ($name === '' || $quantity <= 0) {
                continue;
            }
            if (!isset($orderItemsByName[$name])) {
                continue;
            }
            
            $originalQuantity = intval($orderItemsByName[$name]['quantity'] ?? 0);
            $originalPrice = floatval($orderItemsByName[$name]['price'] ?? 0);
            
            // Prevent receiving more than ordered
            if ($originalQuantity > 0 && $quantity > $originalQuantity) {
                $quantity = $originalQuantity;
            }
            
            $itemsToReceive[] = [
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price > 0 ? $price : $originalPrice,
                'image_path' => $orderItemsByName[$name]['image_path'] ?? ''
            ];
        }
        
        if (empty($itemsToReceive)) {
            $response['message'] = 'Please enter a valid quantity before receiving.';
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        // Ensure inventory table exists
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS inventory (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_name VARCHAR(255) NOT NULL,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    stock INT NOT NULL DEFAULT 0,
                    product_image VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch(PDOException $e) {
            // Table might already exist
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Process each confirmed item
            foreach ($itemsToReceive as $item) {
                $product_name = $item['name'];
                $quantity = $item['quantity'];
                $price = $item['price'];
                
                if (empty($product_name) || $quantity <= 0) {
                    continue;
                }
                
                // Check if item exists in inventory
                $checkStmt = $conn->prepare("SELECT id, stock FROM inventory WHERE product_name = :product_name");
                $checkStmt->bindParam(':product_name', $product_name);
                $checkStmt->execute();
                $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingItem) {
                    // Update existing item - add quantity to stock
                    $updateStmt = $conn->prepare("UPDATE inventory SET stock = stock + :quantity, price = :price WHERE id = :id");
                    $updateStmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                    $updateStmt->bindParam(':price', $price);
                    $updateStmt->bindParam(':id', $existingItem['id'], PDO::PARAM_INT);
                    $updateStmt->execute();
                } else {
                    // Insert new item - use image_path from purchase order item if available
                    $imagePath = $item['image_path'] ?? '';
                    
                    // If no image in PO item, try to get from additem_list
                    if (empty($imagePath)) {
                        $imageStmt = $conn->prepare("SELECT image_path FROM additem_list WHERE product_name = :product_name LIMIT 1");
                        $imageStmt->bindParam(':product_name', $product_name);
                        $imageStmt->execute();
                        $imageResult = $imageStmt->fetch(PDO::FETCH_ASSOC);
                        if ($imageResult && !empty($imageResult['image_path'])) {
                            $imagePath = $imageResult['image_path'];
                        }
                    }
                    
                    $insertStmt = $conn->prepare("INSERT INTO inventory (product_name, price, stock, product_image) VALUES (:product_name, :price, :stock, :product_image)");
                    $insertStmt->bindParam(':product_name', $product_name);
                    $insertStmt->bindParam(':price', $price);
                    $insertStmt->bindParam(':stock', $quantity, PDO::PARAM_INT);
                    $insertStmt->bindParam(':product_image', $imagePath);
                    $insertStmt->execute();
                }
            }
            
            // Update purchase order status to Received and store received items
            $receivedItemsJson = json_encode($itemsToReceive);
            $updateOrderStmt = $conn->prepare("UPDATE purchase_orders SET received_status = 'Received', date_received = CURDATE(), received_items = :received_items WHERE id = :id");
            $updateOrderStmt->bindParam(':received_items', $receivedItemsJson);
            $updateOrderStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $updateOrderStmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Receive order approved successfully! Items have been added to inventory.';
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            throw $e;
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

ob_clean();
echo json_encode($response);
exit;
?>

