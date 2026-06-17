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
    
    $requestor = trim($data['requestor'] ?? '');
    $po_date = trim($data['po_date'] ?? '');
    $description = trim($data['description'] ?? '');
    $address = trim($data['address'] ?? '');
    $items = $data['items'] ?? [];
    
    // Validate inputs
    if (empty($requestor) || empty($po_date) || empty($items)) {
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
    
    // Ensure purchase_orders table exists
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS purchase_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                po_number VARCHAR(50) NOT NULL UNIQUE,
                requestor VARCHAR(255) NOT NULL,
                po_date DATE NOT NULL,
                description TEXT NOT NULL,
                address VARCHAR(255) DEFAULT NULL,
                items JSON NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                status VARCHAR(50) DEFAULT 'Pending',
                date_approved DATE DEFAULT NULL,
                received_status VARCHAR(50) DEFAULT 'Pending',
                date_received DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Backfill missing columns for older tables
        $schemaPatches = [
            'received_status' => "ALTER TABLE purchase_orders ADD COLUMN received_status VARCHAR(50) DEFAULT 'Pending'",
            'date_received' => "ALTER TABLE purchase_orders ADD COLUMN date_received DATE DEFAULT NULL"
        ];

        foreach ($schemaPatches as $column => $sql) {
            $columnEscaped = str_replace('`', '``', $column);
            $checkSql = "SHOW COLUMNS FROM purchase_orders LIKE '{$columnEscaped}'";
            $checkStmt = $conn->query($checkSql);
            if ($checkStmt->rowCount() === 0) {
                $conn->exec($sql);
            }
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Generate PO number
    try {
        $lastPOStmt = $conn->query("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
        $lastPO = $lastPOStmt->fetch(PDO::FETCH_ASSOC);
        
        $poNumber = 'MO - 0001';
        if ($lastPO && isset($lastPO['po_number'])) {
            $lastNumber = intval(str_replace('MO - ', '', $lastPO['po_number']));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $poNumber = 'MO - ' . $newNumber;
        }
    } catch(PDOException $e) {
        $poNumber = 'MO - ' . str_pad(1, 4, '0', STR_PAD_LEFT);
    }
    
    // Insert into database
    try {
        $itemsJson = json_encode($items);
        
        $stmt = $conn->prepare("
            INSERT INTO purchase_orders (po_number, requestor, po_date, description, address, items, total, status, received_status)
            VALUES (:po_number, :requestor, :po_date, :description, :address, :items, :total, 'Pending', 'Pending')
        ");
        
        $stmt->bindParam(':po_number', $poNumber);
        $stmt->bindParam(':requestor', $requestor);
        $stmt->bindParam(':po_date', $po_date);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':items', $itemsJson);
        $stmt->bindParam(':total', $total);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Purchase order added successfully!';
            $response['po_number'] = $poNumber;
        } else {
            $response['message'] = 'Failed to add purchase order!';
        }
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            $response['message'] = 'PO number already exists!';
        } else {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

ob_clean();
echo json_encode($response);
exit;
?>

