<?php
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    
    // Validate input
    if (empty($id)) {
        $response['message'] = 'Item ID is required!';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Get food image path before deletion
        $stmt = $conn->prepare("SELECT food_image FROM breakfast WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            $response['message'] = 'Food item not found!';
            echo json_encode($response);
            exit;
        }
        
        // Delete item from database
        $delete_stmt = $conn->prepare("DELETE FROM breakfast WHERE id = :id");
        $delete_stmt->bindParam(':id', $id);
        
        if ($delete_stmt->execute()) {
            // Delete food image file if it exists
            if ($item['food_image'] && file_exists($item['food_image'])) {
                unlink($item['food_image']);
            }
            
            $response['success'] = true;
            $response['message'] = 'Food item deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete food item!';
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>

