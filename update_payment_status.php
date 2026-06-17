<?php
require_once 'config.php';
require_once 'report_helpers.php';
require_once 'inventory_helpers.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method!';
    echo json_encode($response);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$paid_status = trim($_POST['paid_status'] ?? 'Pending');
$payment_status = trim($_POST['payment_status'] ?? '');
$reference_no_g_cash = trim($_POST['reference_no_g_cash'] ?? '');
$reference_no_maya = trim($_POST['reference_no_maya'] ?? '');
$reference_no_instapay = trim($_POST['reference_no_instapay'] ?? '');
$reference_no_online_banking = trim($_POST['reference_no_online_banking'] ?? '');
$reference_no_airbnb = trim($_POST['reference_no_airbnb'] ?? '');
$payment_status_g_cash = trim($_POST['payment_status_g_cash'] ?? '');
$payment_status_maya = trim($_POST['payment_status_maya'] ?? '');
$payment_status_instapay = trim($_POST['payment_status_instapay'] ?? '');
$payment_status_online_banking = trim($_POST['payment_status_online_banking'] ?? '');
$payment_status_airbnb = trim($_POST['payment_status_airbnb'] ?? '');
$payment_status_cash = trim($_POST['payment_status_cash'] ?? '');
$reference_no_input = trim($_POST['reference_no'] ?? '');
$reference_numbers_json = $_POST['reference_numbers'] ?? '';
$missing_items_fees = floatval($_POST['missing_items_fees'] ?? 0);
$missing_items_list = trim($_POST['missing_items_list'] ?? '');
$penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
$penalty_list = trim($_POST['penalty_list'] ?? '');
$inventory_deduct_list = $_POST['inventory_deduct_list'] ?? '';
$change_amount = floatval($_POST['change_amount'] ?? 0);

// CRITICAL FIX: Get existing deposit from database and ADD new payment to it
// First, get the current deposit values from the database
$getDepositStmt = $conn->prepare("SELECT deposit, deposit_cash, deposit_g_cash, deposit_maya, deposit_details, deposit_gcash_ref, deposit_maya_ref FROM bookings WHERE id = :booking_id");
$getDepositStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
$getDepositStmt->execute();
$existingDeposit = $getDepositStmt->fetch(PDO::FETCH_ASSOC);

$existing_deposit_total = floatval($existingDeposit['deposit'] ?? 0);
$existing_deposit_cash = floatval($existingDeposit['deposit_cash'] ?? 0);
$existing_deposit_g_cash = floatval($existingDeposit['deposit_g_cash'] ?? 0);
$existing_deposit_maya = floatval($existingDeposit['deposit_maya'] ?? 0);
$existing_deposit_details = trim($existingDeposit['deposit_details'] ?? '');
$existing_deposit_gcash_ref = trim($existingDeposit['deposit_gcash_ref'] ?? '');
$existing_deposit_maya_ref = trim($existingDeposit['deposit_maya_ref'] ?? '');

// Keep payment_status_* labels exactly as posted by frontend.
// This avoids rewriting Instapay/Online Banking/Airbnb as Cash.
$extractAmount = function ($status) {
    if (!is_string($status) || trim($status) === '') {
        return 0.0;
    }
    if (preg_match('/([0-9,]+\.?[0-9]*)/', $status, $m)) {
        return floatval(str_replace(',', '', $m[1]));
    }
    return 0.0;
};

$checkoutCashAmount = $extractAmount($payment_status_cash);
$checkoutGcashAmount = $extractAmount($payment_status_g_cash);
$checkoutMayaAmount = $extractAmount($payment_status_maya);
$checkoutInstapayAmount = $extractAmount($payment_status_instapay);
$checkoutOnlineBankingAmount = $extractAmount($payment_status_online_banking);
$checkoutAirbnbAmount = $extractAmount($payment_status_airbnb);

// Get new deposit values from POST
$new_deposit = floatval($_POST['deposit'] ?? 0);
$new_deposit_cash = floatval($_POST['deposit_cash'] ?? 0);
$new_deposit_g_cash = floatval($_POST['deposit_g_cash'] ?? 0);
$new_deposit_maya = floatval($_POST['deposit_maya'] ?? 0);
$new_deposit_instapay = floatval($_POST['deposit_instapay'] ?? 0);
$new_deposit_online_banking = floatval($_POST['deposit_online_banking'] ?? 0);
$new_deposit_airbnb = floatval($_POST['deposit_airbnb'] ?? 0);
$new_deposit_details = trim($_POST['deposit_details'] ?? '');
$new_deposit_gcash_ref = trim($_POST['deposit_gcash_ref'] ?? '');
$new_deposit_maya_ref = trim($_POST['deposit_maya_ref'] ?? '');

// Keep Instapay/Online Banking/Airbnb labels separate in payment_status_* columns.
// We still roll amounts into deposit_cash for historical compatibility.
$new_deposit_cash += ($new_deposit_instapay + $new_deposit_online_banking + $new_deposit_airbnb);

// These represent the incremental payment amounts for THIS payment event (after change deduction on the frontend).
// We append them into reports.payment_amount_*_history aligned with payment_date_time.
$payment_event_cash = max(0, $new_deposit_cash);
$payment_event_g_cash = max(0, $new_deposit_g_cash);
$payment_event_maya = max(0, $new_deposit_maya);
$payment_event_instapay = max(0, $new_deposit_instapay);
$payment_event_online_banking = max(0, $new_deposit_online_banking);
$payment_event_airbnb = max(0, $new_deposit_airbnb);

// Safety fallback: if the frontend didn't send deposit_* correctly (e.g. deposit=0
// but checkout amount is present in payment_status_cash), still record the event
// using the parsed checkout amount.
// Fetch booking details to get downpayment info to adjust safety fallback
$dpCash = 0;
$dpGcash = 0;
$dpMaya = 0;
$dpInstapay = 0;
$dpOnlineBanking = 0;
$dpAirbnb = 0;
$isRes = false;
try {
    $bkStmt = $conn->prepare("SELECT booking_type, status, downpayment_cash, downpayment_gcash, downpayment_maya, downpayment_instapay, downpayment_online_banking, downpayment_airbnb FROM bookings WHERE id = :id LIMIT 1");
    $bkStmt->execute([':id' => $booking_id]);
    $bkRow = $bkStmt->fetch(PDO::FETCH_ASSOC);
    if ($bkRow) {
        $isRes = strcasecmp(trim((string) ($bkRow['booking_type'] ?? '')), 'Reservation') === 0 
                 || strcasecmp(trim((string) ($bkRow['status'] ?? '')), 'Reserved') === 0;
        $dpCash = floatval($bkRow['downpayment_cash'] ?? 0);
        $dpGcash = floatval($bkRow['downpayment_gcash'] ?? 0);
        $dpMaya = floatval($bkRow['downpayment_maya'] ?? 0);
        $dpInstapay = floatval($bkRow['downpayment_instapay'] ?? 0);
        $dpOnlineBanking = floatval($bkRow['downpayment_online_banking'] ?? 0);
        $dpAirbnb = floatval($bkRow['downpayment_airbnb'] ?? 0);
    }
} catch (Exception $e) {
    error_log("Failed to fetch downpayment info for safety fallback in update_payment_status.php: " . $e->getMessage());
}

