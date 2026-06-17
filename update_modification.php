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
function returnJson($data) {
    // Clean any output that might have been generated
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit;
}

// Function to log and return error
function returnError($message, $details = []) {
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
    $editedCashHist          = isset($data['payment_amount_cash_history'])           ? trim((string)$data['payment_amount_cash_history'])           : null;
    $editedGcashHist         = isset($data['payment_amount_g_cash_history'])         ? trim((string)$data['payment_amount_g_cash_history'])         : null;
    $editedMayaHist          = isset($data['payment_amount_maya_history'])           ? trim((string)$data['payment_amount_maya_history'])           : null;
    $editedInstapayHist      = isset($data['payment_amount_instapay_history'])       ? trim((string)$data['payment_amount_instapay_history'])       : null;
    $editedOnlineBankingHist = isset($data['payment_amount_online_banking_history']) ? trim((string)$data['payment_amount_online_banking_history']) : null;
    $editedAirbnbHist        = isset($data['payment_amount_airbnb_history'])         ? trim((string)$data['payment_amount_airbnb_history'])         : null;
    $editedPaymentDateTime   = isset($data['payment_date_time'])                     ? trim((string)$data['payment_date_time'])                     : null;
    $editedDiscountHist      = array_key_exists('discount_amount_history', $data)  ? trim((string)$data['discount_amount_history'])               : null;
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
        'discount_amount_history' => "TEXT NULL DEFAULT NULL"
    ];

    $tables = ['bookings', 'reports'];
    foreach ($tables as $table) {
        foreach ($requiredColumns as $columnName => $columnDefinition) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM $table LIKE '$columnName'");
                if ($checkColumn->num_rows == 0) {
                    $conn->exec("ALTER TABLE $table ADD COLUMN $columnName $columnDefinition");
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
    $additionalGuest = intval($data['additional_guest'] ?? 0);
    $additionalPet = intval($data['additional_pet'] ?? 0);

    // Ensure these are strings, not NULL
    $bookingType = $data['booking_type'] ?? 'Walk-in';
    $guestType = $data['guest_type'] ?? 'Solo';
    $guestNames = $data['guest_names'] ?? '';
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
            if (!empty($foodArr))
                $additionalFood = json_encode($foodArr);
            if (!empty($itemArr))
                $additionalItems = json_encode($itemArr);
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

        // 17s = room_type..breakfast | ii = guest/pet | ss = add_food/add_items
        // 8s = pay_status×7 + reference_no | 5s = refs×5 | 6s = history×6 | 7d+s = deposit×7+details | 4s = deposit_refs×4 | 1d+6d = downpayment_amount + reservation deposits×6
        // 5s = reservation_refs×5 | i = discount_count | d = discount_amount | s = id_number | s = cancellation_reason | d = cancellation_refund | s = modification_reason | s = modification_updated_at | 3s = vehicle fields | s = transfer_room_from | d = transfer_refund_amount | iiddds = extend fields | i = whereValue
        $bindTypes = "sssssssssssssssssii" . "ss" . "ssssssss" . "sssss" . "ssssss" . "dddd" . "ddd" . "s" . "ssss" . "d" . "ddd" . "ddd" . "ss" . "sss" . "i" . "d" . "s" . "s" . "d" . "s" . "s" . "sss" . "s" . "d" . "iiddds" . "i";
        $stmt->bind_param(
            $bindTypes,
            $roomType,
            $roomId,
            $bookingType,
            $guestType,
            $guestNames,
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
            "ssssssssssssssssssii" . "ss" . "ssssssss" . "sssss" . "ssssss" . "dddd" . "ddd" . "s" . "ssss" . "d" . "ddd" . "ddd" . "ss" . "sss" . "i" . "d" . "s" . "s" . "d" . "s" . "s" . "sss" . "s" . "d" . "iiddds" . "i",
            $roomType,
            $roomId,
            $bookingType,
            $guestType,
            $guestNames,
            $reasonForStay,
            $contactPersonName,
            $contactNo,
            $address,
            $tinNumber,
            $request,
            $checkIn,
            $checkOut,
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
                                    "sssssssssssssssssii" . "ss" . "ssssssss" . "sssss" . "ssssss" . "dddd" . "ddd" . "s" . "ssss" . "d" . "ddd" . "ddd" . "ss" . "sss" . "i" . "d" . "s" . "s" . "d" . "s" . "s" . "s" . "d" . "s",
                                    $roomType,
                                    $roomId,
                                    $bookingType,
                                    $guestType,
                                    $guestNames,
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

    // ── Save edited Payment Method History (if the UI cards were rendered and submitted) ──
    if ($hasEditedHistory) {
        try {
            $histCashSave          = $editedCashHist ?? '';
            $histGcashSave         = $editedGcashHist ?? '';
            $histMayaSave          = $editedMayaHist ?? '';
            $histInstapaySave      = $editedInstapayHist ?? '';
            $histOnlineBankingSave = $editedOnlineBankingHist ?? '';
            $histAirbnbSave        = $editedAirbnbHist ?? '';
            $payDtSave             = $editedPaymentDateTime ?? '';

            // Recalculate cumulative deposit amounts from history
            $sumParts = function(string $hist): float {
                if ($hist === '') return 0.0;
                return array_sum(array_map('floatval', explode('|', $hist)));
            };
            $newDepositCash          = $sumParts($histCashSave);
            $newDepositGcash         = $sumParts($histGcashSave);
            $newDepositMaya          = $sumParts($histMayaSave);
            $newDepositInstapay      = $sumParts($histInstapaySave);
            $newDepositOnlineBanking = $sumParts($histOnlineBankingSave);
            $newDepositAirbnb        = $sumParts($histAirbnbSave);
            $newTotalDeposit         = $newDepositCash + $newDepositGcash + $newDepositMaya
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
                    $histCashSave, $histGcashSave, $histMayaSave,
                    $histInstapaySave, $histOnlineBankingSave, $histAirbnbSave,
                    $payDtSave,
                    $newTotalDeposit, $newDepositCash, $newDepositGcash, $newDepositMaya,
                    $newDepositInstapay, $newDepositOnlineBanking, $newDepositAirbnb,
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
                    $histCashSave, $histGcashSave, $histMayaSave,
                    $histInstapaySave, $histOnlineBankingSave, $histAirbnbSave,
                    $payDtSave,
                    $newTotalDeposit, $newDepositCash, $newDepositGcash, $newDepositMaya,
                    $newDepositInstapay, $newDepositOnlineBanking, $newDepositAirbnb,
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