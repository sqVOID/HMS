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

$requiredFields = ['title', 'description', 'price_12hrs', 'price_24hrs'];

foreach ($requiredFields as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
        exit;
    }
}

$title = trim($_POST['title']);
$description = trim($_POST['description']);
$roomSize = trim($_POST['room_size'] ?? '');
$bedType = trim($_POST['bed_type'] ?? '');
$guestCapacity = trim($_POST['guest_capacity'] ?? '');
$price12 = (float) $_POST['price_12hrs'];
$price24 = (float) $_POST['price_24hrs'];
$facilitiesRaw = trim($_POST['facilities'] ?? '');

if ($price12 < 0 || $price24 < 0) {
    echo json_encode(['success' => false, 'message' => 'Prices cannot be negative.']);
    exit;
}

$facilities = [];
if ($facilitiesRaw !== '') {
    $facilities = array_values(
        array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $facilitiesRaw))
        )
    );
}
$facilitiesJson = json_encode($facilities);

try {
    $existingStmt = $conn->prepare("SELECT image_path FROM promos WHERE id = :id");
    $existingStmt->execute([':id' => $promoId]);
    $existingPromo = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingPromo) {
        echo json_encode(['success' => false, 'message' => 'Promo not found.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$imagePath = $existingPromo['image_path'] ?? '';
$replacementImagePath = '';

if (!empty($_FILES['promo_image']['name']) && $_FILES['promo_image']['error'] === UPLOAD_ERR_OK) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $uploadDir = 'uploads/promos/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $originalName = $_FILES['promo_image']['name'];
    $fileSize = $_FILES['promo_image']['size'];
    $tmpName = $_FILES['promo_image']['tmp_name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
        exit;
    }

    if ($fileSize > (5 * 1024 * 1024)) {
        echo json_encode(['success' => false, 'message' => 'Image file exceeds 5MB limit.']);
        exit;
    }

    $newFileName = time() . '_' . uniqid('', true) . '.' . $extension;
    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
        exit;
    }

    $replacementImagePath = $destination;
}

if ($replacementImagePath !== '') {
    if ($imagePath && file_exists($imagePath)) {
        @unlink($imagePath);
    }
    $imagePath = $replacementImagePath;
}

try {
    $stmt = $conn->prepare("
        UPDATE promos
        SET title = :title,
            description = :description,
            room_size = :room_size,
            bed_type = :bed_type,
            guest_capacity = :guest_capacity,
            facilities = :facilities,
            price_12hrs = :price_12hrs,
            price_24hrs = :price_24hrs,
            image_path = :image_path,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':room_size', $roomSize);
    $stmt->bindParam(':bed_type', $bedType);
    $stmt->bindParam(':guest_capacity', $guestCapacity);
    $stmt->bindParam(':facilities', $facilitiesJson);
    $stmt->bindParam(':price_12hrs', $price12);
    $stmt->bindParam(':price_24hrs', $price24);
    $stmt->bindParam(':image_path', $imagePath);
    $stmt->bindParam(':id', $promoId, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Promo updated successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