if ($payment_event_cash <= 0.00001 && isset($checkoutCashAmount) && floatval($checkoutCashAmount) > 0) {
    $val = floatval($checkoutCashAmount);
    $payment_event_cash = $isRes ? max(0.0, $val - $dpCash) : $val;
}
if ($payment_event_g_cash <= 0.00001 && isset($checkoutGcashAmount) && floatval($checkoutGcashAmount) > 0) {
    $val = floatval($checkoutGcashAmount);
    $payment_event_g_cash = $isRes ? max(0.0, $val - $dpGcash) : $val;
}
if ($payment_event_maya <= 0.00001 && isset($checkoutMayaAmount) && floatval($checkoutMayaAmount) > 0) {
    $val = floatval($checkoutMayaAmount);
    $payment_event_maya = $isRes ? max(0.0, $val - $dpMaya) : $val;
}
if ($payment_event_instapay <= 0.00001 && $checkoutInstapayAmount > 0) {
    $val = floatval($checkoutInstapayAmount);
    $payment_event_instapay = $isRes ? max(0.0, $val - $dpInstapay) : $val;
}
if ($payment_event_online_banking <= 0.00001 && $checkoutOnlineBankingAmount > 0) {
    $val = floatval($checkoutOnlineBankingAmount);
    $payment_event_online_banking = $isRes ? max(0.0, $val - $dpOnlineBanking) : $val;
}
if ($payment_event_airbnb <= 0.00001 && $checkoutAirbnbAmount > 0) {
    $val = floatval($checkoutAirbnbAmount);
    $payment_event_airbnb = $isRes ? max(0.0, $val - $dpAirbnb) : $val;
}

// ADD new deposit to existing deposit
$deposit = $existing_deposit_total + $new_deposit;
$deposit_cash = $existing_deposit_cash + $new_deposit_cash;
$deposit_g_cash = $existing_deposit_g_cash + $new_deposit_g_cash;
$deposit_maya = $existing_deposit_maya + $new_deposit_maya;

// CRITICAL FIX: Rebuild deposit_details from actual amounts instead of concatenating strings
// This prevents issues like "11,529.05" instead of "1,529.05"
$deposit_details_parts = [];
if ($deposit_cash > 0) {
    $cashLabel = 'Cash';
    if (stripos($payment_status_cash, 'Instapay') !== false || stripos($payment_status, 'Instapay') !== false) {
        $cashLabel = 'Instapay';
    } elseif (stripos($payment_status_cash, 'Online Banking') !== false || stripos($payment_status, 'Online Banking') !== false) {
        $cashLabel = 'Online Banking';
    } elseif (stripos($payment_status_cash, 'Airbnb') !== false || stripos($payment_status, 'Airbnb') !== false) {
        $cashLabel = 'Airbnb';
    }
    $deposit_details_parts[] = number_format($deposit_cash, 2) . ' ' . $cashLabel;
}
if ($deposit_g_cash > 0) {
    $deposit_details_parts[] = number_format($deposit_g_cash, 2) . ' G-cash';
}
if ($deposit_maya > 0) {
    $deposit_details_parts[] = number_format($deposit_maya, 2) . ' Maya';
}
$deposit_details = implode(', ', $deposit_details_parts);

error_log("=== DEPOSIT DETAILS REBUILD ===");
error_log("deposit_cash: " . $deposit_cash);
error_log("deposit_g_cash: " . $deposit_g_cash);
error_log("deposit_maya: " . $deposit_maya);
error_log("Rebuilt deposit_details: " . $deposit_details);
error_log("=== END ===");

// Merge reference numbers
$deposit_gcash_ref_parts = [];
if ($existing_deposit_gcash_ref) {
    $deposit_gcash_ref_parts[] = $existing_deposit_gcash_ref;
}
if ($new_deposit_gcash_ref) {
    $deposit_gcash_ref_parts[] = $new_deposit_gcash_ref;
}
$deposit_gcash_ref = implode(', ', $deposit_gcash_ref_parts);

$deposit_maya_ref_parts = [];
if ($existing_deposit_maya_ref) {
    $deposit_maya_ref_parts[] = $existing_deposit_maya_ref;
}
if ($new_deposit_maya_ref) {
    $deposit_maya_ref_parts[] = $new_deposit_maya_ref;
}
$deposit_maya_ref = implode(', ', $deposit_maya_ref_parts);

// Convert empty strings to NULL for database
if ($deposit_gcash_ref === '')
    $deposit_gcash_ref = null;
if ($deposit_maya_ref === '')
    $deposit_maya_ref = null;
if ($deposit_details === '')
    $deposit_details = null;

error_log("=== UPDATE_PAYMENT_STATUS DEPOSIT DEBUG ===");
error_log("existing_deposit_total: " . $existing_deposit_total);
error_log("new_deposit: " . $new_deposit);
error_log("FINAL deposit (existing + new): " . $deposit);
error_log("existing_deposit_cash: " . $existing_deposit_cash);
error_log("new_deposit_cash: " . $new_deposit_cash);
error_log("FINAL deposit_cash: " . $deposit_cash);
error_log("existing_deposit_g_cash: " . $existing_deposit_g_cash);
error_log("new_deposit_g_cash: " . $new_deposit_g_cash);
error_log("FINAL deposit_g_cash: " . $deposit_g_cash);
error_log("existing_deposit_maya: " . $existing_deposit_maya);
error_log("new_deposit_maya: " . $new_deposit_maya);
error_log("FINAL deposit_maya: " . $deposit_maya);
error_log("FINAL deposit_details: " . ($deposit_details ?? 'NULL'));
error_log("FINAL deposit_gcash_ref: " . ($deposit_gcash_ref ?? 'NULL'));
error_log("FINAL deposit_maya_ref: " . ($deposit_maya_ref ?? 'NULL'));
error_log("deposit_maya: " . $deposit_maya);
error_log("deposit_details: " . ($deposit_details ?? 'NULL'));
error_log("deposit_gcash_ref: " . ($deposit_gcash_ref ?? 'NULL'));
error_log("deposit_maya_ref: " . ($deposit_maya_ref ?? 'NULL'));

