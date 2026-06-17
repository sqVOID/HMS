<?php
// CRITICAL: Prevent ANY output before JSON response
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@error_reporting(0);

// Start session to get logged-in user info (encoder)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering IMMEDIATELY
if (ob_get_level() == 0) {
    ob_start();
}

// Include required files
require_once 'config.php';
require_once 'report_helpers.php';

// Determine encoder = full name of the currently logged-in user
$_encoder_first = trim($_SESSION['first_name'] ?? '');
$_encoder_last = trim($_SESSION['last_name'] ?? '');
if ($_encoder_first !== '' || $_encoder_last !== '') {
    $encoder = trim($_encoder_first . ' ' . $_encoder_last);
} else {
    $encoder = trim($_SESSION['username'] ?? 'Unknown');
}

// Clear ALL output buffers and start fresh
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Set JSON header
@header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = $_POST['room_id'] ?? null;
    $room_type = $_POST['room_type'] ?? '';
    $guest_name = trim($_POST['guest_name'] ?? '');
    $reason_for_stay = trim($_POST['reason_for_stay'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $request = trim($_POST['request'] ?? '');
    // Additional Guest
    $additional_guest = isset($_POST['additional_capacity']) ? intval($_POST['additional_capacity']) : 0;
    // Additional Pet
    $additional_pet = isset($_POST['additional_pet']) ? intval($_POST['additional_pet']) : 0;

    // New Guest Type Fields
    $guest_type = trim($_POST['guest_type'] ?? 'Solo');
    $second_guest_name = trim($_POST['second_guest_name'] ?? '');
    $contact_person_name = trim($_POST['contact_person_name'] ?? '');
    $number_of_adults = intval($_POST['number_of_adults'] ?? 0);
    $number_of_children = intval($_POST['number_of_children'] ?? 0);
    $tin_number = trim($_POST['number_of_guests_info'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Sales Channel
    $sales_channel = trim($_POST['sales_channel'] ?? '');

    // Debug log for sales_channel
    error_log("=== CONFIRM BOOKING - SALES CHANNEL DEBUG ===");
    error_log("sales_channel from POST: " . $sales_channel);
    error_log("=== END SALES CHANNEL DEBUG ===");

    // Debug logging
    error_log("=== CONFIRM_BOOKING DEBUG ===");
    error_log("POST additional_capacity isset: " . (isset($_POST['additional_capacity']) ? 'YES' : 'NO'));
    error_log("POST additional_capacity raw value: " . ($_POST['additional_capacity'] ?? 'NOT SET'));
    error_log("Processed additional_guest value: " . $additional_guest);
    error_log("POST additional_pet isset: " . (isset($_POST['additional_pet']) ? 'YES' : 'NO'));
    error_log("POST additional_pet raw value: " . ($_POST['additional_pet'] ?? 'NOT SET'));
    error_log("Processed additional_pet value: " . $additional_pet);

    $check_in = $_POST['check_in'] ?? null;
    $duration = intval($_POST['duration'] ?? 0);
    $duration_unit = $_POST['duration_unit'] ?? 'hours';
    $status = 'Confirming';
    $room_image = $_POST['room_image'] ?? '';

    // Get booking_type from POST (Walk-in or Reservation)
    $booking_type = trim($_POST['booking_type'] ?? 'Walk-in');
    // Validate booking_type
    if ($booking_type !== 'Walk-in' && $booking_type !== 'Reservation') {
        $booking_type = 'Walk-in'; // Default to Walk-in if invalid
    }

    // New optional fields coming from Booking.html confirmBooking()
    $referral_name = trim($_POST['referral_name'] ?? '');
    $promo = trim($_POST['promo'] ?? '');
    $breakfast = trim($_POST['breakfast'] ?? '');
    $breakfast_qty = intval($_POST['breakfast_qty'] ?? 1);
    $payment_status = trim($_POST['payment_status'] ?? '');
    $payment_status_cash = trim($_POST['payment_status_cash'] ?? '');
    $payment_status_g_cash = trim($_POST['payment_status_g_cash'] ?? '');
    $payment_status_maya = trim($_POST['payment_status_maya'] ?? '');
    $payment_status_instapay = trim($_POST['payment_status_instapay'] ?? '');
    $payment_status_online_banking = trim($_POST['payment_status_online_banking'] ?? '');
    $payment_status_airbnb = trim($_POST['payment_status_airbnb'] ?? '');
    $reference_no_g_cash = trim($_POST['reference_no_g_cash'] ?? '');
    $reference_no_maya = trim($_POST['reference_no_maya'] ?? '');
    $reference_no_instapay = trim($_POST['reference_no_instapay'] ?? '');
    $reference_no_online_banking = trim($_POST['reference_no_online_banking'] ?? '');
    $reference_no_airbnb = trim($_POST['reference_no_airbnb'] ?? '');
    $additional = trim($_POST['additional'] ?? '');
    $additional_fees_items = $_POST['additional_fees_items'] ?? '[]';
    $additional_charges = $_POST['additional_charges'] ?? '[]'; // From editAdditionalCharges in edit modal
    $paid_status = trim($_POST['paid_status'] ?? 'Unpaid');
    $paid_status_from_post = $paid_status;

    error_log("=== PAID_STATUS FROM FRONTEND ===");
    error_log("paid_status from POST: " . $paid_status);
    error_log("This will be recalculated by backend based on payment amount");
    error_log("=== END ===");
    $supplier = trim($_POST['supplier'] ?? '');
    $deposit = floatval($_POST['deposit'] ?? 0);
    $deposit_cash = floatval($_POST['deposit_cash'] ?? 0);
    $deposit_g_cash = floatval($_POST['deposit_g_cash'] ?? 0);
    $deposit_maya = floatval($_POST['deposit_maya'] ?? 0);
    $deposit_instapay = floatval($_POST['deposit_instapay'] ?? 0);
    $deposit_online_banking = floatval($_POST['deposit_online_banking'] ?? 0);
    $deposit_airbnb = floatval($_POST['deposit_airbnb'] ?? 0);
    $deposit_details = isset($_POST['deposit_details']) && $_POST['deposit_details'] !== '' ? $_POST['deposit_details'] : null;
    $deposit_gcash_ref = isset($_POST['deposit_gcash_ref']) && $_POST['deposit_gcash_ref'] !== '' ? $_POST['deposit_gcash_ref'] : null;
    $deposit_maya_ref = isset($_POST['deposit_maya_ref']) && $_POST['deposit_maya_ref'] !== '' ? $_POST['deposit_maya_ref'] : null;
    $deposit_instapay_ref = isset($_POST['deposit_instapay_ref']) && $_POST['deposit_instapay_ref'] !== '' ? $_POST['deposit_instapay_ref'] : null;
    $deposit_online_banking_ref = isset($_POST['deposit_online_banking_ref']) && $_POST['deposit_online_banking_ref'] !== '' ? $_POST['deposit_online_banking_ref'] : null;
    $deposit_airbnb_ref = isset($_POST['deposit_airbnb_ref']) && $_POST['deposit_airbnb_ref'] !== '' ? $_POST['deposit_airbnb_ref'] : null;
    $check_in_change_amount = floatval($_POST['check_in_change_amount'] ?? 0);

    $parsePaymentAmount = function ($value) {
        if (!is_string($value) || trim($value) === '') {
            return 0.0;
        }
        if (preg_match('/([0-9][0-9,]*(?:\.[0-9]+)?)/', $value, $m)) {
            return floatval(str_replace(',', '', $m[1]));
        }
        return 0.0;
    };

    // Keep Instapay / Online Banking / Airbnb as independent methods for reporting.

    // CRITICAL FIX: Get discount data from POST
    $discount_enabled = isset($_POST['discount_enabled']) && $_POST['discount_enabled'] === '1';
    $discount_type = trim($_POST['discount_type'] ?? 'regular');
    $sc_pwd_count = intval($_POST['sc_pwd_count'] ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $id_number = trim($_POST['id_number'] ?? '');

    error_log("=== DISCOUNT DATA FROM POST ===");
    error_log("discount_enabled: " . ($discount_enabled ? 'YES' : 'NO'));
    error_log("discount_type: " . $discount_type);
    error_log("sc_pwd_count: " . $sc_pwd_count);
    error_log("discount_amount (from frontend): " . $discount_amount);
    error_log("id_number: " . $id_number);
    error_log("=== END DISCOUNT DATA ===");

    // Initialize discount amount history matching update_booking.php logic
    $discount_amount_history = "";
    if ($discount_amount > 0) {
        $discount_amount_history = number_format($discount_amount, 2, '.', '') . ":" . date('Y-m-d H:i:s');
    }

    // REMOVED OLD INCORRECT DISCOUNT CALCULATION
    // Discount amount is now accepted from frontend (manual input by user)

    // CRITICAL FIX: If deposit is 0 but breakdown fields have values, calculate deposit from breakdown
    if ($deposit == 0 && ($deposit_cash > 0 || $deposit_g_cash > 0 || $deposit_maya > 0 || $deposit_instapay > 0 || $deposit_online_banking > 0 || $deposit_airbnb > 0)) {
        $deposit = $deposit_cash + $deposit_g_cash + $deposit_maya + $deposit_instapay + $deposit_online_banking + $deposit_airbnb;
        error_log("CRITICAL FIX: deposit was 0, calculated from breakdown fields: " . $deposit);

        // Also build deposit_details if it's missing
        if (!$deposit_details) {
            $details_parts = [];
            if ($deposit_cash > 0)
                $details_parts[] = number_format($deposit_cash, 2) . ' Cash';
            if ($deposit_g_cash > 0)
                $details_parts[] = number_format($deposit_g_cash, 2) . ' G-cash';
            if ($deposit_maya > 0)
                $details_parts[] = number_format($deposit_maya, 2) . ' Maya';
            if ($deposit_instapay > 0)
                $details_parts[] = number_format($deposit_instapay, 2) . ' Instapay';
            if ($deposit_online_banking > 0)
                $details_parts[] = number_format($deposit_online_banking, 2) . ' Online Banking';
            if ($deposit_airbnb > 0)
                $details_parts[] = number_format($deposit_airbnb, 2) . ' Airbnb';
            $deposit_details = implode(', ', $details_parts);
            error_log("CRITICAL FIX: deposit_details was empty, built from breakdown: " . $deposit_details);
        }
    }

    // CRITICAL FIX: Always recalculate deposit from breakdown fields to ensure accuracy
    // This prevents issues where the deposit field contains the raw payment amount instead of the discounted amount
    if ($deposit_cash > 0 || $deposit_g_cash > 0 || $deposit_maya > 0 || $deposit_instapay > 0 || $deposit_online_banking > 0 || $deposit_airbnb > 0) {
        $calculated_deposit = $deposit_cash + $deposit_g_cash + $deposit_maya + $deposit_instapay + $deposit_online_banking + $deposit_airbnb;
        if (abs($deposit - $calculated_deposit) > 0.01) { // Allow for small floating point differences
            error_log("CRITICAL FIX: deposit mismatch detected!");
            error_log("Original deposit from POST: " . $deposit);
            error_log("Calculated from breakdown: " . $calculated_deposit);
            error_log("Using calculated value from breakdown fields");
            $deposit = $calculated_deposit;
        }
    }

    // Store original deposit values BEFORE any change deduction/normalization for later use
    // (used for per-payment history exports)
    $original_deposit_cash = $deposit_cash;
    $original_deposit_g_cash = $deposit_g_cash;
    $original_deposit_maya = $deposit_maya;
    $original_deposit_instapay = $deposit_instapay;
    $original_deposit_online_banking = $deposit_online_banking;
    $original_deposit_airbnb = $deposit_airbnb;

    // CRITICAL DEBUG: Log all deposit-related POST values
    error_log("=== CONFIRM_BOOKING DEPOSIT DEBUG ===");
    error_log("POST deposit: " . ($_POST['deposit'] ?? 'NOT SET'));
    error_log("POST deposit_cash: " . ($_POST['deposit_cash'] ?? 'NOT SET'));
    error_log("POST deposit_g_cash: " . ($_POST['deposit_g_cash'] ?? 'NOT SET'));
    error_log("POST deposit_maya: " . ($_POST['deposit_maya'] ?? 'NOT SET'));
    error_log("POST deposit_details: " . ($_POST['deposit_details'] ?? 'NOT SET'));
    error_log("POST deposit_gcash_ref: " . ($_POST['deposit_gcash_ref'] ?? 'NOT SET'));
    error_log("POST deposit_maya_ref: " . ($_POST['deposit_maya_ref'] ?? 'NOT SET'));
    error_log("Parsed deposit: " . $deposit);
    error_log("Parsed deposit_cash: " . $deposit_cash);
    error_log("Parsed deposit_g_cash: " . $deposit_g_cash);
    error_log("Parsed deposit_maya: " . $deposit_maya);
    error_log("Parsed deposit_details: " . ($deposit_details ?? 'NULL'));
    error_log("=== END DEPOSIT DEBUG ===");

    // Convert empty strings to null
    if ($deposit_details === '' || $deposit_details === 'null')
        $deposit_details = null;
    if ($deposit_gcash_ref === '' || $deposit_gcash_ref === 'null')
        $deposit_gcash_ref = null;
    if ($deposit_maya_ref === '' || $deposit_maya_ref === 'null')
        $deposit_maya_ref = null;

    // Debug logging
    error_log("=== INITIAL DEPOSIT VALUES FROM POST ===");
    error_log("deposit from POST: " . ($_POST['deposit'] ?? 'NOT SET'));
    error_log("deposit_cash from POST: " . ($_POST['deposit_cash'] ?? 'NOT SET'));
    error_log("deposit_g_cash from POST: " . ($_POST['deposit_g_cash'] ?? 'NOT SET'));
    error_log("deposit_maya from POST: " . ($_POST['deposit_maya'] ?? 'NOT SET'));
    error_log("deposit_details from POST: " . ($_POST['deposit_details'] ?? 'NOT SET'));
    error_log("Parsed deposit: " . $deposit);
    error_log("Parsed deposit_details: " . ($deposit_details ?? 'NULL'));
    error_log("=== END INITIAL VALUES ===");

    // Debug logging
    error_log("=== DEPOSIT ADJUSTMENT DEBUG ===");
    error_log("Original deposit: " . $deposit);
    error_log("Original deposit_details: " . ($deposit_details ?? 'NULL'));
    error_log("check_in_change_amount: " . $check_in_change_amount);

    // Deduct change amount from deposit (deposit should reflect actual amount kept, not including change)
    if ($check_in_change_amount > 0 && $deposit > 0) {
        $originalDeposit = $deposit;
        $deposit = max(0, $deposit - $check_in_change_amount);

        error_log("Adjusted deposit: " . $deposit);

        // CRITICAL FIX: Also adjust the breakdown fields proportionally
        // This ensures that when the modal reopens, it shows the correct net amounts
        if ($originalDeposit > 0) {
            $ratio = $deposit / $originalDeposit;
            $deposit_cash = $deposit_cash * $ratio;
            $deposit_g_cash = $deposit_g_cash * $ratio;
            $deposit_maya = $deposit_maya * $ratio;
            $deposit_instapay = $deposit_instapay * $ratio;
            $deposit_online_banking = $deposit_online_banking * $ratio;
            $deposit_airbnb = $deposit_airbnb * $ratio;

            error_log("Adjusted deposit_cash: " . $deposit_cash);
            error_log("Adjusted deposit_g_cash: " . $deposit_g_cash);
            error_log("Adjusted deposit_maya: " . $deposit_maya);
            error_log("Adjusted deposit_instapay: " . $deposit_instapay);
            error_log("Adjusted deposit_online_banking: " . $deposit_online_banking);
            error_log("Adjusted deposit_airbnb: " . $deposit_airbnb);
        }

        // Also update deposit_details to reflect the adjusted amount
        if ($deposit_details) {
            // CRITICAL FIX: Parse the deposit_details and rebuild it with the new amount
            // Handle formats like "1729.05 Cash" or "1,729.05 Cash" or "1729.05"

            // Extract the payment method (everything after the number)
            if (preg_match('/^[\d,]+\.?\d*\s+(.+)$/', $deposit_details, $matches)) {
                // Format: "1729.05 Cash" -> extract "Cash"
                $paymentMethod = $matches[1];
                $deposit_details = number_format($deposit, 2, '.', ',') . ' ' . $paymentMethod;
            } else {
                // Just a number, no payment method
                $deposit_details = number_format($deposit, 2, '.', ',');
            }

            error_log("Adjusted deposit_details: " . $deposit_details);
        }
    }

    // Debug logging
    error_log("=== CONFIRM BOOKING DEBUG ===");
    error_log("check_in_change_amount from POST: " . ($_POST['check_in_change_amount'] ?? 'NOT SET'));
    error_log("check_in_change_amount parsed: " . $check_in_change_amount);

    if ($deposit_gcash_ref === 'null' || $deposit_gcash_ref === '')
        $deposit_gcash_ref = null;
    if ($deposit_maya_ref === 'null' || $deposit_maya_ref === '')
        $deposit_maya_ref = null;
    if ($deposit_instapay_ref === 'null' || $deposit_instapay_ref === '')
        $deposit_instapay_ref = null;
    if ($deposit_online_banking_ref === 'null' || $deposit_online_banking_ref === '')
        $deposit_online_banking_ref = null;
    if ($deposit_airbnb_ref === 'null' || $deposit_airbnb_ref === '')
        $deposit_airbnb_ref = null;
    $use_hygiene_kit = isset($_POST['use_hygiene_kit']) && $_POST['use_hygiene_kit'] === '1';
    $hygiene_kit_inventory_id = isset($_POST['hygiene_kit_inventory_id']) && $_POST['hygiene_kit_inventory_id'] !== ''
        ? intval($_POST['hygiene_kit_inventory_id'])
        : null;
    $hygiene_kit_price = 0;
    $status_override = $_POST['status_override'] ?? null;

    // Normalize empty/select values to NULL or appropriate defaults
    if ($payment_status === 'Select' || $payment_status === 'Select Method' || $payment_status === '') {
        $payment_status = null;
    }
    if ($payment_status_cash === 'Select' || $payment_status_cash === 'Select Method') {
        $payment_status_cash = '';
    }
    if ($payment_status_g_cash === 'Select' || $payment_status_g_cash === 'Select Method') {
        $payment_status_g_cash = '';
    }
    if ($payment_status_maya === 'Select' || $payment_status_maya === 'Select Method') {
        $payment_status_maya = '';
    }
    if ($payment_status_instapay === 'Select' || $payment_status_instapay === 'Select Method') {
        $payment_status_instapay = '';
    }
    if ($payment_status_online_banking === 'Select' || $payment_status_online_banking === 'Select Method') {
        $payment_status_online_banking = '';
    }
    if ($payment_status_airbnb === 'Select' || $payment_status_airbnb === 'Select Method') {
        $payment_status_airbnb = '';
    }
    if ($promo === 'Select Promo' || $promo === 'None' || $promo === '') {
        $promo = null;
    }

    // Format breakfast with quantity and calculated total price
    // Input: "HOTDOG - ₱120.00" with quantity 2
    // Output: "2 HOTDOG - ₱240.00"
    // Format breakfast with quantity and calculated total price
    // Input: "HOTDOG - ₱120.00" with quantity 2
    // Output: "2 HOTDOG - ₱240.00"
    if ($breakfast && $breakfast !== 'Select Breakfast' && $breakfast !== 'None' && $breakfast !== '') {
        // Check if breakfast already has quantity prefix (from edit modal with multiple breakfasts joined by |)
        if (strpos($breakfast, '|') !== false) {
            // Multiple breakfast items separated by |
            $breakfastItems = explode('|', $breakfast);
            $formattedItems = [];

            foreach ($breakfastItems as $item) {
                $item = trim($item);
                if (empty($item))
                    continue;

                // Check if item already has quantity prefix (e.g., "2 HOTDOG - ₱240.00")
                if (preg_match('/^(\d+)\s+(.+?)\s+-\s+₱([\d,]+\.?\d*)$/', $item, $matches)) {
                    $quantity = intval($matches[1]);
                    $itemName = trim($matches[2]);
                    $totalPrice = floatval(str_replace(',', '', $matches[3]));
                    $itemName = ucwords(strtolower($itemName));
                    $formattedItems[] = $quantity . ' ' . $itemName . ' - ₱' . number_format($totalPrice, 2, '.', ',');
                }
                // Check for Promo item with quantity: "1 TAPA (Promo)"
                elseif (preg_match('/^(\d+)\s+(.+?\(Promo\))$/i', $item, $matches)) {
                    $quantity = intval($matches[1]);
                    $itemName = trim($matches[2]);
                    $itemName = ucwords(strtolower(str_replace('(Promo)', '', $itemName))) . ' (Promo)';
                    $formattedItems[] = $quantity . ' ' . $itemName;
                }
                // Parse item without quantity: "HOTDOG - ₱120.00"
                elseif (preg_match('/^(.+?)\s+-\s+₱([\d,]+\.?\d*)$/', $item, $matches)) {
                    $itemName = trim($matches[1]);
                    $unitPrice = floatval(str_replace(',', '', $matches[2]));
                    $itemName = ucwords(strtolower($itemName));
                    $formattedItems[] = '1 ' . $itemName . ' - ₱' . number_format($unitPrice, 2, '.', ',');
                }
                // Item with (Promo) suffix: "TAPA (Promo)"
                elseif (stripos($item, '(Promo)') !== false) {
                    $itemName = trim(str_replace('(Promo)', '', $item));
                    $itemName = ucwords(strtolower($itemName)) . ' (Promo)';
                    $formattedItems[] = '1 ' . $itemName;
                }
                // Fallback for items without quantity prefix
                elseif (!preg_match('/^\d+\s+/', $item)) {
                    $formattedItems[] = '1 ' . ucwords(strtolower($item));
                } else {
                    $formattedItems[] = ucwords(strtolower($item));
                }
            }

            $breakfast = implode(' | ', $formattedItems);
        } else {
            // Single breakfast item
            if (preg_match('/^(\d+)\s+(.+?)\s+-\s+₱([\d,]+\.?\d*)$/', $breakfast, $matches)) {
                $quantity = intval($matches[1]);
                $itemName = trim($matches[2]);
                $totalPrice = floatval(str_replace(',', '', $matches[3]));
                $itemName = ucwords(strtolower($itemName));
                $breakfast = $quantity . ' ' . $itemName . ' - ₱' . number_format($totalPrice, 2, '.', ',');
            }
            // Single promo item with quantity: "2 TAPA (Promo)"
            elseif (preg_match('/^(\d+)\s+(.+?\(Promo\))$/i', $breakfast, $matches)) {
                $quantity = intval($matches[1]);
                $itemName = trim($matches[2]);
                $itemName = ucwords(strtolower(str_replace('(Promo)', '', $itemName))) . ' (Promo)';
                $breakfast = $quantity . ' ' . $itemName;
            }
            // Single item with price but no quantity
            elseif (preg_match('/^(.+?)\s+-\s+₱([\d,]+\.?\d*)$/', $breakfast, $matches)) {
                $itemName = trim($matches[1]);
                $unitPrice = floatval(str_replace(',', '', $matches[2]));
                $itemName = ucwords(strtolower($itemName));
                $totalPrice = $unitPrice * $breakfast_qty;
                $breakfast = $breakfast_qty . ' ' . $itemName . ' - ₱' . number_format($totalPrice, 2, '.', ',');
            }
            // Promo item without quantity: "TAPA (Promo)"
            elseif (stripos($breakfast, '(Promo)') !== false) {
                $itemName = trim(str_replace('(Promo)', '', $breakfast));
                $itemName = ucwords(strtolower($itemName)) . ' (Promo)';
                $breakfast = $breakfast_qty . ' ' . $itemName;
            }
            // Fallback for single item without quantity
            elseif (!preg_match('/^\d+\s+/', $breakfast)) {
                $breakfast = $breakfast_qty . ' ' . ucwords(strtolower($breakfast));
            }
        }
    } else {
        $breakfast = null;
    }

    if ($referral_name === '') {
        $referral_name = null;
    }
    if ($additional === '') {
        $additional = null;
    }
    if ($paid_status === '') {
        $paid_status = 'Unpaid';
    }
    if ($supplier === '' && $referral_name !== null) {
        $supplier = $referral_name;
    }

    if ($reason_for_stay === '') {
        $reason_for_stay = null;
    }
    if ($address === '') {
        $address = null;
    }
    if ($contact_no === '') {
        $contact_no = null;
    }

    // Note: Initial status is set here, but may be changed based on status_override
    $initialStatus = 'Confirming';
    if ($status_override === 'Confirmed') {
        $initialStatus = 'Confirmed';
    } elseif ($status_override === 'Reserved') {
        $initialStatus = 'Reserved';
    }
    $status = $initialStatus;

    if (!$room_id) {
        $response['message'] = 'Room ID is required!';
        ob_clean();
        echo json_encode($response);
        ob_end_flush();
        exit;
    }

    ensureReportFinancialColumns($conn);

    // Generate booking ID: B-MM/DD/YY-XXX
    $date = date('m/d/y');

    // Get the next booking number for today
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'bookings'");
        $hasBookingsTable = $checkTable->rowCount() > 0;

        if (!$hasBookingsTable) {
            $response['message'] = 'Bookings table does not exist! Please create it first.';
            ob_clean();
            echo json_encode($response);
            ob_end_flush();
            exit;
        }

        // Ensure all required columns exist in the bookings table
        $requiredColumns = [
            'referral_name' => "VARCHAR(255) NULL DEFAULT NULL",
            'promo' => "VARCHAR(255) NULL DEFAULT NULL",
            'breakfast' => "VARCHAR(255) NULL DEFAULT NULL",
            'payment_status' => "VARCHAR(50) NULL DEFAULT NULL",
            'payment_status_cash' => "TEXT NULL DEFAULT NULL",
            'payment_status_g_cash' => "TEXT NULL DEFAULT NULL",
            'payment_status_maya' => "TEXT NULL DEFAULT NULL",
            'payment_status_instapay' => "TEXT NULL DEFAULT NULL",
            'payment_status_online_banking' => "TEXT NULL DEFAULT NULL",
            'payment_status_airbnb' => "TEXT NULL DEFAULT NULL",
            'reference_no' => "TEXT NULL DEFAULT NULL",
            'additional' => "TEXT NULL DEFAULT NULL",
            'additional_fees_items' => "TEXT NULL DEFAULT NULL",
            'additional_food' => "TEXT NULL DEFAULT NULL",
            'additional_items' => "TEXT NULL DEFAULT NULL",
            'paid_status' => "VARCHAR(50) NULL DEFAULT 'Unpaid'",
            'additional_paid_status' => "VARCHAR(50) NULL DEFAULT 'None'",
            'hygiene_kit_used' => "TINYINT(1) DEFAULT 0",
            'hygiene_kit_price' => "DECIMAL(10,2) DEFAULT 0",
            'supplier' => "VARCHAR(255) NULL DEFAULT NULL",
            'total_amount' => "DECIMAL(12,2) DEFAULT 0",
            'room_price' => "DECIMAL(10,2) DEFAULT 0",
            'reason_for_stay' => "VARCHAR(255) NULL DEFAULT NULL",
            'contact_no' => "VARCHAR(20) NULL DEFAULT NULL",
            'address' => "TEXT NULL DEFAULT NULL",

            'deposit' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_details' => "TEXT NULL DEFAULT NULL",
            'deposit_cash' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_g_cash' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_maya' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_instapay' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_online_banking' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_airbnb' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_gcash_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_maya_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'check_in_change_amount' => "DECIMAL(10,2) DEFAULT 0.00",
            'discount_enabled' => "TINYINT(1) DEFAULT 0",
            'discount_type' => "VARCHAR(50) NULL DEFAULT 'regular'",
            'discount_amount' => "DECIMAL(10,2) DEFAULT 0",
            'sc_pwd_count' => "INT DEFAULT 0",
            'guest_type' => "VARCHAR(50) NULL DEFAULT 'Solo'",
            'second_guest_name' => "VARCHAR(255) NULL DEFAULT NULL",
            'contact_person_name' => "VARCHAR(255) NULL DEFAULT NULL",
            'number_of_adults' => "INT DEFAULT 0",
            'number_of_children' => "INT DEFAULT 0",
            'tin_number' => "VARCHAR(50) NULL DEFAULT NULL",
            'email' => "VARCHAR(255) NULL DEFAULT NULL",
            'encoder' => "VARCHAR(255) NULL DEFAULT NULL"
            ,
            // Reservation downpayment (extra methods)
            'downpayment_instapay' => "DECIMAL(10,2) DEFAULT 0.00",
            'downpayment_online_banking' => "DECIMAL(10,2) DEFAULT 0.00",
            'downpayment_airbnb' => "DECIMAL(10,2) DEFAULT 0.00",
            'downpayment_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'downpayment_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'downpayment_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'discount_amount_history' => "TEXT NULL DEFAULT NULL"
        ];

        foreach ($requiredColumns as $columnName => $columnDefinition) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE '$columnName'");
                if ($checkColumn->rowCount() == 0) {
                    // Determine position based on column name
                    $afterClause = '';
                    if ($columnName === 'reference_no') {
                        $afterClause = ' AFTER payment_status';
                    } elseif ($columnName === 'total_amount') {
                        $afterClause = ' AFTER hygiene_kit_price';
                    } elseif ($columnName === 'supplier') {
                        $afterClause = ' AFTER referral_name';
                    }
                    $conn->exec("ALTER TABLE bookings ADD COLUMN $columnName $columnDefinition$afterClause");
                } else {
                    // If column exists but is wrong type, alter it
                    if ($columnName === 'reference_no') {
                        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
                        if (isset($columnInfo['Type']) && stripos($columnInfo['Type'], 'varchar') !== false) {
                            // Change from VARCHAR to TEXT
                            $conn->exec("ALTER TABLE bookings MODIFY COLUMN reference_no TEXT NULL DEFAULT NULL");
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Failed to check/add column $columnName: " . $e->getMessage());
            }
        }

        // CRITICAL FIX: Add discount columns to bookings table
        $discountColumns = [
            'discount_enabled' => "TINYINT(1) DEFAULT 0",
            'discount_type' => "VARCHAR(20) DEFAULT 'regular'",
            'sc_pwd_count' => "INT DEFAULT 0",
            'discount_amount' => "DECIMAL(10,2) DEFAULT 0"
        ];

        foreach ($discountColumns as $columnName => $columnDefinition) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE '$columnName'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec("ALTER TABLE bookings ADD COLUMN $columnName $columnDefinition AFTER paid_status");
                    error_log("Added discount column: $columnName");
                }
            } catch (PDOException $e) {
                error_log("Failed to check/add discount column $columnName: " . $e->getMessage());
            }
        }




        // Calculate check-out and hours
        $check_out = null;
        $hours = null;

        if ($check_in) {
            $totalHours = ($duration_unit === 'night') ? $duration * 12 : $duration;

            // If promo/bundle is selected, derive hours from the promo label itself
            // (e.g. "Package 2 24hrs" → 24 hours, "Package 1 12hrs" → 12 hours).
            // "Regular" / "Select Bundle" / "None" are NOT promos.
            if ($promo && $promo !== 'Select Promo' && $promo !== 'Select Bundle' && $promo !== 'Regular' && $promo !== 'None' && $promo !== '') {
                if (preg_match('/(\d+)\s*hrs?/i', $promo, $promoMatch)) {
                    $promoHours = intval($promoMatch[1]);
                    if ($promoHours > 0) {
                        $totalHours = $promoHours; // Use promo hours directly (replaces any duration)
                    } else {
                        $totalHours += 12; // Fallback for older 12-hour-only bundles
                    }
                } else {
                    $totalHours += 12; // Fallback for bundles without explicit hour count
                }
            }

            // Calculate checkout if we have check-in and (duration > 0 OR promo is selected)
            if ($totalHours > 0) {
                $checkInDate = new DateTime($check_in);
                $checkOutDate = clone $checkInDate;
                $checkOutDate->modify("+{$totalHours} hours");

                $check_out = $checkOutDate->format('Y-m-d H:i:s');
                $hours = $totalHours . ' hrs';
            }
        }

        // ── ROOM AVAILABILITY OVERLAP CHECK ──────────────────────────────────
        // Prevent double-booking: check if the requested time window overlaps
        // with any existing booking (bookings table) OR past record (reports table).
        if ($check_in && $check_out) {
            // Check active bookings table
            $overlapBookingStmt = $conn->prepare("
                SELECT booking_id, guest_name, check_in, check_out
                FROM bookings
                WHERE room_id = :room_id
                  AND status IN ('Confirming', 'Confirmed', 'Occupied')
                  AND check_in  < :new_check_out
                  AND check_out > :new_check_in
                LIMIT 1
            ");
            $overlapBookingStmt->bindParam(':room_id', $room_id);
            $overlapBookingStmt->bindParam(':new_check_in', $check_in);
            $overlapBookingStmt->bindParam(':new_check_out', $check_out);
            $overlapBookingStmt->execute();
            $conflictBooking = $overlapBookingStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflictBooking) {
                $cIn = date('m/d/Y h:i A', strtotime($conflictBooking['check_in']));
                $cOut = date('m/d/Y h:i A', strtotime($conflictBooking['check_out']));
                $response['message'] = "Room " . $room_id . " is already booked during this period.\n\n"
                    . "Existing Booking ID: " . $conflictBooking['booking_id'] . "\n"
                    . "Guest: " . $conflictBooking['guest_name'] . "\n"
                    . "Check-In: " . $cIn . "\n"
                    . "Check-Out: " . $cOut . "\n\n"
                    . "The selected Check-In and Check-Out period already exists. Please choose a different time.";
                ob_clean();
                echo json_encode($response);
                ob_end_flush();
                exit;
            }

            // Also check reports table — use actual checkout time for 'Checked Out' records
            // (early checkouts free the room at checked_out_at, not the scheduled check_out)
            $overlapReportStmt = $conn->prepare("
                SELECT booking_id, guest_name, check_in, check_out,
                       checked_out_at, status,
                       CASE
                           WHEN status = 'Checked Out' AND checked_out_at IS NOT NULL
                           THEN checked_out_at
                           ELSE check_out
                       END AS effective_check_out
                FROM reports
                WHERE room_id = :room_id
                  AND status NOT IN ('Canceled')
                  AND checked_out_at IS NULL
                  AND check_in < :new_check_out
                  AND CASE
                          WHEN status = 'Checked Out' AND checked_out_at IS NOT NULL
                          THEN checked_out_at
                          ELSE check_out
                      END > :new_check_in
                LIMIT 1
            ");
            $overlapReportStmt->bindParam(':room_id', $room_id);
            $overlapReportStmt->bindParam(':new_check_in', $check_in);
            $overlapReportStmt->bindParam(':new_check_out', $check_out);
            $overlapReportStmt->execute();
            $conflictReport = $overlapReportStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflictReport) {
                $cIn = date('m/d/Y h:i A', strtotime($conflictReport['check_in']));
                // Show the actual checkout time (checked_out_at for early checkouts)
                $effectiveOut = $conflictReport['effective_check_out'] ?? $conflictReport['check_out'];
                $cOut = date('m/d/Y h:i A', strtotime($effectiveOut));
                $response['message'] = "Room " . $room_id . " has a record that overlaps with this period.\n\n"
                    . "Existing Booking ID: " . $conflictReport['booking_id'] . "\n"
                    . "Guest: " . $conflictReport['guest_name'] . "\n"
                    . "Check-In: " . $cIn . "\n"
                    . "Check-Out: " . $cOut . "\n\n"
                    . "The selected Check-In and Check-Out period already exists. Please choose a different time.";
                ob_clean();
                echo json_encode($response);
                ob_end_flush();
                exit;
            }

            // ── ALSO CHECK AGAINST FUTURE RESERVATIONS (duration-aware) ───────
            // Reservations now have a duration, so we must treat them as a time window:
            //   res_start = reservation_date
            //   res_end   = res_start + duration (+promo hours if applicable)
            // Cleaning gap rule: allow next booking only after (res_end + 30 min).
            //
            // We also apply a 30-min cleaning gap after THIS booking's check_out when comparing windows.
            try {
                $checkOutObj = new DateTime($check_out);
                $checkOutPlus30 = (clone $checkOutObj)->add(new DateInterval('PT30M'));
                $gap_end = $checkOutPlus30->format('Y-m-d H:i:s');

                $overlapReservationStmt = $conn->prepare("
                    SELECT booking_id, guest_name, reservation_date, duration, duration_unit, promo
                    FROM bookings
                    WHERE room_id = :room_id
                      AND booking_type = 'Reservation'
                      AND status IN ('Reserved', 'Confirming', 'Confirmed')
                      AND reservation_date IS NOT NULL
                      AND reservation_date < :new_check_out_plus_30
                      AND DATE_ADD(
                            DATE_ADD(
                                reservation_date,
                                INTERVAL (
                                    (CASE
                                        WHEN duration_unit IN ('night','nights') THEN (IFNULL(duration,0) * 12)
                                        ELSE IFNULL(duration,0)
                                    END)
                                    +
                                    (CASE
                                        WHEN promo IS NOT NULL
                                         AND promo <> ''
                                         AND promo <> 'None'
                                         AND promo <> 'Regular'
                                         AND promo <> 'Select Bundle'
                                         AND promo <> 'Select Promo'
                                        THEN 12
                                        ELSE 0
                                    END)
                                ) HOUR
                            ),
                            INTERVAL 30 MINUTE
                          ) > :new_check_in
                    LIMIT 1
                ");
                $overlapReservationStmt->bindParam(':room_id', $room_id);
                $overlapReservationStmt->bindParam(':new_check_in', $check_in);
                $overlapReservationStmt->bindParam(':new_check_out_plus_30', $gap_end);
                $overlapReservationStmt->execute();
                $conflictReservation = $overlapReservationStmt->fetch(PDO::FETCH_ASSOC);

                if ($conflictReservation) {
                    $resStartObj = new DateTime($conflictReservation['reservation_date']);
                    $d = intval($conflictReservation['duration'] ?? 0);
                    $u = strtolower(trim($conflictReservation['duration_unit'] ?? 'hours'));
                    $promoStr = trim($conflictReservation['promo'] ?? '');
                    $resHours = ($u === 'night' || $u === 'nights') ? ($d * 12) : $d;
                    if ($promoStr !== '' && $promoStr !== 'None' && $promoStr !== 'Regular' && $promoStr !== 'Select Bundle' && $promoStr !== 'Select Promo') {
                        $resHours += 12;
                    }
                    if ($resHours < 0)
                        $resHours = 0;

                    $resEndObj = clone $resStartObj;
                    if ($resHours > 0)
                        $resEndObj->modify('+' . intval($resHours) . ' hours');
                    $resCleanEndObj = (clone $resEndObj)->add(new DateInterval('PT30M'));
                    $nextAvailableObj = (clone $resCleanEndObj)->add(new DateInterval('PT1M')); // first allowed minute after cleaning

                    $response['message'] = "Error: Room " . $room_id . " already has a reservation that overlaps this period.\n\n"
                        . "Existing Booking ID: " . ($conflictReservation['booking_id'] ?? 'N/A') . "\n"
                        . "Guest: " . ($conflictReservation['guest_name'] ?? 'N/A') . "\n"
                        . "Reservation Start: " . $resStartObj->format('m/d/Y h:i A') . "\n"
                        . "Reservation End: " . $resEndObj->format('m/d/Y h:i A') . "\n"
                        . "Cleaning Until: " . $resCleanEndObj->format('m/d/Y h:i A') . "\n"
                        . "Duration: " . $resHours . " Hour" . ($resHours !== 1 ? 's' : '') . "\n\n"
                        . "Next available time for booking/check-in: " . $nextAvailableObj->format('m/d/Y h:i A') . "\n"
                        . "Please choose a different time or room.";
                    ob_clean();
                    echo json_encode($response);
                    ob_end_flush();
                    exit;
                }
            } catch (Exception $e) {
                error_log('Reservation overlap check failed: ' . $e->getMessage());
            }

        }
        // ── END OVERLAP CHECK ─────────────────────────────────────────────────

        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_guest'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN additional_guest INT DEFAULT 0 AFTER guest_capacity");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add additional_guest column: " . $e->getMessage());
        }
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_pet'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN additional_pet INT DEFAULT 0 AFTER additional_guest");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add additional_pet column: " . $e->getMessage());
        }

        if ($use_hygiene_kit && $hygiene_kit_inventory_id) {
            try {
                $kitStmt = $conn->prepare("SELECT price, stock FROM inventory WHERE id = :id LIMIT 1");
                $kitStmt->bindParam(':id', $hygiene_kit_inventory_id, PDO::PARAM_INT);
                $kitStmt->execute();
                $kit = $kitStmt->fetch(PDO::FETCH_ASSOC);

                if ($kit && intval($kit['stock']) > 0) {
                    $hygiene_kit_price = floatval($kit['price'] ?? 0);
                } else {
                    $use_hygiene_kit = false;
                    $hygiene_kit_inventory_id = null;
                }
            } catch (PDOException $e) {
                error_log("Failed to fetch hygiene kit inventory: " . $e->getMessage());
                $use_hygiene_kit = false;
                $hygiene_kit_inventory_id = null;
            }
        }
        $hygiene_kit_used_value = $use_hygiene_kit ? 1 : 0;

        // Calculate room_price from room_durations table
        $room_price = 0.0;

        // CRITICAL FIX: If promo is selected, use promo price as room_price
        // "Regular" / "Select Bundle" are NOT promos.
        if ($promo && $promo !== 'Select Promo' && $promo !== 'Select Bundle' && $promo !== 'Regular' && $promo !== 'None' && $promo !== '') {
            require_once 'report_helpers.php';
            $promoMeta = parsePromoSelection($promo);
            $room_price = floatval($promoMeta['price'] ?? 0);
            error_log("=== PROMO PRICE USED ===");
            error_log("Promo: $promo");
            error_log("Promo price: $room_price");
            error_log("=== END PROMO PRICE ===");
        }

        // If no promo or promo price is 0, calculate from room_durations table
        if ($room_price == 0) {
            try {
                // Get room_db_id from rooms table
                $getRoomStmt = $conn->prepare("SELECT id FROM rooms WHERE room_id = :room_id LIMIT 1");
                $getRoomStmt->bindParam(':room_id', $room_id);
                $getRoomStmt->execute();
                $roomData = $getRoomStmt->fetch(PDO::FETCH_ASSOC);
                $room_db_id = $roomData['id'] ?? null;

                if ($room_db_id && $duration > 0) {
                    $totalHours = ($duration_unit === 'night') ? $duration * 12 : $duration;

                    // Ensure room_durations table exists
                    $conn->exec("CREATE TABLE IF NOT EXISTS room_durations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        room_id INT NOT NULL,
                        duration_hours INT NOT NULL,
                        price DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_room_duration (room_id, duration_hours),
                        INDEX idx_room_id (room_id),
                        INDEX idx_duration_hours (duration_hours)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                    // Get exact match first
                    $priceStmt = $conn->prepare("SELECT price FROM room_durations WHERE room_id = :room_id AND duration_hours = :duration_hours");
                    $priceStmt->execute([
                        ':room_id' => $room_db_id,
                        ':duration_hours' => $totalHours
                    ]);
                    $priceResult = $priceStmt->fetch(PDO::FETCH_ASSOC);

                    if ($priceResult) {
                        $room_price = floatval($priceResult['price']);
                    } else {
                        // If no exact match, find the closest lower duration
                        $priceStmt = $conn->prepare("SELECT price FROM room_durations WHERE room_id = :room_id AND duration_hours <= :duration_hours ORDER BY duration_hours DESC LIMIT 1");
                        $priceStmt->execute([
                            ':room_id' => $room_db_id,
                            ':duration_hours' => $totalHours
                        ]);
                        $priceResult = $priceStmt->fetch(PDO::FETCH_ASSOC);
                        if ($priceResult) {
                            $room_price = floatval($priceResult['price']);
                        }
                    }
                }

                // Fallback to calculateRoomRate if room_durations table doesn't have the price
                if ($room_price == 0 && $room_type) {
                    require_once 'report_helpers.php';
                    $totalHours = ($duration_unit === 'night') ? $duration * 12 : $duration;
                    $room_price = calculateRoomRate($room_type, $totalHours);
                }
            } catch (PDOException $e) {
                error_log("Failed to get room price: " . $e->getMessage());
                // Fallback to calculateRoomRate
                if ($room_price == 0 && $room_type) {
                    require_once 'report_helpers.php';
                    $totalHours = ($duration_unit === 'night') ? $duration * 12 : $duration;
                    $room_price = calculateRoomRate($room_type, $totalHours);
                }
            }
        }

        // First calculate the FULL amount WITHOUT deposit deduction
        $full_booking_amount = computeBookingTotalAmount([
            'room_type' => $room_type,
            'duration' => $duration,
            'duration_unit' => $duration_unit,
            'promo' => $promo,
            'breakfast' => $breakfast,
            'hygiene_kit_used' => $hygiene_kit_used_value,
            'hygiene_kit_price' => $hygiene_kit_price,
            'room_price' => $room_price,
            'deposit' => 0 // Don't deduct deposit yet
        ]);

        // CRITICAL FIX: Use manual discount amount from frontend instead of calculating
        // The discount amount is already set from POST data above (line 100)
        // No need to recalculate here - user enters the exact discount amount they want

        error_log("=== USING MANUAL DISCOUNT AMOUNT ===");
        error_log("Discount amount from frontend: $discount_amount");
        error_log("=== END ===");

        // Apply discount to full booking amount
        $full_booking_amount_after_discount = $full_booking_amount - $discount_amount;

        error_log("=== PAID STATUS CALCULATION ===");
        error_log("Full booking amount (before discount): $full_booking_amount");
        error_log("Discount amount: $discount_amount");
        error_log("Full booking amount (after discount): $full_booking_amount_after_discount");
        error_log("Total payments (deposit): $deposit");
        error_log("Promo: " . ($promo ?? 'NULL'));
        error_log("Room price: $room_price");
        error_log("Breakfast: " . ($breakfast ?? 'NULL'));
        error_log("Hygiene kit used: $hygiene_kit_used_value");
        error_log("Hygiene kit price: $hygiene_kit_price");
        error_log("Booking type: " . $booking_type);
        error_log("Status: " . $status);

        // If generic payment_status is missing but we have method-specific payment values,
        // synthesize the display value so Booking table won't show "-" for paid bookings.
        if ($payment_status === null) {
            $parts = [];
            if ($payment_status_cash !== '') {
                $parts[] = $payment_status_cash;
            }
            if ($payment_status_g_cash !== '') {
                $parts[] = $payment_status_g_cash;
            }
            if ($payment_status_maya !== '') {
                $parts[] = $payment_status_maya;
            }
            if ($payment_status_instapay !== '') {
                $parts[] = $payment_status_instapay;
            }
            if ($payment_status_online_banking !== '') {
                $parts[] = $payment_status_online_banking;
            }
            if ($payment_status_airbnb !== '') {
                $parts[] = $payment_status_airbnb;
            }
            if (!empty($parts)) {
                $payment_status = implode(', ', $parts);
            }
        }

        // Safety: when deposit fields are empty but method payment strings exist,
        // derive totals from payment_status_* so immediate paid bookings are not
        // accidentally saved as Unpaid.
        if ($deposit <= 0) {
            $cashFromStatus = $parsePaymentAmount($payment_status_cash);
            $gcashFromStatus = $parsePaymentAmount($payment_status_g_cash);
            $mayaFromStatus = $parsePaymentAmount($payment_status_maya);
            $instapayFromStatus = $parsePaymentAmount($payment_status_instapay);
            $onlineBankingFromStatus = $parsePaymentAmount($payment_status_online_banking);
            $airbnbFromStatus = $parsePaymentAmount($payment_status_airbnb);
            $derivedDeposit = $cashFromStatus + $gcashFromStatus + $mayaFromStatus + $instapayFromStatus + $onlineBankingFromStatus + $airbnbFromStatus;
            if ($derivedDeposit > 0) {
                $deposit_cash = $cashFromStatus;
                $deposit_g_cash = $gcashFromStatus;
                $deposit_maya = $mayaFromStatus;
                $deposit_instapay = $instapayFromStatus;
                $deposit_online_banking = $onlineBankingFromStatus;
                $deposit_airbnb = $airbnbFromStatus;
                $deposit = $derivedDeposit;
                if (!$deposit_details) {
                    $detailParts = [];
                    if ($deposit_cash > 0)
                        $detailParts[] = number_format($deposit_cash, 2) . ' Cash';
                    if ($deposit_g_cash > 0)
                        $detailParts[] = number_format($deposit_g_cash, 2) . ' G-cash';
                    if ($deposit_maya > 0)
                        $detailParts[] = number_format($deposit_maya, 2) . ' Maya';
                    if ($deposit_instapay > 0)
                        $detailParts[] = number_format($deposit_instapay, 2) . ' Instapay';
                    if ($deposit_online_banking > 0)
                        $detailParts[] = number_format($deposit_online_banking, 2) . ' Online Banking';
                    if ($deposit_airbnb > 0)
                        $detailParts[] = number_format($deposit_airbnb, 2) . ' Airbnb';
                    $deposit_details = !empty($detailParts) ? implode(', ', $detailParts) : null;
                }
            }
        }

        // Set paid_status based on total payments vs full booking amount (AFTER discount).
        // In confirm flow, payloads can occasionally miss `deposit` while still sending
        // method-specific payment strings. Derive a fallback payment amount from those
        // fields so full-payment confirms do not get downgraded to Unpaid.
        $cashFromStatus = $parsePaymentAmount($payment_status_cash);
        $gcashFromStatus = $parsePaymentAmount($payment_status_g_cash);
        $mayaFromStatus = $parsePaymentAmount($payment_status_maya);
        $instapayFromStatus = $parsePaymentAmount($payment_status_instapay);
        $onlineBankingFromStatus = $parsePaymentAmount($payment_status_online_banking);
        $airbnbFromStatus = $parsePaymentAmount($payment_status_airbnb);
        $derivedPaymentFromStatus = $cashFromStatus + $gcashFromStatus + $mayaFromStatus + $instapayFromStatus + $onlineBankingFromStatus + $airbnbFromStatus;
        $total_payments = max($deposit, $derivedPaymentFromStatus);

        // Calculate remaining amount due
        $amount_due = $full_booking_amount_after_discount - $total_payments;

        error_log("=== AMOUNT DUE CALCULATION ===");
        error_log("full_booking_amount_after_discount: " . $full_booking_amount_after_discount);
        error_log("total_payments: " . $total_payments);
        error_log("derivedPaymentFromStatus: " . $derivedPaymentFromStatus);
        error_log("amount_due: " . $amount_due);
        error_log("Condition check: full_booking_amount_after_discount <= 0? " . ($full_booking_amount_after_discount <= 0 ? 'YES' : 'NO'));
        error_log("Condition check: amount_due <= 0? " . ($amount_due <= 0 ? 'YES' : 'NO'));
        error_log("=== END ===");

        // SPECIAL CASE: If amount due is 0 or negative, booking is fully paid/credited
        // This handles cases where discounts/credits reduce the amount to 0
        // CRITICAL FIX: When full_booking_amount_after_discount is 0 or negative (free promo/bundle),
        // mark as Paid regardless of whether any payment was made (nothing to pay)
        if ($full_booking_amount_after_discount <= 0) {
            // Amount due is 0 or negative due to discounts/credits/promo - mark as Paid
            $paid_status = 'Paid';
            // For Walk-in mode, also set status to "Confirmed"
            if ($status !== 'Reserved') {
                $status = 'Confirmed';
            }
            error_log("✓ CONDITION MET: full_booking_amount_after_discount <= 0");
            error_log("Setting paid_status to: Paid (amount due is 0 or negative, nothing to pay)");
        } elseif ($amount_due <= 0.01) {
            // Fully paid or overpaid - amount due is 0 or negative
            // No need to check total_payments > 0 because if amount_due <= 0, it means payment covered the cost
            $paid_status = 'Paid';
            // For Walk-in mode, also set status to "Confirmed"
            if ($status !== 'Reserved') {
                $status = 'Confirmed';
            }
            error_log("✓ CONDITION MET: amount_due <= 0");
            error_log("Setting paid_status to: Paid (fully paid)");
        } else {
            $paid_status = 'Unpaid';
            error_log("✗ NO CONDITION MET: Setting paid_status to: Unpaid");
            error_log("This means: full_booking_amount_after_discount > 0 AND amount_due > 0");

            // Final safeguard for confirm flow:
            // if frontend explicitly sent Paid and provided payment evidence,
            // do not downgrade to Unpaid due intermediate parsing mismatch.
            $hasPaymentEvidence = (
                $total_payments > 0 ||
                ($payment_status !== null && trim((string) $payment_status) !== '') ||
                trim((string) $payment_status_cash) !== '' ||
                trim((string) $payment_status_g_cash) !== '' ||
                trim((string) $payment_status_maya) !== ''
            );
            if (strcasecmp($paid_status_from_post, 'Paid') === 0 && $hasPaymentEvidence) {
                $paid_status = 'Paid';
                if ($status !== 'Reserved') {
                    $status = 'Confirmed';
                }
                error_log("✓ SAFEGUARD APPLIED: honoring explicit Paid with payment evidence");
            }
        }
        error_log("=== END PAID STATUS CALCULATION ===");
        error_log("FINAL paid_status value: " . $paid_status);
        error_log("FINAL status value: " . $status);
        error_log("=== END ===");

        // Now calculate the total_amount WITH deposit deduction for storage (remaining balance)
        $total_amount = computeBookingTotalAmount([
            'room_type' => $room_type,
            'duration' => $duration,
            'duration_unit' => $duration_unit,
            'promo' => $promo,
            'breakfast' => $breakfast,
            'hygiene_kit_used' => $hygiene_kit_used_value,
            'hygiene_kit_price' => $hygiene_kit_price,
            'room_price' => $room_price,
            'deposit' => $deposit
        ]);

        // Apply discount to total_amount as well
        $total_amount = max(0, $total_amount - $discount_amount);

        // Separate food and items from additional_charges
        $additional_food = null;
        $additional_items = null;
        if ($additional_charges && $additional_charges !== '[]') {
            try {
                $chargesData = json_decode($additional_charges, true);
                if (is_array($chargesData)) {
                    $foodItems = [];
                    $itemList = [];
                    foreach ($chargesData as $charge) {
                        if (isset($charge['type']) && $charge['type'] === 'food') {
                            $foodItems[] = $charge;
                        } else {
                            $itemList[] = $charge;
                        }
                    }

                    // Format food items for storage (readable format: "1 Hotdog = ₱120.00")
                    if (!empty($foodItems)) {
                        $foodLines = [];
                        foreach ($foodItems as $food) {
                            $name = $food['selectedItem'] ?? '';
                            $quantity = intval($food['quantity'] ?? 1);
                            $price = floatval($food['price'] ?? 0);

                            // Skip invalid or placeholder items
                            if (empty($name) || trim($name) === '' || trim($name) === 'Select Food')
                                continue;

                            $foodLines[] = "{$quantity} {$name} = ₱" . number_format($price, 2);
                        }
                        if (!empty($foodLines)) {
                            $additional_food = implode("\n", $foodLines);
                        }
                    }

                    // Format items for storage (readable format: "1 Cover = ₱150.00")
                    if (!empty($itemList)) {
                        $itemLines = [];
                        foreach ($itemList as $item) {
                            $name = $item['selectedItem'] ?? '';
                            $quantity = intval($item['quantity'] ?? 1);
                            $price = floatval($item['price'] ?? 0);

                            // Skip invalid or placeholder items
                            if (empty($name) || trim($name) === '' || trim($name) === 'Select Item')
                                continue;

                            $itemLines[] = "{$quantity} {$name} = ₱" . number_format($price, 2);
                        }
                        if (!empty($itemLines)) {
                            $additional_items = implode("\n", $itemLines);
                        }
                    }
                }
            } catch (Exception $e) {
                // Handle parse error
            }
        }

        // Match update_booking.php behavior for UI:
        // Booking list forces payment badge to Unpaid when additional_paid_status = Unpaid.
        // So for new confirms, set this explicitly based on whether there are add-on charges.
        $hasAdditionalCharges = (
            !empty($additional_food) ||
            !empty($additional_items) ||
            intval($additional_guest) > 0 ||
            intval($additional_pet) > 0
        );

        // Set dates for additionals that are present at booking confirmation (JSON history in TEXT columns)
        $currentTimestamp = date('Y-m-d H:i:s');
        $additional_food_date = buildInitialAdditionalDateHistory(!empty($additional_food), $currentTimestamp);
        $additional_items_date = buildInitialAdditionalDateHistory(!empty($additional_items), $currentTimestamp);
        $additional_guest_date = buildInitialAdditionalDateHistory(intval($additional_guest) > 0, $currentTimestamp);
        $additional_pet_date = buildInitialAdditionalDateHistory(intval($additional_pet) > 0, $currentTimestamp);
        $breakfast_date = buildInitialAdditionalDateHistory(!empty($breakfast) && $breakfast !== 'None', $currentTimestamp);
        if ($hasAdditionalCharges) {
            $additional_paid_status = ($paid_status === 'Paid') ? 'Paid' : 'Unpaid';
        } else {
            $additional_paid_status = 'None';
        }

        // Insert new booking into BOOKINGS table first (primary storage)
        // We'll generate the booking_id after insert using the actual database ID
        $reference_no = trim($_POST['reference_no'] ?? '');
        $collectedReferences = [];
        foreach ([
            $reference_no,
            $reference_no_g_cash,
            $reference_no_maya,
            $reference_no_instapay,
            $reference_no_online_banking,
            $reference_no_airbnb,
            $deposit_gcash_ref,
            $deposit_maya_ref,
            $deposit_instapay_ref,
            $deposit_online_banking_ref,
            $deposit_airbnb_ref
        ] as $refCandidate) {
            $refCandidate = trim((string) $refCandidate);
            if ($refCandidate !== '') {
                $collectedReferences[] = $refCandidate;
            }
        }
        $collectedReferences = array_values(array_unique($collectedReferences));
        $reference_no = !empty($collectedReferences) ? implode(', ', $collectedReferences) : '';

        error_log("=== ABOUT TO INSERT BOOKING ===");
        error_log("paid_status value being inserted: " . $paid_status);
        error_log("status value being inserted: " . $status);
        error_log("=== END ===");

        // PAYMENT HISTORY: Record payment timestamp if any deposit/payment is made
        // payment_date_time stores multiple timestamps separated by "|"
        $payment_date_time_confirm = null;
        if ($deposit > 0) {
            // New payment made, record the timestamp
            $payment_date_time_confirm = date('Y-m-d H:i:s');
        }

        // Prepare history strings for each payment method
        $payment_amount_cash_history = $payment_date_time_confirm !== null ? number_format((float) ($original_deposit_cash ?? 0), 2, '.', '') : null;
        $payment_amount_g_cash_history = $payment_date_time_confirm !== null ? number_format((float) ($original_deposit_g_cash ?? 0), 2, '.', '') : null;
        $payment_amount_maya_history = $payment_date_time_confirm !== null ? number_format((float) ($original_deposit_maya ?? 0), 2, '.', '') : null;
        $payment_amount_instapay_history = $payment_date_time_confirm !== null ? number_format((float) ($original_deposit_instapay ?? 0), 2, '.', '') : null;
        $payment_amount_online_banking_history = $payment_date_time_confirm !== null ? number_format((float) ($original_deposit_online_banking ?? 0), 2, '.', '') : null;
        $payment_amount_airbnb_history = $payment_date_time_confirm !== null ? number_format((float) ($original_deposit_airbnb ?? 0), 2, '.', '') : null;

        // Reservation Date: should come from frontend (selected Reservation Date).
        // Fallback to "now" only if not provided.
        $reservation_date = null;
        $reservation_date_raw = trim($_POST['reservation_date'] ?? '');
        if ($reservation_date_raw !== '') {
            // Accept "YYYY-MM-DDTHH:mm(:ss)" or "YYYY-MM-DD HH:mm(:ss)"
            $reservation_date_raw = str_replace('T', ' ', $reservation_date_raw);
            try {
                $rd = new DateTime($reservation_date_raw);
                $reservation_date = $rd->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Ignore invalid input and allow fallback below
                $reservation_date = null;
            }
        }
        if ($reservation_date === null && $booking_type === 'Reservation' && $deposit > 0) {
            $reservation_date = date('Y-m-d H:i:s');
        }

        // ── RESERVATION CONFLICT CHECK (Reservation mode, uses duration) ────────
        // Reservations often have NULL check_in/check_out, so the overlap logic above won't run.
        // Here we treat the slot as:
        //   start = reservation_date
        //   end   = reservation_date + duration (hours; nights = 12h each; promo adds +12h like walk-in)
        // And enforce a cleaning gap:
        //   effective_end = end + 30 minutes
        // A new reservation can be created only if it does NOT overlap any existing active booking/reservation.
        if ($booking_type === 'Reservation' && $reservation_date !== null) {
            try {
                $startObj = new DateTime($reservation_date);

                $totalHours = ($duration_unit === 'night') ? ($duration * 12) : $duration;
                if ($promo && $promo !== 'Select Promo' && $promo !== 'Select Bundle' && $promo !== 'Regular' && $promo !== 'None' && $promo !== '') {
                    $totalHours += 12;
                }
                if ($totalHours < 0)
                    $totalHours = 0;

                $endObj = clone $startObj;
                if ($totalHours > 0) {
                    $endObj->modify('+' . intval($totalHours) . ' hours');
                }
                $endPlus30Obj = (clone $endObj)->add(new DateInterval('PT30M'));

                $new_start = $startObj->format('Y-m-d H:i:s');
                $new_end_plus30 = $endPlus30Obj->format('Y-m-d H:i:s');

                // 1) Block overlap with active walk-in/occupied bookings (check_in/check_out), with 30-min cleaning after check_out.
                $overlapBookingStmt = $conn->prepare("
                SELECT booking_id, guest_name, check_in, check_out, status
                FROM bookings
                WHERE room_id = :room_id
                  AND status IN ('Confirming', 'Confirmed', 'Occupied')
                  AND check_in IS NOT NULL
                  AND check_out IS NOT NULL
                  AND check_in < :new_end_plus30
                  AND DATE_ADD(check_out, INTERVAL 30 MINUTE) > :new_start
                LIMIT 1
            ");
                $overlapBookingStmt->bindParam(':room_id', $room_id);
                $overlapBookingStmt->bindParam(':new_start', $new_start);
                $overlapBookingStmt->bindParam(':new_end_plus30', $new_end_plus30);
                $overlapBookingStmt->execute();
                $conflictBooking = $overlapBookingStmt->fetch(PDO::FETCH_ASSOC);

                if ($conflictBooking) {
                    $cIn = date('m/d/Y h:i A', strtotime($conflictBooking['check_in']));
                    $cOut = date('m/d/Y h:i A', strtotime($conflictBooking['check_out']));
                    $response['message'] = "Room " . $room_id . " is already booked during this reservation slot.\n\n"
                        . "Existing Booking ID: " . ($conflictBooking['booking_id'] ?? 'N/A') . "\n"
                        . "Guest: " . ($conflictBooking['guest_name'] ?? 'N/A') . "\n"
                        . "Check-In: " . $cIn . "\n"
                        . "Check-Out: " . $cOut . "\n\n"
                        . "Please choose a different time or room.";
                    ob_clean();
                    echo json_encode($response);
                    ob_end_flush();
                    exit;
                }

                // 2) Block overlap with existing active reservations, using their stored duration (+ promo) and 30-min cleaning.
                $resOverlapStmt = $conn->prepare("
                SELECT booking_id, guest_name, reservation_date, status, duration, duration_unit, promo
                FROM bookings
                WHERE room_id = :room_id
                  AND booking_type = 'Reservation'
                  AND reservation_date IS NOT NULL
                  AND status IN ('Reserved', 'Confirming', 'Confirmed')
                  AND reservation_date < :new_end_plus30
                  AND DATE_ADD(
                        DATE_ADD(
                            reservation_date,
                            INTERVAL (
                                (CASE
                                    WHEN duration_unit IN ('night','nights') THEN (IFNULL(duration,0) * 12)
                                    ELSE IFNULL(duration,0)
                                END)
                                +
                                (CASE
                                    WHEN promo IS NOT NULL
                                     AND promo <> ''
                                     AND promo <> 'None'
                                     AND promo <> 'Regular'
                                     AND promo <> 'Select Bundle'
                                     AND promo <> 'Select Promo'
                                    THEN 12
                                    ELSE 0
                                END)
                            ) HOUR
                        ),
                        INTERVAL 30 MINUTE
                      ) > :new_start
                ORDER BY reservation_date ASC
                LIMIT 1
            ");
                $resOverlapStmt->bindParam(':room_id', $room_id);
                $resOverlapStmt->bindParam(':new_start', $new_start);
                $resOverlapStmt->bindParam(':new_end_plus30', $new_end_plus30);
                $resOverlapStmt->execute();
                $resConflict = $resOverlapStmt->fetch(PDO::FETCH_ASSOC);

                if ($resConflict) {
                    $resStartObj = new DateTime($resConflict['reservation_date']);
                    $d = intval($resConflict['duration'] ?? 0);
                    $u = strtolower(trim($resConflict['duration_unit'] ?? 'hours'));
                    $promoStr = trim($resConflict['promo'] ?? '');
                    $resHours = ($u === 'night' || $u === 'nights') ? ($d * 12) : $d;
                    if ($promoStr !== '' && $promoStr !== 'None' && $promoStr !== 'Regular' && $promoStr !== 'Select Bundle' && $promoStr !== 'Select Promo') {
                        $resHours += 12;
                    }
                    if ($resHours < 0)
                        $resHours = 0;

                    $resEndObj = clone $resStartObj;
                    if ($resHours > 0)
                        $resEndObj->modify('+' . intval($resHours) . ' hours');
                    $resCleanEndObj = (clone $resEndObj)->add(new DateInterval('PT30M'));
                    $nextAvailableObj = (clone $resCleanEndObj)->add(new DateInterval('PT1M'));

                    $newResFmt = date('m/d/Y h:i A', strtotime($reservation_date));
                    $response['message'] = "Error: Room " . $room_id . " already has a reservation that overlaps this slot.\n\n"
                        . "Requested: " . $newResFmt . "\n\n"
                        . "Existing Booking ID: " . ($resConflict['booking_id'] ?? 'N/A') . "\n"
                        . "Guest: " . ($resConflict['guest_name'] ?? 'N/A') . "\n"
                        . "Reservation Start: " . $resStartObj->format('m/d/Y h:i A') . "\n"
                        . "Reservation End: " . $resEndObj->format('m/d/Y h:i A') . "\n"
                        . "Cleaning Until: " . $resCleanEndObj->format('m/d/Y h:i A') . "\n"
                        . "Duration: " . $resHours . " Hour" . ($resHours !== 1 ? 's' : '') . "\n"
                        . "Status: " . ($resConflict['status'] ?? 'N/A') . "\n\n"
                        . "Next available reservation time: " . $nextAvailableObj->format('m/d/Y h:i A') . "\n"
                        . "Please choose a different time or room.";
                    ob_clean();
                    echo json_encode($response);
                    ob_end_flush();
                    exit;
                }
            } catch (Exception $e) {
                error_log('Reservation conflict check failed: ' . $e->getMessage());
            }
        }
        // ── END RESERVATION CONFLICT CHECK ──────────────────────────────────────

        $stmt = $conn->prepare("
    INSERT INTO bookings (
        booking_id, room_id, room_type, guest_name, reason_for_stay, contact_no, address, request, additional_guest, additional_pet,
        check_in, check_out, duration, duration_unit, hours,
        status, booking_type, room_image,
        referral_name, promo, breakfast, breakfast_date, payment_status, reference_no, additional, additional_fees_items, paid_status,
        additional_paid_status,
        payment_status_cash, payment_status_g_cash, payment_status_maya, payment_status_instapay, payment_status_online_banking, payment_status_airbnb,
        discount_enabled, discount_type, sc_pwd_count, discount_amount, discount_amount_history, id_number,
        hygiene_kit_used, hygiene_kit_price, supplier, total_amount, room_price, additional_food, additional_items, 
        additional_food_date, additional_items_date, additional_guest_date, additional_pet_date,
        deposit, deposit_details,
        deposit_cash, deposit_g_cash, deposit_maya, deposit_instapay, deposit_online_banking, deposit_airbnb,
        deposit_gcash_ref, deposit_maya_ref, deposit_instapay_ref, deposit_online_banking_ref, deposit_airbnb_ref, check_in_change_amount,
        downpayment_amount, downpayment_cash, downpayment_gcash, downpayment_maya, downpayment_instapay, downpayment_online_banking, downpayment_airbnb, downpayment_gcash_ref, downpayment_maya_ref, downpayment_instapay_ref, downpayment_online_banking_ref, downpayment_airbnb_ref, downpayment_status, downpayment_date, total_amount_reservation,
        guest_type, second_guest_name, contact_person_name, number_of_adults, number_of_children, tin_number, email,
        payment_date_time, reservation_date, encoder, sales_channel,
        payment_amount_cash_history, payment_amount_g_cash_history, payment_amount_maya_history,
        payment_amount_instapay_history, payment_amount_online_banking_history, payment_amount_airbnb_history
    ) VALUES (
        '', :room_id, :room_type, :guest_name, :reason_for_stay, :contact_no, :address, :request, :additional_guest, :additional_pet,
        :check_in, :check_out, :duration, :duration_unit, :hours,
        :status, :booking_type, :room_image,    
        :referral_name, :promo, :breakfast, :breakfast_date, :payment_status, :reference_no, :additional, :additional_fees_items, :paid_status,
        :additional_paid_status,
        :payment_status_cash, :payment_status_g_cash, :payment_status_maya, :payment_status_instapay, :payment_status_online_banking, :payment_status_airbnb,
        :discount_enabled, :discount_type, :sc_pwd_count, :discount_amount, :discount_amount_history, :id_number,
        :hygiene_kit_used, :hygiene_kit_price, :supplier, :total_amount, :room_price, :additional_food, :additional_items, 
        :additional_food_date, :additional_items_date, :additional_guest_date, :additional_pet_date,
        :deposit, :deposit_details,
        :deposit_cash, :deposit_g_cash, :deposit_maya, :deposit_instapay, :deposit_online_banking, :deposit_airbnb,
        :deposit_gcash_ref, :deposit_maya_ref, :deposit_instapay_ref, :deposit_online_banking_ref, :deposit_airbnb_ref, :check_in_change_amount,
        :downpayment_amount, :downpayment_cash, :downpayment_gcash, :downpayment_maya, :downpayment_instapay, :downpayment_online_banking, :downpayment_airbnb, :downpayment_gcash_ref, :downpayment_maya_ref, :downpayment_instapay_ref, :downpayment_online_banking_ref, :downpayment_airbnb_ref, :downpayment_status, :downpayment_date, :total_amount_reservation,
        :guest_type, :second_guest_name, :contact_person_name, :number_of_adults, :number_of_children, :tin_number, :email,
        :payment_date_time, :reservation_date, :encoder, :sales_channel,
        :cash_hist, :gcash_hist, :maya_hist, :instapay_hist, :online_banking_hist, :airbnb_hist
    )
");

        $stmt->bindParam(':room_id', $room_id);
        $stmt->bindParam(':room_type', $room_type);
        $stmt->bindParam(':guest_name', $guest_name);
        $stmt->bindParam(':reason_for_stay', $reason_for_stay);
        $stmt->bindParam(':contact_no', $contact_no);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':request', $request);
        $stmt->bindParam(':additional_guest', $additional_guest, PDO::PARAM_INT);
        $stmt->bindParam(':additional_pet', $additional_pet, PDO::PARAM_INT);
        $stmt->bindParam(':check_in', $check_in);
        $stmt->bindParam(':check_out', $check_out);
        $stmt->bindParam(':duration', $duration);
        $stmt->bindParam(':duration_unit', $duration_unit);
        $stmt->bindParam(':hours', $hours);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':booking_type', $booking_type);
        $stmt->bindParam(':room_image', $room_image);
        $stmt->bindParam(':referral_name', $referral_name);
        $stmt->bindParam(':promo', $promo);
        $stmt->bindParam(':breakfast', $breakfast);
        if ($breakfast_date === null) {
            $stmt->bindValue(':breakfast_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':breakfast_date', $breakfast_date);
        }
        $stmt->bindParam(':payment_status', $payment_status);
        $stmt->bindParam(':payment_status_cash', $payment_status_cash);
        $stmt->bindParam(':payment_status_g_cash', $payment_status_g_cash);
        $stmt->bindParam(':payment_status_maya', $payment_status_maya);
        $stmt->bindParam(':payment_status_instapay', $payment_status_instapay);
        $stmt->bindParam(':payment_status_online_banking', $payment_status_online_banking);
        $stmt->bindParam(':payment_status_airbnb', $payment_status_airbnb);
        $stmt->bindParam(':reference_no', $reference_no);
        $stmt->bindParam(':additional', $additional);
        $stmt->bindParam(':additional_fees_items', $additional_fees_items);
        $stmt->bindParam(':paid_status', $paid_status);
        $stmt->bindParam(':additional_paid_status', $additional_paid_status);
        $stmt->bindParam(':discount_enabled', $discount_enabled, PDO::PARAM_INT);
        $stmt->bindParam(':discount_type', $discount_type);
        $stmt->bindParam(':sc_pwd_count', $sc_pwd_count, PDO::PARAM_INT);
        $stmt->bindParam(':discount_amount', $discount_amount);
        $stmt->bindParam(':discount_amount_history', $discount_amount_history);
        $stmt->bindParam(':id_number', $id_number);
        $stmt->bindParam(':hygiene_kit_used', $hygiene_kit_used_value, PDO::PARAM_INT);
        $stmt->bindParam(':hygiene_kit_price', $hygiene_kit_price);
        $stmt->bindParam(':supplier', $supplier);
        $stmt->bindParam(':total_amount', $total_amount);
        $stmt->bindParam(':room_price', $room_price);
        $stmt->bindParam(':additional_food', $additional_food);
        $stmt->bindParam(':additional_items', $additional_items);

        // Bind additional date columns
        if ($additional_food_date === null) {
            $stmt->bindValue(':additional_food_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':additional_food_date', $additional_food_date);
        }
        if ($additional_items_date === null) {
            $stmt->bindValue(':additional_items_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':additional_items_date', $additional_items_date);
        }
        if ($additional_guest_date === null) {
            $stmt->bindValue(':additional_guest_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':additional_guest_date', $additional_guest_date);
        }
        if ($additional_pet_date === null) {
            $stmt->bindValue(':additional_pet_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':additional_pet_date', $additional_pet_date);
        }

        $stmt->bindParam(':guest_type', $guest_type);
        $stmt->bindParam(':second_guest_name', $second_guest_name);
        $stmt->bindParam(':contact_person_name', $contact_person_name);
        $stmt->bindParam(':number_of_adults', $number_of_adults, PDO::PARAM_INT);
        $stmt->bindParam(':number_of_children', $number_of_children, PDO::PARAM_INT);
        $stmt->bindParam(':tin_number', $tin_number);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':encoder', $encoder);
        $stmt->bindParam(':sales_channel', $sales_channel);
        if ($payment_date_time_confirm === null) {
            $stmt->bindValue(':payment_date_time', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_date_time', $payment_date_time_confirm);
        }

        // Bind history columns
        if ($payment_date_time_confirm === null) {
            $stmt->bindValue(':cash_hist', null, PDO::PARAM_NULL);
            $stmt->bindValue(':gcash_hist', null, PDO::PARAM_NULL);
            $stmt->bindValue(':maya_hist', null, PDO::PARAM_NULL);
            $stmt->bindValue(':instapay_hist', null, PDO::PARAM_NULL);
            $stmt->bindValue(':online_banking_hist', null, PDO::PARAM_NULL);
            $stmt->bindValue(':airbnb_hist', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':cash_hist', $payment_amount_cash_history);
            $stmt->bindParam(':gcash_hist', $payment_amount_g_cash_history);
            $stmt->bindParam(':maya_hist', $payment_amount_maya_history);
            $stmt->bindParam(':instapay_hist', $payment_amount_instapay_history);
            $stmt->bindParam(':online_banking_hist', $payment_amount_online_banking_history);
            $stmt->bindParam(':airbnb_hist', $payment_amount_airbnb_history);
        }

        // Bind reservation_date parameter
        if ($reservation_date === null) {
            $stmt->bindValue(':reservation_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':reservation_date', $reservation_date);
        }

        // CRITICAL FIX: Separate deposit and downpayment based on booking status
        // For Reserved bookings, payment goes to downpayment_* columns ONLY
        // For Walk-in bookings (Confirming/Confirmed), payment goes to deposit_* columns ONLY
        $isReservation = ($status === 'Reserved');

        // DEBUG: Log deposit values received from POST
        error_log("=== DEPOSIT VALUES DEBUG ===");
        error_log("status: " . $status);
        error_log("isReservation: " . ($isReservation ? 'TRUE' : 'FALSE'));
        error_log("deposit from POST: " . $deposit);
        error_log("deposit_cash from POST: " . $deposit_cash);
        error_log("deposit_g_cash from POST: " . $deposit_g_cash);
        error_log("deposit_maya from POST: " . $deposit_maya);
        error_log("deposit_details from POST: " . ($deposit_details ?? 'NULL'));
        error_log("deposit_gcash_ref from POST: " . ($deposit_gcash_ref ?? 'NULL'));
        error_log("deposit_maya_ref from POST: " . ($deposit_maya_ref ?? 'NULL'));

        if ($isReservation) {
            // Reservation mode: Clear deposit columns, payment goes to downpayment only
            $deposit_for_db = 0;
            $deposit_cash_for_db = 0;
            $deposit_g_cash_for_db = 0;
            $deposit_maya_for_db = 0;
            $deposit_instapay_for_db = 0;
            $deposit_online_banking_for_db = 0;
            $deposit_airbnb_for_db = 0;
            $deposit_details_for_db = null;
            $deposit_gcash_ref_for_db = null;
            $deposit_maya_ref_for_db = null;
        } else {
            // Walk-in mode: Use deposit columns as normal
            $deposit_for_db = $deposit;
            $deposit_cash_for_db = $deposit_cash;
            $deposit_g_cash_for_db = $deposit_g_cash;
            $deposit_maya_for_db = $deposit_maya;
            $deposit_instapay_for_db = $deposit_instapay;
            $deposit_online_banking_for_db = $deposit_online_banking;
            $deposit_airbnb_for_db = $deposit_airbnb;
            $deposit_details_for_db = $deposit_details;
            $deposit_gcash_ref_for_db = $deposit_gcash_ref;
            $deposit_maya_ref_for_db = $deposit_maya_ref;
        }

        // DEBUG: Log values that will be saved to database
        error_log("=== VALUES FOR DATABASE ===");
        error_log("deposit_for_db: " . $deposit_for_db);
        error_log("deposit_cash_for_db: " . $deposit_cash_for_db);
        error_log("deposit_g_cash_for_db: " . $deposit_g_cash_for_db);
        error_log("deposit_maya_for_db: " . $deposit_maya_for_db);
        error_log("deposit_details_for_db: " . ($deposit_details_for_db ?? 'NULL'));
        error_log("=== END DEBUG ===");

        $stmt->bindParam(':deposit', $deposit_for_db);
        $stmt->bindParam(':deposit_details', $deposit_details_for_db);
        $stmt->bindParam(':deposit_cash', $deposit_cash_for_db);
        $stmt->bindParam(':deposit_g_cash', $deposit_g_cash_for_db);
        $stmt->bindParam(':deposit_maya', $deposit_maya_for_db);
        $stmt->bindParam(':deposit_instapay', $deposit_instapay_for_db);
        $stmt->bindParam(':deposit_online_banking', $deposit_online_banking_for_db);
        $stmt->bindParam(':deposit_airbnb', $deposit_airbnb_for_db);
        $stmt->bindParam(':deposit_gcash_ref', $deposit_gcash_ref_for_db);
        $stmt->bindParam(':deposit_maya_ref', $deposit_maya_ref_for_db);
        $stmt->bindParam(':deposit_instapay_ref', $deposit_instapay_ref);
        $stmt->bindParam(':deposit_online_banking_ref', $deposit_online_banking_ref);
        $stmt->bindParam(':deposit_airbnb_ref', $deposit_airbnb_ref);
        $stmt->bindParam(':check_in_change_amount', $check_in_change_amount);

        // Calculate the FULL reservation amount (before deposit deduction)
        // NOTE: For Reservation mode we want total_amount_reservation to reflect
        // the actual downpayment collected (reservation fee), not the full room
        // price. This value is what we show in Reservation List and reports.
        $additional_guest_charge = $additional_guest * 300;
        $additional_pet_charge = $additional_pet * 500;
        $full_amount_before_deposit = $total_amount + $deposit; // Add back the deposit that was subtracted

        if ($isReservation) {
            // Reservation mode:
            //  - $deposit represents the downpayment the guest actually paid.
            //  - Store that as the reservation fee so Reservation List / reports
            //    show the correct amount (e.g. ₱500 instead of full ₱600 room rate).
            $total_amount_reservation = $deposit;
        } else {
            // Walk-in mode:
            // keep existing behaviour if ever used; usually 0 for non‑reservation.
            $total_amount_reservation = $full_amount_before_deposit + $additional_guest_charge + $additional_pet_charge;
        }

        // Downpayment columns - only populated for Reservation mode
        if ($isReservation) {
            // Store the actual downpayment amount that was collected.
            $downpayment_amount = $deposit;
            $downpayment_cash = $deposit_cash;
            $downpayment_gcash = $deposit_g_cash;
            $downpayment_maya = $deposit_maya;
            $downpayment_instapay = $deposit_instapay;
            $downpayment_online_banking = $deposit_online_banking;
            $downpayment_airbnb = $deposit_airbnb;
            $downpayment_gcash_ref = $deposit_gcash_ref;
            $downpayment_maya_ref = $deposit_maya_ref;
            $downpayment_instapay_ref = $deposit_instapay_ref;
            $downpayment_online_banking_ref = $deposit_online_banking_ref;
            $downpayment_airbnb_ref = $deposit_airbnb_ref;
            $downpayment_status = ($deposit_cash + $deposit_g_cash + $deposit_maya + $deposit_instapay + $deposit_online_banking + $deposit_airbnb) > 0 ? 'Paid' : 'None';
            $downpayment_date = ($deposit_cash + $deposit_g_cash + $deposit_maya + $deposit_instapay + $deposit_online_banking + $deposit_airbnb) > 0 ? date('Y-m-d H:i:s') : null;
        } else {
            // Walk-in mode: Clear downpayment columns
            $downpayment_amount = 0;
            $downpayment_cash = 0;
            $downpayment_gcash = 0;
            $downpayment_maya = 0;
            $downpayment_instapay = 0;
            $downpayment_online_banking = 0;
            $downpayment_airbnb = 0;
            $downpayment_gcash_ref = null;
            $downpayment_maya_ref = null;
            $downpayment_instapay_ref = null;
            $downpayment_online_banking_ref = null;
            $downpayment_airbnb_ref = null;
            $downpayment_status = 'None';
            $downpayment_date = null;
        }

        $stmt->bindParam(':downpayment_amount', $downpayment_amount);
        $stmt->bindParam(':downpayment_cash', $downpayment_cash);
        $stmt->bindParam(':downpayment_gcash', $downpayment_gcash);
        $stmt->bindParam(':downpayment_maya', $downpayment_maya);
        $stmt->bindParam(':downpayment_instapay', $downpayment_instapay);
        $stmt->bindParam(':downpayment_online_banking', $downpayment_online_banking);
        $stmt->bindParam(':downpayment_airbnb', $downpayment_airbnb);
        $stmt->bindParam(':downpayment_gcash_ref', $downpayment_gcash_ref);
        $stmt->bindParam(':downpayment_maya_ref', $downpayment_maya_ref);
        $stmt->bindParam(':downpayment_instapay_ref', $downpayment_instapay_ref);
        $stmt->bindParam(':downpayment_online_banking_ref', $downpayment_online_banking_ref);
        $stmt->bindParam(':downpayment_airbnb_ref', $downpayment_airbnb_ref);
        $stmt->bindParam(':downpayment_status', $downpayment_status);
        $stmt->bindParam(':downpayment_date', $downpayment_date);
        $stmt->bindParam(':total_amount_reservation', $total_amount_reservation);

        // Save to bookings table first - this is the primary storage
        if ($stmt->execute()) {
            // Get the actual database ID that was just inserted
            $db_id = $conn->lastInsertId();

            // Generate booking ID using the actual database ID: B-MM/DD/YY-XXX
            $date = date('m/d/y');
            $todayPrefix = 'B-' . $date . '-';
            $booking_id = $todayPrefix . str_pad($db_id, 3, '0', STR_PAD_LEFT);

            // Update the booking with the correct booking_id
            $updateBookingIdStmt = $conn->prepare("UPDATE bookings SET booking_id = :booking_id WHERE id = :id");
            $updateBookingIdStmt->bindParam(':booking_id', $booking_id);
            $updateBookingIdStmt->bindParam(':id', $db_id);
            $updateBookingIdStmt->execute();

            // Add columns for tracking Hygiene Kit and Tissue usage
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'hygiene_kit_inventory_id'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec("ALTER TABLE bookings ADD COLUMN hygiene_kit_inventory_id INT DEFAULT NULL AFTER hygiene_kit_price");
                }
            } catch (PDOException $e) {
                error_log("Failed to add hygiene_kit_inventory_id column: " . $e->getMessage());
            }

            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'tissue_inventory_id'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec("ALTER TABLE bookings ADD COLUMN tissue_inventory_id INT DEFAULT NULL AFTER hygiene_kit_inventory_id");
                }
            } catch (PDOException $e) {
                error_log("Failed to add tissue_inventory_id column: " . $e->getMessage());
            }

            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'tissue_used'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec("ALTER TABLE bookings ADD COLUMN tissue_used INT DEFAULT 0 AFTER tissue_inventory_id");
                }
            } catch (PDOException $e) {
                error_log("Failed to add tissue_used column: " . $e->getMessage());
            }

            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'hygiene_kit_restocked'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec("ALTER TABLE bookings ADD COLUMN hygiene_kit_restocked INT DEFAULT 0 AFTER tissue_used");
                }
            } catch (PDOException $e) {
                error_log("Failed to add hygiene_kit_restocked column: " . $e->getMessage());
            }

            // AUTOMATIC DEDUCTION REMOVED: Hygiene Kit and Tissue inventory is no longer automatically deducted on booking confirmation
            // Inventory will only be deducted manually or through other processes
            // Update room status to match booking status in the rooms table
            try {
                // Set room status based on booking status
                // Reserved = Reservation with downpayment
                // Confirming = Reservation without downpayment or Walk-in without payment
                // Occupied = Walk-in with payment (Confirmed) or checked-in guest
                if ($status === 'Reserved') {
                    $room_status = 'Reserved';
                } elseif ($status === 'Confirming') {
                    $room_status = 'Confirming';
                } else {
                    $room_status = 'Occupied';
                }

                $updateRoomStmt = $conn->prepare("UPDATE rooms SET status = :room_status WHERE room_id = :room_id");
                $updateRoomStmt->bindParam(':room_status', $room_status);
                $updateRoomStmt->bindParam(':room_id', $room_id);
                $updateRoomStmt->execute();
            } catch (PDOException $e) {
                // Log error but don't fail the booking creation
                error_log("Failed to update room status: " . $e->getMessage());
            }

            // Also store confirmed booking in REPORTS table (for reporting/analytics)
            // Note: This is secondary storage - bookings table is the primary source
            try {
                // Check if reports table exists, create it if it doesn't
                $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
                $hasReportsTable = $checkReportsTable->rowCount() > 0;

                if (!$hasReportsTable) {
                    // Create reports table with same structure as bookings table
                    $createReportsTable = $conn->exec("
                        CREATE TABLE IF NOT EXISTS reports (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            booking_id VARCHAR(50) NOT NULL,
                            room_id VARCHAR(50) NOT NULL,
                            room_type VARCHAR(100),
                            guest_name VARCHAR(255),
                            request TEXT,
                            promo VARCHAR(255) NULL,
                            breakfast VARCHAR(255) NULL,
                            payment_status VARCHAR(50) NULL,
                            referral_name VARCHAR(255) NULL,
                            supplier VARCHAR(255) NULL,
                            additional TEXT NULL,
                            paid_status VARCHAR(50) DEFAULT 'Unpaid',
                            check_in DATETIME,
                            check_out DATETIME,
                            duration INT DEFAULT 0,
                            duration_unit VARCHAR(20) DEFAULT 'hours',
                            hours VARCHAR(50),
                            status VARCHAR(50) DEFAULT 'Confirmed',
                            room_image VARCHAR(255),
                            hygiene_kit_used TINYINT(1) DEFAULT 0,
                            hygiene_kit_price DECIMAL(10,2) DEFAULT 0,
                            total_amount DECIMAL(12,2) DEFAULT 0,
                            confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            checked_out_at DATETIME NULL,
                            canceled_at DATETIME NULL,
                            extended_time BOOLEAN DEFAULT FALSE,
                            original_duration INT DEFAULT 0,
                            INDEX idx_booking_id (booking_id),
                            INDEX idx_room_id (room_id),
                            INDEX idx_status (status),
                            INDEX idx_confirmed_at (confirmed_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                } else {
                    // Add missing columns if table exists but columns are missing
                    try {
                        $columns = $conn->query("SHOW COLUMNS FROM reports LIKE 'checked_out_at'")->fetchAll();
                        if (empty($columns)) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN checked_out_at DATETIME NULL");
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to add checked_out_at column: " . $e->getMessage());
                    }

                    try {
                        $columns = $conn->query("SHOW COLUMNS FROM reports LIKE 'canceled_at'")->fetchAll();
                        if (empty($columns)) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN canceled_at DATETIME NULL");
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to add canceled_at column: " . $e->getMessage());
                    }

                    try {
                        $columns = $conn->query("SHOW COLUMNS FROM reports LIKE 'extended_time'")->fetchAll();
                        if (empty($columns)) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN extended_time BOOLEAN DEFAULT FALSE");
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to add extended_time column: " . $e->getMessage());
                    }

                    try {
                        $columns = $conn->query("SHOW COLUMNS FROM reports LIKE 'original_duration'")->fetchAll();
                        if (empty($columns)) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN original_duration INT DEFAULT 0");
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to add original_duration column: " . $e->getMessage());
                    }

                    try {
                        $columns = $conn->query("SHOW COLUMNS FROM reports LIKE 'hygiene_kit_used'")->fetchAll();
                        if (empty($columns)) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN hygiene_kit_used TINYINT(1) DEFAULT 0");
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to add hygiene_kit_used column: " . $e->getMessage());
                    }

                    try {
                        $columns = $conn->query("SHOW COLUMNS FROM reports LIKE 'hygiene_kit_price'")->fetchAll();
                        if (empty($columns)) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN hygiene_kit_price DECIMAL(10,2) DEFAULT 0");
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to add hygiene_kit_price column: " . $e->getMessage());
                    }
                }

                // Insert confirmed booking into reports table
                // Get additional fees from bookings if they exist (for existing bookings being confirmed)
                $missing_items_list = null;
                $missing_items_fees = 0;
                $additional_fees_status = 'None';
                $additional_fees_payment_method = null;
                $additional_fees_reference_no = null;

                // Initialize default values from current booking confirmation data (for new bookings where existingBooking is false)
                $extend_paid_status_from_booking = 'Unpaid';
                $check_in_charge_amount_from_booking = $check_in_change_amount;
                $downpayment_amount_from_booking = $downpayment_amount;
                $downpayment_cash_from_booking = $downpayment_cash;
                $downpayment_gcash_from_booking = $downpayment_gcash;
                $downpayment_maya_from_booking = $downpayment_maya;
                // CRITICAL FIX: Also initialize Instapay / Online Banking / Airbnb _from_booking defaults.
                // Previously missing, so the reportsStmt always bound 0 for these methods.
                $downpayment_instapay_from_booking = $downpayment_instapay;
                $downpayment_online_banking_from_booking = $downpayment_online_banking;
                $downpayment_airbnb_from_booking = $downpayment_airbnb;
                $downpayment_gcash_ref_from_booking = $downpayment_gcash_ref;
                $downpayment_maya_ref_from_booking = $downpayment_maya_ref;
                $downpayment_instapay_ref_from_booking = $downpayment_instapay_ref ?? null;
                $downpayment_online_banking_ref_from_booking = $downpayment_online_banking_ref ?? null;
                $downpayment_airbnb_ref_from_booking = $downpayment_airbnb_ref ?? null;
                $downpayment_status_from_booking = $downpayment_status;
                $downpayment_date_from_booking = $downpayment_date;
                $discount_enabled_from_booking = $discount_enabled ? 1 : 0;
                $discount_type_from_booking = $discount_type;
                $sc_pwd_count_from_booking = $sc_pwd_count;
                $discount_amount_from_booking = $discount_amount;
                $discount_amount_history_from_booking = $discount_amount_history;
                $id_number_from_booking = $id_number;
                $deposit_from_booking = $deposit;
                $total_amount_reservation_from_booking = $total_amount_reservation;

                try {
                    $getBookingStmt = $conn->prepare("
                        SELECT missing_items_list, missing_items_fees, additional_fees_status, additional_fees_payment_method, additional_fees_reference_no,
                               extend_paid_status, check_in_change_amount,
                               downpayment_amount, downpayment_cash, downpayment_gcash, downpayment_maya,
                               downpayment_instapay, downpayment_online_banking, downpayment_airbnb,
                               downpayment_gcash_ref, downpayment_maya_ref,
                               downpayment_instapay_ref, downpayment_online_banking_ref, downpayment_airbnb_ref,
                               downpayment_status, downpayment_date,
                               discount_enabled, discount_type, sc_pwd_count, discount_amount, discount_amount_history, id_number,
                               deposit, total_amount_reservation,
                               additional_food_date, additional_items_date, additional_guest_date, additional_pet_date, breakfast_date
                        FROM bookings WHERE booking_id = :booking_id LIMIT 1
                    ");
                    $getBookingStmt->bindParam(':booking_id', $booking_id);
                    $getBookingStmt->execute();
                    $existingBooking = $getBookingStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existingBooking) {
                        $missing_items_list = $existingBooking['missing_items_list'] ?? null;
                        $missing_items_fees = floatval($existingBooking['missing_items_fees'] ?? 0);
                        $additional_fees_status = $existingBooking['additional_fees_status'] ?? 'None';
                        $additional_fees_payment_method = $existingBooking['additional_fees_payment_method'] ?? null;
                        $additional_fees_reference_no = $existingBooking['additional_fees_reference_no'] ?? null;

                        // Get the 16 new columns from bookings
                        $extend_paid_status_from_booking = $existingBooking['extend_paid_status'] ?? 'Unpaid';
                        $check_in_charge_amount_from_booking = floatval($existingBooking['check_in_change_amount'] ?? 0);
                        $downpayment_amount_from_booking = floatval($existingBooking['downpayment_amount'] ?? 0);
                        $downpayment_cash_from_booking = floatval($existingBooking['downpayment_cash'] ?? 0);
                        $downpayment_gcash_from_booking = floatval($existingBooking['downpayment_gcash'] ?? 0);
                        $downpayment_maya_from_booking = floatval($existingBooking['downpayment_maya'] ?? 0);
                        // CRITICAL FIX: Read Instapay / Online Banking / Airbnb from existingBooking
                        $downpayment_instapay_from_booking = floatval($existingBooking['downpayment_instapay'] ?? 0);
                        $downpayment_online_banking_from_booking = floatval($existingBooking['downpayment_online_banking'] ?? 0);
                        $downpayment_airbnb_from_booking = floatval($existingBooking['downpayment_airbnb'] ?? 0);
                        $downpayment_gcash_ref_from_booking = $existingBooking['downpayment_gcash_ref'] ?? null;
                        $downpayment_maya_ref_from_booking = $existingBooking['downpayment_maya_ref'] ?? null;
                        $downpayment_instapay_ref_from_booking = $existingBooking['downpayment_instapay_ref'] ?? null;
                        $downpayment_online_banking_ref_from_booking = $existingBooking['downpayment_online_banking_ref'] ?? null;
                        $downpayment_airbnb_ref_from_booking = $existingBooking['downpayment_airbnb_ref'] ?? null;
                        $downpayment_status_from_booking = $existingBooking['downpayment_status'] ?? 'None';
                        $downpayment_date_from_booking = $existingBooking['downpayment_date'] ?? null;
                        $discount_enabled_from_booking = intval($existingBooking['discount_enabled'] ?? 0);
                        $discount_type_from_booking = $existingBooking['discount_type'] ?? 'regular';
                        $sc_pwd_count_from_booking = intval($existingBooking['sc_pwd_count'] ?? 0);
                        $discount_amount_from_booking = floatval($existingBooking['discount_amount'] ?? 0);
                        $discount_amount_history_from_booking = $existingBooking['discount_amount_history'] ?? '';
                        $id_number_from_booking = $existingBooking['id_number'] ?? '';
                        $deposit_from_booking = floatval($existingBooking['deposit'] ?? 0);
                        $total_amount_reservation_from_booking = floatval($existingBooking['total_amount_reservation'] ?? 0);

                        // Get additional date columns
                        $additional_food_date = $existingBooking['additional_food_date'] ?? null;
                        $additional_items_date = $existingBooking['additional_items_date'] ?? null;
                        $additional_guest_date = $existingBooking['additional_guest_date'] ?? null;
                        $additional_pet_date = $existingBooking['additional_pet_date'] ?? null;
                        $breakfast_date = $existingBooking['breakfast_date'] ?? null;
                    }
                } catch (PDOException $e) {
                    error_log("Failed to get additional fees: " . $e->getMessage());
                }

                // Ensure encoder column exists in reports table
                try {
                    $chkEncoderRep = $conn->query("SHOW COLUMNS FROM reports LIKE 'encoder'");
                    if ($chkEncoderRep->rowCount() == 0) {
                        $conn->exec("ALTER TABLE reports ADD COLUMN encoder VARCHAR(255) NULL DEFAULT NULL");
                    }
                } catch (PDOException $e) {
                    error_log("Failed to add encoder column to reports: " . $e->getMessage());
                }

                // PAYMENT AMOUNT HISTORY (per timestamp)
                // This is required so exports can show 1 row per payment_date_time entry
                // with the correct "Amount Paid" (e.g., 800 then 300).
                $histColumns = [
                    'payment_amount_cash_history' => "TEXT NULL DEFAULT NULL",
                    'payment_amount_g_cash_history' => "TEXT NULL DEFAULT NULL",
                    'payment_amount_maya_history' => "TEXT NULL DEFAULT NULL",
                    'payment_amount_instapay_history' => "TEXT NULL DEFAULT NULL",
                    'payment_amount_online_banking_history' => "TEXT NULL DEFAULT NULL",
                    'payment_amount_airbnb_history' => "TEXT NULL DEFAULT NULL",
                    'discount_amount_history' => "TEXT NULL DEFAULT NULL"
                ];
                foreach ($histColumns as $colName => $colDef) {
                    try {
                        $chk = $conn->query("SHOW COLUMNS FROM reports LIKE '" . $colName . "'");
                        if ($chk && $chk->rowCount() == 0) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN " . $colName . " " . $colDef);
                        }
                        // Keep bookings and reports schemas aligned for payment history.
                        $chkBooking = $conn->query("SHOW COLUMNS FROM bookings LIKE '" . $colName . "'");
                        if ($chkBooking && $chkBooking->rowCount() == 0) {
                            $conn->exec("ALTER TABLE bookings ADD COLUMN " . $colName . " " . $colDef);
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to ensure reports history column {$colName}: " . $e->getMessage());
                    }
                }

                // Ensure deposit_* columns exist for newer payment methods (best-effort).
                // Exports read these numeric columns directly.
                try {
                    $depositCols = [
                        'deposit_instapay' => "DECIMAL(10,2) DEFAULT 0",
                        'deposit_online_banking' => "DECIMAL(10,2) DEFAULT 0",
                        'deposit_airbnb' => "DECIMAL(10,2) DEFAULT 0",
                        'deposit_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL",
                        'deposit_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL",
                        'deposit_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL"
                    ];
                    foreach (['reports', 'bookings'] as $t) {
                        foreach ($depositCols as $c => $def) {
                            try {
                                $chk = $conn->query("SHOW COLUMNS FROM {$t} LIKE '{$c}'");
                                if ($chk && $chk->rowCount() == 0) {
                                    $conn->exec("ALTER TABLE {$t} ADD COLUMN {$c} {$def}");
                                }
                            } catch (Exception $e) {
                            }
                        }
                    }
                } catch (Exception $e) {
                }


                $reportsStmt = $conn->prepare("
    INSERT INTO reports (
        id, booking_id, room_id, room_type, guest_name, guest_type, contact_person_name, tin_number, reason_for_stay, address, request,
        promo, breakfast, breakfast_date, additional_guest, additional_pet, payment_status, reference_no, referral_name, supplier, additional, additional_food, additional_items, paid_status,
        check_in, check_out, duration, duration_unit, hours, 
        status, booking_type, room_image, hygiene_kit_used, hygiene_kit_price, total_amount, room_price,
        missing_items_list, missing_items_fees, additional_fees_status, additional_fees_payment_method, additional_fees_reference_no,
        deposit, deposit_cash, deposit_g_cash, deposit_maya, deposit_instapay, deposit_online_banking, deposit_airbnb,
        deposit_details, deposit_gcash_ref, deposit_maya_ref, deposit_instapay_ref, deposit_online_banking_ref, deposit_airbnb_ref,
        extend_paid_status, check_in_charge_amount,
        downpayment_amount, downpayment_cash, downpayment_gcash, downpayment_maya, downpayment_instapay, downpayment_online_banking, downpayment_airbnb, downpayment_gcash_ref, downpayment_maya_ref, downpayment_instapay_ref, downpayment_online_banking_ref, downpayment_airbnb_ref, downpayment_status, downpayment_date,
        discount_enabled, discount_type, sc_pwd_count, discount_amount, discount_amount_history, id_number,
        total_amount_reservation,
        payment_date_time, payment_amount_cash_history, payment_amount_g_cash_history, payment_amount_maya_history, payment_amount_instapay_history, payment_amount_online_banking_history, payment_amount_airbnb_history, reservation_date, encoder,
        additional_food_date, additional_items_date, additional_guest_date, additional_pet_date
    ) VALUES (
        :id, :booking_id, :room_id, :room_type, :guest_name, :guest_type, :contact_person_name, :tin_number, :reason_for_stay, :address, :request,
        :promo, :breakfast, :breakfast_date, :additional_guest, :additional_pet, :payment_status, :reference_no, :referral_name, :supplier, :additional, :additional_food, :additional_items, :paid_status,
        :check_in, :check_out, :duration, :duration_unit, :hours,
        :status, :booking_type, :room_image, :hygiene_kit_used, :hygiene_kit_price, :total_amount, :room_price,
        :missing_items_list, :missing_items_fees, :additional_fees_status, :additional_fees_payment_method, :additional_fees_reference_no,
        :deposit, :deposit_cash, :deposit_g_cash, :deposit_maya, :deposit_instapay, :deposit_online_banking, :deposit_airbnb,
        :deposit_details, :deposit_gcash_ref, :deposit_maya_ref, :deposit_instapay_ref, :deposit_online_banking_ref, :deposit_airbnb_ref,
        :extend_paid_status, :check_in_charge_amount,
        :downpayment_amount, :downpayment_cash, :downpayment_gcash, :downpayment_maya, :downpayment_instapay, :downpayment_online_banking, :downpayment_airbnb, :downpayment_gcash_ref, :downpayment_maya_ref, :downpayment_instapay_ref, :downpayment_online_banking_ref, :downpayment_airbnb_ref, :downpayment_status, :downpayment_date,
        :discount_enabled, :discount_type, :sc_pwd_count, :discount_amount, :discount_amount_history, :id_number,
        :total_amount_reservation,
        :payment_date_time, :payment_amount_cash_history, :payment_amount_g_cash_history, :payment_amount_maya_history, :payment_amount_instapay_history, :payment_amount_online_banking_history, :payment_amount_airbnb_history, :reservation_date, :encoder,
        :additional_food_date, :additional_items_date, :additional_guest_date, :additional_pet_date
    )
");
                $reportsStmt->bindParam(':id', $db_id, PDO::PARAM_INT);
                $reportsStmt->bindParam(':booking_id', $booking_id);
                $reportsStmt->bindParam(':room_id', $room_id);
                $reportsStmt->bindParam(':room_type', $room_type);
                $reportsStmt->bindParam(':guest_name', $guest_name);
                $reportsStmt->bindParam(':guest_type', $guest_type);
                $reportsStmt->bindParam(':contact_person_name', $contact_person_name);
                $reportsStmt->bindParam(':tin_number', $tin_number);
                $reportsStmt->bindParam(':reason_for_stay', $reason_for_stay);
                $reportsStmt->bindParam(':address', $address);
                $reportsStmt->bindParam(':request', $request);
                $reportsStmt->bindParam(':promo', $promo);
                $reportsStmt->bindParam(':breakfast', $breakfast);
                if ($breakfast_date === null) {
                    $reportsStmt->bindValue(':breakfast_date', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':breakfast_date', $breakfast_date);
                }
                $reportsStmt->bindParam(':additional_guest', $additional_guest);
                $reportsStmt->bindParam(':additional_pet', $additional_pet);
                $reportsStmt->bindParam(':payment_status', $payment_status);
                $reportsStmt->bindParam(':reference_no', $reference_no);
                $reportsStmt->bindParam(':referral_name', $referral_name);
                $reportsStmt->bindParam(':supplier', $supplier);
                $reportsStmt->bindParam(':additional', $additional);
                if ($additional_food === null) {
                    $reportsStmt->bindValue(':additional_food', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_food', $additional_food);
                }
                if ($additional_items === null) {
                    $reportsStmt->bindValue(':additional_items', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_items', $additional_items);
                }
                $reportsStmt->bindParam(':paid_status', $paid_status);
                $reportsStmt->bindParam(':check_in', $check_in);
                $reportsStmt->bindParam(':check_out', $check_out);
                $reportsStmt->bindParam(':duration', $duration);
                $reportsStmt->bindParam(':duration_unit', $duration_unit);
                $reportsStmt->bindParam(':hours', $hours);
                $reportsStmt->bindParam(':status', $status);
                $reportsStmt->bindParam(':booking_type', $booking_type);
                $reportsStmt->bindParam(':room_image', $room_image);
                $reportsStmt->bindParam(':hygiene_kit_used', $hygiene_kit_used_value, PDO::PARAM_INT);
                $reportsStmt->bindParam(':hygiene_kit_price', $hygiene_kit_price);
                $reportsStmt->bindParam(':total_amount', $total_amount);
                $reportsStmt->bindParam(':room_price', $room_price);
                if ($missing_items_list === null) {
                    $reportsStmt->bindValue(':missing_items_list', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':missing_items_list', $missing_items_list);
                }
                $reportsStmt->bindParam(':missing_items_fees', $missing_items_fees);
                $reportsStmt->bindParam(':additional_fees_status', $additional_fees_status);
                if ($additional_fees_payment_method === null) {
                    $reportsStmt->bindValue(':additional_fees_payment_method', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_fees_payment_method', $additional_fees_payment_method);
                }
                if ($additional_fees_reference_no === null) {
                    $reportsStmt->bindValue(':additional_fees_reference_no', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_fees_reference_no', $additional_fees_reference_no);
                }
                $reportsStmt->bindParam(':deposit', $deposit);
                $reportsStmt->bindParam(':deposit_cash', $original_deposit_cash);
                $reportsStmt->bindParam(':deposit_g_cash', $original_deposit_g_cash);
                $reportsStmt->bindParam(':deposit_maya', $original_deposit_maya);
                $reportsStmt->bindParam(':deposit_instapay', $original_deposit_instapay);
                $reportsStmt->bindParam(':deposit_online_banking', $original_deposit_online_banking);
                $reportsStmt->bindParam(':deposit_airbnb', $original_deposit_airbnb);
                $reportsStmt->bindParam(':deposit_details', $deposit_details);
                $reportsStmt->bindParam(':deposit_gcash_ref', $deposit_gcash_ref);
                $reportsStmt->bindParam(':deposit_maya_ref', $deposit_maya_ref);
                $reportsStmt->bindParam(':deposit_instapay_ref', $deposit_instapay_ref);
                $reportsStmt->bindParam(':deposit_online_banking_ref', $deposit_online_banking_ref);
                $reportsStmt->bindParam(':deposit_airbnb_ref', $deposit_airbnb_ref);

                // Bind the 16 new columns from the bookings data
                // 1. extend_paid_status
                $reportsStmt->bindParam(':extend_paid_status', $extend_paid_status_from_booking);

                // 2. check_in_charge_amount
                $reportsStmt->bindParam(':check_in_charge_amount', $check_in_charge_amount_from_booking);

                // 3-10. Downpayment fields (8 columns)
                $reportsStmt->bindParam(':downpayment_amount', $downpayment_amount_from_booking);
                $reportsStmt->bindParam(':downpayment_cash', $downpayment_cash_from_booking);
                $reportsStmt->bindParam(':downpayment_gcash', $downpayment_gcash_from_booking);
                $reportsStmt->bindParam(':downpayment_maya', $downpayment_maya_from_booking);
                // CRITICAL FIX: Use _from_booking variables (which now carry the correct values
                // fetched from the SELECT query) instead of reading directly from $existingBooking
                // which was unreliable after the SELECT was missing these columns.
                $reportsStmt->bindValue(':downpayment_instapay', $downpayment_instapay_from_booking);
                $reportsStmt->bindValue(':downpayment_online_banking', $downpayment_online_banking_from_booking);
                $reportsStmt->bindValue(':downpayment_airbnb', $downpayment_airbnb_from_booking);
                $reportsStmt->bindParam(':downpayment_gcash_ref', $downpayment_gcash_ref_from_booking);
                $reportsStmt->bindParam(':downpayment_maya_ref', $downpayment_maya_ref_from_booking);
                if ($downpayment_instapay_ref_from_booking === null || $downpayment_instapay_ref_from_booking === '')
                    $reportsStmt->bindValue(':downpayment_instapay_ref', null, PDO::PARAM_NULL);
                else
                    $reportsStmt->bindValue(':downpayment_instapay_ref', $downpayment_instapay_ref_from_booking);
                if ($downpayment_online_banking_ref_from_booking === null || $downpayment_online_banking_ref_from_booking === '')
                    $reportsStmt->bindValue(':downpayment_online_banking_ref', null, PDO::PARAM_NULL);
                else
                    $reportsStmt->bindValue(':downpayment_online_banking_ref', $downpayment_online_banking_ref_from_booking);
                if ($downpayment_airbnb_ref_from_booking === null || $downpayment_airbnb_ref_from_booking === '')
                    $reportsStmt->bindValue(':downpayment_airbnb_ref', null, PDO::PARAM_NULL);
                else
                    $reportsStmt->bindValue(':downpayment_airbnb_ref', $downpayment_airbnb_ref_from_booking);
                $reportsStmt->bindParam(':downpayment_status', $downpayment_status_from_booking);
                $reportsStmt->bindParam(':downpayment_date', $downpayment_date_from_booking);

                // 11-14. Discount fields (4 columns)
                $reportsStmt->bindParam(':discount_enabled', $discount_enabled_from_booking, PDO::PARAM_INT);
                $reportsStmt->bindParam(':discount_type', $discount_type_from_booking);
                $reportsStmt->bindParam(':sc_pwd_count', $sc_pwd_count_from_booking, PDO::PARAM_INT);
                $reportsStmt->bindParam(':discount_amount', $discount_amount_from_booking);
                $reportsStmt->bindParam(':discount_amount_history', $discount_amount_history_from_booking);
                $reportsStmt->bindParam(':id_number', $id_number_from_booking);

                // 15. deposit is already bound above

                // 16. total_amount_reservation
                $reportsStmt->bindParam(':total_amount_reservation', $total_amount_reservation_from_booking);

                // payment_date_time
                if ($payment_date_time_confirm === null) {
                    $reportsStmt->bindValue(':payment_date_time', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':payment_date_time', $payment_date_time_confirm);
                }

                // payment_amount_*_history (aligned 1:1 with payment_date_time timestamps)
                if ($payment_date_time_confirm === null) {
                    $reportsStmt->bindValue(':payment_amount_cash_history', null, PDO::PARAM_NULL);
                    $reportsStmt->bindValue(':payment_amount_g_cash_history', null, PDO::PARAM_NULL);
                    $reportsStmt->bindValue(':payment_amount_maya_history', null, PDO::PARAM_NULL);
                    $reportsStmt->bindValue(':payment_amount_instapay_history', null, PDO::PARAM_NULL);
                    $reportsStmt->bindValue(':payment_amount_online_banking_history', null, PDO::PARAM_NULL);
                    $reportsStmt->bindValue(':payment_amount_airbnb_history', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':payment_amount_cash_history', $payment_amount_cash_history);
                    $reportsStmt->bindParam(':payment_amount_g_cash_history', $payment_amount_g_cash_history);
                    $reportsStmt->bindParam(':payment_amount_maya_history', $payment_amount_maya_history);
                    $reportsStmt->bindParam(':payment_amount_instapay_history', $payment_amount_instapay_history);
                    $reportsStmt->bindParam(':payment_amount_online_banking_history', $payment_amount_online_banking_history);
                    $reportsStmt->bindParam(':payment_amount_airbnb_history', $payment_amount_airbnb_history);
                }

                // reservation_date
                if ($reservation_date === null) {
                    $reportsStmt->bindValue(':reservation_date', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':reservation_date', $reservation_date);
                }

                // encoder
                $reportsStmt->bindParam(':encoder', $encoder);

                // Additional date columns - get from bookings table
                if ($additional_food_date === null) {
                    $reportsStmt->bindValue(':additional_food_date', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_food_date', $additional_food_date);
                }
                if ($additional_items_date === null) {
                    $reportsStmt->bindValue(':additional_items_date', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_items_date', $additional_items_date);
                }
                if ($additional_guest_date === null) {
                    $reportsStmt->bindValue(':additional_guest_date', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_guest_date', $additional_guest_date);
                }
                if ($additional_pet_date === null) {
                    $reportsStmt->bindValue(':additional_pet_date', null, PDO::PARAM_NULL);
                } else {
                    $reportsStmt->bindParam(':additional_pet_date', $additional_pet_date);
                }

                // DEBUG: Log breakfast_date value before execute
                error_log("=== REPORTS INSERT DEBUG ===");
                error_log("breakfast: " . ($breakfast ?? 'NULL'));
                error_log("breakfast_date: " . ($breakfast_date ?? 'NULL'));
                error_log("additional_food_date: " . ($additional_food_date ?? 'NULL'));
                error_log("additional_items_date: " . ($additional_items_date ?? 'NULL'));
                error_log("additional_guest_date: " . ($additional_guest_date ?? 'NULL'));
                error_log("additional_pet_date: " . ($additional_pet_date ?? 'NULL'));
                error_log("=== END DEBUG ===");

                $reportsStmt->execute();

                // Mirror first-payment history values into bookings as well so phpMyAdmin
                // shows the same payment history in both tables.
                try {
                    $bookingHistUpdateStmt = $conn->prepare("
                        UPDATE bookings
                        SET payment_amount_cash_history = :cash_hist,
                            payment_amount_g_cash_history = :gcash_hist,
                            payment_amount_maya_history = :maya_hist,
                            payment_amount_instapay_history = :instapay_hist,
                            payment_amount_online_banking_history = :online_banking_hist,
                            payment_amount_airbnb_history = :airbnb_hist
                        WHERE id = :id
                    ");
                    if ($payment_date_time_confirm === null) {
                        $bookingHistUpdateStmt->bindValue(':cash_hist', null, PDO::PARAM_NULL);
                        $bookingHistUpdateStmt->bindValue(':gcash_hist', null, PDO::PARAM_NULL);
                        $bookingHistUpdateStmt->bindValue(':maya_hist', null, PDO::PARAM_NULL);
                        $bookingHistUpdateStmt->bindValue(':instapay_hist', null, PDO::PARAM_NULL);
                        $bookingHistUpdateStmt->bindValue(':online_banking_hist', null, PDO::PARAM_NULL);
                        $bookingHistUpdateStmt->bindValue(':airbnb_hist', null, PDO::PARAM_NULL);
                    } else {
                        $bookingHistUpdateStmt->bindParam(':cash_hist', $payment_amount_cash_history);
                        $bookingHistUpdateStmt->bindParam(':gcash_hist', $payment_amount_g_cash_history);
                        $bookingHistUpdateStmt->bindParam(':maya_hist', $payment_amount_maya_history);
                        $bookingHistUpdateStmt->bindParam(':instapay_hist', $payment_amount_instapay_history);
                        $bookingHistUpdateStmt->bindParam(':online_banking_hist', $payment_amount_online_banking_history);
                        $bookingHistUpdateStmt->bindParam(':airbnb_hist', $payment_amount_airbnb_history);
                    }
                    $bookingHistUpdateStmt->bindParam(':id', $db_id, PDO::PARAM_INT);
                    $bookingHistUpdateStmt->execute();
                } catch (PDOException $e) {
                    error_log("Failed to save booking payment history on bookings table: " . $e->getMessage());
                }
            } catch (PDOException $e) {
                // Log error but don't fail the booking creation
                error_log("Failed to save booking to reports table: " . $e->getMessage());
            }

            $response['success'] = true;
            $response['message'] = 'Booking confirmed and saved successfully!';
            $response['booking_id'] = $booking_id;
            $response['paid_status'] = $paid_status; // Return calculated paid_status to frontend
        } else {
            $response['message'] = 'Failed to create booking!';
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            $response['message'] = 'Booking ID already exists! Please try again.';
        } else {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }
} else {
    $response['message'] = 'Invalid request method!';
}

// Clean output buffer and send ONLY JSON
ob_clean();
echo json_encode($response);
ob_end_flush();
exit;
?>