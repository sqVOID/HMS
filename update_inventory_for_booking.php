<?php
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'] ?? null;
    
    if (!$booking_id) {
        $response['message'] = 'Booking ID is required!';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Get inventory item IDs for Hygiene Kit and Tissue
        $hygieneKitStmt = $conn->prepare("SELECT id, stock, product_name FROM inventory WHERE LOWER(product_name) = 'hygiene kit' LIMIT 1");
        $hygieneKitStmt->execute();
        $hygieneKit = $hygieneKitStmt->fetch(PDO::FETCH_ASSOC);
        
        $tissueStmt = $conn->prepare("SELECT id, stock, product_name FROM inventory WHERE LOWER(product_name) = 'tissue' LIMIT 1");
        $tissueStmt->execute();
        $tissue = $tissueStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hygieneKit || !$tissue) {
            $response['message'] = 'Hygiene Kit or Tissue not found in inventory!';
            echo json_encode($response);
            exit;
        }
        
        // Check if we have enough stock
        if ($hygieneKit['stock'] < 1 || $tissue['stock'] < 1) {
            $response['message'] = 'Insufficient stock for Hygiene Kit or Tissue!';
            echo json_encode($response);
            exit;
        }
        
        // Ensure the columns exist in bookings table
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'hygiene_kit_inventory_id'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN hygiene_kit_inventory_id INT DEFAULT NULL AFTER hygiene_kit_price");
            }
        } catch(PDOException $e) {
            error_log("Failed to add hygiene_kit_inventory_id column: " . $e->getMessage());
        }
        
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'tissue_inventory_id'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN tissue_inventory_id INT DEFAULT NULL AFTER hygiene_kit_inventory_id");
            }
        } catch(PDOException $e) {
            error_log("Failed to add tissue_inventory_id column: " . $e->getMessage());
        }
        
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'tissue_used'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN tissue_used INT DEFAULT 0 AFTER tissue_inventory_id");
            }
        } catch(PDOException $e) {
            error_log("Failed to add tissue_used column: " . $e->getMessage());
        }
        
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'hygiene_kit_restocked'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN hygiene_kit_restocked INT DEFAULT 0 AFTER tissue_used");
            }
        } catch(PDOException $e) {
            error_log("Failed to add hygiene_kit_restocked column: " . $e->getMessage());
        }
        
        // Reduce inventory for both items
        $conn->beginTransaction();
        
        try {
            // Reduce Hygiene Kit stock by 1
            $updateHygieneStmt = $conn->prepare("
                UPDATE inventory 
                SET stock = CASE WHEN stock > 0 THEN stock - 1 ELSE 0 END 
                WHERE id = :id AND stock > 0
            ");
            $updateHygieneStmt->bindParam(':id', $hygieneKit['id'], PDO::PARAM_INT);
            $updateHygieneStmt->execute();
            
            // Reduce Tissue stock by 1
            $updateTissueStmt = $conn->prepare("
                UPDATE inventory 
                SET stock = CASE WHEN stock > 0 THEN stock - 1 ELSE 0 END 
                WHERE id = :id AND stock > 0
            ");
            $updateTissueStmt->bindParam(':id', $tissue['id'], PDO::PARAM_INT);
            $updateTissueStmt->execute();
            
            // Update the booking record to track which inventory items were used
            $updateBookingStmt = $conn->prepare("
                UPDATE bookings 
                SET hygiene_kit_inventory_id = :hygiene_id,
                    tissue_inventory_id = :tissue_id,
                    tissue_used = 1,
                    hygiene_kit_restocked = 0
                WHERE id = :booking_id
            ");
            $updateBookingStmt->bindParam(':hygiene_id', $hygieneKit['id'], PDO::PARAM_INT);
            $updateBookingStmt->bindParam(':tissue_id', $tissue['id'], PDO::PARAM_INT);
            $updateBookingStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $updateBookingStmt->execute();
            
            $conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Inventory updated successfully!';
            $response['hygiene_kit_new_stock'] = max(0, $hygieneKit['stock'] - 1);
            $response['tissue_new_stock'] = max(0, $tissue['stock'] - 1);
        } catch(PDOException $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method!';
}

echo json_encode($response);
?>
