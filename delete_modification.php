<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Safety net: catch PHP fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode([
            'success' => false,
            'error'   => 'PHP Fatal Error: ' . $error['message'] . ' in ' . $error['file']
        ]);
    }
});

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data['id']) || empty($data['source'])) {
        throw new Exception('Missing ID or source table');
    }

    $id = (int) $data['id'];
    $source = $data['source'];

    // Only allow specific tables
    if (!in_array($source, ['bookings', 'reports'])) {
        throw new Exception('Invalid source table provided.');
    }

    require_once 'config.php';

    if ($source === 'bookings') {
        // Fetch booking first
        $getBookingStmt = $conn->prepare("SELECT * FROM bookings WHERE id = :id");
        $getBookingStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $getBookingStmt->execute();
        $booking = $getBookingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception('Booking not found in database.');
        }

        // ========================================
        // RESTORE HYGIENE KIT INVENTORY
        // ========================================
        $hygiene_kit_inventory_id = intval($booking['hygiene_kit_inventory_id'] ?? 0);
        $hygiene_kit_restocked = intval($booking['hygiene_kit_restocked'] ?? 0);
        
        if ($hygiene_kit_inventory_id > 0 && $hygiene_kit_restocked === 0) {
            $restockHygieneStmt = $conn->prepare("UPDATE inventory SET stock = stock + 1 WHERE id = :id");
            $restockHygieneStmt->bindParam(':id', $hygiene_kit_inventory_id, PDO::PARAM_INT);
            $restockHygieneStmt->execute();
        }
        
        // ========================================
        // RESTORE TISSUE INVENTORY
        // ========================================
        $tissue_inventory_id = intval($booking['tissue_inventory_id'] ?? 0);
        $tissue_used = intval($booking['tissue_used'] ?? 0);
        
        if ($tissue_inventory_id > 0 && $tissue_used > 0) {
            $restockTissueStmt = $conn->prepare("UPDATE inventory SET stock = stock + :qty WHERE id = :id");
            $restockTissueStmt->bindParam(':qty', $tissue_used, PDO::PARAM_INT);
            $restockTissueStmt->bindParam(':id', $tissue_inventory_id, PDO::PARAM_INT);
            $restockTissueStmt->execute();
        }
        
        // ========================================
        // RESTORE MISSING ITEMS (Towel, Cover, Bedsheet)
        // ========================================
        $missing_items_list = $booking['missing_items_list'] ?? null;
        $restoredItems = [];
        
        if ($missing_items_list && $missing_items_list !== 'null' && $missing_items_list !== '') {
            try {
                $missing_items = json_decode($missing_items_list, true);
                if (is_array($missing_items) && !empty($missing_items)) {
                    foreach ($missing_items as $item) {
                        $itemName = isset($item['name']) ? strtolower(trim($item['name'])) : strtolower(trim($item));
                        if (empty($itemName)) {
                            continue;
                        }
                        
                        $invStmt = $conn->prepare("
                            SELECT id, product_name 
                            FROM inventory 
                            WHERE LOWER(TRIM(product_name)) = :item_name 
                            LIMIT 1
                        ");
                        $invStmt->bindParam(':item_name', $itemName);
                        $invStmt->execute();
                        $invItem = $invStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($invItem) {
                            $restoreStmt = $conn->prepare("
                                UPDATE inventory 
                                SET stock = stock + 1 
                                WHERE id = :id
                            ");
                            $restoreStmt->bindParam(':id', $invItem['id'], PDO::PARAM_INT);
                            if ($restoreStmt->execute() && $restoreStmt->rowCount() > 0) {
                                $restoredItems[] = $invItem['product_name'];
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Error restoring missing items in delete_modification: ' . $e->getMessage());
            }
        }
        
        // Update room status back to Available
        if (!empty($booking['room_id'])) {
            try {
                $updateRoomStmt = $conn->prepare("UPDATE rooms SET status = 'Available' WHERE room_id = :room_id");
                $updateRoomStmt->bindParam(':room_id', $booking['room_id']);
                $updateRoomStmt->execute();
            } catch(PDOException $e) {
                error_log("Failed to update room status in delete_modification: " . $e->getMessage());
            }
        }

        // ========================================
        // DELETE FROM REPORTS TABLE (if exists)
        // ========================================
        try {
            $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
            $hasReportsTable = $checkReportsTable->rowCount() > 0;
            
            if ($hasReportsTable) {
                $deleteReportStmt = $conn->prepare("DELETE FROM reports WHERE booking_id = :booking_id");
                $deleteReportStmt->bindParam(':booking_id', $booking['booking_id']);
                $deleteReportStmt->execute();
            }
        } catch(PDOException $e) {
            error_log("Failed to delete booking from reports in delete_modification: " . $e->getMessage());
        }

        // Delete active booking from bookings
        $stmt = $conn->prepare("DELETE FROM bookings WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $affectedRows = $stmt->rowCount();

        $successMsg = 'Booking deleted successfully!';
        if (!empty($restoredItems)) {
            $successMsg .= ' Restored to inventory: ' . implode(', ', $restoredItems);
        }

        echo json_encode([
            'success' => true,
            'affected_rows' => $affectedRows,
            'message' => $successMsg
        ]);
        exit;

    } else {
        // Direct deletion from reports table
        $stmt = $conn->prepare("DELETE FROM reports WHERE id = :id");
        if (!$stmt) {
            throw new Exception("Prepare statement failed");
        }

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $affectedRows = $stmt->rowCount();

        echo json_encode([
            'success' => true,
            'affected_rows' => $affectedRows,
            'message' => 'Report deleted successfully!'
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(200); // 200 so JSON parsing doesn't fail on client side
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
