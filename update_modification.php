<?php
// Clean error handling for JSON responses
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Start output buffering to catch any unwanted output
ob_start();

// Start session
session_start();

// Set JSON header immediately
header('Content-Type: application/json');

// Function to clean output and return JSON
function returnJson($data)
{
    // Clean any output that might have been generated
    if (ob_get_length())
        ob_clean();
    echo json_encode($data);
    exit;
}

// Function to log and return error
function returnError($message, $details = [])
{
    error_log("Update modification error: " . $message);
    returnJson([
        'success' => false,
        'error' => $message,
        'details' => $details
    ]);
}

// ─── Safety net: catch PHP fatal errors (e.g. call on false) ─────────────────
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'PHP Fatal Error: ' . $error['message'] . ' at line ' . $error['line'] . ' in ' . $error['file']
        ]);
    }
});

if (!isset($_SESSION['username'])) {
    // Temporary bypass for debugging - remove this in production
    $_SESSION['username'] = 'debug_user';
    error_log("DEBUG: Session not set, using temporary bypass");
    // returnError('Not authenticated');
    // exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Log the received data for debugging
    error_log("Received data: " . print_r($data, true));

    if (!$data || !isset($data['id'])) {
        returnError('Invalid input data - missing id field');
    }

    // ── Payment Method History fields (from editable per-payment UI cards) ──
    $editedCashHist = isset($data['payment_amount_cash_history']) ? trim((string) $data['payment_amount_cash_history']) : null;
    $editedGcashHist = isset($data['payment_amount_g_cash_history']) ? trim((string) $data['payment_amount_g_cash_history']) : null;
    $editedMayaHist = isset($data['payment_amount_maya_history']) ? trim((string) $data['payment_amount_maya_history']) : null;
    $editedInstapayHist = isset($data['payment_amount_instapay_history']) ? trim((string) $data['payment_amount_instapay_history']) : null;
    $editedOnlineBankingHist = isset($data['payment_amount_online_banking_history']) ? trim((string) $data['payment_amount_online_banking_history']) : null;
    $editedAirbnbHist = isset($data['payment_amount_airbnb_history']) ? trim((string) $data['payment_amount_airbnb_history']) : null;
    $editedPaymentDateTime = isset($data['payment_date_time']) ? trim((string) $data['payment_date_time']) : null;
    $editedDiscountHist = array_key_exists('discount_amount_history', $data) ? trim((string) $data['discount_amount_history']) : null;
    $hasEditedHistory = ($editedCashHist !== null);   // presence of cash history key means the cards were rendered

    // Include database configuration
    require_once 'config.php';

    // Convert PDO connection to mysqli for compatibility with existing code
    // Use config.php variables instead of hardcoded values
    $mysqli_conn = new mysqli($host, $username, $password, $dbname);

    if ($mysqli_conn->connect_error) {
        returnError('Database connection failed', ['error' => $mysqli_conn->connect_error]);
    }

    $mysqli_conn->set_charset("utf8mb4");

    // Ensure autocommit is enabled
    $mysqli_conn->autocommit(TRUE);

    // Use mysqli connection for the rest of the code
    $conn = $mysqli_conn;

    // Ensure required columns exist in both tables
    $requiredColumns = [
        'payment_status' => "VARCHAR(255) NULL DEFAULT NULL",
        'payment_status_cash' => "TEXT NULL DEFAULT NULL",
        'payment_status_g_cash' => "TEXT NULL DEFAULT NULL",
        'payment_status_maya' => "TEXT NULL DEFAULT NULL",
        'payment_status_instapay' => "TEXT NULL DEFAULT NULL",
        'payment_status_online_banking' => "TEXT NULL DEFAULT NULL",
        'payment_status_airbnb' => "TEXT NULL DEFAULT NULL",
        'reference_no' => "TEXT NULL DEFAULT NULL",
        'reference_no_g_cash' => "VARCHAR(255) NULL DEFAULT NULL",
        'reference_no_maya' => "VARCHAR(255) NULL DEFAULT NULL",
        'reference_no_instapay' => "VARCHAR(255) NULL DEFAULT NULL",
        'reference_no_online_banking' => "VARCHAR(255) NULL DEFAULT NULL",
        'reference_no_airbnb' => "VARCHAR(255) NULL DEFAULT NULL",
        'payment_amount_cash_history' => "TEXT NULL DEFAULT NULL",
        'payment_amount_g_cash_history' => "TEXT NULL DEFAULT NULL",
        'payment_amount_maya_history' => "TEXT NULL DEFAULT NULL",
        'payment_amount_instapay_history' => "TEXT NULL DEFAULT NULL",
        'payment_amount_online_banking_history' => "TEXT NULL DEFAULT NULL",
        'payment_amount_airbnb_history' => "TEXT NULL DEFAULT NULL",
        'deposit' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_cash' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_g_cash' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_maya' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_instapay' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_online_banking' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_airbnb' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_details' => "TEXT NULL DEFAULT NULL",
        'deposit_gcash_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'deposit_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'deposit_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'deposit_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'discount_amount_history' => "TEXT NULL DEFAULT NULL",
        'lgp_discount_enabled' => "TINYINT(1) DEFAULT 0",
        'lgp_discount_amount' => "DECIMAL(10,2) DEFAULT 0",
        'lgp_approver_name' => "VARCHAR(255) NULL DEFAULT NULL",
        'vip_discount_enabled' => "TINYINT(1) DEFAULT 0",
        'vip_discount_amount' => "DECIMAL(10,2) DEFAULT 0",
        'vip_approver_name' => "VARCHAR(255) NULL DEFAULT NULL",
        'long_discount_enabled' => "TINYINT(1) DEFAULT 0",
        'long_discount_amount' => "DECIMAL(10,2) DEFAULT 0",
        'long_discount_percent' => "DECIMAL(5,2) DEFAULT 0",
        'additional_slippers' => "INT DEFAULT 0",
        'slipper_status' => "VARCHAR(20) DEFAULT 'added'",
        'new_deposit' => "DECIMAL(10,2) DEFAULT 0",
        'new_deposit_cash' => "DECIMAL(10,2) DEFAULT 0",
        'new_deposit_g_cash' => "DECIMAL(10,2) DEFAULT 0",
        'new_deposit_maya' => "DECIMAL(10,2) DEFAULT 0",
        'new_deposit_instapay' => "DECIMAL(10,2) DEFAULT 0",
        'new_deposit_online_banking' => "DECIMAL(10,2) DEFAULT 0",
        'new_deposit_airbnb' => "DECIMAL(10,2) DEFAULT 0",
        'new_deposit_details' => "TEXT NULL DEFAULT NULL",
        'new_deposit_gcash_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'new_deposit_maya_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'new_deposit_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'new_deposit_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'new_deposit_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'missing_items_fees' => "DECIMAL(10,2) DEFAULT 0",
        'missing_items_list' => "TEXT NULL DEFAULT NULL",
        'penalty_amount' => "DECIMAL(10,2) DEFAULT 0",
        'penalty_list' => "TEXT NULL DEFAULT NULL",
        'additional_fees_status' => "VARCHAR(50) DEFAULT 'None'"
    ];

    $tables = ['bookings', 'reports'];
    foreach ($tables as $table) {
        foreach ($requiredColumns as $columnName => $columnDefinition) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM $table LIKE '$columnName'");
                if ($checkColumn->num_rows == 0) {
                    $conn->query("ALTER TABLE $table ADD COLUMN $columnName $columnDefinition");
                    error_log("Added missing column $columnName to $table table");
                }
            } catch (Exception $e) {
                error_log("Failed to check/add column $columnName to $table: " . $e->getMessage());
            }
        }
    }

    $bookingId = $data['id'];

    // ── Find the booking row (bookings or reports table) ─────────────────────
    // Search by NUMERIC id for bookings, or booking_id string for reports
    $findQuery = "SELECT
                  id AS numeric_id,
                  CAST(id AS CHAR) COLLATE utf8mb4_general_ci AS id,
                  CAST(COALESCE(booking_id, '') AS CHAR) COLLATE utf8mb4_general_ci AS booking_id,
                  COALESCE(payment_status_cash,'') COLLATE utf8mb4_general_ci AS payment_status_cash,
                  COALESCE(payment_status_g_cash,'') COLLATE utf8mb4_general_ci AS payment_status_g_cash,
                  COALESCE(payment_status_maya,'') COLLATE utf8mb4_general_ci AS payment_status_maya,
                  COALESCE(deposit_cash, 0)  AS deposit_cash,
                  COALESCE(deposit_g_cash, 0) AS deposit_g_cash,
                  COALESCE(deposit_maya, 0)  AS deposit_maya,
                  COALESCE(payment_amount_cash_history,'') COLLATE utf8mb4_general_ci AS payment_amount_cash_history,
                  COALESCE(payment_amount_g_cash_history,'') COLLATE utf8mb4_general_ci AS payment_amount_g_cash_history,
                  COALESCE(payment_amount_maya_history,'') COLLATE utf8mb4_general_ci AS payment_amount_maya_history,
                  COALESCE(payment_amount_instapay_history,'') COLLATE utf8mb4_general_ci AS payment_amount_instapay_history,
                  COALESCE(payment_amount_online_banking_history,'') COLLATE utf8mb4_general_ci AS payment_amount_online_banking_history,
                  COALESCE(payment_amount_airbnb_history,'') COLLATE utf8mb4_general_ci AS payment_amount_airbnb_history,
                  'bookings' COLLATE utf8mb4_general_ci AS source
                  FROM bookings WHERE CAST(id AS CHAR) = ?
                  UNION ALL
                  SELECT
                  id AS numeric_id,
                  CAST(id AS CHAR) COLLATE utf8mb4_general_ci AS id,
                  CAST(booking_id AS CHAR) COLLATE utf8mb4_general_ci AS booking_id,
                  COALESCE(payment_status_cash,'') COLLATE utf8mb4_general_ci AS payment_status_cash,
                  COALESCE(payment_status_g_cash,'') COLLATE utf8mb4_general_ci AS payment_status_g_cash,
                  COALESCE(payment_status_maya,'') COLLATE utf8mb4_general_ci AS payment_status_maya,
                  COALESCE(deposit_cash, 0)  AS deposit_cash,
                  COALESCE(deposit_g_cash, 0) AS deposit_g_cash,
                  COALESCE(deposit_maya, 0)  AS deposit_maya,
                  COALESCE(payment_amount_cash_history,'') COLLATE utf8mb4_general_ci AS payment_amount_cash_history,
                  COALESCE(payment_amount_g_cash_history,'') COLLATE utf8mb4_general_ci AS payment_amount_g_cash_history,
                  COALESCE(payment_amount_maya_history,'') COLLATE utf8mb4_general_ci AS payment_amount_maya_history,
                  COALESCE(payment_amount_instapay_history,'') COLLATE utf8mb4_general_ci AS payment_amount_instapay_history,
                  COALESCE(payment_amount_online_banking_history,'') COLLATE utf8mb4_general_ci AS payment_amount_online_banking_history,
                  COALESCE(payment_amount_airbnb_history,'') COLLATE utf8mb4_general_ci AS payment_amount_airbnb_history,
                  'reports' COLLATE utf8mb4_general_ci AS source
                  FROM reports WHERE CAST(id AS CHAR) = ?
                  LIMIT 1";

    $findStmt = $conn->prepare($findQuery);
    if (!$findStmt) {
        returnError('Prepare (find) failed', ['error' => $conn->error]);
    }
    $findStmt->bind_param("ss", $bookingId, $bookingId);
    $findStmt->execute();
    $result = $findStmt->get_result();
    $current = $result->fetch_assoc();
    $findStmt->close();

    if (!$current) {
        returnError('Booking not found', ['booking_id' => $bookingId]);
    }


    $actualId = $current['id'];
    $numericId = $current['numeric_id'];
    $bookingIdField = $current['booking_id'];
    $sourceTable = $current['source'];


    error_log("Found booking - String ID: " . $actualId . ", Numeric ID: " . $numericId . ", Booking ID: " . $bookingIdField . ", Source: " . $sourceTable . ", Original booking_id param: " . $bookingId);

    // ── Resolve current deposit amount ────────────────────────────────────────
    $currentAmount = max(
        floatval($current['deposit_cash']),
        floatval($current['deposit_g_cash']),
        floatval($current['deposit_maya'])
    );

    if ($currentAmount == 0) {
        foreach (['payment_status_cash', 'payment_status_g_cash', 'payment_status_maya'] as $col) {
            if (!empty($current[$col]) && preg_match('/([0-9,]+\.?[0-9]*)/', $current[$col], $m)) {
                $currentAmount = floatval(str_replace(',', '', $m[1]));
                if ($currentAmount > 0)
                    break;
            }
        }
    }

    // Also check for newer payment methods in the database if available
    if ($currentAmount == 0) {
        // Try to get additional payment method data if columns exist
        $additionalQuery = "SELECT 
            COALESCE(deposit_instapay, 0) AS deposit_instapay,
            COALESCE(deposit_online_banking, 0) AS deposit_online_banking,
            COALESCE(deposit_airbnb, 0) AS deposit_airbnb,
            COALESCE(payment_status_instapay, '') AS payment_status_instapay,
            COALESCE(payment_status_online_banking, '') AS payment_status_online_banking,
            COALESCE(payment_status_airbnb, '') AS payment_status_airbnb
            FROM {$sourceTable} WHERE id = ? LIMIT 1";

        $additionalStmt = $conn->prepare($additionalQuery);
        if ($additionalStmt) {
            $additionalStmt->bind_param("i", $numericId);
            $additionalStmt->execute();
            $additionalResult = $additionalStmt->get_result();
            if ($additionalData = $additionalResult->fetch_assoc()) {
                $currentAmount = max(
                    $currentAmount,
                    floatval($additionalData['deposit_instapay']),
                    floatval($additionalData['deposit_online_banking']),
                    floatval($additionalData['deposit_airbnb'])
                );

                // If still zero, try to extract from payment status strings
                if ($currentAmount == 0) {
                    foreach (['payment_status_instapay', 'payment_status_online_banking', 'payment_status_airbnb'] as $col) {
                        if (!empty($additionalData[$col]) && preg_match('/([0-9,]+\.?[0-9]*)/', $additionalData[$col], $m)) {
                            $currentAmount = floatval(str_replace(',', '', $m[1]));
                            if ($currentAmount > 0)
                                break;
                        }
                    }
                }
            }
            $additionalStmt->close();
        }
    }

    // ── Parse room info ───────────────────────────────────────────────────────
    $roomParts = explode(' ', trim($data['room'] ?? ''));
    $roomId = array_pop($roomParts);
    $roomType = implode(' ', $roomParts);

    $checkIn = str_replace('T', ' ', $data['check_in'] ?? '');
    $checkOut = str_replace('T', ' ', $data['check_out'] ?? '');
    $checkedOutAt = !empty($data['checked_out_at']) ? str_replace('T', ' ', $data['checked_out_at']) : null;
    if (isset($data['status']) && $data['status'] === 'Confirmed') {
        $checkedOutAt = null;
    }
    $additionalGuest = intval($data['additional_guest'] ?? 0);
    $additionalPet = intval($data['additional_pet'] ?? 0);
    $additionalSlippers = intval($data['additional_slippers'] ?? 0);
    $slipperStatus = trim($data['slipper_status'] ?? 'added');
    if (!in_array($slipperStatus, ['added', 'missing', 'returned'], true)) {
        $slipperStatus = 'added';
    }

    $newDepositCash = floatval($data['new_deposit_cash'] ?? 0);
    $newDepositGcash = floatval($data['new_deposit_g_cash'] ?? 0);
    $newDepositMaya = floatval($data['new_deposit_maya'] ?? 0);
    $newDepositInstapay = floatval($data['new_deposit_instapay'] ?? 0);
    $newDepositOnlineBanking = floatval($data['new_deposit_online_banking'] ?? 0);
    $newDepositAirbnb = floatval($data['new_deposit_airbnb'] ?? 0);
    $newDeposit = floatval($data['new_deposit'] ?? 0);
    if ($newDeposit <= 0) {
        $newDeposit = $newDepositCash + $newDepositGcash + $newDepositMaya + $newDepositInstapay + $newDepositOnlineBanking + $newDepositAirbnb;
    }
    $newDepositDetails = $data['new_deposit_details'] ?? '';
    $newDepositGcashRef = $newDepositGcash > 0 ? trim($data['new_deposit_gcash_ref'] ?? '') : '';
    $newDepositMayaRef = $newDepositMaya > 0 ? trim($data['new_deposit_maya_ref'] ?? '') : '';
    $newDepositInstapayRef = $newDepositInstapay > 0 ? trim($data['new_deposit_instapay_ref'] ?? '') : '';
    $newDepositOnlineBankingRef = $newDepositOnlineBanking > 0 ? trim($data['new_deposit_online_banking_ref'] ?? '') : '';
    $newDepositAirbnbRef = $newDepositAirbnb > 0 ? trim($data['new_deposit_airbnb_ref'] ?? '') : '';

    // Missing Items Checklist + Penalty Fine Rules (shared with Booking checkoutModal)
    $missingItemsFees = floatval($data['missing_items_fees'] ?? 0);
    $missingItemsList = array_key_exists('missing_items_list', $data)
        ? ($data['missing_items_list'] ?? '[]')
        : null;
    if (is_array($missingItemsList)) {
        $missingItemsList = json_encode($missingItemsList);
    }
    if ($missingItemsList === null || $missingItemsList === '') {
        $missingItemsList = '[]';
    }

    $penaltyAmount = floatval($data['penalty_amount'] ?? 0);
    $penaltyList = array_key_exists('penalty_list', $data)
        ? ($data['penalty_list'] ?? '[]')
        : null;
    if (is_array($penaltyList)) {
        $penaltyList = json_encode($penaltyList);
    }
    if ($penaltyList === null || $penaltyList === '') {
        $penaltyList = '[]';
    }

    $additionalFeesStatus = trim((string) ($data['additional_fees_status'] ?? ''));
    if ($additionalFeesStatus === '') {
        if ($missingItemsFees > 0 || $penaltyAmount > 0) {
            $additionalFeesStatus = 'Pending';
        } else {
            $additionalFeesStatus = 'None';
        }
    }
    if (!in_array($additionalFeesStatus, ['None', 'Pending', 'Paid', 'Unpaid'], true)) {
        $additionalFeesStatus = ($missingItemsFees > 0 || $penaltyAmount > 0) ? 'Pending' : 'None';
    }
    if ($missingItemsFees <= 0 && $penaltyAmount <= 0 && $additionalFeesStatus !== 'Paid') {
        $additionalFeesStatus = 'None';
        $missingItemsList = '[]';
        $penaltyList = '[]';
    }

    // When no missing checklist fees remain, clear stale slipper_status so checkout UI stops showing slippers as missing
    if ($missingItemsFees <= 0) {
        $decodedMissing = json_decode($missingItemsList, true);
        $hasStandardSlippersMissing = false;
        if (is_array($decodedMissing)) {
            foreach ($decodedMissing as $missingItem) {
                $missingName = strtolower(trim((string) ($missingItem['name'] ?? $missingItem['item'] ?? '')));
                if ($missingName === 'slippers') {
                    $hasStandardSlippersMissing = true;
                    break;
                }
            }
        }
        if (!$hasStandardSlippersMissing && $slipperStatus === 'missing') {
            $slipperStatus = ($additionalSlippers > 0) ? 'returned' : 'added';
        }
    }

    // Ensure these are strings, not NULL
    $bookingType = $data['booking_type'] ?? 'Walk-in';
    $guestType = $data['guest_type'] ?? 'Solo';
    // guest_names is the pipe-joined full list; also accept explicit guest_name / second_guest_name from the modal
    $guestNamesRaw = trim($data['guest_names'] ?? '');
    if (!empty($data['guest_name'])) {
        // Modal sends explicit fields — use them directly
        $guestNames = strtoupper(trim($data['guest_name'] ?? ''));
        $secondGuestName = strtoupper(trim($data['second_guest_name'] ?? ''));
    } else {
        // Fallback: split pipe-joined guest_names
        $parts = array_map('trim', explode('|', $guestNamesRaw));
        $parts = array_filter($parts);
        $parts = array_values($parts);
        $guestNames = !empty($parts[0]) ? strtoupper($parts[0]) : '';
        $secondGuestName = count($parts) > 1 ? strtoupper(implode(' | ', array_slice($parts, 1))) : '';
    }
    $additionalGuestNames = strtoupper(trim($data['additional_guest_names_from_modal'] ?? ''));

    $reasonForStay = $data['reason_for_stay'] ?? '';
    $contactPersonName = $data['contact_person_name'] ?? '';
    $contactNo = $data['contact_no'] ?? '';
    $address = $data['address'] ?? '';
    $tinNumber = $data['tin_number'] ?? '';
    $request = $data['request'] ?? '';
    $duration = $data['duration'] ?? '12';
    $referralCode = $data['referral_code'] ?? '';
    $promo = $data['promo'] ?? '';
    $breakfast = $data['breakfast'] ?? '';

    $extendHours = intval($data['extend_hours'] ?? 0);
    $extendMinutes = intval($data['extend_minutes'] ?? 0);
    $extendPrice = floatval($data['extend_price'] ?? 0);
    $extendRegularRate = floatval($data['extend_regular_rate'] ?? 0);
    $extendBundleRate = floatval($data['extend_bundle_rate'] ?? 0);
    $extendBundleBreakfast = $data['extend_bundle_breakfast'] ?? '';

    // Vehicle Details
    $vehicleType = $data['vehicle_type'] ?? '';
    $plateNumber = $data['plate_number'] ?? '';
    $vehicleDescription = $data['vehicle_description'] ?? '';

    // Transfer Details
    $transferRoomFrom = $data['transfer_room_from'] ?? '';
    $transferRefundAmount = floatval($data['transfer_refund_amount'] ?? 0);

    $additionalFood = null;
    $additionalItems = null;

    if (!empty($data['additional_data'])) {
        $addCharges = json_decode($data['additional_data'], true);
        if (is_array($addCharges)) {
            $foodArr = [];
            $itemArr = [];
            foreach ($addCharges as $charge) {
                if (($charge['type'] ?? '') === 'food') {
                    $foodArr[] = $charge;
                } elseif (($charge['type'] ?? '') === 'item') {
                    $itemArr[] = $charge;
                }
            }
            // Match confirm_booking.php / update_booking.php readable storage:
            // "1 Hotdog = ₱120.00" (not raw JSON — reports display this as-is)
            if (!empty($foodArr)) {
                $foodLines = [];
                foreach ($foodArr as $food) {
                    $name = $food['selectedItem'] ?? '';
                    $quantity = intval($food['quantity'] ?? 1);
                    $price = floatval($food['price'] ?? 0);
                    if (empty($name) || trim($name) === '' || trim($name) === 'Select Food') {
                        continue;
                    }
                    $foodLines[] = "{$quantity} {$name} = ₱" . number_format($price, 2);
                }
                if (!empty($foodLines)) {
                    $additionalFood = implode("\n", $foodLines);
                }
            }
            if (!empty($itemArr)) {
                $itemLines = [];
                foreach ($itemArr as $item) {
                    $name = $item['selectedItem'] ?? '';
                    $quantity = intval($item['quantity'] ?? 1);
                    $price = floatval($item['price'] ?? 0);
                    if (empty($name) || trim($name) === '' || trim($name) === 'Select Item') {
                        continue;
                    }
                    $itemLines[] = "{$quantity} {$name} = ₱" . number_format($price, 2);
                }
                if (!empty($itemLines)) {
                    $additionalItems = implode("\n", $itemLines);
                }
            }
        }
    }

    $paymentMethod = $data['payment_method'] ?? 'Cash';

    // Initialize reference numbers - only set them if the payment method is being used
    $referenceNo = ''; // Main reference number
    $referenceNoGcash = '';
    $referenceNoMaya = '';
    $referenceNoInstapay = '';
    $referenceNoOnlineBanking = '';
    $referenceNoAirbnb = '';

    // Only set reference numbers for active payment methods
    if (strpos($paymentMethod, 'G-cash') !== false) {
        $referenceNoGcash = $data['gcash_reference'] ?? '';
        $referenceNo = $referenceNoGcash; // Set main reference to the active payment method
    }
    if (strpos($paymentMethod, 'Maya') !== false) {
        $referenceNoMaya = $data['maya_reference'] ?? '';
        $referenceNo = $referenceNoMaya;
    }
    if (strpos($paymentMethod, 'Instapay') !== false) {
        $referenceNoInstapay = $data['instapay_reference'] ?? '';
        $referenceNo = $referenceNoInstapay;
    }
    if (strpos($paymentMethod, 'Online Banking') !== false) {
        $referenceNoOnlineBanking = $data['online_banking_reference'] ?? '';
        $referenceNo = $referenceNoOnlineBanking;
    }
    if (strpos($paymentMethod, 'Airbnb') !== false) {
        $referenceNoAirbnb = $data['airbnb_reference'] ?? '';
        $referenceNo = $referenceNoAirbnb;
    }

    // For Cash payment method, ensure ALL reference numbers are cleared
    if ($paymentMethod === 'Cash') {
        $referenceNo = '';
        $referenceNoGcash = '';
        $referenceNoMaya = '';
        $referenceNoInstapay = '';
        $referenceNoOnlineBanking = '';
        $referenceNoAirbnb = '';
    }

    $cashAmount = floatval($data['cash_amount'] ?? 0);
    $gcashAmount = floatval($data['gcash_amount'] ?? 0);
    $mayaAmount = floatval($data['maya_amount'] ?? 0);
    $instapayAmount = floatval($data['instapay_amount'] ?? 0);
    $onlineBankingAmount = floatval($data['online_banking_amount'] ?? 0);
    $airbnbAmount = floatval($data['airbnb_amount'] ?? 0);

    $reservationCash = floatval($data['reservation_cash'] ?? 0);
    $reservationGcash = floatval($data['reservation_gcash'] ?? 0);
    $reservationMaya = floatval($data['reservation_maya'] ?? 0);
    $reservationInstapay = floatval($data['reservation_instapay'] ?? 0);
    $reservationOnlineBanking = floatval($data['reservation_online_banking'] ?? 0);
    $reservationAirbnb = floatval($data['reservation_airbnb'] ?? 0);

    // Initialize reservation reference numbers - only set them if the payment method is being used
    $reservationGcashRef = '';
    $reservationMayaRef = '';
    $reservationInstapayRef = '';
    $reservationOnlineBankingRef = '';
    $reservationAirbnbRef = '';

    // Initialize deposit reference numbers - only set them if the payment method is being used
    $depositGcashRef = '';
    $depositInstapayRef = '';
    $depositOnlineBankingRef = '';
    $depositAirbnbRef = '';

    // Only set reservation reference numbers for active payment methods
    if (strpos($paymentMethod, 'G-cash') !== false) {
        $reservationGcashRef = $data['reservation_gcash_ref'] ?? '';
        $depositGcashRef = $data['deposit_gcash_ref'] ?? '';
    }
    if (strpos($paymentMethod, 'Maya') !== false) {
        $reservationMayaRef = $data['reservation_maya_ref'] ?? '';
    }
    if (strpos($paymentMethod, 'Instapay') !== false) {
        $reservationInstapayRef = $data['reservation_instapay_ref'] ?? '';
        $depositInstapayRef = $data['deposit_instapay_ref'] ?? '';
    }
    if (strpos($paymentMethod, 'Online Banking') !== false) {
        $reservationOnlineBankingRef = $data['reservation_online_banking_ref'] ?? '';
        $depositOnlineBankingRef = $data['deposit_online_banking_ref'] ?? '';
    }
    if (strpos($paymentMethod, 'Airbnb') !== false) {
        $reservationAirbnbRef = $data['reservation_airbnb_ref'] ?? '';
        $depositAirbnbRef = $data['deposit_airbnb_ref'] ?? '';
    }

    // For Cash payment method, ensure ALL reservation and deposit reference numbers are cleared
    if ($paymentMethod === 'Cash') {
        $reservationGcashRef = '';
        $reservationMayaRef = '';
        $reservationInstapayRef = '';
        $reservationOnlineBankingRef = '';
        $reservationAirbnbRef = '';
        $depositGcashRef = '';
        $depositInstapayRef = '';
        $depositOnlineBankingRef = '';
        $depositAirbnbRef = '';
    }

    // Debug log the new payment variables
    error_log("New payment variables - instapayAmount: $instapayAmount, onlineBankingAmount: $onlineBankingAmount, airbnbAmount: $airbnbAmount");
    error_log("New reservation variables - reservationInstapay: $reservationInstapay, reservationOnlineBanking: $reservationOnlineBanking, reservationAirbnb: $reservationAirbnb");

    // Calculate total downpayment amount (sum of all payment methods)
    $downpaymentAmount = $reservationCash + $reservationGcash + $reservationMaya + $reservationInstapay + $reservationOnlineBanking + $reservationAirbnb;

    // Get current timestamp for modification_updated_at
    $modificationUpdatedAt = date('Y-m-d H:i:s');

    // Log reservation amounts for debugging
    error_log("Reservation amounts - Cash: $reservationCash, Gcash: $reservationGcash, Maya: $reservationMaya, Total: $downpaymentAmount, Updated at: $modificationUpdatedAt");

    $discountCount = intval($data['discount_count'] ?? 0);
    $discountAmount = floatval($data['discount_amount'] ?? 0);
    $discountId = $data['discount_id'] ?? '';

    // LPG / VIP / Long Term discounts
    $lgpDiscountAmount = floatval($data['lgp_discount_amount'] ?? 0);
    $lgpApproverName = trim((string) ($data['lgp_approver_name'] ?? ''));
    $lgpDiscountEnabled = isset($data['lgp_discount_enabled'])
        ? (intval($data['lgp_discount_enabled']) ? 1 : 0)
        : ($lgpDiscountAmount > 0 ? 1 : 0);
    $vipDiscountAmount = floatval($data['vip_discount_amount'] ?? 0);
    $vipApproverName = trim((string) ($data['vip_approver_name'] ?? ''));
    $vipDiscountEnabled = isset($data['vip_discount_enabled'])
        ? (intval($data['vip_discount_enabled']) ? 1 : 0)
        : ($vipDiscountAmount > 0 ? 1 : 0);
    $longDiscountAmount = floatval($data['long_discount_amount'] ?? 0);
    $longDiscountPercent = floatval($data['long_discount_percent'] ?? 0);
    $longDiscountEnabled = isset($data['long_discount_enabled'])
        ? (intval($data['long_discount_enabled']) ? 1 : 0)
        : (($longDiscountAmount > 0 || $longDiscountPercent > 0) ? 1 : 0);
    if (!$lgpDiscountEnabled) {
        $lgpDiscountAmount = 0;
        $lgpApproverName = '';
    }
    if (!$vipDiscountEnabled) {
        $vipDiscountAmount = 0;
        $vipApproverName = '';
    }
    if (!$longDiscountEnabled) {
        $longDiscountAmount = 0;
        $longDiscountPercent = 0;
    }

    // Cancellation fields
    $cancellationReason = $data['cancellation_reason'] ?? '';
    $cancellationRefund = floatval($data['cancellation_refund'] ?? 0);

    // Modification reason (required)
    $modificationReason = $data['modification_reason'] ?? '';

    // ── Build payment status strings ──────────────────────────────────────────
    // Initialize ALL payment fields to empty/zero - this ensures old payment data is cleared
    $paymentStatusCash = '';
    $paymentStatusGcash = '';
    $paymentStatusMaya = '';
    $paymentStatusInstapay = '';
    $paymentStatusOnlineBanking = '';
    $paymentStatusAirbnb = '';
    $depositCash = 0.0;
    $depositGcash = 0.0;
    $depositMaya = 0.0;
    $depositInstapay = 0.0;
    $depositOnlineBanking = 0.0;
    $depositAirbnb = 0.0;

    // Debug log payment method
    error_log("Payment method: $paymentMethod");

    if ($paymentMethod === 'Cash') {
        $amount = $cashAmount > 0 ? $cashAmount : $currentAmount;
        $paymentStatusCash = $amount > 0 ? 'Cash (₱' . number_format($amount, 2) . ')' : 'Cash';
        $depositCash = $amount;
        // Explicitly clear all other payment methods
        $paymentStatusGcash = '';
        $paymentStatusMaya = '';
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
    } elseif ($paymentMethod === 'G-cash') {
        $amount = $gcashAmount > 0 ? $gcashAmount : $currentAmount;
        $paymentStatusGcash = $amount > 0 ? 'G-cash (₱' . number_format($amount, 2) . ')' : 'G-cash';
        $depositGcash = $amount;
        // Explicitly clear all other payment methods
        $paymentStatusCash = '';
        $paymentStatusMaya = '';
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
    } elseif ($paymentMethod === 'Maya') {
        $amount = $mayaAmount > 0 ? $mayaAmount : $currentAmount;
        $paymentStatusMaya = $amount > 0 ? 'Maya (₱' . number_format($amount, 2) . ')' : 'Maya';
        $depositMaya = $amount;
        // Explicitly clear all other payment methods
        $paymentStatusCash = '';
        $paymentStatusGcash = '';
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
    } elseif ($paymentMethod === 'Instapay') {
        $amount = $instapayAmount > 0 ? $instapayAmount : $currentAmount;
        $paymentStatusInstapay = $amount > 0 ? 'Instapay (₱' . number_format($amount, 2) . ')' : 'Instapay';
        $depositInstapay = $amount;
        // Explicitly clear all other payment methods
        $paymentStatusCash = '';
        $paymentStatusGcash = '';
        $paymentStatusMaya = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
        error_log("Instapay payment - amount: $amount, paymentStatusInstapay: $paymentStatusInstapay");
    } elseif ($paymentMethod === 'Online Banking') {
        $amount = $onlineBankingAmount > 0 ? $onlineBankingAmount : $currentAmount;
        $paymentStatusOnlineBanking = $amount > 0 ? 'Online Banking (₱' . number_format($amount, 2) . ')' : 'Online Banking';
        $depositOnlineBanking = $amount;
        // Explicitly clear all other payment methods
        $paymentStatusCash = '';
        $paymentStatusGcash = '';
        $paymentStatusMaya = '';
        $paymentStatusInstapay = '';
        $paymentStatusAirbnb = '';
    } elseif ($paymentMethod === 'Airbnb') {
        $amount = $airbnbAmount > 0 ? $airbnbAmount : $currentAmount;
        $paymentStatusAirbnb = $amount > 0 ? 'Airbnb (₱' . number_format($amount, 2) . ')' : 'Airbnb';
        $depositAirbnb = $amount;
        // Explicitly clear all other payment methods
        $paymentStatusCash = '';
        $paymentStatusGcash = '';
        $paymentStatusMaya = '';
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
    } elseif ($paymentMethod === 'Cash & G-cash') {
        // Clear all other payment methods first
        $paymentStatusMaya = '';
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
        // Set only the active ones
        if ($cashAmount > 0) {
            $paymentStatusCash = 'Cash (₱' . number_format($cashAmount, 2) . ')';
            $depositCash = $cashAmount;
        }
        if ($gcashAmount > 0) {
            $paymentStatusGcash = 'G-cash (₱' . number_format($gcashAmount, 2) . ')';
            $depositGcash = $gcashAmount;
        }
    } elseif ($paymentMethod === 'Cash & Maya') {
        // Clear all other payment methods first
        $paymentStatusGcash = '';
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
        // Set only the active ones
        if ($cashAmount > 0) {
            $paymentStatusCash = 'Cash (₱' . number_format($cashAmount, 2) . ')';
            $depositCash = $cashAmount;
        }
        if ($mayaAmount > 0) {
            $paymentStatusMaya = 'Maya (₱' . number_format($mayaAmount, 2) . ')';
            $depositMaya = $mayaAmount;
        }
    } elseif ($paymentMethod === 'G-cash & Maya') {
        // Clear all other payment methods first
        $paymentStatusCash = '';
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
        // Set only the active ones
        if ($gcashAmount > 0) {
            $paymentStatusGcash = 'G-cash (₱' . number_format($gcashAmount, 2) . ')';
            $depositGcash = $gcashAmount;
        }
        if ($mayaAmount > 0) {
            $paymentStatusMaya = 'Maya (₱' . number_format($mayaAmount, 2) . ')';
            $depositMaya = $mayaAmount;
        }
    } elseif ($paymentMethod === 'Cash, G-cash & Maya') {
        // Clear all other payment methods first
        $paymentStatusInstapay = '';
        $paymentStatusOnlineBanking = '';
        $paymentStatusAirbnb = '';
        // Set only the active ones
        if ($cashAmount > 0) {
            $paymentStatusCash = 'Cash (₱' . number_format($cashAmount, 2) . ')';
            $depositCash = $cashAmount;
        }
        if ($gcashAmount > 0) {
            $paymentStatusGcash = 'G-cash (₱' . number_format($gcashAmount, 2) . ')';
            $depositGcash = $gcashAmount;
        }
        if ($mayaAmount > 0) {
            $paymentStatusMaya = 'Maya (₱' . number_format($mayaAmount, 2) . ')';
            $depositMaya = $mayaAmount;
        }
    }

    $totalDeposit = (float) ($depositCash + $depositGcash + $depositMaya + $depositInstapay + $depositOnlineBanking + $depositAirbnb);
    $depositCash = (float) $depositCash;
    $depositGcash = (float) $depositGcash;
    $depositMaya = (float) $depositMaya;
    $depositInstapay = (float) $depositInstapay;
    $depositOnlineBanking = (float) $depositOnlineBanking;
    $depositAirbnb = (float) $depositAirbnb;

    // Debug log deposit amounts
    error_log("Deposit amounts - Cash: $depositCash, Gcash: $depositGcash, Maya: $depositMaya, Instapay: $depositInstapay, OnlineBanking: $depositOnlineBanking, Airbnb: $depositAirbnb, Total: $totalDeposit");

    $depositDetailsParts = [];
    if ($depositCash > 0)
        $depositDetailsParts[] = number_format($depositCash, 2, '.', ',') . ' Cash';
    if ($depositGcash > 0)
        $depositDetailsParts[] = number_format($depositGcash, 2, '.', ',') . ' G-cash';
    if ($depositMaya > 0)
        $depositDetailsParts[] = number_format($depositMaya, 2, '.', ',') . ' Maya';
    if ($depositInstapay > 0)
        $depositDetailsParts[] = number_format($depositInstapay, 2, '.', ',') . ' Instapay';
    if ($depositOnlineBanking > 0)
        $depositDetailsParts[] = number_format($depositOnlineBanking, 2, '.', ',') . ' Online Banking';
    if ($depositAirbnb > 0)
        $depositDetailsParts[] = number_format($depositAirbnb, 2, '.', ',') . ' Airbnb';
    $depositDetails = !empty($depositDetailsParts) ? implode(', ', $depositDetailsParts) : null;

    // ── Build unified payment_status string ──
    $unifiedPaymentStatusParts = [];
    if (!empty($paymentStatusCash))
        $unifiedPaymentStatusParts[] = $paymentStatusCash;
    if (!empty($paymentStatusGcash))
        $unifiedPaymentStatusParts[] = $paymentStatusGcash;
    if (!empty($paymentStatusMaya))
        $unifiedPaymentStatusParts[] = $paymentStatusMaya;
    if (!empty($paymentStatusInstapay))
        $unifiedPaymentStatusParts[] = $paymentStatusInstapay;
    if (!empty($paymentStatusOnlineBanking))
        $unifiedPaymentStatusParts[] = $paymentStatusOnlineBanking;
    if (!empty($paymentStatusAirbnb))
        $unifiedPaymentStatusParts[] = $paymentStatusAirbnb;
    // Fall back to Cash if empty, though logic usually guarantees one is set
    $unifiedPaymentStatus = !empty($unifiedPaymentStatusParts) ? implode(', ', $unifiedPaymentStatusParts) : 'Cash';

    // ── Preserve Existing Payment History (keep pipe-separated history intact) ──
    // We do NOT overwrite history on a modification save — the history is built
    // incrementally by update_payment_status.php / update_booking.php when a new
    // payment is actually collected.  Modification only updates guest/room details.
    // So we simply pass the existing history strings back unchanged.
    $histCash = $current['payment_amount_cash_history'] ?? '';
    $histGcash = $current['payment_amount_g_cash_history'] ?? '';
    $histMaya = $current['payment_amount_maya_history'] ?? '';
    $histInstapay = $current['payment_amount_instapay_history'] ?? '';
    $histOnlineBanking = $current['payment_amount_online_banking_history'] ?? '';
    $histAirbnb = $current['payment_amount_airbnb_history'] ?? '';

    // ── Enhanced Debug Logging ──
    error_log("=== MODIFICATION UPDATE DEBUG ===");
    error_log("Payment Method: " . $paymentMethod);
    error_log("Current Amount: " . $currentAmount);
    error_log("Cash Amount: " . $cashAmount);
    error_log("Gcash Amount: " . $gcashAmount);
    error_log("Maya Amount: " . $mayaAmount);
    error_log("Instapay Amount: " . $instapayAmount);
    error_log("Online Banking Amount: " . $onlineBankingAmount);
    error_log("Airbnb Amount: " . $airbnbAmount);
    error_log("Total Deposit: " . $totalDeposit);
    error_log("Unified Payment Status: " . $unifiedPaymentStatus);
    error_log("Deposit Details: " . ($depositDetails ?? 'NULL'));
    error_log("Reference No: " . $referenceNo);
    error_log("=== END DEBUG ===");

    // ── Run the UPDATE ────────────────────────────────────────────────────────
    if ($sourceTable === 'bookings') {
        // IMPORTANT:
        // Always update the bookings table by its primary key `id`.
        // `booking_id` is not guaranteed to be present/unique (often blank/duplicated),
        // which can accidentally update multiple rows.
        if ($numericId === null) {
            returnError('Invalid booking identifier for bookings table (missing numeric id)');
        }

        $whereClause = "WHERE id = ?";
        $whereValue = (int) $numericId;

        error_log("Updating bookings table - WHERE id = $whereValue, reservation_cash = $reservationCash");

        // Guardrail: make sure the id is unique (prevents accidental multi-row updates)
        $chkStmt = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE id = ?");
        if (!$chkStmt) {
            returnError('Prepare (bookings id check) failed', ['error' => $conn->error]);
        }
        $chkStmt->bind_param("i", $whereValue);
        $chkStmt->execute();
        $chkRes = $chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();
        $cnt = intval($chkRes['c'] ?? 0);
        if ($cnt !== 1) {
            returnError("Unsafe update prevented: bookings.id={$whereValue} matched {$cnt} rows");
        }

        $updateQuery = "UPDATE bookings SET
            room_type = ?, room_id = ?, booking_type = ?, guest_type = ?, guest_name = ?,
            second_guest_name = ?, additional_guest_names = ?,
            reason_for_stay = ?, contact_person_name = ?, contact_no = ?, address = ?, tin_number = ?,
            request = ?, check_in = ?, check_out = ?, duration = ?, referral_name = ?,
            promo = ?, breakfast = ?, additional_guest = ?, additional_pet = ?,
            additional_food = ?, additional_items = ?,
            payment_status = ?, payment_status_cash = ?, payment_status_g_cash = ?, payment_status_maya = ?,
            payment_status_instapay = ?, payment_status_online_banking = ?, payment_status_airbnb = ?,
            reference_no = ?, reference_no_g_cash = ?, reference_no_maya = ?,
            reference_no_instapay = ?, reference_no_online_banking = ?, reference_no_airbnb = ?,
            payment_amount_cash_history = ?, payment_amount_g_cash_history = ?, payment_amount_maya_history = ?,
            payment_amount_instapay_history = ?, payment_amount_online_banking_history = ?, payment_amount_airbnb_history = ?,
            deposit = ?, deposit_cash = ?, deposit_g_cash = ?, deposit_maya = ?,
            deposit_instapay = ?, deposit_online_banking = ?, deposit_airbnb = ?, deposit_details = ?,
            deposit_gcash_ref = ?, deposit_instapay_ref = ?, deposit_online_banking_ref = ?, deposit_airbnb_ref = ?,
            downpayment_amount = ?, downpayment_cash = ?, downpayment_gcash = ?, downpayment_maya = ?,
            downpayment_instapay = ?, downpayment_online_banking = ?, downpayment_airbnb = ?,
            downpayment_gcash_ref = ?, downpayment_maya_ref = ?,
            downpayment_instapay_ref = ?, downpayment_online_banking_ref = ?, downpayment_airbnb_ref = ?,
            sc_pwd_count = ?, discount_amount = ?, id_number = ?,
            cancellation_reason = ?, refund_amount = ?,
            modification_reason = ?, modification_updated_at = ?,
            vehicle_type = ?, plate_number = ?, vehicle_description = ?,
            transfer_room_from = ?, transfer_refund_amount = ?,
            extend_hours = ?, extend_minutes = ?, extend_price = ?,
            extend_regular_rate = ?, extend_bundle_rate = ?, extend_bundle_breakfast = ?
            {$whereClause} LIMIT 1";

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            returnError('Prepare (bookings update) failed', ['error' => $conn->error]);
        }

        // 17s = room_type..breakfast | 2s = second_guest_name + additional_guest_names | ii = guest/pet | ss = add_food/add_items
        // 8s = pay_status×7 + reference_no | 5s = refs×5 | 6s = history×6 | 7d+s = deposit×7+details | 4s = deposit_refs×4 | 1d+6d = downpayment_amount + reservation deposits×6
        // 5s = reservation_refs×5 | i = discount_count | d = discount_amount | s = id_number | s = cancellation_reason | d = cancellation_refund | s = modification_reason | s = modification_updated_at | 3s = vehicle fields | s = transfer_room_from | d = transfer_refund_amount | iiddds = extend fields | i = whereValue
        $bindTypes = "sssssssssssssssssssii" . "ss" . "ssssssss" . "sssss" . "ssssss" . "dddd" . "ddd" . "s" . "ssss" . "d" . "ddd" . "ddd" . "ss" . "sss" . "i" . "d" . "s" . "s" . "d" . "s" . "s" . "sss" . "s" . "d" . "iiddds" . "i";
        $stmt->bind_param(
            $bindTypes,
            $roomType,
            $roomId,
            $bookingType,
            $guestType,
            $guestNames,
            $secondGuestName,
            $additionalGuestNames,
            $reasonForStay,
            $contactPersonName,
            $contactNo,
            $address,
            $tinNumber,
            $request,
            $checkIn,
            $checkOut,
            $duration,
            $referralCode,
            $promo,
            $breakfast,
            $additionalGuest,
            $additionalPet,
            $additionalFood,
            $additionalItems,
            $unifiedPaymentStatus,
            $paymentStatusCash,
            $paymentStatusGcash,
            $paymentStatusMaya,
            $paymentStatusInstapay,
            $paymentStatusOnlineBanking,
            $paymentStatusAirbnb,
            $referenceNo,
            $referenceNoGcash,
            $referenceNoMaya,
            $referenceNoInstapay,
            $referenceNoOnlineBanking,
            $referenceNoAirbnb,
            $histCash,
            $histGcash,
            $histMaya,
            $histInstapay,
            $histOnlineBanking,
            $histAirbnb,
            $totalDeposit,
            $depositCash,
            $depositGcash,
            $depositMaya,
            $depositInstapay,
            $depositOnlineBanking,
            $depositAirbnb,
            $depositDetails,
            $depositGcashRef,
            $depositInstapayRef,
            $depositOnlineBankingRef,
            $depositAirbnbRef,
            $downpaymentAmount,
            $reservationCash,
            $reservationGcash,
            $reservationMaya,
            $reservationInstapay,
            $reservationOnlineBanking,
            $reservationAirbnb,
            $reservationGcashRef,
            $reservationMayaRef,
            $reservationInstapayRef,
            $reservationOnlineBankingRef,
            $reservationAirbnbRef,
            $discountCount,
            $discountAmount,
            $discountId,
            $cancellationReason,
            $cancellationRefund,
            $modificationReason,
            $modificationUpdatedAt,
            $vehicleType,
            $plateNumber,
            $vehicleDescription,
            $transferRoomFrom,
            $transferRefundAmount,
            $extendHours,
            $extendMinutes,
            $extendPrice,
            $extendRegularRate,
            $extendBundleRate,
            $extendBundleBreakfast,
            $whereValue
        );


    } else {
        // reports table (no request column; uses reference_no instead of referral_name)
        // Always use id for reports table
        $whereValue = (int) $numericId;

        error_log("Updating REPORTS table - WHERE id = $whereValue, Payment Method: $paymentMethod, clearing all reference numbers for Cash");

        $updateQuery = "UPDATE reports SET
            room_type = ?, room_id = ?, booking_type = ?, guest_type = ?, guest_name = ?,
            second_guest_name = ?, additional_guest_names = ?,
            reason_for_stay = ?, contact_person_name = ?, contact_no = ?, address = ?, tin_number = ?,
            request = ?, check_in = ?, check_out = ?, checked_out_at = ?, duration = ?, referral_name = ?,
            promo = ?, breakfast = ?, additional_guest = ?, additional_pet = ?,
            additional_food = ?, additional_items = ?,
            payment_status = ?, payment_status_cash = ?, payment_status_g_cash = ?, payment_status_maya = ?,
            payment_status_instapay = ?, payment_status_online_banking = ?, payment_status_airbnb = ?,
            reference_no = ?, reference_no_g_cash = ?, reference_no_maya = ?,
            reference_no_instapay = ?, reference_no_online_banking = ?, reference_no_airbnb = ?,
            payment_amount_cash_history = ?, payment_amount_g_cash_history = ?, payment_amount_maya_history = ?,
            payment_amount_instapay_history = ?, payment_amount_online_banking_history = ?, payment_amount_airbnb_history = ?,
            deposit = ?, deposit_cash = ?, deposit_g_cash = ?, deposit_maya = ?,
            deposit_instapay = ?, deposit_online_banking = ?, deposit_airbnb = ?, deposit_details = ?,
            deposit_gcash_ref = ?, deposit_instapay_ref = ?, deposit_online_banking_ref = ?, deposit_airbnb_ref = ?,
            downpayment_amount = ?, downpayment_cash = ?, downpayment_gcash = ?, downpayment_maya = ?,
            downpayment_instapay = ?, downpayment_online_banking = ?, downpayment_airbnb = ?,
            downpayment_gcash_ref = ?, downpayment_maya_ref = ?,
            downpayment_instapay_ref = ?, downpayment_online_banking_ref = ?, downpayment_airbnb_ref = ?,
            sc_pwd_count = ?, discount_amount = ?, id_number = ?,
            cancellation_reason = ?, refund_amount = ?,
            modification_reason = ?, modification_updated_at = ?,
            vehicle_type = ?, plate_number = ?, vehicle_description = ?,
            transfer_room_from = ?, transfer_refund_amount = ?,
            extend_hours = ?, extend_minutes = ?, extend_price = ?,
            extend_regular_rate = ?, extend_bundle_rate = ?, extend_bundle_breakfast = ?
            WHERE id = ? LIMIT 1";

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            returnError('Prepare (reports update) failed', ['error' => $conn->error]);
        }
        // 18s = room_type..breakfast (includes 2x checkOut) | ii = guest/pet | ss = add_food/add_items
        // 8s = pay_status×7 + reference_no | 5s = refs×5 | 6s = history×6 | 7d+s = deposit×7+details | 4s = deposit_refs×4 | 1d+6d = downpayment_amount + reservation deposits×6
        // 5s = reservation_refs×5 | i = discount_count | d = discount_amount | s = id_number | s = cancellation_reason | d = cancellation_refund | s = modification_reason | s = modification_updated_at | 3s = vehicle fields | s = transfer_room_from | d = transfer_refund_amount | iiddds = extend fields | i = whereValue
        $stmt->bind_param(
            "ssssssssssssssssssssii" . "ss" . "ssssssss" . "sssss" . "ssssss" . "dddd" . "ddd" . "s" . "ssss" . "d" . "ddd" . "ddd" . "ss" . "sss" . "i" . "d" . "s" . "s" . "d" . "s" . "s" . "sss" . "s" . "d" . "iiddds" . "i",
            $roomType,
            $roomId,
            $bookingType,
            $guestType,
            $guestNames,
            $secondGuestName,
            $additionalGuestNames,
            $reasonForStay,
            $contactPersonName,
            $contactNo,
            $address,
            $tinNumber,
            $request,
            $checkIn,
            $checkOut,
            $checkedOutAt,
            $duration,
            $referralCode,
            $promo,
            $breakfast,
            $additionalGuest,
            $additionalPet,
            $additionalFood,
            $additionalItems,
            $unifiedPaymentStatus,
            $paymentStatusCash,
            $paymentStatusGcash,
            $paymentStatusMaya,
            $paymentStatusInstapay,
            $paymentStatusOnlineBanking,
            $paymentStatusAirbnb,
            $referenceNo,
            $referenceNoGcash,
            $referenceNoMaya,
            $referenceNoInstapay,
            $referenceNoOnlineBanking,
            $referenceNoAirbnb,
            $histCash,
            $histGcash,
            $histMaya,
            $histInstapay,
            $histOnlineBanking,
            $histAirbnb,
            $totalDeposit,
            $depositCash,
            $depositGcash,
            $depositMaya,
            $depositInstapay,
            $depositOnlineBanking,
            $depositAirbnb,
            $depositDetails,
            $depositGcashRef,
            $depositInstapayRef,
            $depositOnlineBankingRef,
            $depositAirbnbRef,
            $downpaymentAmount,
            $reservationCash,
            $reservationGcash,
            $reservationMaya,
            $reservationInstapay,
            $reservationOnlineBanking,
            $reservationAirbnb,
            $reservationGcashRef,
            $reservationMayaRef,
            $reservationInstapayRef,
            $reservationOnlineBankingRef,
            $reservationAirbnbRef,
            $discountCount,
            $discountAmount,
            $discountId,
            $cancellationReason,
            $cancellationRefund,
            $modificationReason,
            $modificationUpdatedAt,
            $vehicleType,
            $plateNumber,
            $vehicleDescription,
            $transferRoomFrom,
            $transferRefundAmount,
            $extendHours,
            $extendMinutes,
            $extendPrice,
            $extendRegularRate,
            $extendBundleRate,
            $extendBundleBreakfast,
            $whereValue
        );
    }

    if (!$stmt->execute()) {
        returnError('Update failed', ['error' => $stmt->error]);
    }

    $affectedRows = $stmt->affected_rows;
    error_log("Update executed. Affected rows: " . $affectedRows . ", Source: " . $sourceTable . ", WHERE value: " . $whereValue . ", Guest: " . $guestNames);

    // Verify only one row was updated
    if ($affectedRows > 1) {
        error_log("WARNING: Multiple rows updated! Affected rows: " . $affectedRows);
    }

    $stmt->close();

    // Persist Additional Slippers + New Deposit Payment Method (separate from room deposit)
    $slipperDepositSql = "UPDATE {$sourceTable} SET
        additional_slippers = ?,
        slipper_status = ?,
        new_deposit = ?,
        new_deposit_cash = ?,
        new_deposit_g_cash = ?,
        new_deposit_maya = ?,
        new_deposit_instapay = ?,
        new_deposit_online_banking = ?,
        new_deposit_airbnb = ?,
        new_deposit_details = ?,
        new_deposit_gcash_ref = ?,
        new_deposit_maya_ref = ?,
        new_deposit_instapay_ref = ?,
        new_deposit_online_banking_ref = ?,
        new_deposit_airbnb_ref = ?
        WHERE id = ? LIMIT 1";
    $slipperDepositStmt = $conn->prepare($slipperDepositSql);
    if ($slipperDepositStmt) {
        $slipperDepositStmt->bind_param(
            "isdddddddssssssi",
            $additionalSlippers,
            $slipperStatus,
            $newDeposit,
            $newDepositCash,
            $newDepositGcash,
            $newDepositMaya,
            $newDepositInstapay,
            $newDepositOnlineBanking,
            $newDepositAirbnb,
            $newDepositDetails,
            $newDepositGcashRef,
            $newDepositMayaRef,
            $newDepositInstapayRef,
            $newDepositOnlineBankingRef,
            $newDepositAirbnbRef,
            $whereValue
        );
        if (!$slipperDepositStmt->execute()) {
            error_log("Failed to update slippers/new_deposit on {$sourceTable}: " . $slipperDepositStmt->error);
        }
        $slipperDepositStmt->close();
    } else {
        error_log("Prepare slippers/new_deposit update failed: " . $conn->error);
    }

    // Persist Missing Items + Penalty Fine Rules (same fields used by Booking checkoutModal)
    $missingPenaltySql = "UPDATE {$sourceTable} SET
        missing_items_fees = ?,
        missing_items_list = ?,
        penalty_amount = ?,
        penalty_list = ?,
        additional_fees_status = ?
        WHERE id = ? LIMIT 1";
    $missingPenaltyStmt = $conn->prepare($missingPenaltySql);
    if ($missingPenaltyStmt) {
        $missingPenaltyStmt->bind_param(
            "dsdssi",
            $missingItemsFees,
            $missingItemsList,
            $penaltyAmount,
            $penaltyList,
            $additionalFeesStatus,
            $whereValue
        );
        if (!$missingPenaltyStmt->execute()) {
            error_log("Failed to update missing/penalty on {$sourceTable}: " . $missingPenaltyStmt->error);
        }
        $missingPenaltyStmt->close();
    } else {
        error_log("Prepare missing/penalty update failed: " . $conn->error);
    }

    // Verify the update was successful by reading back the payment values
    $verifyStmt = $conn->prepare("SELECT 
        payment_status, payment_status_cash, payment_status_g_cash, payment_status_maya,
        payment_status_instapay, payment_status_online_banking, payment_status_airbnb,
        reference_no, deposit, deposit_cash, deposit_g_cash, deposit_maya,
        deposit_instapay, deposit_online_banking, deposit_airbnb, deposit_details,
        downpayment_amount, downpayment_cash, downpayment_gcash, downpayment_maya
        FROM {$sourceTable} WHERE id = ?");
    if ($verifyStmt) {
        $verifyStmt->bind_param("i", $whereValue);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        if ($verifyRow = $verifyResult->fetch_assoc()) {
            error_log("=== VERIFICATION AFTER UPDATE ===");
            error_log("payment_status: " . ($verifyRow['payment_status'] ?? 'NULL'));
            error_log("payment_status_cash: " . ($verifyRow['payment_status_cash'] ?? 'NULL'));
            error_log("payment_status_g_cash: " . ($verifyRow['payment_status_g_cash'] ?? 'NULL'));
            error_log("payment_status_maya: " . ($verifyRow['payment_status_maya'] ?? 'NULL'));
            error_log("payment_status_instapay: " . ($verifyRow['payment_status_instapay'] ?? 'NULL'));
            error_log("payment_status_online_banking: " . ($verifyRow['payment_status_online_banking'] ?? 'NULL'));
            error_log("payment_status_airbnb: " . ($verifyRow['payment_status_airbnb'] ?? 'NULL'));
            error_log("reference_no: " . ($verifyRow['reference_no'] ?? 'NULL'));
            error_log("deposit: " . ($verifyRow['deposit'] ?? 'NULL'));
            error_log("deposit_cash: " . ($verifyRow['deposit_cash'] ?? 'NULL'));
            error_log("deposit_g_cash: " . ($verifyRow['deposit_g_cash'] ?? 'NULL'));
            error_log("deposit_maya: " . ($verifyRow['deposit_maya'] ?? 'NULL'));
            error_log("deposit_instapay: " . ($verifyRow['deposit_instapay'] ?? 'NULL'));
            error_log("deposit_online_banking: " . ($verifyRow['deposit_online_banking'] ?? 'NULL'));
            error_log("deposit_airbnb: " . ($verifyRow['deposit_airbnb'] ?? 'NULL'));
            error_log("deposit_details: " . ($verifyRow['deposit_details'] ?? 'NULL'));
            error_log("downpayment_amount: " . ($verifyRow['downpayment_amount'] ?? 'NULL'));
            error_log("downpayment_cash: " . ($verifyRow['downpayment_cash'] ?? 'NULL'));
            error_log("downpayment_gcash: " . ($verifyRow['downpayment_gcash'] ?? 'NULL'));
            error_log("downpayment_maya: " . ($verifyRow['downpayment_maya'] ?? 'NULL'));
            error_log("=== END VERIFICATION ===");
        }
        $verifyStmt->close();
    }

    // If updating bookings table, also sync to reports table if a corresponding record exists
    if ($sourceTable === 'bookings') {
        // Get the booking_id (public code) from bookings table
        $getBookingCodeStmt = $conn->prepare("SELECT booking_id FROM bookings WHERE id = ?");
        if ($getBookingCodeStmt) {
            $getBookingCodeStmt->bind_param("i", $whereValue);
            $getBookingCodeStmt->execute();
            $bookingCodeResult = $getBookingCodeStmt->get_result();
            if ($bookingCodeResult && $bookingCodeResult->num_rows > 0) {
                $bookingCodeRow = $bookingCodeResult->fetch_assoc();
                $bookingCode = $bookingCodeRow['booking_id'];
                $getBookingCodeStmt->close();

                // Check if this booking exists in reports table
                if (!empty($bookingCode)) {
                    $checkReportsStmt = $conn->prepare("SELECT id FROM reports WHERE booking_id = ? LIMIT 1");
                    if ($checkReportsStmt) {
                        $checkReportsStmt->bind_param("s", $bookingCode);
                        $checkReportsStmt->execute();
                        $reportsResult = $checkReportsStmt->get_result();

                        if ($reportsResult && $reportsResult->num_rows > 0) {
                            // Reports record exists, update it with the same data
                            $checkReportsStmt->close();

                            $updateReportsQuery = "UPDATE reports SET
                                room_type = ?, room_id = ?, booking_type = ?, guest_type = ?, guest_name = ?,
                                second_guest_name = ?, additional_guest_names = ?,
                                reason_for_stay = ?, contact_person_name = ?, contact_no = ?, address = ?, tin_number = ?,
                                request = ?, check_in = ?, check_out = ?, duration = ?, referral_name = ?,
                                promo = ?, breakfast = ?, additional_guest = ?, additional_pet = ?,
                                additional_food = ?, additional_items = ?,
                                payment_status = ?, payment_status_cash = ?, payment_status_g_cash = ?, payment_status_maya = ?,
                                payment_status_instapay = ?, payment_status_online_banking = ?, payment_status_airbnb = ?,
                                reference_no = ?, reference_no_g_cash = ?, reference_no_maya = ?,
                                reference_no_instapay = ?, reference_no_online_banking = ?, reference_no_airbnb = ?,
                                payment_amount_cash_history = ?, payment_amount_g_cash_history = ?, payment_amount_maya_history = ?,
                                payment_amount_instapay_history = ?, payment_amount_online_banking_history = ?, payment_amount_airbnb_history = ?,
                                deposit = ?, deposit_cash = ?, deposit_g_cash = ?, deposit_maya = ?,
                                deposit_instapay = ?, deposit_online_banking = ?, deposit_airbnb = ?, deposit_details = ?,
                                deposit_gcash_ref = ?, deposit_instapay_ref = ?, deposit_online_banking_ref = ?, deposit_airbnb_ref = ?,
                                downpayment_amount = ?, downpayment_cash = ?, downpayment_gcash = ?, downpayment_maya = ?,
                                downpayment_instapay = ?, downpayment_online_banking = ?, downpayment_airbnb = ?,
                                downpayment_gcash_ref = ?, downpayment_maya_ref = ?,
                                downpayment_instapay_ref = ?, downpayment_online_banking_ref = ?, downpayment_airbnb_ref = ?,
                                sc_pwd_count = ?, discount_amount = ?, id_number = ?,
                                cancellation_reason = ?, refund_amount = ?,
                                modification_reason = ?, modification_updated_at = ?,
                                transfer_room_from = ?, transfer_refund_amount = ?
                                WHERE booking_id = ? LIMIT 1";

                            $updateReportsStmt = $conn->prepare($updateReportsQuery);
                            if ($updateReportsStmt) {
                                $updateReportsStmt->bind_param(
                                    "sssssssssssssssssssii" . "ss" . "ssssssss" . "sssss" . "ssssss" . "dddd" . "ddd" . "s" . "ssss" . "d" . "ddd" . "ddd" . "ss" . "sss" . "i" . "d" . "s" . "s" . "d" . "s" . "s" . "s" . "d" . "s",
                                    $roomType,
                                    $roomId,
                                    $bookingType,
                                    $guestType,
                                    $guestNames,
                                    $secondGuestName,
                                    $additionalGuestNames,
                                    $reasonForStay,
                                    $contactPersonName,
                                    $contactNo,
                                    $address,
                                    $tinNumber,
                                    $request,
                                    $checkIn,
                                    $checkOut,
                                    $duration,
                                    $referralCode,
                                    $promo,
                                    $breakfast,
                                    $additionalGuest,
                                    $additionalPet,
                                    $additionalFood,
                                    $additionalItems,
                                    $unifiedPaymentStatus,
                                    $paymentStatusCash,
                                    $paymentStatusGcash,
                                    $paymentStatusMaya,
                                    $paymentStatusInstapay,
                                    $paymentStatusOnlineBanking,
                                    $paymentStatusAirbnb,
                                    $referenceNo,
                                    $referenceNoGcash,
                                    $referenceNoMaya,
                                    $referenceNoInstapay,
                                    $referenceNoOnlineBanking,
                                    $referenceNoAirbnb,
                                    $histCash,
                                    $histGcash,
                                    $histMaya,
                                    $histInstapay,
                                    $histOnlineBanking,
                                    $histAirbnb,
                                    $totalDeposit,
                                    $depositCash,
                                    $depositGcash,
                                    $depositMaya,
                                    $depositInstapay,
                                    $depositOnlineBanking,
                                    $depositAirbnb,
                                    $depositDetails,
                                    $depositGcashRef,
                                    $depositInstapayRef,
                                    $depositOnlineBankingRef,
                                    $depositAirbnbRef,
                                    $downpaymentAmount,
                                    $reservationCash,
                                    $reservationGcash,
                                    $reservationMaya,
                                    $reservationInstapay,
                                    $reservationOnlineBanking,
                                    $reservationAirbnb,
                                    $reservationGcashRef,
                                    $reservationMayaRef,
                                    $reservationInstapayRef,
                                    $reservationOnlineBankingRef,
                                    $reservationAirbnbRef,
                                    $discountCount,
                                    $discountAmount,
                                    $discountId,
                                    $cancellationReason,
                                    $cancellationRefund,
                                    $modificationReason,
                                    $modificationUpdatedAt,
                                    $transferRoomFrom,
                                    $transferRefundAmount,
                                    $bookingCode
                                );
                                $updateReportsStmt->execute();
                                $updateReportsStmt->close();

                                $syncSlipperReports = $conn->prepare("UPDATE reports SET
                                    additional_slippers = ?,
                                    slipper_status = ?,
                                    new_deposit = ?,
                                    new_deposit_cash = ?,
                                    new_deposit_g_cash = ?,
                                    new_deposit_maya = ?,
                                    new_deposit_instapay = ?,
                                    new_deposit_online_banking = ?,
                                    new_deposit_airbnb = ?,
                                    new_deposit_details = ?,
                                    new_deposit_gcash_ref = ?,
                                    new_deposit_maya_ref = ?,
                                    new_deposit_instapay_ref = ?,
                                    new_deposit_online_banking_ref = ?,
                                    new_deposit_airbnb_ref = ?
                                    WHERE booking_id = ? LIMIT 1");
                                if ($syncSlipperReports) {
                                    $syncSlipperReports->bind_param(
                                        "isdddddddsssssss",
                                        $additionalSlippers,
                                        $slipperStatus,
                                        $newDeposit,
                                        $newDepositCash,
                                        $newDepositGcash,
                                        $newDepositMaya,
                                        $newDepositInstapay,
                                        $newDepositOnlineBanking,
                                        $newDepositAirbnb,
                                        $newDepositDetails,
                                        $newDepositGcashRef,
                                        $newDepositMayaRef,
                                        $newDepositInstapayRef,
                                        $newDepositOnlineBankingRef,
                                        $newDepositAirbnbRef,
                                        $bookingCode
                                    );
                                    $syncSlipperReports->execute();
                                    $syncSlipperReports->close();
                                }
                            }
                        } else {
                            $checkReportsStmt->close();
                        }
                    }
                }
            } else {
                $getBookingCodeStmt->close();
            }
        }
    }

    // ── Save per-payment discount history (from payment cards) ──
    if ($editedDiscountHist !== null) {
        try {
            $discHistSave = $editedDiscountHist !== '' ? $editedDiscountHist : null;

            $discBookingStmt = $conn->prepare("UPDATE bookings SET discount_amount_history = ? WHERE id = ?");
            if ($discBookingStmt) {
                $discBookingStmt->bind_param("si", $discHistSave, $whereValue);
                $discBookingStmt->execute();
                $discBookingStmt->close();
            }

            $discReportStmt = $conn->prepare("UPDATE reports SET discount_amount_history = ? WHERE id = ?");
            if ($discReportStmt) {
                $discReportStmt->bind_param("si", $discHistSave, $whereValue);
                $discReportStmt->execute();
                $discReportStmt->close();
            }

            error_log("[discount_hist] Updated id={$whereValue}: history=" . ($discHistSave ?? 'NULL'));
        } catch (Exception $e) {
            error_log("[discount_hist] Failed to save discount history: " . $e->getMessage());
        }
    }

    // ── Save LPG / VIP / Long Term discount fields ──
    try {
        $extraDiscSql = "SET
            lgp_discount_enabled = ?, lgp_discount_amount = ?, lgp_approver_name = ?,
            vip_discount_enabled = ?, vip_discount_amount = ?, vip_approver_name = ?,
            long_discount_enabled = ?, long_discount_amount = ?, long_discount_percent = ?
            WHERE id = ?";
        $extraDiscTypes = "idsidsiddi";

        $extraBookingStmt = $conn->prepare("UPDATE bookings {$extraDiscSql}");
        if ($extraBookingStmt) {
            $extraBookingStmt->bind_param(
                $extraDiscTypes,
                $lgpDiscountEnabled,
                $lgpDiscountAmount,
                $lgpApproverName,
                $vipDiscountEnabled,
                $vipDiscountAmount,
                $vipApproverName,
                $longDiscountEnabled,
                $longDiscountAmount,
                $longDiscountPercent,
                $whereValue
            );
            $extraBookingStmt->execute();
            $extraBookingStmt->close();
        }

        $extraReportStmt = $conn->prepare("UPDATE reports {$extraDiscSql}");
        if ($extraReportStmt) {
            $extraReportStmt->bind_param(
                $extraDiscTypes,
                $lgpDiscountEnabled,
                $lgpDiscountAmount,
                $lgpApproverName,
                $vipDiscountEnabled,
                $vipDiscountAmount,
                $vipApproverName,
                $longDiscountEnabled,
                $longDiscountAmount,
                $longDiscountPercent,
                $whereValue
            );
            $extraReportStmt->execute();
            $extraReportStmt->close();
        }
    } catch (Exception $e) {
        error_log("[extra_discounts] Failed to save LPG/VIP/Long Term discounts: " . $e->getMessage());
    }

    // ── Save edited Payment Method History (if the UI cards were rendered and submitted) ──
    if ($hasEditedHistory) {
        try {
            $histCashSave = $editedCashHist ?? '';
            $histGcashSave = $editedGcashHist ?? '';
            $histMayaSave = $editedMayaHist ?? '';
            $histInstapaySave = $editedInstapayHist ?? '';
            $histOnlineBankingSave = $editedOnlineBankingHist ?? '';
            $histAirbnbSave = $editedAirbnbHist ?? '';
            $payDtSave = $editedPaymentDateTime ?? '';

            // Recalculate cumulative deposit amounts from history
            $sumParts = function (string $hist): float {
                if ($hist === '')
                    return 0.0;
                return array_sum(array_map('floatval', explode('|', $hist)));
            };
            $newDepositCash = $sumParts($histCashSave);
            $newDepositGcash = $sumParts($histGcashSave);
            $newDepositMaya = $sumParts($histMayaSave);
            $newDepositInstapay = $sumParts($histInstapaySave);
            $newDepositOnlineBanking = $sumParts($histOnlineBankingSave);
            $newDepositAirbnb = $sumParts($histAirbnbSave);
            $newTotalDeposit = $newDepositCash + $newDepositGcash + $newDepositMaya
                + $newDepositInstapay + $newDepositOnlineBanking + $newDepositAirbnb;

            // Update bookings table
            $histBookingStmt = $conn->prepare("
                UPDATE bookings SET
                    payment_amount_cash_history = ?,
                    payment_amount_g_cash_history = ?,
                    payment_amount_maya_history = ?,
                    payment_amount_instapay_history = ?,
                    payment_amount_online_banking_history = ?,
                    payment_amount_airbnb_history = ?,
                    payment_date_time = ?,
                    deposit = ?,
                    deposit_cash = ?,
                    deposit_g_cash = ?,
                    deposit_maya = ?,
                    deposit_instapay = ?,
                    deposit_online_banking = ?,
                    deposit_airbnb = ?
                WHERE id = ?
            ");
            if ($histBookingStmt) {
                $histBookingStmt->bind_param(
                    "sssssssdddddddi",
                    $histCashSave,
                    $histGcashSave,
                    $histMayaSave,
                    $histInstapaySave,
                    $histOnlineBankingSave,
                    $histAirbnbSave,
                    $payDtSave,
                    $newTotalDeposit,
                    $newDepositCash,
                    $newDepositGcash,
                    $newDepositMaya,
                    $newDepositInstapay,
                    $newDepositOnlineBanking,
                    $newDepositAirbnb,
                    $whereValue
                );
                $histBookingStmt->execute();
                $histBookingStmt->close();
                error_log("[payment_hist] Updated bookings id={$whereValue}: cash={$histCashSave}, datetime={$payDtSave}");
            }

            // Mirror to reports table if it exists
            $histReportStmt = $conn->prepare("
                UPDATE reports SET
                    payment_amount_cash_history = ?,
                    payment_amount_g_cash_history = ?,
                    payment_amount_maya_history = ?,
                    payment_amount_instapay_history = ?,
                    payment_amount_online_banking_history = ?,
                    payment_amount_airbnb_history = ?,
                    payment_date_time = ?,
                    deposit = ?,
                    deposit_cash = ?,
                    deposit_g_cash = ?,
                    deposit_maya = ?,
                    deposit_instapay = ?,
                    deposit_online_banking = ?,
                    deposit_airbnb = ?
                WHERE id = ?
            ");
            if ($histReportStmt) {
                $histReportStmt->bind_param(
                    "sssssssdddddddi",
                    $histCashSave,
                    $histGcashSave,
                    $histMayaSave,
                    $histInstapaySave,
                    $histOnlineBankingSave,
                    $histAirbnbSave,
                    $payDtSave,
                    $newTotalDeposit,
                    $newDepositCash,
                    $newDepositGcash,
                    $newDepositMaya,
                    $newDepositInstapay,
                    $newDepositOnlineBanking,
                    $newDepositAirbnb,
                    $whereValue
                );
                $histReportStmt->execute();
                $histReportStmt->close();
                error_log("[payment_hist] Updated reports id={$whereValue}: cash={$histCashSave}");
            }
        } catch (Exception $e) {
            error_log("[payment_hist] Failed to save payment history: " . $e->getMessage());
        }
    }

    // Also update cancellation_requests table if this booking has a cancellation request
    if (!empty($cancellationReason) || !empty($cancellationRefund)) {
        $updateCancelStmt = $conn->prepare("
            UPDATE cancellation_requests 
            SET reason = ?, refund_amount = ? 
            WHERE booking_id = ?
        ");
        if ($updateCancelStmt) {
            $updateCancelStmt->bind_param("sdi", $cancellationReason, $cancellationRefund, $whereValue);
            $updateCancelStmt->execute();
            $updateCancelStmt->close();
        }
    }

    $conn->close();

    returnJson([
        'success' => true,
        'message' => 'Booking updated successfully',
        'source' => $sourceTable,
        'affected_rows' => $affectedRows
    ]);

} catch (Exception $e) {
    ob_end_clean();
    error_log("Update modification error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    ob_end_clean();
    error_log("Update modification fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>