<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'system_logger.php';

$_enc_first = trim($_SESSION['first_name'] ?? '');
$_enc_last  = trim($_SESSION['last_name'] ?? '');
if ($_enc_first !== '' || $_enc_last !== '') {
    $encoder = trim($_enc_first . ' ' . $_enc_last);
} else {
    $encoder = trim($_SESSION['username'] ?? 'Unknown User');
}

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
        // Get food image & name path before deletion
        $stmt = $conn->prepare("SELECT food_name, food_image FROM breakfast WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            $response['message'] = 'Food item not found!';
            echo json_encode($response);
            exit;
        }
        
        $foodName = $item['food_name'] ?? "Item #{$id}";

        // Delete item from database
        $delete_stmt = $conn->prepare("DELETE FROM breakfast WHERE id = :id");
        $delete_stmt->bindParam(':id', $id);
        
        if ($delete_stmt->execute()) {
            // Delete food image file if it exists
            if (!empty($item['food_image']) && file_exists($item['food_image'])) {
                @unlink($item['food_image']);
            }
            
            $response['success'] = true;
            $response['message'] = 'Food item deleted successfully!';

            logActivity($conn, 'menu', 'DELETE', "Breakfast item '{$foodName}' deleted (ID: {$id}) by {$encoder}", [
                'food_id'    => $id,
                'food_name'  => $foodName,
                'deleted_by' => $encoder
            ], $id);
        } else {
            $response['message'] = 'Failed to delete food item!';
            logError($conn, 'menu', 'DELETE', "Failed to delete breakfast item '{$foodName}'", [
                'food_id'   => $id,
                'food_name' => $foodName
            ], $id);
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        logError($conn, 'menu', 'DELETE', "Error deleting breakfast item #{$id}: " . $e->getMessage(), [
            'food_id' => $id,
            'error'   => $e->getMessage()
        ], $id);
    }
}

echo json_encode($response);
?>

