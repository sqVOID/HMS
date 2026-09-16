<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
header('Content-Type: application/json');

$_u_first = trim($_SESSION['first_name'] ?? ''); $_u_last = trim($_SESSION['last_name'] ?? '');
$_actor = ($_u_first !== '' || $_u_last !== '') ? trim($_u_first . ' ' . $_u_last) : trim($_SESSION['username'] ?? 'Unknown');


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
        // Get product details before deletion
        $stmt = $conn->prepare("SELECT product_name, product_image FROM inventory WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            $response['message'] = 'Item not found!';
            echo json_encode($response);
            exit;
        }

        $itemName = trim($item['product_name'] ?? '') ?: 'Unknown Item';
        
        // Delete item from database
        $delete_stmt = $conn->prepare("DELETE FROM inventory WHERE id = :id");
        $delete_stmt->bindParam(':id', $id);
        
        if ($delete_stmt->execute()) {
            if ($item['product_image'] && file_exists($item['product_image'])) {
                unlink($item['product_image']);
            }
            logActivity($conn, 'delete', 'INVENTORY_ITEM_DELETE',
                "Inventory item '{$itemName}' (ID: #{$id}) deleted by {$_actor}",
                ['item_id' => $id, 'name' => $itemName, 'deleted_by' => $_actor]
            );
            $response['success'] = true;
            $response['message'] = 'Item deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete item!';
        }
    } catch(PDOException $e) {
        logError($conn, 'delete', 'INVENTORY_ITEM_DELETE', "Failed to delete inventory item #{$id}: " . $e->getMessage(), ['item_id' => $id, 'deleted_by' => $_actor]);
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>




