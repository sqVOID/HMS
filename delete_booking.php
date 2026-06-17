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
        error_log("=== DELETE BOOKING INVENTORY DEBUG ===");
        error_log("Booking ID: " . $booking_id);
        
        $getBookingStmt = $conn->prepare("SELECT * FROM bookings WHERE id = :booking_id");
        $getBookingStmt->bindParam(':booking_id', $booking_id);
        $getBookingStmt->execute();
        $booking = $getBookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $response['message'] = 'Booking not found!';
            echo json_encode($response);
            exit;
        }
        
        // ========================================
        // RESTORE HYGIENE KIT INVENTORY
        // ========================================
        $hygiene_kit_inventory_id = intval($booking['hygiene_kit_inventory_id'] ?? 0);
        $hygiene_kit_restocked = intval($booking['hygiene_kit_restocked'] ?? 0);
        
        if ($hygiene_kit_inventory_id > 0 && $hygiene_kit_restocked === 0) {
            $restockHygieneStmt = $conn->prepare("UPDATE inventory SET stock = stock + 1 WHERE id = :id");
            $restockHygieneStmt->bindParam(':id', $hygiene_kit_inventory_id, PDO::PARAM_INT);
            if ($restockHygieneStmt->execute()) {
                error_log("✓ Restored Hygiene Kit to inventory (ID: " . $hygiene_kit_inventory_id . ")");
            }
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
            if ($restockTissueStmt->execute()) {
                error_log("✓ Restored Tissue to inventory (ID: " . $tissue_inventory_id . ", Qty: " . $tissue_used . ")");
            }
        }
        
        // ========================================
        // RESTORE MISSING ITEMS (Towel, Cover, Bedsheet)
        // ========================================
        $missing_items_list = $booking['missing_items_list'] ?? null;
        $restoredItems = [];
        
        error_log("Missing Items List: " . $missing_items_list);
        
        if ($missing_items_list && $missing_items_list !== 'null' && $missing_items_list !== '') {
            try {
                $missing_items = json_decode($missing_items_list, true);
                error_log("Parsed missing items: " . print_r($missing_items, true));
                
                if (is_array($missing_items) && !empty($missing_items)) {
                    foreach ($missing_items as $item) {
                        $itemName = isset($item['name']) ? strtolower(trim($item['name'])) : strtolower(trim($item));
                        
                        if (empty($itemName)) {
                            continue;
                        }
                        
                        error_log("Processing restore for: " . $itemName);
                        
                        // Find inventory item
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
                            error_log("Found inventory item: " . print_r($invItem, true));
                            
                            // Restore 1 to inventory
                            $restoreStmt = $conn->prepare("
                                UPDATE inventory 
                                SET stock = stock + 1 
                                WHERE id = :id
                            ");
                            $restoreStmt->bindParam(':id', $invItem['id'], PDO::PARAM_INT);
                            
                            if ($restoreStmt->execute() && $restoreStmt->rowCount() > 0) {
                                $restoredItems[] = $invItem['product_name'];
                                error_log("✓ Restored 1 " . $invItem['product_name'] . " to inventory (ID: " . $invItem['id'] . ")");
                            } else {
                                error_log("✗ Failed to restore " . $invItem['product_name']);
                            }
                        } else {
                            error_log("✗ Item not found in inventory: " . $itemName);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Error restoring missing items: ' . $e->getMessage());
            }
        } else {
            error_log("No missing items to restore");
        }
        
        error_log("Total items restored: " . count($restoredItems));
        error_log("=== END DELETE BOOKING INVENTORY DEBUG ===");
        
        // Update room status back to Available
        if ($booking['room_id']) {
            try {
                $updateRoomStmt = $conn->prepare("UPDATE rooms SET status = 'Available' WHERE room_id = :room_id");
                $updateRoomStmt->bindParam(':room_id', $booking['room_id']);
                $updateRoomStmt->execute();
            } catch(PDOException $e) {
                error_log("Failed to update room status: " . $e->getMessage());
            }
        }
        
        // ========================================
        // SAVE TO REPORTS TABLE AS CANCELED
        // ========================================
        require_once 'report_helpers.php';
        ensureReportFinancialColumns($conn);
        
        try {
            $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
            $hasReportsTable = $checkReportsTable->rowCount() > 0;
            
            if ($hasReportsTable) {
                // Check if this booking already exists in reports
                $checkExistsStmt = $conn->prepare("SELECT id FROM reports WHERE booking_id = :booking_id LIMIT 1");
                $checkExistsStmt->bindParam(':booking_id', $booking['booking_id']);
                $checkExistsStmt->execute();
                $existingReport = $checkExistsStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingReport) {
                    // Update existing report to Canceled and sync extension data
                    $updateReportsStmt = $conn->prepare("
                        UPDATE reports r
                        INNER JOIN bookings b ON r.booking_id = b.booking_id
                        SET r.status = 'Canceled', 
                            r.canceled_at = NOW(),
                            r.extend_hours = b.extend_hours,
                            r.extend_minutes = b.extend_minutes,
                            r.extend_price = b.extend_price,
                            r.extend_regular_rate = b.extend_regular_rate,
                            r.extend_bundle_rate = b.extend_bundle_rate,
                            r.extend_bundle_breakfast = b.extend_bundle_breakfast
                        WHERE r.booking_id = :booking_id
                    ");
                    $updateReportsStmt->bindParam(':booking_id', $booking['booking_id']);
                    $updateReportsStmt->execute();
                } else {
                    // Insert new report as Canceled
                    $promoValue = $booking['promo'] ?? null;
                    $breakfastValue = $booking['breakfast'] ?? null;
                    $additionalGuestValue = intval($booking['additional_guest'] ?? 0);
                    $paymentMethodValue = $booking['payment_status'] ?? null;
                    $referenceNoValue = $booking['reference_no'] ?? null;
                    $referralValue = $booking['referral_name'] ?? null;
                    $supplierValue = resolveSupplier($booking['supplier'] ?? '', $referralValue);
                    $additionalValue = $booking['additional'] ?? null;
                    $paidStatusValue = $booking['paid_status'] ?? 'Unpaid';
                    $hygieneUsedValue = $booking['hygiene_kit_used'] ?? 0;
                    $hygienePriceValue = $booking['hygiene_kit_price'] ?? 0;
                    $roomPriceValue = floatval($booking['room_price'] ?? 0);
                    $totalAmount = floatval($booking['total_amount'] ?? 0);
                    $extendHoursValue = intval($booking['extend_hours'] ?? 0);
                    $extendMinutesValue = intval($booking['extend_minutes'] ?? 0);
                    $extendPriceValue = floatval($booking['extend_price'] ?? 0);
                    $extendRegularRateValue = $booking['extend_regular_rate'] ?? null;
                    $extendBundleRateValue = $booking['extend_bundle_rate'] ?? null;
                    $extendBundleBreakfastValue = $booking['extend_bundle_breakfast'] ?? null;
                    
                    $insertReportsStmt = $conn->prepare("
                        INSERT INTO reports (
                            id, booking_id, room_id, room_type, guest_name, guest_type, contact_person_name, tin_number, request,
                            promo, breakfast, additional_guest, additional_pet, payment_status, reference_no, referral_name, supplier, additional, paid_status,
                            check_in, check_out, duration, duration_unit, hours, 
                            status, booking_type, room_image, hygiene_kit_used, hygiene_kit_price, total_amount, room_price, canceled_at,
                            extend_hours, extend_minutes, extend_price, extend_regular_rate, extend_bundle_rate, extend_bundle_breakfast
                        ) VALUES (
                            :id, :booking_id, :room_id, :room_type, :guest_name, :guest_type, :contact_person_name, :tin_number, :request,
                            :promo, :breakfast, :additional_guest, :additional_pet, :payment_status, :reference_no, :referral_name, :supplier, :additional, :paid_status,
                            :check_in, :check_out, :duration, :duration_unit, :hours,
                            'Canceled', :booking_type, :room_image, :hygiene_kit_used, :hygiene_kit_price, :total_amount, :room_price, NOW(),
                            :extend_hours, :extend_minutes, :extend_price, :extend_regular_rate, :extend_bundle_rate, :extend_bundle_breakfast
                        )
                    ");
                    $insertReportsStmt->bindParam(':id', $booking['id'], PDO::PARAM_INT);
                    $insertReportsStmt->bindParam(':booking_id', $booking['booking_id']);
                    $insertReportsStmt->bindParam(':room_id', $booking['room_id']);
                    $insertReportsStmt->bindParam(':room_type', $booking['room_type']);
                    $guestValue = $booking['guest_name'] ?? '';
                    $guestTypeValue = $booking['guest_type'] ?? null;
                    $contactPersonValue = $booking['contact_person_name'] ?? null;
                    $tinNumberValue = $booking['tin_number'] ?? null;
                    $requestValue = $booking['request'] ?? '';
                    $insertReportsStmt->bindParam(':guest_name', $guestValue);
                    $insertReportsStmt->bindParam(':guest_type', $guestTypeValue);
                    $insertReportsStmt->bindParam(':contact_person_name', $contactPersonValue);
                    $insertReportsStmt->bindParam(':tin_number', $tinNumberValue);
                    $insertReportsStmt->bindParam(':request', $requestValue);
                    $insertReportsStmt->bindParam(':promo', $promoValue);
                    $insertReportsStmt->bindParam(':breakfast', $breakfastValue);
                    $insertReportsStmt->bindParam(':additional_guest', $additionalGuestValue);
                    $additionalPetValue = intval($booking['additional_pet'] ?? 0);
                    $insertReportsStmt->bindParam(':additional_pet', $additionalPetValue);
                    $insertReportsStmt->bindParam(':payment_status', $paymentMethodValue);
                    $insertReportsStmt->bindParam(':reference_no', $referenceNoValue);
                    $insertReportsStmt->bindParam(':referral_name', $referralValue);
                    $insertReportsStmt->bindParam(':supplier', $supplierValue);
                    $insertReportsStmt->bindParam(':additional', $additionalValue);
                    $insertReportsStmt->bindParam(':paid_status', $paidStatusValue);
                    $insertReportsStmt->bindParam(':check_in', $booking['check_in']);
                    $insertReportsStmt->bindParam(':check_out', $booking['check_out']);
                    $insertReportsStmt->bindParam(':duration', $booking['duration']);
                    $insertReportsStmt->bindParam(':duration_unit', $booking['duration_unit']);
                    $insertReportsStmt->bindParam(':hours', $booking['hours']);
                    $bookingTypeValue = $booking['booking_type'] ?? null;
                    $insertReportsStmt->bindParam(':booking_type', $bookingTypeValue);
                    $roomImageValue = $booking['room_image'] ?? null;
                    $insertReportsStmt->bindParam(':room_image', $roomImageValue);
                    $insertReportsStmt->bindParam(':hygiene_kit_used', $hygieneUsedValue);
                    $insertReportsStmt->bindParam(':hygiene_kit_price', $hygienePriceValue);
                    $insertReportsStmt->bindParam(':total_amount', $totalAmount);
                    $insertReportsStmt->bindParam(':room_price', $roomPriceValue);
                    $insertReportsStmt->bindParam(':extend_hours', $extendHoursValue);
                    $insertReportsStmt->bindParam(':extend_minutes', $extendMinutesValue);
                    $insertReportsStmt->bindParam(':extend_price', $extendPriceValue);
                    $insertReportsStmt->bindParam(':extend_regular_rate', $extendRegularRateValue);
                    $insertReportsStmt->bindParam(':extend_bundle_rate', $extendBundleRateValue);
                    $insertReportsStmt->bindParam(':extend_bundle_breakfast', $extendBundleBreakfastValue);
                    $insertReportsStmt->execute();
                }
            }
        } catch(PDOException $e) {
            error_log("Failed to save canceled booking to reports: " . $e->getMessage());
        }
        
        // Delete the booking
        $deleteStmt = $conn->prepare("DELETE FROM bookings WHERE id = :booking_id");
        $deleteStmt->bindParam(':booking_id', $booking_id);
        $deleteStmt->execute();
        
        $response['success'] = true;
        $response['message'] = 'Booking deleted successfully!';
        
        if (!empty($restoredItems)) {
            $response['restored_items'] = $restoredItems;
            $response['message'] .= ' Restored to inventory: ' . implode(', ', $restoredItems);
        }
        
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>