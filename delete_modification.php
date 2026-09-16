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
    require_once 'system_logger.php';

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
        // SAVE TO DELETED_DATA TABLE (ARCHIVE)
        // ========================================
        $archiveSuccess = false;
        $archiveError = '';
        try {
            // Create deleted_data table with all booking columns if it doesn't exist
            $createTableSQL = "CREATE TABLE IF NOT EXISTS deleted_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_table VARCHAR(50) NOT NULL,
                original_id INT NOT NULL,
                booking_id VARCHAR(50),
                room_id VARCHAR(50),
                room_no VARCHAR(50),
                guest_names TEXT,
                guest_type VARCHAR(50),
                contact_no VARCHAR(50),
                address TEXT,
                vehicle_type VARCHAR(100),
                plate_number VARCHAR(50),
                vehicle_description TEXT,
                referral VARCHAR(100),
                reason TEXT,
                check_in DATETIME,
                check_out DATETIME,
                duration INT,
                encoder VARCHAR(100),
                encoder_checkin VARCHAR(100),
                encoder_checkout VARCHAR(100),
                booking_type VARCHAR(50),
                promo VARCHAR(100),
                breakfast TEXT,
                additional_guest INT,
                additional_pet INT,
                discount_type VARCHAR(50),
                discount_id_number VARCHAR(100),
                discount_amount DECIMAL(10,2),
                discount_applied TINYINT(1),
                hygiene_kit_inventory_id INT,
                hygiene_kit_restocked TINYINT(1),
                tissue_inventory_id INT,
                tissue_used INT,
                missing_items_list TEXT,
                additional_charges TEXT,
                payment_method VARCHAR(50),
                amount_paid DECIMAL(10,2),
                change_amount DECIMAL(10,2),
                payment_status VARCHAR(50),
                payment_history TEXT,
                downpayment_amount DECIMAL(10,2),
                downpayment_date DATETIME,
                additional_fees_paid_date DATETIME,
                cancellation_reason TEXT,
                cancellation_date DATETIME,
                cancellation_status VARCHAR(50),
                extend_hours INT,
                extend_minutes INT,
                extend_price DECIMAL(10,2),
                extend_regular_rate DECIMAL(10,2),
                extend_bundle_rate DECIMAL(10,2),
                extend_bundle_breakfast TEXT,
                extend_additional_item TEXT,
                extend_breakfast_date DATETIME,
                extend_additional_item_date DATETIME,
                status VARCHAR(50),
                deleted_by VARCHAR(100),
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_original_table (original_table),
                INDEX idx_original_id (original_id),
                INDEX idx_booking_id (booking_id),
                INDEX idx_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $conn->exec($createTableSQL);

            // Build column list and values dynamically
            $deletedBy = $_SESSION['username'] ?? 'Unknown';
            
            $columns = [
                'original_table', 'original_id', 'booking_id', 'room_id', 'room_no', 'guest_names', 'guest_type',
                'contact_no', 'address', 'vehicle_type', 'plate_number', 'vehicle_description', 'referral', 'reason',
                'check_in', 'check_out', 'duration', 'encoder', 'encoder_checkin', 'encoder_checkout',
                'booking_type', 'promo', 'breakfast', 'additional_guest', 'additional_pet',
                'discount_type', 'discount_id_number', 'discount_amount', 'discount_applied',
                'hygiene_kit_inventory_id', 'hygiene_kit_restocked', 'tissue_inventory_id', 'tissue_used',
                'missing_items_list', 'additional_charges', 'payment_method', 'amount_paid', 'change_amount',
                'payment_status', 'payment_history', 'downpayment_amount', 'downpayment_date',
                'additional_fees_paid_date', 'cancellation_reason', 'cancellation_date', 'cancellation_status',
                'extend_hours', 'extend_minutes', 'extend_price', 'extend_regular_rate', 'extend_bundle_rate',
                'extend_bundle_breakfast', 'extend_additional_item', 'extend_breakfast_date',
                'extend_additional_item_date', 'status', 'deleted_by'
            ];
            
            $values = [
                'bookings',
                $booking['id'],
                $booking['booking_id'] ?? null,
                $booking['room_id'] ?? null,
                $booking['room_no'] ?? null,
                $booking['guest_names'] ?? null,
                $booking['guest_type'] ?? null,
                $booking['contact_no'] ?? null,
                $booking['address'] ?? null,
                $booking['vehicle_type'] ?? null,
                $booking['plate_number'] ?? null,
                $booking['vehicle_description'] ?? null,
                $booking['referral'] ?? null,
                $booking['reason'] ?? null,
                $booking['check_in'] ?? null,
                $booking['check_out'] ?? null,
                $booking['duration'] ?? null,
                $booking['encoder'] ?? null,
                $booking['encoder_checkin'] ?? null,
                $booking['encoder_checkout'] ?? null,
                $booking['booking_type'] ?? null,
                $booking['promo'] ?? null,
                $booking['breakfast'] ?? null,
                $booking['additional_guest'] ?? null,
                $booking['additional_pet'] ?? null,
                $booking['discount_type'] ?? null,
                $booking['discount_id_number'] ?? null,
                $booking['discount_amount'] ?? null,
                $booking['discount_applied'] ?? null,
                $booking['hygiene_kit_inventory_id'] ?? null,
                $booking['hygiene_kit_restocked'] ?? null,
                $booking['tissue_inventory_id'] ?? null,
                $booking['tissue_used'] ?? null,
                $booking['missing_items_list'] ?? null,
                $booking['additional_charges'] ?? null,
                $booking['payment_method'] ?? null,
                $booking['amount_paid'] ?? null,
                $booking['change_amount'] ?? null,
                $booking['payment_status'] ?? null,
                $booking['payment_history'] ?? null,
                $booking['downpayment_amount'] ?? null,
                $booking['downpayment_date'] ?? null,
                $booking['additional_fees_paid_date'] ?? null,
                $booking['cancellation_reason'] ?? null,
                $booking['cancellation_date'] ?? null,
                $booking['cancellation_status'] ?? null,
                $booking['extend_hours'] ?? null,
                $booking['extend_minutes'] ?? null,
                $booking['extend_price'] ?? null,
                $booking['extend_regular_rate'] ?? null,
                $booking['extend_bundle_rate'] ?? null,
                $booking['extend_bundle_breakfast'] ?? null,
                $booking['extend_additional_item'] ?? null,
                $booking['extend_breakfast_date'] ?? null,
                $booking['extend_additional_item_date'] ?? null,
                $booking['status'] ?? null,
                $deletedBy
            ];
            
            $columnList = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            
            $insertSQL = "INSERT INTO deleted_data ($columnList) VALUES ($placeholders)";
            $insertStmt = $conn->prepare($insertSQL);
            $result = $insertStmt->execute($values);
            
            if ($result) {
                $archiveSuccess = true;
            }
            
        } catch (Exception $e) {
            // Log the error and store it
            $archiveError = $e->getMessage();
            error_log("Failed to save deleted booking to deleted_data table: " . $archiveError);
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

        // ========================================
        // RESET AUTO_INCREMENT FOR BOOKINGS TABLE
        // ========================================
        try {
            // Get the maximum id from bookings table
            $maxIdStmt = $conn->query("SELECT IFNULL(MAX(id), 0) as max_id FROM bookings");
            $maxIdResult = $maxIdStmt->fetch(PDO::FETCH_ASSOC);
            $maxId = intval($maxIdResult['max_id']);
            
            // Reset AUTO_INCREMENT to max_id + 1
            $conn->exec("ALTER TABLE bookings AUTO_INCREMENT = " . ($maxId + 1));
            
            // Also reset booking_id if it's auto_increment (check first)
            $checkBookingIdStmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'booking_id'");
            $bookingIdColumn = $checkBookingIdStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bookingIdColumn && strpos($bookingIdColumn['Extra'], 'auto_increment') !== false) {
                $maxBookingIdStmt = $conn->query("SELECT IFNULL(MAX(booking_id), 0) as max_booking_id FROM bookings");
                $maxBookingIdResult = $maxBookingIdStmt->fetch(PDO::FETCH_ASSOC);
                $maxBookingId = intval($maxBookingIdResult['max_booking_id']);
                
                // Note: Cannot directly set AUTO_INCREMENT for non-primary key columns
                // This would require booking_id to be the primary key or use triggers
            }
        } catch (PDOException $e) {
            error_log("Failed to reset AUTO_INCREMENT for bookings: " . $e->getMessage());
        }

        $successMsg = 'Booking deleted successfully!';
        if (!empty($restoredItems)) {
            $successMsg .= ' Restored to inventory: ' . implode(', ', $restoredItems);
        }
        if (!$archiveSuccess && $archiveError) {
            $successMsg .= ' (Warning: Archive failed - ' . $archiveError . ')';
        }

        logActivity($conn, 'delete', 'BOOKING_FORCE_DELETE',
            "Booking #{$id} (ID: {$booking['booking_id']}) force-deleted from {$source} by {$deletedBy} — Guest: {$booking['guest_names']}, Room: {$booking['room_no']}",
            ['booking_id' => $id, 'ref' => $booking['booking_id'], 'guest' => $booking['guest_names'], 'room' => $booking['room_no'], 'archived' => $archiveSuccess, 'deleted_by' => $deletedBy]
        );

        echo json_encode([
            'success' => true,
            'affected_rows' => $affectedRows,
            'message' => $successMsg,
            'archived' => $archiveSuccess
        ]);
        exit;

    } else {
        // Direct deletion from reports table
        
        // First, fetch the report data before deleting
        $getReportStmt = $conn->prepare("SELECT * FROM reports WHERE id = :id");
        $getReportStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $getReportStmt->execute();
        $report = $getReportStmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            throw new Exception('Report not found in database.');
        }

        // ========================================
        // SAVE TO DELETED_DATA TABLE (ARCHIVE)
        // ========================================
        try {
            $deletedBy = $_SESSION['username'] ?? 'Unknown';
            
            $columns = [
                'original_table', 'original_id', 'booking_id', 'room_id', 'room_no', 'guest_names', 'guest_type',
                'contact_no', 'address', 'vehicle_type', 'plate_number', 'vehicle_description', 'referral', 'reason',
                'check_in', 'check_out', 'duration', 'encoder', 'encoder_checkin', 'encoder_checkout',
                'booking_type', 'promo', 'breakfast', 'additional_guest', 'additional_pet',
                'discount_type', 'discount_id_number', 'discount_amount', 'discount_applied',
                'hygiene_kit_inventory_id', 'hygiene_kit_restocked', 'tissue_inventory_id', 'tissue_used',
                'missing_items_list', 'additional_charges', 'payment_method', 'amount_paid', 'change_amount',
                'payment_status', 'payment_history', 'downpayment_amount', 'downpayment_date',
                'additional_fees_paid_date', 'cancellation_reason', 'cancellation_date', 'cancellation_status',
                'extend_hours', 'extend_minutes', 'extend_price', 'extend_regular_rate', 'extend_bundle_rate',
                'extend_bundle_breakfast', 'extend_additional_item', 'extend_breakfast_date',
                'extend_additional_item_date', 'status', 'deleted_by'
            ];
            
            $values = [
                'reports',
                $report['id'],
                $report['booking_id'] ?? null,
                $report['room_id'] ?? null,
                $report['room_no'] ?? null,
                $report['guest_names'] ?? null,
                $report['guest_type'] ?? null,
                $report['contact_no'] ?? null,
                $report['address'] ?? null,
                $report['vehicle_type'] ?? null,
                $report['plate_number'] ?? null,
                $report['vehicle_description'] ?? null,
                $report['referral'] ?? null,
                $report['reason'] ?? null,
                $report['check_in'] ?? null,
                $report['check_out'] ?? null,
                $report['duration'] ?? null,
                $report['encoder'] ?? null,
                $report['encoder_checkin'] ?? null,
                $report['encoder_checkout'] ?? null,
                $report['booking_type'] ?? null,
                $report['promo'] ?? null,
                $report['breakfast'] ?? null,
                $report['additional_guest'] ?? null,
                $report['additional_pet'] ?? null,
                $report['discount_type'] ?? null,
                $report['discount_id_number'] ?? null,
                $report['discount_amount'] ?? null,
                $report['discount_applied'] ?? null,
                $report['hygiene_kit_inventory_id'] ?? null,
                $report['hygiene_kit_restocked'] ?? null,
                $report['tissue_inventory_id'] ?? null,
                $report['tissue_used'] ?? null,
                $report['missing_items_list'] ?? null,
                $report['additional_charges'] ?? null,
                $report['payment_method'] ?? null,
                $report['amount_paid'] ?? null,
                $report['change_amount'] ?? null,
                $report['payment_status'] ?? null,
                $report['payment_history'] ?? null,
                $report['downpayment_amount'] ?? null,
                $report['downpayment_date'] ?? null,
                $report['additional_fees_paid_date'] ?? null,
                $report['cancellation_reason'] ?? null,
                $report['cancellation_date'] ?? null,
                $report['cancellation_status'] ?? null,
                $report['extend_hours'] ?? null,
                $report['extend_minutes'] ?? null,
                $report['extend_price'] ?? null,
                $report['extend_regular_rate'] ?? null,
                $report['extend_bundle_rate'] ?? null,
                $report['extend_bundle_breakfast'] ?? null,
                $report['extend_additional_item'] ?? null,
                $report['extend_breakfast_date'] ?? null,
                $report['extend_additional_item_date'] ?? null,
                $report['status'] ?? null,
                $deletedBy
            ];
            
            $columnList = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            
            $insertSQL = "INSERT INTO deleted_data ($columnList) VALUES ($placeholders)";
            $insertStmt = $conn->prepare($insertSQL);
            $insertStmt->execute($values);
            
        } catch (Exception $e) {
            // Log the error but continue with deletion
            error_log("Failed to save deleted report to deleted_data table: " . $e->getMessage());
        }

        // Now perform the actual deletion
        $stmt = $conn->prepare("DELETE FROM reports WHERE id = :id");
        if (!$stmt) {
            throw new Exception("Prepare statement failed");
        }

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $affectedRows = $stmt->rowCount();

        // ========================================
        // RESET AUTO_INCREMENT FOR REPORTS TABLE
        // ========================================
        try {
            // Get the maximum id from reports table
            $maxIdStmt = $conn->query("SELECT IFNULL(MAX(id), 0) as max_id FROM reports");
            $maxIdResult = $maxIdStmt->fetch(PDO::FETCH_ASSOC);
            $maxId = intval($maxIdResult['max_id']);
            
            // Reset AUTO_INCREMENT to max_id + 1
            $conn->exec("ALTER TABLE reports AUTO_INCREMENT = " . ($maxId + 1));
        } catch (PDOException $e) {
            error_log("Failed to reset AUTO_INCREMENT for reports: " . $e->getMessage());
        }

        logActivity($conn, 'delete', 'REPORT_FORCE_DELETE',
            "Report record #{$id} (Booking: {$report['booking_id']}) force-deleted by {$deletedBy} — Guest: {$report['guest_names']}, Room: {$report['room_no']}",
            ['report_id' => $id, 'booking_id' => $report['booking_id'], 'guest' => $report['guest_names'], 'room' => $report['room_no'], 'deleted_by' => $deletedBy]
        );
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
