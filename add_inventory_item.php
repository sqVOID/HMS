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
    $product_name = trim($_POST['product_name'] ?? '');
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    
    // Validate inputs
    if (empty($product_name)) {
        $response['message'] = 'Product Name is required!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Validate price and stock
    $price = floatval($price);
    $stock = intval($stock);
    
    if ($price < 0) {
        $response['message'] = 'Price cannot be negative!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    if ($stock < 0) {
        $response['message'] = 'Stock cannot be negative!';
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/inventory/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle file upload - store as file
    $product_image = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
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
                $product_image = $upload_path;
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
    
    // Insert into database
    try {
        $stmt = $conn->prepare("INSERT INTO inventory (product_name, price, stock, product_image) VALUES (:product_name, :price, :stock, :product_image)");
        $stmt->bindParam(':product_name', $product_name);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':product_image', $product_image);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Item added successfully!';
        } else {
            $response['message'] = 'Failed to add item!';
        }
    } catch(PDOException $e) {
        // Delete uploaded file if database insert fails
        if (!empty($product_image) && file_exists($product_image)) {
            @unlink($product_image);
        }
        
        if ($e->getCode() == 23000) { // Duplicate entry
            $response['message'] = 'Product name already exists!';
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

