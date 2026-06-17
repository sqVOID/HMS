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
    $food_name = trim($_POST['food_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;
    
    // Validate inputs
    if (empty($food_name)) {
        $response['message'] = 'Food Name is required!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Validate price
    $price = floatval($price);
    
    if ($price < 0) {
        $response['message'] = 'Price cannot be negative!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/breakfast/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle file upload - store as file
    $food_image = '';
    if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['food_image'];
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
                $food_image = $upload_path;
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
    }
    
    // Check if breakfast table exists, create it if it doesn't
    try {
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
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Insert into database
    try {
        $stmt = $conn->prepare("INSERT INTO breakfast (food_name, description, price, food_image) VALUES (:food_name, :description, :price, :food_image)");
        $stmt->bindParam(':food_name', $food_name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':food_image', $food_image);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Food item added successfully!';
        } else {
            $response['message'] = 'Failed to add food item!';
        }
    } catch(PDOException $e) {
        // Delete uploaded file if database insert fails
        if (!empty($food_image) && file_exists($food_image)) {
            @unlink($food_image);
        }
        
        if ($e->getCode() == 23000) { // Duplicate entry
            $response['message'] = 'Food name already exists!';
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