// Handle reference numbers - can be single value or JSON array
$reference_no = null;

// Process inventory deduction if missing items are being paid for
if ($inventory_deduct_list !== '' && $inventory_deduct_list !== '[]') {
    $deductItems = json_decode($inventory_deduct_list, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($deductItems) && !empty($deductItems)) {
        // Use updated helper function to deduct from inventory
        error_log("Processing inventory deduction for missing items: " . print_r($deductItems, true));
        $deducted = process_missing_items_inventory($conn, $deductItems);
        error_log("Inventory deduction result: " . print_r($deducted, true));
    }
}

// Priority 1: If reference_numbers_json is provided and valid, use it (for multiple refs from edit modal)
if ($reference_numbers_json !== '' && $reference_numbers_json !== '[]') {
    $decoded = json_decode($reference_numbers_json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $filtered = array_filter($decoded, function ($v) {
            return trim($v) !== ''; });
        if (!empty($filtered)) {
            $reference_no = json_encode(array_values($filtered));
        }
    }
}

// Also include method-specific references from paymentOptionsModal payload.
$methodRefs = array_filter([
    $reference_no_g_cash,
    $reference_no_maya,
    $reference_no_instapay,
    $reference_no_online_banking,
    $reference_no_airbnb
], function ($v) {
    return trim((string) $v) !== '';
});

if (!empty($methodRefs)) {
    $existingRefs = [];
    if ($reference_no !== null && trim((string) $reference_no) !== '') {
        $decoded = json_decode($reference_no, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $existingRefs = $decoded;
        } else {
            $existingRefs = array_filter(explode(',', (string) $reference_no), function ($v) {
                return trim((string) $v) !== '';
            });
        }
    }
    foreach ($methodRefs as $ref) {
        if (!in_array($ref, $existingRefs, true)) {
            $existingRefs[] = $ref;
        }
    }
    $reference_no = json_encode(array_values($existingRefs));
}

