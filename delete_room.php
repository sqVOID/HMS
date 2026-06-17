<?php
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    
    // Validate input
    if (empty($id)) {
        $response['message'] = 'Room ID is required!';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Get room image path before deletion
        $stmt = $conn->prepare("SELECT room_image FROM rooms WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            $response['message'] = 'Room not found!';
            echo json_encode($response);
            exit;
        }
        
        // Delete room from database
        $delete_stmt = $conn->prepare("DELETE FROM rooms WHERE id = :id");
        $delete_stmt->bindParam(':id', $id);
        
        if ($delete_stmt->execute()) {
            // Delete room image file if it exists
            if ($room['room_image'] && file_exists($room['room_image'])) {
                unlink($room['room_image']);
            }
            
            $response['success'] = true;
            $response['message'] = 'Room deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete room!';
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>

