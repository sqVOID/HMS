<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Ensure additem_list table exists
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS additem_list (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_name VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                image_path VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch(PDOException $e) {
        // Table might already exist
    }
    
    $stmt = $conn->prepare("
        SELECT
            id,
            product_name,
            price,
            image_path,
            created_at,
            updated_at
        FROM additem_list
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'items' => $items]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