// Priority 2: If we have a single reference_no_input, merge with existing or create new
if ($reference_no_input !== '') {
    // Get existing reference numbers from database
    $getExistingStmt = $conn->prepare("SELECT reference_no FROM bookings WHERE id = :id");
    $getExistingStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
    $getExistingStmt->execute();
    $existingRow = $getExistingStmt->fetch(PDO::FETCH_ASSOC);
    $existingRefs = [];

    if ($existingRow && !empty($existingRow['reference_no'])) {
        try {
            $decoded = json_decode($existingRow['reference_no'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $existingRefs = array_filter($decoded, function ($v) {
                    return trim($v) !== ''; });
            } else {
                // If not JSON, treat as single value or comma-separated
                $existingRefs = array_filter(explode(',', $existingRow['reference_no']), function ($v) {
                    return trim($v) !== ''; });
            }
        } catch (Exception $e) {
            $existingRefs = [];
        }
    }

    // Add new reference number if not already in list
    if (!in_array($reference_no_input, $existingRefs)) {
        $existingRefs[] = $reference_no_input;
    }

    // Convert to JSON array
    $reference_no = json_encode(array_values($existingRefs));
}

// Debug logging
error_log("update_payment_status.php - booking_id: $booking_id, payment_status: '$payment_status'");
error_log("update_payment_status.php - reference_no_g_cash: '$reference_no_g_cash', reference_no_maya: '$reference_no_maya'");
error_log("update_payment_status.php - reference_no_input: '$reference_no_input', reference_numbers_json: '$reference_numbers_json', final reference_no: " . ($reference_no ?? 'NULL'));
error_log("update_payment_status.php - missing_items_fees: $missing_items_fees, missing_items_list: '$missing_items_list'");

$allowedStatuses = ['Unpaid', 'Pending', 'Paid'];
if (!in_array($paid_status, $allowedStatuses, true)) {
    $paid_status = 'Pending';
}

if ($booking_id <= 0) {
    $response['message'] = 'Booking ID is required!';
    echo json_encode($response);
    exit;
}

ensureReportFinancialColumns($conn);

try {
    // Ensure reference_no column exists in bookings table with TEXT type
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'reference_no'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN reference_no TEXT NULL DEFAULT NULL AFTER payment_status");
            error_log("Created reference_no column in bookings table");
        } else {
            // Check if it's VARCHAR and change to TEXT
            $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
            if (isset($columnInfo['Type']) && stripos($columnInfo['Type'], 'varchar') !== false) {
                $conn->exec("ALTER TABLE bookings MODIFY COLUMN reference_no TEXT NULL DEFAULT NULL");
                error_log("Modified reference_no column from VARCHAR to TEXT");
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add reference_no column: " . $e->getMessage());
    }

    // Ensure reference_no_g_cash column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'reference_no_g_cash'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN reference_no_g_cash TEXT NULL DEFAULT NULL AFTER payment_status");
            error_log("Created reference_no_g_cash column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add reference_no_g_cash column: " . $e->getMessage());
    }

    // Ensure reference_no_maya column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'reference_no_maya'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN reference_no_maya TEXT NULL DEFAULT NULL AFTER reference_no_g_cash");
            error_log("Created reference_no_maya column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add reference_no_maya column: " . $e->getMessage());
    }

    // Ensure payment_status_g_cash column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'payment_status_g_cash'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN payment_status_g_cash TEXT NULL DEFAULT NULL AFTER payment_status");
            error_log("Created payment_status_g_cash column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add payment_status_g_cash column: " . $e->getMessage());
    }

    // Ensure payment_status_maya column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'payment_status_maya'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN payment_status_maya TEXT NULL DEFAULT NULL AFTER payment_status_g_cash");
            error_log("Created payment_status_maya column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add payment_status_maya column: " . $e->getMessage());
    }

    // Ensure payment_status_cash column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'payment_status_cash'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN payment_status_cash TEXT NULL DEFAULT NULL AFTER payment_status_maya");
            error_log("Created payment_status_cash column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add payment_status_cash column: " . $e->getMessage());
    }

    // Ensure missing_items_fees column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'missing_items_fees'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN missing_items_fees DECIMAL(10,2) DEFAULT 0");
            error_log("Created missing_items_fees column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add missing_items_fees column: " . $e->getMessage());
    }

    // Ensure missing_items_list column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'missing_items_list'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN missing_items_list TEXT NULL DEFAULT NULL");
            error_log("Created missing_items_list column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add missing_items_list column: " . $e->getMessage());
    }

    // Ensure penalty_amount column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'penalty_amount'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN penalty_amount DECIMAL(10,2) DEFAULT 0.00");
            error_log("Created penalty_amount column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add penalty_amount column: " . $e->getMessage());
    }

    // Ensure penalty_list column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'penalty_list'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN penalty_list TEXT NULL DEFAULT NULL");
            error_log("Created penalty_list column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add penalty_list column: " . $e->getMessage());
    }

    // Ensure change_amount column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'change_amount'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN change_amount DECIMAL(10,2) DEFAULT 0.00");
            error_log("Created change_amount column in bookings table");
        }
    } catch (PDOException $e) {
        error_log("Failed to check/add change_amount column: " . $e->getMessage());
    }

    // Debug: Log what we're trying to save
    error_log("update_payment_status.php - booking_id: $booking_id, payment_status: '$payment_status'");
    error_log("update_payment_status.php - reference_no_g_cash: '$reference_no_g_cash', reference_no_maya: '$reference_no_maya'");
    error_log("update_payment_status.php - reference_no_input: '$reference_no_input', reference_numbers_json: '$reference_numbers_json', final reference_no: " . ($reference_no ?? 'NULL'));

    // Determine the booking status based on paid_status
    $booking_status = ($paid_status === 'Paid') ? 'Confirmed' : null;

    // CRITICAL: Check if this is a payment for additional items only
    // If the booking is already Paid but we're paying for additional items, update additional_paid_status
    $getBookingStmt = $conn->prepare("SELECT paid_status, additional_paid_status FROM bookings WHERE id = :booking_id");
    $getBookingStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
    $getBookingStmt->execute();
    $currentBooking = $getBookingStmt->fetch(PDO::FETCH_ASSOC);

    $isPayingForAdditionalItems = false;
    if ($currentBooking && $currentBooking['paid_status'] === 'Paid' && $currentBooking['additional_paid_status'] === 'Unpaid') {
        // This is a payment for additional items only
        $isPayingForAdditionalItems = true;
        error_log("=== PAYING FOR ADDITIONAL ITEMS ===");
        error_log("Current paid_status: " . $currentBooking['paid_status']);
        error_log("Current additional_paid_status: " . $currentBooking['additional_paid_status']);
    }

    // Update paid_status and status (if paid_status is 'Paid', set status to 'Confirmed')
    // We store payment details in payment_status column when payment is confirmed
    // CRITICAL FIX: Add deposit fields and additional_paid_status to UPDATE query
    // payment_date_time must accumulate timestamps (pipe-separated) so reports can detect
    // a second payment (e.g. extension paid via checkout "Proceed to Payment"). The old
    // COALESCE(...) only ever stored the first payment time, which hid extension revenue rows.
    $paymentDateTimeQuery = '';
    $paymentDateTimeValue = null;
    $shouldAppendPaymentHistory = false;
    if ($paid_status === 'Paid') {
        $getPtStmt = $conn->prepare('SELECT payment_date_time FROM bookings WHERE id = :booking_id LIMIT 1');
        $getPtStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
        $getPtStmt->execute();
        $payDtRow = $getPtStmt->fetch(PDO::FETCH_ASSOC);
        $existing_pt = trim((string) ($payDtRow['payment_date_time'] ?? ''));
        if ($existing_pt === '0000-00-00 00:00:00') {
            $existing_pt = '';
        }
        $has_existing_pt = $existing_pt !== '';
        $ts = date('Y-m-d H:i:s');
        $newDepositTotalForEvent = floatval($new_deposit_cash) + floatval($new_deposit_g_cash) + floatval($new_deposit_maya) + floatval($new_deposit_instapay) + floatval($new_deposit_online_banking) + floatval($new_deposit_airbnb);
        if (!$has_existing_pt) {
            $paymentDateTimeValue = $ts;
            $paymentDateTimeQuery = ', payment_date_time = :payment_date_time';
            // First ever payment timestamp for this booking.
            // Record payment history row even if frontend amount is 0 (edge cases like free promos).
            $shouldAppendPaymentHistory = true;
        } elseif ($newDepositTotalForEvent > 0.00001) {
            $paymentDateTimeValue = appendPaymentTimestamp($existing_pt, $ts);
            $paymentDateTimeQuery = ', payment_date_time = :payment_date_time';
            $shouldAppendPaymentHistory = true;
        }
    }

    if ($booking_status !== null) {
        $stmt = $conn->prepare("UPDATE bookings SET paid_status = :paid_status, status = :status, payment_status = :payment_status, payment_status_g_cash = :payment_status_g_cash, payment_status_maya = :payment_status_maya, payment_status_cash = :payment_status_cash, payment_status_instapay = :payment_status_instapay, payment_status_online_banking = :payment_status_online_banking, payment_status_airbnb = :payment_status_airbnb, reference_no = :reference_no, reference_no_g_cash = :reference_no_g_cash, reference_no_maya = :reference_no_maya, reference_no_instapay = :reference_no_instapay, reference_no_online_banking = :reference_no_online_banking, reference_no_airbnb = :reference_no_airbnb, missing_items_fees = :missing_items_fees, missing_items_list = :missing_items_list, penalty_amount = :penalty_amount, penalty_list = :penalty_list, change_amount = :change_amount, deposit = :deposit, deposit_cash = :deposit_cash, deposit_g_cash = :deposit_g_cash, deposit_maya = :deposit_maya, deposit_details = :deposit_details, deposit_gcash_ref = :deposit_gcash_ref, deposit_maya_ref = :deposit_maya_ref, additional_paid_status = :additional_paid_status {$paymentDateTimeQuery} WHERE id = :id");
        $stmt->bindParam(':paid_status', $paid_status);
        $stmt->bindParam(':status', $booking_status);
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET paid_status = :paid_status, payment_status = :payment_status, payment_status_g_cash = :payment_status_g_cash, payment_status_maya = :payment_status_maya, payment_status_cash = :payment_status_cash, payment_status_instapay = :payment_status_instapay, payment_status_online_banking = :payment_status_online_banking, payment_status_airbnb = :payment_status_airbnb, reference_no = :reference_no, reference_no_g_cash = :reference_no_g_cash, reference_no_maya = :reference_no_maya, reference_no_instapay = :reference_no_instapay, reference_no_online_banking = :reference_no_online_banking, reference_no_airbnb = :reference_no_airbnb, missing_items_fees = :missing_items_fees, missing_items_list = :missing_items_list, penalty_amount = :penalty_amount, penalty_list = :penalty_list, change_amount = :change_amount, deposit = :deposit, deposit_cash = :deposit_cash, deposit_g_cash = :deposit_g_cash, deposit_maya = :deposit_maya, deposit_details = :deposit_details, deposit_gcash_ref = :deposit_gcash_ref, deposit_maya_ref = :deposit_maya_ref, additional_paid_status = :additional_paid_status {$paymentDateTimeQuery} WHERE id = :id");
        $stmt->bindParam(':paid_status', $paid_status);
    }

    // Bind additional_paid_status
    // If paying for additional items, set to 'Paid'
    // If this is the first payment and paid_status is 'Paid', also set additional_paid_status to 'Paid'
    // Otherwise, keep current value or set to 'None'
    if ($isPayingForAdditionalItems) {
        $additional_paid_status_value = 'Paid';
    } elseif ($paid_status === 'Paid') {
        // First payment - set additional_paid_status to 'Paid' as well
        $additional_paid_status_value = 'Paid';
    } else {
        // Keep current value
        $additional_paid_status_value = $currentBooking['additional_paid_status'] ?? 'None';
    }
    $stmt->bindParam(':additional_paid_status', $additional_paid_status_value);

    // Bind payment_status (contains payment details like "Cash (₱1000.00), G-Cash (₱1000.00)")
    if ($payment_status === '') {
        $stmt->bindValue(':payment_status', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':payment_status', $payment_status);
    }

    // Bind payment_status_g_cash
    if ($payment_status_g_cash === '') {
        $stmt->bindValue(':payment_status_g_cash', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':payment_status_g_cash', $payment_status_g_cash);
    }

    // Bind payment_status_maya
    if ($payment_status_maya === '') {
        $stmt->bindValue(':payment_status_maya', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':payment_status_maya', $payment_status_maya);
    }

    // Bind payment_status_cash
    if ($payment_status_cash === '') {
        $stmt->bindValue(':payment_status_cash', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':payment_status_cash', $payment_status_cash);
    }

    // Bind payment_status_instapay
    if ($payment_status_instapay === '') {
        $stmt->bindValue(':payment_status_instapay', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':payment_status_instapay', $payment_status_instapay);
    }

    // Bind payment_status_online_banking
    if ($payment_status_online_banking === '') {
        $stmt->bindValue(':payment_status_online_banking', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':payment_status_online_banking', $payment_status_online_banking);
    }

    // Bind payment_status_airbnb
    if ($payment_status_airbnb === '') {
        $stmt->bindValue(':payment_status_airbnb', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':payment_status_airbnb', $payment_status_airbnb);
    }

    // If reference_no is null, bind as null, otherwise bind as string
    if ($reference_no === null) {
        $stmt->bindValue(':reference_no', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':reference_no', $reference_no);
    }

    // Bind reference_no_g_cash
    if ($reference_no_g_cash === '') {
        $stmt->bindValue(':reference_no_g_cash', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':reference_no_g_cash', $reference_no_g_cash);
    }

    // Bind reference_no_maya
    if ($reference_no_maya === '') {
        $stmt->bindValue(':reference_no_maya', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':reference_no_maya', $reference_no_maya);
    }

    // Bind reference_no_instapay
    if ($reference_no_instapay === '') {
        $stmt->bindValue(':reference_no_instapay', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':reference_no_instapay', $reference_no_instapay);
    }

    // Bind reference_no_online_banking
    if ($reference_no_online_banking === '') {
        $stmt->bindValue(':reference_no_online_banking', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':reference_no_online_banking', $reference_no_online_banking);
    }

    // Bind reference_no_airbnb
    if ($reference_no_airbnb === '') {
        $stmt->bindValue(':reference_no_airbnb', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':reference_no_airbnb', $reference_no_airbnb);
    }

    // Bind missing items parameters
    $stmt->bindParam(':missing_items_fees', $missing_items_fees);
    if ($missing_items_list === '' || $missing_items_list === '[]') {
        $stmt->bindValue(':missing_items_list', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':missing_items_list', $missing_items_list);
    }

    // Bind penalty parameters
    $stmt->bindParam(':penalty_amount', $penalty_amount);
    if ($penalty_list === '' || $penalty_list === '[]') {
        $stmt->bindValue(':penalty_list', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':penalty_list', $penalty_list);
    }
    $stmt->bindParam(':change_amount', $change_amount);

    // CRITICAL FIX: Bind deposit parameters
    $stmt->bindParam(':deposit', $deposit);
    $stmt->bindParam(':deposit_cash', $deposit_cash);
    $stmt->bindParam(':deposit_g_cash', $deposit_g_cash);
    $stmt->bindParam(':deposit_maya', $deposit_maya);
    if ($deposit_details === null || $deposit_details === '') {
        $stmt->bindValue(':deposit_details', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':deposit_details', $deposit_details);
    }
    if ($deposit_gcash_ref === null || $deposit_gcash_ref === '') {
        $stmt->bindValue(':deposit_gcash_ref', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':deposit_gcash_ref', $deposit_gcash_ref);
    }
    if ($deposit_maya_ref === null || $deposit_maya_ref === '') {
        $stmt->bindValue(':deposit_maya_ref', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':deposit_maya_ref', $deposit_maya_ref);
    }

    if ($paymentDateTimeValue !== null) {
        $stmt->bindValue(':payment_date_time', $paymentDateTimeValue);
    }

    $stmt->bindParam(':id', $booking_id, PDO::PARAM_INT);

    if ($stmt->execute()) {

        // -----------------------------------------------------------------------
        // SYNC PAYMENT HISTORY INTO BOOKINGS TABLE
        // This is critical for the paymentOptionsModal flow: when confirmPaymentOptions()
        // is called, the booking does NOT yet have a row in the reports table (checkout
        // hasn't happened). The UPDATE reports below therefore hits 0 rows. By storing
        // the history in bookings, checkout_booking.php can copy it to reports later.
        // -----------------------------------------------------------------------
        if ($shouldAppendPaymentHistory) {
            try {
                // Ensure history columns exist in bookings table
                foreach (['payment_amount_cash_history', 'payment_amount_g_cash_history', 'payment_amount_maya_history', 'payment_amount_instapay_history', 'payment_amount_online_banking_history', 'payment_amount_airbnb_history'] as $hCol) {
                    try {
                        $chk = $conn->query("SHOW COLUMNS FROM bookings LIKE '" . $hCol . "'");
                        if ($chk && $chk->rowCount() == 0) {
                            $conn->exec("ALTER TABLE bookings ADD COLUMN {$hCol} TEXT NULL DEFAULT NULL");
                        }
                    } catch (Exception $e) {
                    }
                }

                // Fetch existing history from bookings
                $bHistStmt = $conn->prepare("SELECT payment_amount_cash_history, payment_amount_g_cash_history, payment_amount_maya_history, payment_amount_instapay_history, payment_amount_online_banking_history, payment_amount_airbnb_history FROM bookings WHERE id = :id LIMIT 1");
                $bHistStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
                $bHistStmt->execute();
                $bHistRow = $bHistStmt->fetch(PDO::FETCH_ASSOC);

                $bCashHist = trim((string) ($bHistRow['payment_amount_cash_history'] ?? ''));
                $bGCashHist = trim((string) ($bHistRow['payment_amount_g_cash_history'] ?? ''));
                $bMayaHist = trim((string) ($bHistRow['payment_amount_maya_history'] ?? ''));
                $bInstapayHist = trim((string) ($bHistRow['payment_amount_instapay_history'] ?? ''));
                $bOnlineBankingHist = trim((string) ($bHistRow['payment_amount_online_banking_history'] ?? ''));
                $bAirbnbHist = trim((string) ($bHistRow['payment_amount_airbnb_history'] ?? ''));

                $newCashEntry = number_format($payment_event_cash, 2, '.', '');
                $newGCashEntry = number_format($payment_event_g_cash, 2, '.', '');
                $newMayaEntry = number_format($payment_event_maya, 2, '.', '');
                $newInstapayEntry = number_format($payment_event_instapay ?? 0, 2, '.', '');
                $newOnlineBankingEntry = number_format($payment_event_online_banking ?? 0, 2, '.', '');
                $newAirbnbEntry = number_format($payment_event_airbnb ?? 0, 2, '.', '');

                $bCashHist = $bCashHist !== '' ? ($bCashHist . '|' . $newCashEntry) : $newCashEntry;
                $bGCashHist = $bGCashHist !== '' ? ($bGCashHist . '|' . $newGCashEntry) : $newGCashEntry;
                $bMayaHist = $bMayaHist !== '' ? ($bMayaHist . '|' . $newMayaEntry) : $newMayaEntry;
                $bInstapayHist = $bInstapayHist !== '' ? ($bInstapayHist . '|' . $newInstapayEntry) : $newInstapayEntry;
                $bOnlineBankingHist = $bOnlineBankingHist !== '' ? ($bOnlineBankingHist . '|' . $newOnlineBankingEntry) : $newOnlineBankingEntry;
                $bAirbnbHist = $bAirbnbHist !== '' ? ($bAirbnbHist . '|' . $newAirbnbEntry) : $newAirbnbEntry;

                $bHistUpdateStmt = $conn->prepare("UPDATE bookings SET payment_amount_cash_history = :cash_hist, payment_amount_g_cash_history = :gcash_hist, payment_amount_maya_history = :maya_hist, payment_amount_instapay_history = :instapay_hist, payment_amount_online_banking_history = :online_banking_hist, payment_amount_airbnb_history = :airbnb_hist WHERE id = :id");
                $bHistUpdateStmt->bindParam(':cash_hist', $bCashHist);
                $bHistUpdateStmt->bindParam(':gcash_hist', $bGCashHist);
                $bHistUpdateStmt->bindParam(':maya_hist', $bMayaHist);
                $bHistUpdateStmt->bindParam(':instapay_hist', $bInstapayHist);
                $bHistUpdateStmt->bindParam(':online_banking_hist', $bOnlineBankingHist);
                $bHistUpdateStmt->bindParam(':airbnb_hist', $bAirbnbHist);
                $bHistUpdateStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
                $bHistUpdateStmt->execute();

                error_log("=== PAYMENT HISTORY SAVED TO BOOKINGS TABLE ===");
                error_log("booking id: {$booking_id} | cash: {$bCashHist} | gcash: {$bGCashHist} | maya: {$bMayaHist}");
            } catch (Exception $e) {
                error_log("Failed to save payment history to bookings: " . $e->getMessage());
            }
        }

        // Verify the update worked
        $verifyStmt = $conn->prepare("SELECT reference_no FROM bookings WHERE id = :id");
        $verifyStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        $savedValue = $verifyResult['reference_no'] ?? 'NULL';
        error_log("Verified reference_no after update for booking_id $booking_id: " . $savedValue);

        // Also update response with saved value for debugging
        $response['debug'] = [
            'reference_no_saved' => $savedValue,
            'reference_no_input' => $reference_no_input,
            'reference_numbers_json' => $reference_numbers_json
        ];
        try {
            $codeStmt = $conn->prepare("SELECT booking_id FROM bookings WHERE id = :id LIMIT 1");
            $codeStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
            $codeStmt->execute();
            $bookingRow = $codeStmt->fetch(PDO::FETCH_ASSOC);
            $bookingCode = $bookingRow['booking_id'] ?? null;

            if ($bookingCode || $booking_id > 0) {
                // Resolve the exact reports row first. Matching only by booking_id can fail for
                // some records, which causes bookings history to update while reports history does not.
                $reportId = null;
                try {
                    if ($bookingCode) {
                        $repFindStmt = $conn->prepare("SELECT id FROM reports WHERE booking_id = :booking_code LIMIT 1");
                        $repFindStmt->bindParam(':booking_code', $bookingCode);
                        $repFindStmt->execute();
                        $repFind = $repFindStmt->fetch(PDO::FETCH_ASSOC);
                        if ($repFind && isset($repFind['id'])) {
                            $reportId = intval($repFind['id']);
                        }
                    }
                    if ($reportId === null && $booking_id > 0) {
                        $repFindByIdStmt = $conn->prepare("SELECT id FROM reports WHERE id = :id LIMIT 1");
                        $repFindByIdStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
                        $repFindByIdStmt->execute();
                        $repFindById = $repFindByIdStmt->fetch(PDO::FETCH_ASSOC);
                        if ($repFindById && isset($repFindById['id'])) {
                            $reportId = intval($repFindById['id']);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Failed to resolve reports row id: " . $e->getMessage());
                }

                if ($reportId === null) {
                    error_log("No reports row found for booking_id={$booking_id}, booking_code=" . ($bookingCode ?? 'NULL'));
                    // Continue response success for booking update; reports sync is best-effort.
                }

                // Ensure reference_no column exists in reports table with TEXT type
                try {
                    $checkReportColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'reference_no'");
                    if ($checkReportColumn->rowCount() == 0) {
                        $conn->exec("ALTER TABLE reports ADD COLUMN reference_no TEXT NULL DEFAULT NULL AFTER payment_status");
                    } else {
                        // Check if it's VARCHAR and change to TEXT
                        $columnInfo = $checkReportColumn->fetch(PDO::FETCH_ASSOC);
                        if (isset($columnInfo['Type']) && stripos($columnInfo['Type'], 'varchar') !== false) {
                            $conn->exec("ALTER TABLE reports MODIFY COLUMN reference_no TEXT NULL DEFAULT NULL");
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Failed to check/add reference_no column in reports: " . $e->getMessage());
                }

                // Ensure change_amount column exists in reports
                try {
                    $checkReportColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'change_amount'");
                    if ($checkReportColumn->rowCount() == 0) {
                        $conn->exec("ALTER TABLE reports ADD COLUMN change_amount DECIMAL(10,2) DEFAULT 0.00");
                    }
                } catch (PDOException $e) {
                    error_log("Failed to check/add change_amount column in reports: " . $e->getMessage());
                }

                // Ensure per-payment amount history columns exist in reports + bookings
                try {
                    $histCols = [
                        'payment_amount_cash_history',
                        'payment_amount_g_cash_history',
                        'payment_amount_maya_history',
                        'payment_amount_instapay_history',
                        'payment_amount_online_banking_history',
                        'payment_amount_airbnb_history'
                    ];
                    foreach ($histCols as $colName) {
                        $chk = $conn->query("SHOW COLUMNS FROM reports LIKE '" . $colName . "'");
                        if ($chk && $chk->rowCount() == 0) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
                        }
                        $chkBookings = $conn->query("SHOW COLUMNS FROM bookings LIKE '" . $colName . "'");
                        if ($chkBookings && $chkBookings->rowCount() == 0) {
                            $conn->exec("ALTER TABLE bookings ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Failed to ensure payment history columns in reports: " . $e->getMessage());
                }

                if ($reportId !== null) {
                    $reportStmt = $conn->prepare("
                        UPDATE reports 
                        SET paid_status = :paid_status, 
                            reference_no = :reference_no,
                            payment_status = :payment_status,
                            payment_status_g_cash = :payment_status_g_cash,
                            payment_status_cash = :payment_status_cash,
                            payment_status_maya = :payment_status_maya,
                            reference_no_g_cash = :reference_no_g_cash,
                            reference_no_maya = :reference_no_maya,
                            change_amount = :change_amount,
                            payment_date_time = (SELECT payment_date_time FROM bookings WHERE id = :booking_id LIMIT 1)
                        WHERE id = :report_id
                    ");
                    $reportStmt->bindParam(':paid_status', $paid_status);
                    $reportStmt->bindParam(':reference_no', $reference_no);
                    $reportStmt->bindParam(':payment_status', $payment_status);

                    // Bind new payment status columns for reports sync
                    if ($payment_status_g_cash === '') {
                        $reportStmt->bindValue(':payment_status_g_cash', null, PDO::PARAM_NULL);
                    } else {
                        $reportStmt->bindParam(':payment_status_g_cash', $payment_status_g_cash);
                    }

                    if ($payment_status_cash === '') {
                        $reportStmt->bindValue(':payment_status_cash', null, PDO::PARAM_NULL);
                    } else {
                        $reportStmt->bindParam(':payment_status_cash', $payment_status_cash);
                    }

                    if ($payment_status_maya === '') {
                        $reportStmt->bindValue(':payment_status_maya', null, PDO::PARAM_NULL);
                    } else {
                        $reportStmt->bindParam(':payment_status_maya', $payment_status_maya);
                    }

                    if ($reference_no_g_cash === '') {
                        $reportStmt->bindValue(':reference_no_g_cash', null, PDO::PARAM_NULL);
                    } else {
                        $reportStmt->bindParam(':reference_no_g_cash', $reference_no_g_cash);
                    }

                    if ($reference_no_maya === '') {
                        $reportStmt->bindValue(':reference_no_maya', null, PDO::PARAM_NULL);
                    } else {
                        $reportStmt->bindParam(':reference_no_maya', $reference_no_maya);
                    }

                    $reportStmt->bindParam(':change_amount', $change_amount);
                    $reportStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                    $reportStmt->bindParam(':report_id', $reportId, PDO::PARAM_INT);
                    $reportStmt->execute();
                }

                // Append per-payment amount history aligned with payment_date_time.
                // This is required so exports can show:
                //  - 05:16 Cash 800
                //  - 05:17 Cash 300
                // instead of one combined 1100.
                if ($shouldAppendPaymentHistory && $reportId !== null) {
                    try {
                        $histFetchStmt = $conn->prepare("
                            SELECT
                                payment_amount_cash_history,
                                payment_amount_g_cash_history,
                                payment_amount_maya_history,
                                payment_amount_instapay_history,
                                payment_amount_online_banking_history,
                                payment_amount_airbnb_history
                            FROM reports
                            WHERE id = :report_id
                            LIMIT 1
                        ");
                        $histFetchStmt->bindParam(':report_id', $reportId, PDO::PARAM_INT);
                        $histFetchStmt->execute();
                        $histRow = $histFetchStmt->fetch(PDO::FETCH_ASSOC);

                        $existingCashHist = trim((string) ($histRow['payment_amount_cash_history'] ?? ''));
                        $existingGCashHist = trim((string) ($histRow['payment_amount_g_cash_history'] ?? ''));
                        $existingMayaHist = trim((string) ($histRow['payment_amount_maya_history'] ?? ''));
                        $existingInstapayHist = trim((string) ($histRow['payment_amount_instapay_history'] ?? ''));
                        $existingOnlineBankingHist = trim((string) ($histRow['payment_amount_online_banking_history'] ?? ''));
                        $existingAirbnbHist = trim((string) ($histRow['payment_amount_airbnb_history'] ?? ''));

                        $newCashEntry = number_format($payment_event_cash, 2, '.', '');
                        $newGCashEntry = number_format($payment_event_g_cash, 2, '.', '');
                        $newMayaEntry = number_format($payment_event_maya, 2, '.', '');
                        $newInstapayEntry = number_format($payment_event_instapay ?? 0, 2, '.', '');
                        $newOnlineBankingEntry = number_format($payment_event_online_banking ?? 0, 2, '.', '');
                        $newAirbnbEntry = number_format($payment_event_airbnb ?? 0, 2, '.', '');

                        $updatedCashHist = $existingCashHist !== '' ? ($existingCashHist . '|' . $newCashEntry) : $newCashEntry;
                        $updatedGCashHist = $existingGCashHist !== '' ? ($existingGCashHist . '|' . $newGCashEntry) : $newGCashEntry;
                        $updatedMayaHist = $existingMayaHist !== '' ? ($existingMayaHist . '|' . $newMayaEntry) : $newMayaEntry;
                        $updatedInstapayHist = $existingInstapayHist !== '' ? ($existingInstapayHist . '|' . $newInstapayEntry) : $newInstapayEntry;
                        $updatedOnlineBankingHist = $existingOnlineBankingHist !== '' ? ($existingOnlineBankingHist . '|' . $newOnlineBankingEntry) : $newOnlineBankingEntry;
                        $updatedAirbnbHist = $existingAirbnbHist !== '' ? ($existingAirbnbHist . '|' . $newAirbnbEntry) : $newAirbnbEntry;

                        // Self-heal legacy rows:
                        // If we now have 2 payment_date_time timestamps but only 1 history entry (or none),
                        // infer the first entry from deposit breakdown totals (total - lastEntry).
                        // This helps older bookings become export-splittable after the next payment.
                        $pdtStmt2 = $conn->prepare("SELECT payment_date_time, deposit_cash, deposit_g_cash, deposit_maya FROM reports WHERE id = :report_id LIMIT 1");
                        $pdtStmt2->bindParam(':report_id', $reportId, PDO::PARAM_INT);
                        $pdtStmt2->execute();
                        $pdtRow2 = $pdtStmt2->fetch(PDO::FETCH_ASSOC);
                        $pdtRaw = trim((string) ($pdtRow2['payment_date_time'] ?? ''));
                        $tsCount = $pdtRaw !== '' ? count(array_filter(array_map('trim', explode('|', $pdtRaw)))) : 0;
                        if ($tsCount === 2) {
                            $totalCash = floatval($pdtRow2['deposit_cash'] ?? 0);
                            $totalGcash = floatval($pdtRow2['deposit_g_cash'] ?? 0);
                            $totalMaya = floatval($pdtRow2['deposit_maya'] ?? 0);

                            $cashParts = array_filter(array_map('trim', explode('|', $updatedCashHist)), fn($v) => $v !== '');
                            $gcashParts = array_filter(array_map('trim', explode('|', $updatedGCashHist)), fn($v) => $v !== '');
                            $mayaParts = array_filter(array_map('trim', explode('|', $updatedMayaHist)), fn($v) => $v !== '');

                            if (count($cashParts) === 1 && $totalCash > 0) {
                                $last = floatval($cashParts[0]);
                                $updatedCashHist = number_format(max(0, $totalCash - $last), 2, '.', '') . '|' . number_format($last, 2, '.', '');
                            }
                            if (count($gcashParts) === 1 && $totalGcash > 0) {
                                $last = floatval($gcashParts[0]);
                                $updatedGCashHist = number_format(max(0, $totalGcash - $last), 2, '.', '') . '|' . number_format($last, 2, '.', '');
                            }
                            if (count($mayaParts) === 1 && $totalMaya > 0) {
                                $last = floatval($mayaParts[0]);
                                $updatedMayaHist = number_format(max(0, $totalMaya - $last), 2, '.', '') . '|' . number_format($last, 2, '.', '');
                            }
                        }

                        $histUpdateStmt = $conn->prepare("
                            UPDATE reports
                            SET payment_amount_cash_history = :cash_hist,
                                payment_amount_g_cash_history = :gcash_hist,
                                payment_amount_maya_history = :maya_hist,
                                payment_amount_instapay_history = :instapay_hist,
                                payment_amount_online_banking_history = :online_banking_hist,
                                payment_amount_airbnb_history = :airbnb_hist
                            WHERE id = :report_id
                        ");
                        $histUpdateStmt->bindParam(':cash_hist', $updatedCashHist);
                        $histUpdateStmt->bindParam(':gcash_hist', $updatedGCashHist);
                        $histUpdateStmt->bindParam(':maya_hist', $updatedMayaHist);
                        $histUpdateStmt->bindParam(':instapay_hist', $updatedInstapayHist);
                        $histUpdateStmt->bindParam(':online_banking_hist', $updatedOnlineBankingHist);
                        $histUpdateStmt->bindParam(':airbnb_hist', $updatedAirbnbHist);
                        $histUpdateStmt->bindParam(':report_id', $reportId, PDO::PARAM_INT);
                        $histUpdateStmt->execute();

                        // Keep bookings history in sync with reports history.
                        $histUpdateBookingStmt = $conn->prepare("
                            UPDATE bookings
                            SET payment_amount_cash_history = :cash_hist,
                                payment_amount_g_cash_history = :gcash_hist,
                                payment_amount_maya_history = :maya_hist,
                                payment_amount_instapay_history = :instapay_hist,
                                payment_amount_online_banking_history = :online_banking_hist,
                                payment_amount_airbnb_history = :airbnb_hist
                            WHERE booking_id = :booking_id
                        ");
                        $histUpdateBookingStmt->bindParam(':cash_hist', $updatedCashHist);
                        $histUpdateBookingStmt->bindParam(':gcash_hist', $updatedGCashHist);
                        $histUpdateBookingStmt->bindParam(':maya_hist', $updatedMayaHist);
                        $histUpdateBookingStmt->bindParam(':instapay_hist', $updatedInstapayHist);
                        $histUpdateBookingStmt->bindParam(':online_banking_hist', $updatedOnlineBankingHist);
                        $histUpdateBookingStmt->bindParam(':airbnb_hist', $updatedAirbnbHist);
                        $histUpdateBookingStmt->bindParam(':gcash_hist', $updatedGCashHist);
                        $histUpdateBookingStmt->bindParam(':maya_hist', $updatedMayaHist);
                        $histUpdateBookingStmt->bindParam(':booking_id', $bookingCode);
                        $histUpdateBookingStmt->execute();
                    } catch (Exception $e) {
                        error_log("Failed to append payment amount history (update_payment_status): " . $e->getMessage());
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Failed to sync paid status with reports: ' . $e->getMessage());
        }

        $response['success'] = true;
        if ($paid_status === 'Paid') {
            $response['message'] = 'Payment status updated to Paid! Booking status changed to Confirmed.';
        } else {
            $response['message'] = 'Payment status updated to ' . $paid_status . '.';
        }
    } else {
        $response['message'] = 'Failed to update payment status.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>
