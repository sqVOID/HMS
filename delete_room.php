<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';

$_u_first = trim($_SESSION['first_name'] ?? ''); $_u_last = trim($_SESSION['last_name'] ?? '');
$_actor = ($_u_first !== '' || $_u_last !== '') ? trim($_u_first . ' ' . $_u_last) : trim($_SESSION['username'] ?? 'Unknown');


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
        // Get room details before deletion
        $stmt = $conn->prepare("SELECT room_id, room_type, room_image FROM rooms WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            $response['message'] = 'Room not found!';
            echo json_encode($response);
            exit;
        }

        $roomNumber = !empty($room['room_id']) ? $room['room_id'] : "#{$id}";
        $roomType = !empty($room['room_type']) ? " (Type: {$room['room_type']})" : '';
        
        // Delete room from database
        $delete_stmt = $conn->prepare("DELETE FROM rooms WHERE id = :id");
        $delete_stmt->bindParam(':id', $id);
        
        if ($delete_stmt->execute()) {
            if ($room['room_image'] && file_exists($room['room_image'])) {
                unlink($room['room_image']);
            }
            logActivity($conn, 'delete', 'ROOM_DELETE',
                "Room '{$roomNumber}'{$roomType} (DB ID: #{$id}) deleted by {$_actor}",
                ['room_db_id' => $id, 'room_number' => $roomNumber, 'room_type' => $room['room_type'] ?? null, 'deleted_by' => $_actor]
            );
            $response['success'] = true;
            $response['message'] = 'Room deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete room!';
        }
    } catch(PDOException $e) {
        logError($conn, 'delete', 'ROOM_DELETE', "Failed to delete room #{$id}: " . $e->getMessage(), ['room_id' => $id, 'deleted_by' => $_actor]);
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>

