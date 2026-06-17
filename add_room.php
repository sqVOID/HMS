<?php
// Turn off error display and set JSON header
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config.php';

// Ensure no output before this point
ob_start();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = trim($_POST['room_id'] ?? '');
    $room_type = trim($_POST['room_type'] ?? '');
    $room_size = trim($_POST['room_size'] ?? '');
    $bed_type = trim($_POST['bed_type'] ?? '');
    $guest_capacity = trim($_POST['guest_capacity'] ?? '');
    $status = $_POST['room_status'] ?? 'Available';
    $durationsJson = $_POST['durations'] ?? '[]';
    
    // Validate inputs
    if (empty($room_id) || empty($room_type) || empty($room_size) || empty($bed_type) || empty($guest_capacity)) {
        $response['message'] = 'All fields are required!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Parse durations
    $durations = json_decode($durationsJson, true);
    if (!is_array($durations) || empty($durations)) {
        $response['message'] = 'Please add at least one duration pricing entry!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/rooms/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle file upload - store as file
    $room_image = '';
    if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['room_image'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check file size (limit to 5MB)
        $max_file_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_file_size) {
            $response['message'] = 'Image file is too large! Maximum size is 5MB.';
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Generate unique filename
            $filename = time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $room_image = $upload_path;
            } else {
                $response['message'] = 'Failed to upload image!';
                ob_clean();
                echo json_encode($response);
                exit;
            }
        } else {
            $response['message'] = 'Invalid file type! Allowed: ' . implode(', ', $allowed_extensions);
            ob_clean();
            echo json_encode($response);
            exit;
        }
    } else {
        $response['message'] = 'Room image is required!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Ensure columns exist in database
    try {
        // Check if columns exist and add them if they don't
        $columns = ['room_size', 'bed_type', 'guest_capacity'];
        $stmt = $conn->query("SHOW COLUMNS FROM rooms");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('room_size', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN room_size VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('bed_type', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN bed_type VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('guest_capacity', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN guest_capacity VARCHAR(50) DEFAULT NULL");
        }
        if (!in_array('price_per_night', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN price_per_night DECIMAL(10,2) DEFAULT 0.00");
        }
    } catch(PDOException $e) {
        // Columns might already exist, continue
    }
    
    // Ensure room_durations table exists
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS room_durations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            duration_hours INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_room_duration (room_id, duration_hours),
            INDEX idx_room_id (room_id),
            INDEX idx_duration_hours (duration_hours)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(PDOException $e) {
        // Table might already exist, continue
    }
    
    // Insert into database
    try {
        // Calculate average price for price_per_night (for backward compatibility only, not required)
        $avgPrice = 0;
        if (!empty($durations)) {
            $totalPrice = 0;
            foreach ($durations as $dur) {
                $totalPrice += floatval($dur['price']);
            }
            $avgPrice = $totalPrice / count($durations);
        }
        
        // Insert room without requiring price_per_night (it's optional now)
        $stmt = $conn->prepare("INSERT INTO rooms (room_id, room_type, room_size, bed_type, guest_capacity, status, room_image, price_per_night) VALUES (:room_id, :room_type, :room_size, :bed_type, :guest_capacity, :status, :room_image, :price_per_night)");
        $stmt->bindParam(':room_id', $room_id);
        $stmt->bindParam(':room_type', $room_type);
        $stmt->bindParam(':room_size', $room_size);
        $stmt->bindParam(':bed_type', $bed_type);
        $stmt->bindParam(':guest_capacity', $guest_capacity);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':room_image', $room_image);
        $stmt->bindParam(':price_per_night', $avgPrice);
        
        if ($stmt->execute()) {
            $newRoomId = $conn->lastInsertId();
            
            // Insert duration pricing
            $durationStmt = $conn->prepare("INSERT INTO room_durations (room_id, duration_hours, price) VALUES (:room_id, :duration_hours, :price) ON DUPLICATE KEY UPDATE price = :update_price");

            foreach ($durations as $dur) {
                $hours = intval($dur['hours']);
                $price = floatval($dur['price']);

                if ($hours > 0 && $price > 0) {
                    $durationStmt->execute([
                        ':room_id' => $newRoomId,
                        ':duration_hours' => $hours,
                        ':price' => $price,
                        ':update_price' => $price
                    ]);
                }
            }
            
            $response['success'] = true;
            $response['message'] = 'Room added successfully!';
        } else {
            $response['message'] = 'Failed to add room!';
        }
    } catch(PDOException $e) {
        // Delete uploaded file if database insert fails
        if (!empty($room_image) && file_exists($room_image)) {
            @unlink($room_image);
        }
        
        if ($e->getCode() == 23000) { // Duplicate entry
            $response['message'] = 'Room ID already exists!';
        } else {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Clear any output buffer and send JSON
ob_clean();
echo json_encode($response);
exit;
?>
