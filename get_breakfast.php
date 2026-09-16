<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Check if breakfast table exists, create it if it doesn't
    $checkTable = $conn->query("SHOW TABLES LIKE 'breakfast'");
    $hasTable = $checkTable->rowCount() > 0;
    
    if (!$hasTable) {
        // Create breakfast table
        $createTable = $conn->exec("
            CREATE TABLE IF NOT EXISTS breakfast (
                id INT AUTO_INCREMENT PRIMARY KEY,
                food_name VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10, 2) NOT NULL,
                food_image VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    $stmt = $conn->prepare("SELECT id, food_name, description, price, food_image, created_at, updated_at FROM breakfast ORDER BY created_at DESC");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

