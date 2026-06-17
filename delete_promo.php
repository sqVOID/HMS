<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config.php';

function ensurePromoSchema(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS promos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            room_size VARCHAR(100) DEFAULT NULL,
            bed_type VARCHAR(100) DEFAULT NULL,
            guest_capacity VARCHAR(50) DEFAULT NULL,
            facilities TEXT DEFAULT NULL,
            price_12hrs DECIMAL(10, 2) DEFAULT 0.00,
            price_24hrs DECIMAL(10, 2) DEFAULT 0.00,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'room_size' => "ALTER TABLE promos ADD COLUMN room_size VARCHAR(100) DEFAULT NULL AFTER description",
        'bed_type' => "ALTER TABLE promos ADD COLUMN bed_type VARCHAR(100) DEFAULT NULL AFTER room_size",
        'guest_capacity' => "ALTER TABLE promos ADD COLUMN guest_capacity VARCHAR(50) DEFAULT NULL AFTER bed_type",
        'facilities' => "ALTER TABLE promos ADD COLUMN facilities TEXT DEFAULT NULL AFTER guest_capacity",
        'price_12hrs' => "ALTER TABLE promos ADD COLUMN price_12hrs DECIMAL(10, 2) DEFAULT 0.00 AFTER facilities",
        'price_24hrs' => "ALTER TABLE promos ADD COLUMN price_24hrs DECIMAL(10, 2) DEFAULT 0.00 AFTER price_12hrs",
        'image_path' => "ALTER TABLE promos ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER price_24hrs",
        'created_at' => "ALTER TABLE promos ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER image_path",
        'updated_at' => "ALTER TABLE promos ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
    ];

    foreach ($columns as $column => $alterQuery) {
        $columnName = $conn->quote($column);
        $columnQuery = "SHOW COLUMNS FROM promos LIKE $columnName";
        $statement = $conn->query($columnQuery);
        if ($statement === false || $statement->rowCount() === 0) {
            $conn->exec($alterQuery);
        }
    }
}

ensurePromoSchema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$promoId = (int)($_POST['promo_id'] ?? 0);
if ($promoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Promo ID is required.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT image_path FROM promos WHERE id = :id");
    $stmt->execute([':id' => $promoId]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Promo not found.']);
        exit;
    }

    $deleteStmt = $conn->prepare("DELETE FROM promos WHERE id = :id LIMIT 1");
    $deleteStmt->execute([':id' => $promoId]);

    if ($deleteStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete promo.']);
        exit;
    }

    $imagePath = $promo['image_path'] ?? '';
    if ($imagePath && file_exists($imagePath)) {
        @unlink($imagePath);
    }

    echo json_encode(['success' => true, 'message' => 'Promo deleted successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

