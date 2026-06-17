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
    $id = $_POST['id'] ?? '';
    $food_name = trim($_POST['food_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;
    
    // Validate inputs
    if (empty($id) || empty($food_name)) {
        $response['message'] = 'All fields are required!';
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
    
    // Handle file upload (optional - only update if new file is uploaded)
    $food_image = '';
    $update_image = false;
    
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
                $update_image = true;
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
    
    // Update database
    try {
        // Update breakfast information
        if ($update_image) {
            // Get old image path to delete it
            $old_image_stmt = $conn->prepare("SELECT food_image FROM breakfast WHERE id = :id");
            $old_image_stmt->bindParam(':id', $id);
            $old_image_stmt->execute();
            $old_image = $old_image_stmt->fetchColumn();
            
            // Update with new image
            $stmt = $conn->prepare("UPDATE breakfast SET food_name = :food_name, description = :description, price = :price, food_image = :food_image WHERE id = :id");
            $stmt->bindParam(':food_name', $food_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':food_image', $food_image);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Delete old image file if it exists
                if ($old_image && file_exists($old_image)) {
                    @unlink($old_image);
                }
                $response['success'] = true;
                $response['message'] = 'Food item updated successfully!';
            } else {
                // Delete new uploaded file if update fails
                if (!empty($food_image) && file_exists($food_image)) {
                    @unlink($food_image);
                }
                $response['message'] = 'Failed to update food item!';
            }
        } else {
            // Update without changing image
            $stmt = $conn->prepare("UPDATE breakfast SET food_name = :food_name, description = :description, price = :price WHERE id = :id");
            $stmt->bindParam(':food_name', $food_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Food item updated successfully!';
            } else {
                $response['message'] = 'Failed to update food item!';
            }
        }
    } catch(PDOException $e) {
        // Delete uploaded file if database update fails
        if ($update_image && !empty($food_image) && file_exists($food_image)) {
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

