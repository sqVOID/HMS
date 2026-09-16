<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    function ensurePromoSchema(PDO $conn): void
    {
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

    ensurePromoSchema($conn);

    $stmt = $conn->prepare("
        SELECT id, title, description, room_size, bed_type, guest_capacity, facilities, price_12hrs, price_24hrs, image_path, created_at, updated_at
        FROM promos
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($promos as &$promo) {
        $facilities = $promo['facilities'] ?? '';
        $decoded = json_decode($facilities, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $promo['facilities'] = array_values(array_filter(array_map('trim', $decoded)));
        } else {
            $promo['facilities'] = array_filter(array_map('trim', explode(',', $facilities)));
        }
    }

    echo json_encode([
        'success' => true,
        'promos' => $promos
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}