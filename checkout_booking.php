<?php
require_once 'config.php';
require_once 'report_helpers.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start session to read logged-in user info (encoder_checkout)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Determine encoder_checkout = full name of the currently logged-in user
    $_enc_first = trim($_SESSION['first_name'] ?? '');
    $_enc_last = trim($_SESSION['last_name'] ?? '');
    if ($_enc_first !== '' || $_enc_last !== '') {
        $encoder_checkout = trim($_enc_first . ' ' . $_enc_last);
    } else {
        $encoder_checkout = trim($_SESSION['username'] ?? 'Unknown');
    }

    $booking_id = $_POST['booking_id'] ?? null;
    $checkout_override_raw = $_POST['checkout_override'] ?? null;

    if (!$booking_id) {
        $response['message'] = 'Booking ID is required!';
        echo json_encode($response);
        exit;
    }

    ensureReportFinancialColumns($conn);

    // Ensure payment_amount_*_history columns exist in both tables.
    foreach (['bookings', 'reports'] as $_histTable) {
        foreach (['payment_amount_cash_history', 'payment_amount_g_cash_history', 'payment_amount_maya_history'] as $_histCol) {
            try {
                $chk = $conn->query("SHOW COLUMNS FROM {$_histTable} LIKE '{$_histCol}'");
                if ($chk && $chk->rowCount() == 0) {
                    $conn->exec("ALTER TABLE {$_histTable} ADD COLUMN {$_histCol} TEXT NULL DEFAULT NULL");
                }
            } catch (Exception $e) {
            }
        }
    }

    try {
        // Ensure encoder_checkout column exists in both tables (best-effort)
        try {
            $chk = $conn->query("SHOW COLUMNS FROM bookings LIKE 'encoder_checkout'");
            if ($chk && $chk->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN encoder_checkout VARCHAR(255) NULL DEFAULT NULL");
            }
        } catch (Exception $e) {
        }
        try {
            $chk = $conn->query("SHOW COLUMNS FROM reports LIKE 'encoder_checkout'");
            if ($chk && $chk->rowCount() == 0) {
                $conn->exec("ALTER TABLE reports ADD COLUMN encoder_checkout VARCHAR(255) NULL DEFAULT NULL");
            }
        } catch (Exception $e) {
        }

        $durationTotalPosted = isset($_POST['duration_total']) ? floatval($_POST['duration_total']) : null;
        $foodTotalPosted = isset($_POST['food_total']) ? floatval($_POST['food_total']) : null;
        $addonsTotalPosted = isset($_POST['addons_total']) ? floatval($_POST['addons_total']) : null;
        $grandTotalPosted = isset($_POST['grand_total']) ? floatval($_POST['grand_total']) : null;

        $getBookingStmt = $conn->prepare("SELECT * FROM bookings WHERE id = :booking_id");
        $getBookingStmt->bindParam(':booking_id', $booking_id);
        $getBookingStmt->execute();
        $booking = $getBookingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $response['message'] = 'Booking not found!';
            echo json_encode($response);
            exit;
        }

        if ($booking['status'] !== 'Confirmed' && $booking['status'] !== 'Occupied') {
            $response['message'] = 'Only Confirmed or Occupied bookings can be checked out!';
            echo json_encode($response);
            exit;
        }

        // Optional Super Admin override for checkout datetime
        $checkoutOverride = null;
        if (!empty($checkout_override_raw)) {
            $v = trim($checkout_override_raw);
            if ($v !== '') {
                $v = str_replace('T', ' ', $v);
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $v)) {
                    $v .= ':00';
                }
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $v);
                if ($dt && $dt->format('Y-m-d H:i:s') === $v) {
                    $checkoutOverride = $v;
                }
            }
        }

        // Effective checked-out timestamp for reports
        $checkedOutAtValue = $checkoutOverride ?: date('Y-m-d H:i:s');

        // ========================================
        // INVENTORY DEDUCTION FOR MISSING ITEMS
        // ========================================
        $missing_items_list = $booking['missing_items_list'] ?? null;
        $deductedItems = [];

        error_log("=== CHECKOUT INVENTORY DEBUG ===");
        error_log("Booking ID: " . $booking_id);
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

                        error_log("Processing missing item: " . $itemName);

                        // Find inventory item
                        $invStmt = $conn->prepare("
                            SELECT id, product_name, stock 
                            FROM inventory 
                            WHERE LOWER(TRIM(product_name)) = :item_name 
                            LIMIT 1
                        ");
                        $invStmt->bindParam(':item_name', $itemName);
                        $invStmt->execute();
                        $invItem = $invStmt->fetch(PDO::FETCH_ASSOC);

                        if ($invItem) {
                            error_log("Found inventory item: " . print_r($invItem, true));

                            if ($invItem['stock'] > 0) {
                                // Deduct 1 from inventory
                                $updateStmt = $conn->prepare("
                                    UPDATE inventory 
                                    SET stock = stock - 1 
                                    WHERE id = :id AND stock > 0
                                ");
                                $updateStmt->bindParam(':id', $invItem['id'], PDO::PARAM_INT);

                                if ($updateStmt->execute() && $updateStmt->rowCount() > 0) {
                                    $deductedItems[] = $invItem['product_name'];
                                    error_log("✓ Deducted 1 " . $invItem['product_name'] . " from inventory (ID: " . $invItem['id'] . ")");
                                } else {
                                    error_log("✗ Failed to deduct " . $invItem['product_name']);
                                }
                            } else {
                                error_log("✗ " . $invItem['product_name'] . " is out of stock");
                            }
                        } else {
                            error_log("✗ Item not found in inventory: " . $itemName);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Error processing missing items: ' . $e->getMessage());
            }
        } else {
            error_log("No missing items to process");
        }

        error_log("Total items deducted: " . count($deductedItems));
        error_log("=== END CHECKOUT INVENTORY DEBUG ===");

        // Continue with normal checkout process
        if (isset($booking['booking_id']) && !empty($booking['booking_id'])) {
            try {
                $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
                $hasReportsTable = $checkReportsTable->rowCount() > 0;

                if ($hasReportsTable) {
                    $checkExistsStmt = $conn->prepare("SELECT id FROM reports WHERE booking_id = :booking_id LIMIT 1");
                    $checkExistsStmt->bindParam(':booking_id', $booking['booking_id']);
                    $checkExistsStmt->execute();
                    $existingReport = $checkExistsStmt->fetch(PDO::FETCH_ASSOC);

                    $totalAmount = computeBookingTotalAmount([
                        'room_type' => $booking['room_type'] ?? '',
                        'duration' => $booking['duration'] ?? 0,
                        'duration_unit' => $booking['duration_unit'] ?? 'hours',
                        'promo' => $booking['promo'] ?? null,
                        'breakfast' => $booking['breakfast'] ?? null,
                        'hygiene_kit_used' => $booking['hygiene_kit_used'] ?? 0,
                        'hygiene_kit_price' => $booking['hygiene_kit_price'] ?? 0,
                        'room_price' => floatval($booking['room_price'] ?? 0)
                    ]);

                    if ($grandTotalPosted !== null && $grandTotalPosted >= 0) {
                        $totalAmount = round($grandTotalPosted, 2);
                    } elseif ($durationTotalPosted !== null || $foodTotalPosted !== null || $addonsTotalPosted !== null) {
                        $totalAmount = round(
                            max(0, $durationTotalPosted ?? 0) +
                            max(0, $foodTotalPosted ?? 0) +
                            max(0, $addonsTotalPosted ?? 0),
                            2
                        );
                    }

                    if ($existingReport) {
                        $referenceNoValue = $booking['reference_no'] ?? null;
                        if (empty($referenceNoValue)) {
                            if (!empty($booking['reference_no_g_cash'])) {
                                $referenceNoValue = $booking['reference_no_g_cash'];
                            } elseif (!empty($booking['reference_no_maya'])) {
                                $referenceNoValue = $booking['reference_no_maya'];
                            }
                        }

                        $paymentMethodValue = $booking['payment_status'] ?? null;
                        $roomPriceValue = floatval($booking['room_price'] ?? 0);
                        $missingItemsListValue = $booking['missing_items_list'] ?? null;
                        $missingItemsFeesValue = floatval($booking['missing_items_fees'] ?? 0);
                        $penaltyAmountValue = floatval($booking['penalty_amount'] ?? 0);
                        $penaltyListValue = $booking['penalty_list'] ?? null;

                        // Automatically set status to 'Paid' if there are missing items fees or penalty fees
                        $additionalFeesStatusValue = ($missingItemsFeesValue > 0 || $penaltyAmountValue > 0) ? 'Paid' : ($booking['additional_fees_status'] ?? 'None');
                        $additionalFeesPaymentMethodValue = $booking['additional_fees_payment_method'] ?? null;
                        $additionalFeesReferenceNoValue = $booking['additional_fees_reference_no'] ?? null;

                        $updateReportsStmt = $conn->prepare("
                            UPDATE reports 
                            SET status = 'Checked Out', 
                                checked_out_at = :checked_out_at,
                                contact_no = :contact_no,
                                reason_for_stay = :reason_for_stay,
                                address = :address,
                                total_amount = :total_amount,
                                payment_status = :payment_status,
                                reference_no = :reference_no,
                                room_price = :room_price,
                                additional_guest = :additional_guest,
                                additional_pet = :additional_pet,
                                missing_items_list = :missing_items_list,
                                missing_items_fees = :missing_items_fees,
                                penalty_amount = :penalty_amount,
                                penalty_list = :penalty_list,
                                additional_fees_status = :additional_fees_status,
                                additional_fees_payment_method = :additional_fees_payment_method,
                                additional_fees_reference_no = :additional_fees_reference_no,
                                deposit = :deposit,
                                deposit_cash = :deposit_cash,
                                deposit_g_cash = :deposit_g_cash,
                                deposit_maya = :deposit_maya,
                                deposit_details = :deposit_details,
                                deposit_gcash_ref = :deposit_gcash_ref,
                                deposit_maya_ref = :deposit_maya_ref,
                                payment_status_g_cash = :payment_status_g_cash,
                                payment_status_cash = :payment_status_cash,
                                payment_status_maya = :payment_status_maya,
                                extend_hours = :extend_hours,
                                extend_minutes = :extend_minutes,
                                extend_price = :extend_price,
                                extend_regular_rate = :extend_regular_rate,
                                extend_bundle_rate = :extend_bundle_rate,
                                extend_bundle_breakfast = :extend_bundle_breakfast,
                                payment_date_time = :payment_date_time,
                                encoder_checkout = :encoder_checkout,
                                vehicle_type = :vehicle_type,
                                plate_number = :plate_number,
                                vehicle_description = :vehicle_description,
                                payment_amount_cash_history = :payment_amount_cash_history,
                                payment_amount_g_cash_history = :payment_amount_g_cash_history,
                                payment_amount_maya_history = :payment_amount_maya_history,
                                discount_enabled = :discount_enabled,
                                discount_type = :discount_type,
                                sc_pwd_count = :sc_pwd_count,
                                discount_amount = :discount_amount,
                                discount_amount_history = :discount_amount_history,
                                id_number = :id_number
                            WHERE booking_id = :booking_id
                        ");
                        $updateReportsStmt->bindParam(':booking_id', $booking['booking_id']);
                        $updateReportsStmt->bindParam(':checked_out_at', $checkedOutAtValue);

                        // Bind contact_no, reason_for_stay, and address
                        $contactNoValue = $booking['contact_no'] ?? null;
                        $reasonForStayValue = $booking['reason_for_stay'] ?? null;
                        $addressValue = $booking['address'] ?? null;
                        $updateReportsStmt->bindParam(':contact_no', $contactNoValue);
                        $updateReportsStmt->bindParam(':reason_for_stay', $reasonForStayValue);
                        $updateReportsStmt->bindParam(':address', $addressValue);

                        $updateReportsStmt->bindParam(':total_amount', $totalAmount);
                        $updateReportsStmt->bindParam(':payment_status', $paymentMethodValue);
                        $updateReportsStmt->bindParam(':reference_no', $referenceNoValue);
                        $updateReportsStmt->bindParam(':room_price', $roomPriceValue);
                        $additionalGuestValue = intval($booking['additional_guest'] ?? 0);
                        $additionalPetValue = intval($booking['additional_pet'] ?? 0);
                        $updateReportsStmt->bindParam(':additional_guest', $additionalGuestValue);
                        $updateReportsStmt->bindParam(':additional_pet', $additionalPetValue, PDO::PARAM_INT);
                        if ($missingItemsListValue === null) {
                            $updateReportsStmt->bindValue(':missing_items_list', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':missing_items_list', $missingItemsListValue);
                        }
                        $updateReportsStmt->bindParam(':missing_items_fees', $missingItemsFeesValue);
                        $updateReportsStmt->bindParam(':penalty_amount', $penaltyAmountValue);
                        if ($penaltyListValue === null) {
                            $updateReportsStmt->bindValue(':penalty_list', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':penalty_list', $penaltyListValue);
                        }
                        $updateReportsStmt->bindParam(':additional_fees_status', $additionalFeesStatusValue);
                        if ($additionalFeesPaymentMethodValue === null) {
                            $updateReportsStmt->bindValue(':additional_fees_payment_method', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_fees_payment_method', $additionalFeesPaymentMethodValue);
                        }
                        if ($additionalFeesReferenceNoValue === null) {
                            $updateReportsStmt->bindValue(':additional_fees_reference_no', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_fees_reference_no', $additionalFeesReferenceNoValue);
                        }

                        // Bind deposit params for UPDATE
                        $depositValue = $booking['deposit'] ?? 0;
                        $depositDetailsValue = $booking['deposit_details'] ?? null;
                        $depositGcashRefValue = $booking['deposit_gcash_ref'] ?? null;
                        $depositMayaRefValue = $booking['deposit_maya_ref'] ?? null;
                        $depositCashValue = floatval($booking['deposit_cash'] ?? 0);
                        $depositGCashValue = floatval($booking['deposit_g_cash'] ?? 0);
                        $depositMayaValue = floatval($booking['deposit_maya'] ?? 0);

                        $updateReportsStmt->bindParam(':deposit', $depositValue);
                        $updateReportsStmt->bindParam(':deposit_cash', $depositCashValue);
                        $updateReportsStmt->bindParam(':deposit_g_cash', $depositGCashValue);
                        $updateReportsStmt->bindParam(':deposit_maya', $depositMayaValue);
                        $updateReportsStmt->bindParam(':deposit_details', $depositDetailsValue);
                        $updateReportsStmt->bindParam(':deposit_gcash_ref', $depositGcashRefValue);
                        $updateReportsStmt->bindParam(':deposit_maya_ref', $depositMayaRefValue);

                        // Derive payment status columns from actual payment data
                        $psGcash = $booking['payment_status_g_cash'] ?? null;
                        $psCash = $booking['payment_status_cash'] ?? null;
                        $psMaya = $booking['payment_status_maya'] ?? null;

                        // Get deposit amounts from individual columns
                        $depositCash = floatval($booking['deposit_cash'] ?? 0);
                        $depositGcash = floatval($booking['deposit_g_cash'] ?? 0);
                        $depositMaya = floatval($booking['deposit_maya'] ?? 0);

                        // Get downpayment amounts
                        $downCash = floatval($booking['downpayment_cash'] ?? 0);
                        $downGcash = floatval($booking['downpayment_gcash'] ?? 0);
                        $downMaya = floatval($booking['downpayment_maya'] ?? 0);

                        // If payment_status columns are empty, build them from deposit + downpayment
                        if (empty($psGcash) && empty($psCash) && empty($psMaya)) {
                            // Calculate totals (deposit + downpayment)
                            $totalCash = $depositCash + $downCash;
                            $totalGcash = $depositGcash + $downGcash;
                            $totalMaya = $depositMaya + $downMaya;

                            if ($totalCash > 0) {
                                $psCash = 'Cash (₱' . number_format($totalCash, 2) . ')';
                            }
                            if ($totalGcash > 0) {
                                $psGcash = 'G-cash (₱' . number_format($totalGcash, 2) . ')';
                            }
                            if ($totalMaya > 0) {
                                $psMaya = 'Maya (₱' . number_format($totalMaya, 2) . ')';
                            }
                        }

                        $updateReportsStmt->bindParam(':payment_status_g_cash', $psGcash);
                        $updateReportsStmt->bindParam(':payment_status_cash', $psCash);
                        $updateReportsStmt->bindParam(':payment_status_maya', $psMaya);

                        // Bind extend fields for UPDATE
                        $extendHoursValue = intval($booking['extend_hours'] ?? 0);
                        $extendMinutesValue = intval($booking['extend_minutes'] ?? 0);
                        $extendPriceValue = floatval($booking['extend_price'] ?? 0);
                        $updateReportsStmt->bindParam(':extend_hours', $extendHoursValue, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':extend_minutes', $extendMinutesValue, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':extend_price', $extendPriceValue);

                        $extendRegularRateValue = $booking['extend_regular_rate'] ?? null;
                        $extendBundleRateValue = $booking['extend_bundle_rate'] ?? null;
                        $extendBundleBreakfastValue = $booking['extend_bundle_breakfast'] ?? null;
                        $updateReportsStmt->bindParam(':extend_regular_rate', $extendRegularRateValue);
                        $updateReportsStmt->bindParam(':extend_bundle_rate', $extendBundleRateValue);
                        $updateReportsStmt->bindParam(':extend_bundle_breakfast', $extendBundleBreakfastValue);

                        // Carry payment_date_time from booking record
                        $paymentDtValue = $booking['payment_date_time'] ?? null;
                        if ($paymentDtValue === null) {
                            $updateReportsStmt->bindValue(':payment_date_time', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_date_time', $paymentDtValue);
                        }
                        $updateReportsStmt->bindParam(':encoder_checkout', $encoder_checkout);

                        // Bind vehicle details for UPDATE
                        $vehicleTypeValue = $booking['vehicle_type'] ?? null;
                        $plateNumberValue = $booking['plate_number'] ?? null;
                        $vehicleDescriptionValue = $booking['vehicle_description'] ?? null;
                        $updateReportsStmt->bindParam(':vehicle_type', $vehicleTypeValue);
                        $updateReportsStmt->bindParam(':plate_number', $plateNumberValue);
                        $updateReportsStmt->bindParam(':vehicle_description', $vehicleDescriptionValue);

                        // Carry payment_amount_*_history from bookings into reports (critical for paymentOptionsModal)
                        $paCashHist = $booking['payment_amount_cash_history'] ?? null;
                        $paGCashHist = $booking['payment_amount_g_cash_history'] ?? null;
                        $paMayaHist = $booking['payment_amount_maya_history'] ?? null;
                        if ($paCashHist === null) {
                            $updateReportsStmt->bindValue(':payment_amount_cash_history', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_amount_cash_history', $paCashHist);
                        }
                        if ($paGCashHist === null) {
                            $updateReportsStmt->bindValue(':payment_amount_g_cash_history', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_amount_g_cash_history', $paGCashHist);
                        }
                        if ($paMayaHist === null) {
                            $updateReportsStmt->bindValue(':payment_amount_maya_history', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_amount_maya_history', $paMayaHist);
                        }

                        $discountEnabledValue = intval($booking['discount_enabled'] ?? 0);
                        $discountTypeValue = $booking['discount_type'] ?? 'regular';
                        $scPwdCountValue = intval($booking['sc_pwd_count'] ?? 0);
                        $discountAmountValue = floatval($booking['discount_amount'] ?? 0);
                        $discountAmountHistoryValue = $booking['discount_amount_history'] ?? '';
                        $idNumberValue = $booking['id_number'] ?? '';

                        $updateReportsStmt->bindParam(':discount_enabled', $discountEnabledValue, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':discount_type', $discountTypeValue);
                        $updateReportsStmt->bindParam(':sc_pwd_count', $scPwdCountValue, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':discount_amount', $discountAmountValue);
                        $updateReportsStmt->bindParam(':discount_amount_history', $discountAmountHistoryValue);
                        $updateReportsStmt->bindParam(':id_number', $idNumberValue);

                        $updateReportsStmt->execute();
                    } else {
                        $promoValue = $booking['promo'] ?? null;
                        $breakfastValue = $booking['breakfast'] ?? null;
                        $paymentMethodValue = $booking['payment_status'] ?? null;
                        $referenceNoValue = $booking['reference_no'] ?? null;
                        if (empty($referenceNoValue)) {
                            if (!empty($booking['reference_no_g_cash'])) {
                                $referenceNoValue = $booking['reference_no_g_cash'];
                            } elseif (!empty($booking['reference_no_maya'])) {
                                $referenceNoValue = $booking['reference_no_maya'];
                            }
                        }
                        $referralValue = $booking['referral_name'] ?? null;
                        $supplierValue = resolveSupplier($booking['supplier'] ?? '', $referralValue);
                        $additionalValue = $booking['additional'] ?? null;
                        $paidStatusValue = $booking['paid_status'] ?? 'Unpaid';
                        $hygieneUsedValue = $booking['hygiene_kit_used'] ?? 0;
                        $hygienePriceValue = $booking['hygiene_kit_price'] ?? 0;
                        $roomPriceValue = floatval($booking['room_price'] ?? 0);
                        $missingItemsListValue = $booking['missing_items_list'] ?? null;
                        $missingItemsFeesValue = floatval($booking['missing_items_fees'] ?? 0);
                        $penaltyAmountValue = floatval($booking['penalty_amount'] ?? 0);
                        $penaltyListValue = $booking['penalty_list'] ?? null;

                        // Automatically set status to 'Paid' if there are missing items fees or penalty fees
                        $additionalFeesStatusValue = ($missingItemsFeesValue > 0 || $penaltyAmountValue > 0) ? 'Paid' : ($booking['additional_fees_status'] ?? 'None');
                        $additionalFeesPaymentMethodValue = $booking['additional_fees_payment_method'] ?? null;
                        $additionalFeesReferenceNoValue = $booking['additional_fees_reference_no'] ?? null;

                        $insertReportsStmt = $conn->prepare("
                            INSERT INTO reports (
                                id, booking_id, room_id, room_type, guest_name, guest_type, contact_person_name, contact_no, tin_number, reason_for_stay, address, request,
                                promo, breakfast, additional_guest, additional_pet, payment_status, reference_no, referral_name, supplier, additional, paid_status,
                                check_in, check_out, duration, duration_unit, hours, 
                                status, booking_type, room_image, hygiene_kit_used, hygiene_kit_price, total_amount, room_price,
                                missing_items_list, missing_items_fees, penalty_amount, penalty_list, additional_fees_status, additional_fees_payment_method, additional_fees_reference_no, checked_out_at,
                                deposit, deposit_cash, deposit_g_cash, deposit_maya, deposit_details, deposit_gcash_ref, deposit_maya_ref,
                                payment_status_g_cash, payment_status_cash, payment_status_maya,
                                extend_hours, extend_minutes, extend_price, extend_regular_rate, extend_bundle_rate, extend_bundle_breakfast,
                                payment_date_time,
                                encoder_checkout,
                                vehicle_type, plate_number, vehicle_description,
                                payment_amount_cash_history, payment_amount_g_cash_history, payment_amount_maya_history,
                                discount_enabled, discount_type, sc_pwd_count, discount_amount, discount_amount_history, id_number
                            ) VALUES (
                                :id, :booking_id, :room_id, :room_type, :guest_name, :guest_type, :contact_person_name, :contact_no, :tin_number, :reason_for_stay, :address, :request,
                                :promo, :breakfast, :additional_guest, :additional_pet, :payment_status, :reference_no, :referral_name, :supplier, :additional, :paid_status,
                                :check_in, :check_out, :duration, :duration_unit, :hours,
                                'Checked Out', :booking_type, :room_image, :hygiene_kit_used, :hygiene_kit_price, :total_amount, :room_price,
                                :missing_items_list, :missing_items_fees, :penalty_amount, :penalty_list, :additional_fees_status, :additional_fees_payment_method, :additional_fees_reference_no, :checked_out_at,
                                :deposit, :deposit_cash, :deposit_g_cash, :deposit_maya, :deposit_details, :deposit_gcash_ref, :deposit_maya_ref,
                                :payment_status_g_cash, :payment_status_cash, :payment_status_maya,
                                :extend_hours, :extend_minutes, :extend_price, :extend_regular_rate, :extend_bundle_rate, :extend_bundle_breakfast,
                                :payment_date_time,
                                :encoder_checkout,
                                :vehicle_type, :plate_number, :vehicle_description,
                                :payment_amount_cash_history, :payment_amount_g_cash_history, :payment_amount_maya_history,
                                :discount_enabled, :discount_type, :sc_pwd_count, :discount_amount, :discount_amount_history, :id_number
                            )
                        ");
                        $insertReportsStmt->bindParam(':id', $booking['id'], PDO::PARAM_INT);
                        $insertReportsStmt->bindParam(':booking_id', $booking['booking_id']);
                        $insertReportsStmt->bindParam(':room_id', $booking['room_id']);
                        $insertReportsStmt->bindParam(':room_type', $booking['room_type']);
                        $guestValue = $booking['guest_name'] ?? '';
                        $guestTypeValue = $booking['guest_type'] ?? null;
                        $contactPersonValue = $booking['contact_person_name'] ?? null;
                        $contactNoValue = $booking['contact_no'] ?? null;
                        $tinNumberValue = $booking['tin_number'] ?? null;
                        $reasonForStayValue = $booking['reason_for_stay'] ?? null;
                        $addressValue = $booking['address'] ?? null;
                        $requestValue = $booking['request'] ?? '';
                        $insertReportsStmt->bindParam(':guest_name', $guestValue);
                        $insertReportsStmt->bindParam(':guest_type', $guestTypeValue);
                        $insertReportsStmt->bindParam(':contact_person_name', $contactPersonValue);
                        $insertReportsStmt->bindParam(':contact_no', $contactNoValue);
                        $insertReportsStmt->bindParam(':tin_number', $tinNumberValue);
                        $insertReportsStmt->bindParam(':reason_for_stay', $reasonForStayValue);
                        $insertReportsStmt->bindParam(':address', $addressValue);
                        $insertReportsStmt->bindParam(':request', $requestValue);
                        $insertReportsStmt->bindParam(':promo', $promoValue);
                        $insertReportsStmt->bindParam(':breakfast', $breakfastValue);
                        $additionalGuestValue = intval($booking['additional_guest'] ?? 0);
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
                        if ($missingItemsListValue === null) {
                            $insertReportsStmt->bindValue(':missing_items_list', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':missing_items_list', $missingItemsListValue);
                        }
                        $insertReportsStmt->bindParam(':missing_items_fees', $missingItemsFeesValue);
                        $insertReportsStmt->bindParam(':penalty_amount', $penaltyAmountValue);
                        if ($penaltyListValue === null) {
                            $insertReportsStmt->bindValue(':penalty_list', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':penalty_list', $penaltyListValue);
                        }
                        $insertReportsStmt->bindParam(':additional_fees_status', $additionalFeesStatusValue);
                        if ($additionalFeesPaymentMethodValue === null) {
                            $insertReportsStmt->bindValue(':additional_fees_payment_method', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':additional_fees_payment_method', $additionalFeesPaymentMethodValue);
                        }
                        if ($additionalFeesReferenceNoValue === null) {
                            $insertReportsStmt->bindValue(':additional_fees_reference_no', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':additional_fees_reference_no', $additionalFeesReferenceNoValue);
                            $insertReportsStmt->bindParam(':checked_out_at', $checkedOutAtValue);
                        }

                        // Bind deposit params for INSERT
                        $depositValue = $booking['deposit'] ?? 0;
                        $depositDetailsValue = $booking['deposit_details'] ?? null;
                        $depositGcashRefValue = $booking['deposit_gcash_ref'] ?? null;
                        $depositMayaRefValue = $booking['deposit_maya_ref'] ?? null;
                        $depositCashValue = floatval($booking['deposit_cash'] ?? 0);
                        $depositGCashValue = floatval($booking['deposit_g_cash'] ?? 0);
                        $depositMayaValue = floatval($booking['deposit_maya'] ?? 0);

                        $insertReportsStmt->bindParam(':deposit', $depositValue);
                        $insertReportsStmt->bindParam(':deposit_cash', $depositCashValue);
                        $insertReportsStmt->bindParam(':deposit_g_cash', $depositGCashValue);
                        $insertReportsStmt->bindParam(':deposit_maya', $depositMayaValue);
                        $insertReportsStmt->bindParam(':deposit_details', $depositDetailsValue);
                        $insertReportsStmt->bindParam(':deposit_gcash_ref', $depositGcashRefValue);
                        $insertReportsStmt->bindParam(':deposit_maya_ref', $depositMayaRefValue);

                        // Derive payment status columns from actual payment data
                        $psGcash = $booking['payment_status_g_cash'] ?? null;
                        $psCash = $booking['payment_status_cash'] ?? null;
                        $psMaya = $booking['payment_status_maya'] ?? null;

                        // Get deposit amounts from individual columns
                        $depositCash = floatval($booking['deposit_cash'] ?? 0);
                        $depositGcash = floatval($booking['deposit_g_cash'] ?? 0);
                        $depositMaya = floatval($booking['deposit_maya'] ?? 0);

                        // Get downpayment amounts
                        $downCash = floatval($booking['downpayment_cash'] ?? 0);
                        $downGcash = floatval($booking['downpayment_gcash'] ?? 0);
                        $downMaya = floatval($booking['downpayment_maya'] ?? 0);

                        // If payment_status columns are empty, build them from deposit + downpayment
                        if (empty($psGcash) && empty($psCash) && empty($psMaya)) {
                            // Calculate totals (deposit + downpayment)
                            $totalCash = $depositCash + $downCash;
                            $totalGcash = $depositGcash + $downGcash;
                            $totalMaya = $depositMaya + $downMaya;

                            if ($totalCash > 0) {
                                $psCash = 'Cash (₱' . number_format($totalCash, 2) . ')';
                            }
                            if ($totalGcash > 0) {
                                $psGcash = 'G-cash (₱' . number_format($totalGcash, 2) . ')';
                            }
                            if ($totalMaya > 0) {
                                $psMaya = 'Maya (₱' . number_format($totalMaya, 2) . ')';
                            }
                        }

                        $insertReportsStmt->bindParam(':payment_status_g_cash', $psGcash);
                        $insertReportsStmt->bindParam(':payment_status_cash', $psCash);
                        $insertReportsStmt->bindParam(':payment_status_maya', $psMaya);

                        // Bind extend fields for INSERT
                        $extendHoursValue = intval($booking['extend_hours'] ?? 0);
                        $extendMinutesValue = intval($booking['extend_minutes'] ?? 0);
                        $extendPriceValue = floatval($booking['extend_price'] ?? 0);
                        $insertReportsStmt->bindParam(':extend_hours', $extendHoursValue, PDO::PARAM_INT);
                        $insertReportsStmt->bindParam(':extend_minutes', $extendMinutesValue, PDO::PARAM_INT);
                        $insertReportsStmt->bindParam(':extend_price', $extendPriceValue);

                        $extendRegularRateValue = $booking['extend_regular_rate'] ?? null;
                        $extendBundleRateValue = $booking['extend_bundle_rate'] ?? null;
                        $extendBundleBreakfastValue = $booking['extend_bundle_breakfast'] ?? null;
                        $insertReportsStmt->bindParam(':extend_regular_rate', $extendRegularRateValue);
                        $insertReportsStmt->bindParam(':extend_bundle_rate', $extendBundleRateValue);
                        $insertReportsStmt->bindParam(':extend_bundle_breakfast', $extendBundleBreakfastValue);

                        // Carry payment_date_time from booking record
                        $paymentDtValue = $booking['payment_date_time'] ?? null;
                        if ($paymentDtValue === null) {
                            $insertReportsStmt->bindValue(':payment_date_time', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':payment_date_time', $paymentDtValue);
                        }
                        $insertReportsStmt->bindParam(':encoder_checkout', $encoder_checkout);

                        // Bind vehicle details
                        $vehicleTypeValue = $booking['vehicle_type'] ?? null;
                        $plateNumberValue = $booking['plate_number'] ?? null;
                        $vehicleDescriptionValue = $booking['vehicle_description'] ?? null;
                        $insertReportsStmt->bindParam(':vehicle_type', $vehicleTypeValue);
                        $insertReportsStmt->bindParam(':plate_number', $plateNumberValue);
                        $insertReportsStmt->bindParam(':vehicle_description', $vehicleDescriptionValue);

                        // Carry payment_amount_*_history from bookings (stored by update_payment_status.php)
                        $paCashHist = $booking['payment_amount_cash_history'] ?? null;
                        $paGCashHist = $booking['payment_amount_g_cash_history'] ?? null;
                        $paMayaHist = $booking['payment_amount_maya_history'] ?? null;
                        if ($paCashHist === null) {
                            $insertReportsStmt->bindValue(':payment_amount_cash_history', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':payment_amount_cash_history', $paCashHist);
                        }
                        if ($paGCashHist === null) {
                            $insertReportsStmt->bindValue(':payment_amount_g_cash_history', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':payment_amount_g_cash_history', $paGCashHist);
                        }
                        if ($paMayaHist === null) {
                            $insertReportsStmt->bindValue(':payment_amount_maya_history', null, PDO::PARAM_NULL);
                        } else {
                            $insertReportsStmt->bindParam(':payment_amount_maya_history', $paMayaHist);
                        }

                        $discountEnabledValue = intval($booking['discount_enabled'] ?? 0);
                        $discountTypeValue = $booking['discount_type'] ?? 'regular';
                        $scPwdCountValue = intval($booking['sc_pwd_count'] ?? 0);
                        $discountAmountValue = floatval($booking['discount_amount'] ?? 0);
                        $discountAmountHistoryValue = $booking['discount_amount_history'] ?? '';
                        $idNumberValue = $booking['id_number'] ?? '';

                        $insertReportsStmt->bindParam(':discount_enabled', $discountEnabledValue, PDO::PARAM_INT);
                        $insertReportsStmt->bindParam(':discount_type', $discountTypeValue);
                        $insertReportsStmt->bindParam(':sc_pwd_count', $scPwdCountValue, PDO::PARAM_INT);
                        $insertReportsStmt->bindParam(':discount_amount', $discountAmountValue);
                        $insertReportsStmt->bindParam(':discount_amount_history', $discountAmountHistoryValue);
                        $insertReportsStmt->bindParam(':id_number', $idNumberValue);

                        $insertReportsStmt->execute();
                    }
                }
            } catch (PDOException $e) {
                error_log("Failed to update/insert reports table: " . $e->getMessage());
            }
        }

        // ==========================================================
        // INVENTORY DEDUCTION RULE (CHECK-OUT ONLY)
        // ==========================================================
        // Business rule from UI:
        // - Every successful Check-Out deducts: Hygiene Kit (1) + Tissue (1)
        // - Missing items (Blanket, Pillow, Bedsheet, etc.) are deducted ONLY when listed in missing_items_list
        //
        // The booking row may not have hygiene/tissue inventory IDs saved, so we look them up by name.

        // Ensure columns exist for tracking (best-effort; don't fail checkout if ALTER fails)
        try {
            $c = $conn->query("SHOW COLUMNS FROM bookings LIKE 'hygiene_kit_inventory_id'");
            if ($c && $c->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN hygiene_kit_inventory_id INT DEFAULT NULL");
            }
        } catch (Exception $e) {
        }
        try {
            $c = $conn->query("SHOW COLUMNS FROM bookings LIKE 'tissue_inventory_id'");
            if ($c && $c->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN tissue_inventory_id INT DEFAULT NULL");
            }
        } catch (Exception $e) {
        }
        try {
            $c = $conn->query("SHOW COLUMNS FROM bookings LIKE 'tissue_used'");
            if ($c && $c->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN tissue_used INT DEFAULT 0");
            }
        } catch (Exception $e) {
        }
        try {
            $c = $conn->query("SHOW COLUMNS FROM bookings LIKE 'hygiene_kit_used'");
            if ($c && $c->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN hygiene_kit_used TINYINT(1) DEFAULT 0");
            }
        } catch (Exception $e) {
        }

        // Look up inventory IDs if not stored
        $hygiene_kit_inventory_id = intval($booking['hygiene_kit_inventory_id'] ?? 0);
        $tissue_inventory_id = intval($booking['tissue_inventory_id'] ?? 0);
        if ($hygiene_kit_inventory_id <= 0) {
            try {
                $s = $conn->prepare("SELECT id FROM inventory WHERE LOWER(TRIM(product_name)) = 'hygiene kit' LIMIT 1");
                $s->execute();
                $r = $s->fetch(PDO::FETCH_ASSOC);
                $hygiene_kit_inventory_id = intval($r['id'] ?? 0);
            } catch (Exception $e) {
            }
        }
        if ($tissue_inventory_id <= 0) {
            try {
                $s = $conn->prepare("SELECT id FROM inventory WHERE LOWER(TRIM(product_name)) = 'tissue' LIMIT 1");
                $s->execute();
                $r = $s->fetch(PDO::FETCH_ASSOC);
                $tissue_inventory_id = intval($r['id'] ?? 0);
            } catch (Exception $e) {
            }
        }

        // Deduct 1 Hygiene Kit + 1 Tissue on successful check-out
        if ($hygiene_kit_inventory_id > 0) {
            $updateHygieneStmt = $conn->prepare("UPDATE inventory SET stock = stock - 1 WHERE id = :id AND stock > 0");
            $updateHygieneStmt->bindParam(':id', $hygiene_kit_inventory_id, PDO::PARAM_INT);
            $updateHygieneStmt->execute();
        }
        if ($tissue_inventory_id > 0) {
            $updateTissueStmt = $conn->prepare("UPDATE inventory SET stock = stock - 1 WHERE id = :id AND stock > 0");
            $updateTissueStmt->bindParam(':id', $tissue_inventory_id, PDO::PARAM_INT);
            $updateTissueStmt->execute();
        }

        // Persist tracking fields back to booking row (only if the row will remain, e.g. Reservation checkout)
        // For normal bookings this is best-effort and may be deleted right after.
        try {
            $trackStmt = $conn->prepare("
                UPDATE bookings
                SET hygiene_kit_used = 1,
                    tissue_used = 1,
                    hygiene_kit_inventory_id = :hkid,
                    tissue_inventory_id = :tid
                WHERE id = :bid
            ");
            $trackStmt->bindParam(':hkid', $hygiene_kit_inventory_id, PDO::PARAM_INT);
            $trackStmt->bindParam(':tid', $tissue_inventory_id, PDO::PARAM_INT);
            $trackStmt->bindParam(':bid', $booking_id, PDO::PARAM_INT);
            $trackStmt->execute();
        } catch (Exception $e) {
        }

        // For Reservation-type bookings, keep the row and just mark it as
        // checked out so it can still appear in Reservationlist.php as "Done".
        // For other booking types, preserve the original behavior and delete.
        if (!empty($booking['booking_type']) && $booking['booking_type'] === 'Reservation') {
            $updateBookingStatusStmt = $conn->prepare("
                UPDATE bookings
                SET status = 'Checked Out',
                    encoder_checkout = :encoder_checkout
                WHERE id = :booking_id
            ");
            $updateBookingStatusStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $updateBookingStatusStmt->bindParam(':encoder_checkout', $encoder_checkout);
            $updateBookingStatusStmt->execute();
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM bookings WHERE id = :booking_id");
            $deleteStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $deleteStmt->execute();
        }

        if (isset($booking['room_id'])) {
            try {
                $updateRoomStmt = $conn->prepare("UPDATE rooms SET status = 'Available' WHERE room_id = :room_id");
                $updateRoomStmt->bindParam(':room_id', $booking['room_id']);
                $updateRoomStmt->execute();
            } catch (PDOException $e) {
                error_log("Failed to update room status: " . $e->getMessage());
            }
        }

        $response['success'] = true;
        $response['message'] = 'Booking checked out successfully! Room is now available.';

        if (!empty($deductedItems)) {
            $response['inventory_deductions'] = $deductedItems;
            $response['message'] .= ' Deducted from inventory: ' . implode(', ', $deductedItems);
        }

    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method!';
}

echo json_encode($response);
?>