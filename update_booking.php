<?php
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@error_reporting(0);

// Start session to read logged-in user info (encoder)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (ob_get_level() == 0) {
    ob_start();
}

require_once 'config.php';
require_once 'report_helpers.php';

// Determine encoder = full name of the currently logged-in user
$_enc_first = trim($_SESSION['first_name'] ?? '');
$_enc_last = trim($_SESSION['last_name'] ?? '');
if ($_enc_first !== '' || $_enc_last !== '') {
    $encoder = trim($_enc_first . ' ' . $_enc_last);
} else {
    $encoder = trim($_SESSION['username'] ?? 'Unknown');
}

// Merge encoder names without duplicates (case-insensitive).
// Format: "Name1 & Name2"
function mergeEncoderNames($existing, $current)
{
    $current = trim((string) $current);
    $existing = trim((string) $existing);
    if ($current === '')
        return $existing;
    if ($existing === '')
        return $current;

    $parts = preg_split('/\s*(?:&|,|\|)\s*/', $existing);
    $names = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '')
            continue;
        $key = mb_strtolower($p);
        $names[$key] = $p;
    }

    $curKey = mb_strtolower($current);
    if (!isset($names[$curKey])) {
        $names[$curKey] = $current;
    }

    return implode(' & ', array_values($names));
}

/**
 * Force additional date fields into JSON-history format.
 * Keeps NULL when there is no valid date history.
 */
function normalizeAdditionalDateHistoryForStorage($raw): ?string
{
    $dates = parseAdditionalDateHistory($raw);
    if (empty($dates)) {
        return null;
    }
    return json_encode(array_values($dates));
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

@header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CRITICAL DEBUG: Log ALL POST data to see what's being sent
    error_log("=== FULL POST DATA ===");
    error_log(print_r($_POST, true));
    error_log("=== END FULL POST DATA ===");

    // Additional Guest
    $additional_guest = isset($_POST['additional_capacity']) ? intval($_POST['additional_capacity']) : 0;
    // Additional Pet
    $additional_pet = isset($_POST['additional_pet']) ? intval($_POST['additional_pet']) : 0;
    $booking_id = $_POST['booking_id'] ?? null;
    $guest_name = trim($_POST['guest_name'] ?? '');
    $reason_for_stay = trim($_POST['reason_for_stay'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $request = trim($_POST['request'] ?? '');
    $check_in = $_POST['check_in'] ?? null;
    // NOTE: reservation_date must be preserved if not provided by the edit modal.
    $reservation_date = $_POST['reservation_date'] ?? null;
    $duration = intval($_POST['duration'] ?? 0);
    $duration_unit = $_POST['duration_unit'] ?? 'hours';
    $status = $_POST['status'] ?? 'Available';

    // CRITICAL DEBUG: Log duration values to verify what's being received
    error_log("=== DURATION UPDATE DEBUG ===");
    error_log("duration from POST: " . $duration);
    error_log("duration_unit from POST: " . $duration_unit);
    error_log("=== END DURATION DEBUG ===");

    $referral_name = trim($_POST['referral_name'] ?? '');
    $promo = trim($_POST['promo'] ?? '');
    $breakfast = trim($_POST['breakfast'] ?? '');
    $breakfast_qty = intval($_POST['breakfast_qty'] ?? 1);
    $payment_status = trim($_POST['payment_status'] ?? '');
    $additional = trim($_POST['additional'] ?? '');
    $additional_fees_items = $_POST['additional_fees_items'] ?? '[]';
    $additional_charges = $_POST['additional_charges'] ?? '[]';
    $paid_status = trim($_POST['paid_status'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $deposit = floatval($_POST['deposit'] ?? 0);
    $deposit_cash = floatval($_POST['deposit_cash'] ?? 0);
    $deposit_g_cash = floatval($_POST['deposit_g_cash'] ?? 0);
    $deposit_maya = floatval($_POST['deposit_maya'] ?? 0);
    $deposit_instapay = floatval($_POST['deposit_instapay'] ?? 0);
    $deposit_online_banking = floatval($_POST['deposit_online_banking'] ?? 0);
    $deposit_airbnb = floatval($_POST['deposit_airbnb'] ?? 0);
    $deposit_details = $_POST['deposit_details'] ?? null;
    $deposit_gcash_ref = trim($_POST['deposit_gcash_ref'] ?? '');
    $deposit_maya_ref = trim($_POST['deposit_maya_ref'] ?? '');
    $deposit_instapay_ref = trim($_POST['deposit_instapay_ref'] ?? '');
    $deposit_online_banking_ref = trim($_POST['deposit_online_banking_ref'] ?? '');
    $deposit_airbnb_ref = trim($_POST['deposit_airbnb_ref'] ?? '');

    // CRITICAL FIX: Receive payment_status columns EXACTLY like update_payment_status.php (confirmPaymentOptions)
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
    $change_amount = floatval($_POST['change_amount'] ?? 0);

    // Keep Instapay / Online Banking / Airbnb as independent methods for reporting.

    $check_in_change_amount = floatval($_POST['check_in_change_amount'] ?? 0);
    $withdraw_extension = isset($_POST['withdraw_extension']) && $_POST['withdraw_extension'] === '1';
    $withdraw_refund_amount = floatval($_POST['withdraw_refund_amount'] ?? 0);
    // Persisted extension-withdraw flags (frontend will also send these).
    // If withdraw_extension is set, we force extension_withdraw=1 and refund_amount_extension=withdraw_refund_amount.
    $extension_withdraw = $withdraw_extension ? 1 : intval($_POST['extension_withdraw'] ?? 0);
    $refund_amount_extension = $withdraw_extension ? $withdraw_refund_amount : floatval($_POST['refund_amount_extension'] ?? 0);
    // Withdrawn extension history (frontend does not need to send; we manage it server-side)

    // CRITICAL FIX: Rebuild deposit_details from breakdown amounts to prevent string concatenation issues
    // New Guest Type Fields
    $guest_type = trim($_POST['guest_type'] ?? 'Solo');
    $second_guest_name = trim($_POST['second_guest_name'] ?? '');
    $contact_person_name = trim($_POST['contact_person_name'] ?? '');
    $number_of_adults = intval($_POST['number_of_adults'] ?? 0);
    $number_of_children = intval($_POST['number_of_children'] ?? 0);
    $tin_number = trim($_POST['number_of_guests_info'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Vehicle Details
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $plate_number = trim($_POST['plate_number'] ?? '');
    $vehicle_description = trim($_POST['vehicle_description'] ?? '');

    // Sales Channel
    $sales_channel = trim($_POST['sales_channel'] ?? '');

    // Debug log for sales_channel
    error_log("=== SALES CHANNEL DEBUG ===");
    error_log("sales_channel from POST: " . $sales_channel);
    error_log("=== END SALES CHANNEL DEBUG ===");
    if (
        $deposit_cash > 0 || $deposit_g_cash > 0 || $deposit_maya > 0
        || $deposit_instapay > 0 || $deposit_online_banking > 0 || $deposit_airbnb > 0
    ) {
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

        error_log("=== DEPOSIT DETAILS REBUILT IN UPDATE_BOOKING ===");
        error_log("deposit_cash: " . $deposit_cash);
        error_log("deposit_g_cash: " . $deposit_g_cash);
        error_log("deposit_maya: " . $deposit_maya);
        error_log("deposit_instapay: " . $deposit_instapay);
        error_log("deposit_online_banking: " . $deposit_online_banking);
        error_log("deposit_airbnb: " . $deposit_airbnb);
        error_log("Rebuilt deposit_details: " . $deposit_details);
        error_log("=== END ===");
    }

    // CRITICAL FIX: Always recalculate deposit from breakdown fields to ensure accuracy
    // This prevents issues where the deposit field contains the raw payment amount instead of the discounted amount
    // Must include Instapay / Online Banking / Airbnb — omitting them broke mixed-method updates (e.g. Instapay + Cash).
    if (
        $deposit_cash > 0 || $deposit_g_cash > 0 || $deposit_maya > 0
        || $deposit_instapay > 0 || $deposit_online_banking > 0 || $deposit_airbnb > 0
    ) {
        $calculated_deposit = $deposit_cash + $deposit_g_cash + $deposit_maya
            + $deposit_instapay + $deposit_online_banking + $deposit_airbnb;
        if (abs($deposit - $calculated_deposit) > 0.01) { // Allow for small floating point differences
            error_log("CRITICAL FIX: deposit mismatch detected in UPDATE!");
            error_log("Original deposit from POST: " . $deposit);
            error_log("Calculated from breakdown: " . $calculated_deposit);
            error_log("Using calculated value from breakdown fields");
            $deposit = $calculated_deposit;
        }
    }

    // Debug logging
    error_log("=== UPDATE BOOKING DEPOSIT ADJUSTMENT DEBUG ===");
    error_log("Original deposit: " . $deposit);
    error_log("Original deposit_details: " . ($deposit_details ?? 'NULL'));
    error_log("check_in_change_amount: " . $check_in_change_amount);

    // Store original deposit values BEFORE change deduction
    $original_deposit_cash = $deposit_cash;
    $original_deposit_g_cash = $deposit_g_cash;
    $original_deposit_maya = $deposit_maya;
    $original_deposit_instapay = $deposit_instapay;
    $original_deposit_online_banking = $deposit_online_banking;
    $original_deposit_airbnb = $deposit_airbnb;

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

    // Convert empty strings to NULL for database
    if ($deposit_gcash_ref === '')
        $deposit_gcash_ref = null;
    if ($deposit_maya_ref === '')
        $deposit_maya_ref = null;
    if ($deposit_instapay_ref === '')
        $deposit_instapay_ref = null;
    if ($deposit_online_banking_ref === '')
        $deposit_online_banking_ref = null;
    if ($deposit_airbnb_ref === '')
        $deposit_airbnb_ref = null;

    // Get discount data from POST
    $discount_enabled = isset($_POST['discount_enabled']) && $_POST['discount_enabled'] === '1' ? 1 : 0;
    $discount_type = trim($_POST['discount_type'] ?? 'regular');
    $sc_pwd_count = intval($_POST['sc_pwd_count'] ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $id_number = trim($_POST['id_number'] ?? '');

    // CRITICAL FIX: Use manual discount amount from frontend
    // The user enters the exact discount amount they want in the UI
    // No need to recalculate on backend
    error_log("=== UPDATE BOOKING DISCOUNT DATA (FROM FRONTEND) ===");
    error_log("discount_enabled: " . $discount_enabled);
    error_log("discount_type: " . $discount_type);
    error_log("sc_pwd_count: " . $sc_pwd_count);
    error_log("discount_amount (manual from frontend): " . $discount_amount);
    error_log("id_number: " . $id_number);
    error_log("=== END DISCOUNT DATA ===");

    $reference_numbers = $_POST['reference_numbers'] ?? '';
    $reference_no = null;
    $collectedReferences = [];

    if ($reference_numbers !== '') {
        $decoded = json_decode($reference_numbers, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $ref) {
                $ref = trim((string) $ref);
                if ($ref !== '') {
                    $collectedReferences[] = $ref;
                }
            }
        }
    }

    // Include method-specific references so Instapay/Online Banking/Airbnb appear in exports.
    foreach ([
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
    if (!empty($collectedReferences)) {
        $reference_no = implode(', ', $collectedReferences);
    }

    if ($payment_status === 'Select' || $payment_status === 'Select Method' || $payment_status === '') {
        $payment_status = null;
    }
    if ($promo === 'Select Promo' || $promo === 'None' || $promo === '') {
        $promo = null;
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

    if ($breakfast && $breakfast !== 'Select Breakfast' && $breakfast !== 'None' && $breakfast !== '') {
        if (strpos($breakfast, '|') !== false) {
            $breakfastItems = explode('|', $breakfast);
            $formattedItems = [];

            foreach ($breakfastItems as $item) {
                $item = trim($item);
                if (empty($item))
                    continue;


                if (preg_match('/^(\d+)\s+(.+?)\s+-\s+₱([\d,]+\.?\d*)$/', $item, $matches)) {
                    $quantity = intval($matches[1]);
                    $itemName = trim($matches[2]);
                    $totalPrice = floatval(str_replace(',', '', $matches[3]));
                    $itemName = ucwords(strtolower($itemName));
                    $formattedItems[] = $quantity . ' ' . $itemName . ' - ₱' . number_format($totalPrice, 2, '.', ',');
                } elseif (preg_match('/^(\d+)\s+(.+?\(Promo\))$/i', $item, $matches)) {
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
                // Item with (Promo) suffix but no quantity: "TAPA (Promo)"
                elseif (stripos($item, '(Promo)') !== false) {
                    $itemName = trim(str_replace('(Promo)', '', $item));
                    $itemName = ucwords(strtolower($itemName)) . ' (Promo)';
                    $formattedItems[] = '1 ' . $itemName;
                }
                // General fallback: add "1 " prefix if no quantity is present
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
            // Single item with price but no quantity: "HOTDOG - ₱120.00"
            elseif (preg_match('/^(.+?)\s+-\s+₱([\d,]+\.?\d*)$/', $breakfast, $matches)) {
                $itemName = trim($matches[1]);
                $unitPrice = floatval(str_replace(',', '', $matches[2]));
                $itemName = ucwords(strtolower($itemName));
                $totalPrice = $unitPrice * $breakfast_qty;
                $breakfast = $breakfast_qty . ' ' . $itemName . ' - ₱' . number_format($totalPrice, 2, '.', ',');
            }
            // Single info item with (Promo) suffix: "TAPA (Promo)"
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
    if ($supplier === '' && $referral_name !== null) {
        $supplier = $referral_name;
    }

    if (!$booking_id) {
        $response['message'] = 'Booking ID is required!';
        ob_clean();
        echo json_encode($response);
        ob_end_flush();
        exit;
    }

    ensureReportFinancialColumns($conn);

    // When withdrawing, only the last extension segment is removed (see extension_stack).
    $withdraw_popped_segment = null;
    $withdraw_stack_json_after = null;

    // Fetch existing extension BEFORE we compute check_out.
    // The Update modal usually does not send extend_* fields, but the booking may already be extended.
    $existing_extend_hours = 0;
    $existing_extend_minutes = 0;
    try {
        $extStmt = $conn->prepare("
            SELECT COALESCE(extend_hours,0) AS extend_hours,
                   COALESCE(extend_minutes,0) AS extend_minutes,
                   COALESCE(extend_price,0) AS extend_price,
                   COALESCE(extend_regular_rate,0) AS extend_regular_rate,
                   COALESCE(extend_bundle_rate,0) AS extend_bundle_rate,
                   extend_bundle_breakfast,
                   COALESCE(extend_additional_guest,0) AS extend_additional_guest,
                   extension_stack
            FROM bookings WHERE id = :booking_id LIMIT 1
        ");
        $extStmt->bindParam(':booking_id', $booking_id);
        $extStmt->execute();
        $extRow = $extStmt->fetch(PDO::FETCH_ASSOC);
        if ($extRow) {
            if ($withdraw_extension) {
                $boot = booking_extension_stack_bootstrap_from_row($extRow);
                if (!empty($boot)) {
                    $withdraw_popped_segment = array_pop($boot);
                    $withdraw_stack_json_after = booking_extension_stack_encode($boot);
                    $remAggEarly = booking_extension_stack_aggregate_segments($boot);
                    $existing_extend_hours = $remAggEarly['h'];
                    $existing_extend_minutes = $remAggEarly['m'];
                }
            } else {
                $existing_extend_hours = intval($extRow['extend_hours'] ?? 0);
                $existing_extend_minutes = intval($extRow['extend_minutes'] ?? 0);
            }
        }
    } catch (Exception $e) {
        // If columns don't exist yet, the requiredColumns block below will add them.
        // We will fall back to 0 for now.
        $existing_extend_hours = 0;
        $existing_extend_minutes = 0;
    }

    // Calculate check-out and hours
    $check_out = null;
    $hours = null;

    if ($check_in) {
        // Preserve extension fields unless explicitly posted
        // (Update modal typically does not send extend_* but we must not lose them)
        $extend_hours_posted = array_key_exists('extend_hours', $_POST);
        $extend_minutes_posted = array_key_exists('extend_minutes', $_POST);
        $extend_hours = $extend_hours_posted ? intval($_POST['extend_hours']) : $existing_extend_hours;
        $extend_minutes = $extend_minutes_posted ? intval($_POST['extend_minutes']) : $existing_extend_minutes;

        // Partial withdraw: $existing_extend_* already excludes the popped segment (computed above).

        $baseHours = ($duration_unit === 'night') ? $duration * 12 : $duration;

        // If promo/bundle is selected, derive hours from the promo label itself
        // (e.g. "Package 2 24hrs" → 24 hours, "Package 1 12hrs" → 12 hours).
        // "Regular" / "Select Bundle" / "None" are NOT promos.
        if ($promo && $promo !== 'Select Promo' && $promo !== 'Select Bundle' && $promo !== 'Regular' && $promo !== 'None' && $promo !== '') {
            if (preg_match('/(\d+)\s*hrs?/i', $promo, $promoMatch)) {
                $promoHours = intval($promoMatch[1]);
                if ($promoHours > 0) {
                    $baseHours = $promoHours; // Use promo hours directly (replaces any duration)
                } else {
                    $baseHours += 12; // Fallback for older 12-hour-only bundles
                }
            } else {
                $baseHours += 12; // Fallback for bundles without explicit hour count
            }
        }

        // Calculate checkout if we have check-in and any stay time (base duration / promo / extension)
        // IMPORTANT: include extension hours/minutes so "Update" won't reset an already-extended booking.
        $totalHours = max(0, intval($baseHours)) + max(0, intval($extend_hours));
        $totalMinutes = max(0, intval($extend_minutes));
        // Normalize minutes into hours if needed
        if ($totalMinutes >= 60) {
            $totalHours += intdiv($totalMinutes, 60);
            $totalMinutes = $totalMinutes % 60;
        }

        if ($totalHours > 0 || $totalMinutes > 0) {
            $checkInDate = new DateTime($check_in);
            $checkOutDate = clone $checkInDate;
            if ($totalHours > 0) {
                $checkOutDate->modify("+{$totalHours} hours");
            }
            if ($totalMinutes > 0) {
                $checkOutDate->modify("+{$totalMinutes} minutes");
            }

            $check_out = $checkOutDate->format('Y-m-d H:i:s');
            // Keep existing "hours" semantics but reflect extension minutes if any
            $hours = $totalMinutes > 0 ? ($totalHours . ':' . str_pad((string) $totalMinutes, 2, '0', STR_PAD_LEFT) . ' hrs') : ($totalHours . ' hrs');
        }
    }

    try {

        $requiredColumns = [
            'referral_name' => "VARCHAR(255) NULL DEFAULT NULL",
            'reason_for_stay' => "VARCHAR(255) NULL DEFAULT NULL",
            'contact_no' => "VARCHAR(20) NULL DEFAULT NULL",
            'address' => "TEXT NULL DEFAULT NULL",
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
            'deposit' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_cash' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_g_cash' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_maya' => "DECIMAL(10,2) DEFAULT 0",
            'deposit_details' => "TEXT NULL DEFAULT NULL",
            'deposit_gcash_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_maya_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'deposit_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL",
            'check_in_change_amount' => "DECIMAL(10,2) DEFAULT 0.00",
            'extended_duration' => "INT DEFAULT 0",
            'extend_hours' => "INT DEFAULT 0",
            'extend_minutes' => "INT DEFAULT 0",
            // Keep extension visible after withdrawal + persist refund line
            'extension_withdraw' => "TINYINT(1) DEFAULT 0",
            'refund_amount_extension' => "DECIMAL(10,2) DEFAULT 0.00",
            // Store withdrawn extension separately so new extensions don't combine
            'withdrawn_extend_hours' => "INT DEFAULT 0",
            'withdrawn_extend_minutes' => "INT DEFAULT 0",
            'withdrawn_extend_price' => "DECIMAL(10,2) DEFAULT 0.00",
            'withdrawn_extend_regular_rate' => "DECIMAL(10,2) DEFAULT NULL",
            'withdrawn_extend_bundle_rate' => "DECIMAL(10,2) DEFAULT NULL",
            'withdrawn_extend_bundle_breakfast' => "TEXT DEFAULT NULL",
            'guest_type' => "VARCHAR(50) NULL DEFAULT 'Solo'",
            'second_guest_name' => "VARCHAR(255) NULL DEFAULT NULL",
            'contact_person_name' => "VARCHAR(255) NULL DEFAULT NULL",
            'number_of_adults' => "INT DEFAULT 0",
            'number_of_children' => "INT DEFAULT 0",
            'tin_number' => "VARCHAR(50) NULL DEFAULT NULL",
            'email' => "VARCHAR(255) NULL DEFAULT NULL",
            'id_number' => "VARCHAR(500) NULL DEFAULT NULL",
            'sales_channel' => "VARCHAR(50) NULL DEFAULT NULL"
        ];

        // Ensure reference_no column exists with correct type
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
                    } elseif ($columnName === 'contact_no') {
                        $afterClause = ' AFTER guest_name';
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

        foreach ($requiredColumns as $columnName => $columnDefinition) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE '$columnName'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec("ALTER TABLE bookings ADD COLUMN $columnName $columnDefinition");
                }
            } catch (PDOException $e) {
                error_log("Failed to check/add column $columnName: " . $e->getMessage());
            }
        }

        // Get original booking to compare duration BEFORE updating
        // Also get paid_status if it exists, and room_db_id for price calculation
        // IMPORTANT: Also get downpayment fields to preserve them
        $getOriginalStmt = $conn->prepare("
            SELECT b.booking_id, b.room_id, b.duration, b.duration_unit, b.promo, b.paid_status, b.hygiene_kit_used, 
                   b.hygiene_kit_price, b.status, b.room_type, b.supplier, r.id as room_db_id,
                   b.additional_food, b.additional_items, b.additional_guest, b.additional_pet,
                   b.encoder,
                   b.extend_hours, b.extend_minutes, b.extend_price,
                   b.extend_regular_rate, b.extend_bundle_rate, b.extend_bundle_breakfast,
                   b.extend_additional_guest,
                   b.extension_stack,
                   b.withdrawn_extend_hours, b.withdrawn_extend_minutes, b.withdrawn_extend_price,
                   b.withdrawn_extend_regular_rate, b.withdrawn_extend_bundle_rate, b.withdrawn_extend_bundle_breakfast,
                   b.downpayment_amount, b.downpayment_cash, b.downpayment_gcash, b.downpayment_maya,
                   b.downpayment_instapay, b.downpayment_online_banking, b.downpayment_airbnb,
                   b.downpayment_gcash_ref, b.downpayment_maya_ref,
                   b.downpayment_instapay_ref, b.downpayment_online_banking_ref, b.downpayment_airbnb_ref,
                   b.downpayment_status, b.downpayment_date,
                   b.booking_type, b.reservation_date, b.check_in AS original_check_in,
                   COALESCE(b.discount_amount, 0) AS old_discount_amount,
                   COALESCE(b.room_price, 0) AS original_room_price,
                   b.total_amount
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.room_id
            WHERE b.id = :booking_id
        ");
        $getOriginalStmt->bindParam(':booking_id', $booking_id);
        $getOriginalStmt->execute();
        $originalBooking = $getOriginalStmt->fetch(PDO::FETCH_ASSOC);
        $originalDuration = $originalBooking['duration'] ?? 0;
        $originalDurationUnit = $originalBooking['duration_unit'] ?? 'hours';
        $originalPromo = trim((string) ($originalBooking['promo'] ?? ''));
        $durationChanged = (
            intval($duration) !== intval($originalDuration)
            || strtolower((string) $duration_unit) !== strtolower((string) $originalDurationUnit)
        );
        $promoChanged = trim((string) $promo) !== $originalPromo;
        $booking_id_value = $originalBooking['booking_id'] ?? null;
        $originalStatus = $originalBooking['status'] ?? null;
        $originalBookingType = $originalBooking['booking_type'] ?? null;
        $originalReservationDate = $originalBooking['reservation_date'] ?? null;
        $room_type = $originalBooking['room_type'] ?? '';
        $room_db_id = $originalBooking['room_db_id'] ?? null;
        $room_id = $originalBooking['room_id'] ?? null; // needed for overlap check
        if ($supplier === '' && !empty($originalBooking['supplier'])) {
            $supplier = $originalBooking['supplier'];
        }

        // Extension withdraw: POST withdraw_refund_amount can be 0 when the edit form's hidden
        // field was not synced into FormData. Infer the refund from the popped stack segment (or
        // the current extension row for legacy/no-stack) so deposit + refund_amount_extension stay
        // consistent. Only when the booking was already Paid — otherwise there is nothing to refund.
        if ($originalBooking && $withdraw_extension && $withdraw_refund_amount < 0.009) {
            $origPaid = strcasecmp(trim((string) ($originalBooking['paid_status'] ?? '')), 'Paid') === 0;
            if ($origPaid) {
                if (is_array($withdraw_popped_segment)) {
                    $poppedRefund = floatval($withdraw_popped_segment['price'] ?? 0);
                    if ($poppedRefund > 0.009) {
                        $withdraw_refund_amount = $poppedRefund;
                    }
                }
                if ($withdraw_refund_amount < 0.009) {
                    $extPriceFallback = floatval($originalBooking['extend_price'] ?? 0);
                    if ($extPriceFallback > 0.009) {
                        $withdraw_refund_amount = $extPriceFallback;
                    }
                }
            }
            if ($withdraw_refund_amount > 0.009) {
                $refund_amount_extension = $withdraw_refund_amount;
                error_log('=== EXTENSION WITHDRAW: inferred withdraw_refund_amount=' . $withdraw_refund_amount . ' (POST withdraw_refund_amount was missing/zero) ===');
            }
        }

        // ── Require Check-In before updating a reservation that has not checked in yet ──
        // Matches UI rule: user must set Check-In (datetime) before clicking Update, same as Confirm flow.
        if ($originalBooking) {
            $origCi = $originalBooking['original_check_in'] ?? ($originalBooking['check_in'] ?? null);
            $hasOrigCheckIn = false;
            if (!empty($origCi) && trim((string) $origCi) !== '' && (string) $origCi !== '0000-00-00 00:00:00') {
                $tsCi = strtotime($origCi);
                if ($tsCi !== false && $tsCi > 946684800) {
                    $hasOrigCheckIn = true;
                }
            }
            $postCi = isset($_POST['check_in']) ? trim((string) $_POST['check_in']) : '';
            $postCi = str_replace('T', ' ', $postCi);
            $hasPostCheckIn = $postCi !== '' && $postCi !== '0000-00-00 00:00:00';

            $btOrig = trim((string) ($originalBooking['booking_type'] ?? ''));
            $stOrig = trim((string) ($originalBooking['status'] ?? ''));
            $requiresCheckInToUpdate = ($btOrig === 'Reservation')
                || in_array($stOrig, ['Reserved', 'Pending'], true);

            if ($requiresCheckInToUpdate && !$hasOrigCheckIn && !$hasPostCheckIn) {
                $response['success'] = false;
                $response['message'] = 'Please set Check-In date and time before updating this reservation.';
                ob_clean();
                echo json_encode($response);
                ob_end_flush();
                exit;
            }
        }

        // If the edit modal posts duration=0 (common after extension flows),
        // preserve the original stored duration so checkout recalculation stays correct.
        if (intval($duration) <= 0 && intval($originalDuration) > 0) {
            $duration = intval($originalDuration);
            $duration_unit = $originalDurationUnit ?: $duration_unit;
        }

        // ── Reservation Date handling (prevent 0000-00-00 00:00:00) ──────────
        // If the edit modal doesn't send reservation_date, keep the existing value.
        // Also normalize datetime-local ("YYYY-MM-DDTHH:mm") to MySQL DATETIME.
        $reservation_date_was_provided = array_key_exists('reservation_date', $_POST);
        $reservation_date_trimmed = is_string($reservation_date) ? trim($reservation_date) : $reservation_date;

        if ($reservation_date_was_provided && is_string($reservation_date_trimmed) && $reservation_date_trimmed !== '') {
            // Normalize separator and ensure seconds
            $normalized = str_replace('T', ' ', $reservation_date_trimmed);
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $normalized)) {
                $normalized .= ':00';
            }
            // Validate format before saving; if invalid, preserve original
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $normalized);
            if ($dt && $dt->format('Y-m-d H:i:s') === $normalized) {
                $reservation_date = $normalized;
            } else {
                $reservation_date = $originalReservationDate;
            }
        } else {
            // Not provided or empty: preserve the original reservation_date
            $reservation_date = $originalReservationDate;
        }

        // If this booking is not a Reservation type, keep reservation_date NULL
        // unless it already exists and we need to preserve it for historical records.
        // (Most flows expect non-reservations to have NULL reservation_date.)
        if ($originalBookingType && $originalBookingType !== 'Reservation') {
            $reservation_date = null;
        }

        // ── ROOM AVAILABILITY OVERLAP CHECK (UPDATE) ─────────────────────────
        // When updating check-in/check-out, make sure the new window doesn't
        // overlap any OTHER booking or report for the same room.
        if ($check_in && $check_out && $room_id) {

            // Build bookings-table exclusion clause in PHP
            // (PDO does not allow the same named placeholder twice in one query)
            $bookingExclude = $booking_id_value
                ? "AND booking_id != :current_bid"
                : "";

            $overlapBookingStmt = $conn->prepare("
                SELECT booking_id, guest_name, check_in, check_out
                FROM bookings
                WHERE room_id = :room_id
                  AND status IN ('Confirming', 'Confirmed', 'Occupied')
                  {$bookingExclude}
                  AND check_in  < :new_check_out
                  AND check_out > :new_check_in
                LIMIT 1
            ");
            $overlapBookingStmt->bindParam(':room_id', $room_id);
            $overlapBookingStmt->bindParam(':new_check_in', $check_in);
            $overlapBookingStmt->bindParam(':new_check_out', $check_out);
            if ($booking_id_value) {
                $overlapBookingStmt->bindParam(':current_bid', $booking_id_value);
            }
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

            // Check reports table — use actual checkout time for early checkouts
            $reportExclude = $booking_id_value
                ? "AND booking_id != :current_bid"
                : "";

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
                  {$reportExclude}
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
            if ($booking_id_value) {
                $overlapReportStmt->bindParam(':current_bid', $booking_id_value);
            }
            $overlapReportStmt->execute();
            $conflictReport = $overlapReportStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflictReport) {
                $cIn = date('m/d/Y h:i A', strtotime($conflictReport['check_in']));
                // Show actual checkout time (checked_out_at for early checkouts)
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

            // ── ALSO CHECK AGAINST OTHER RESERVATIONS (duration-aware) ─────────
            // Reservations are treated as a window:
            //   res_start = reservation_date
            //   res_end   = res_start + duration (+promo hours if applicable)
            // Cleaning rule: allow only after (res_end + 30 minutes).
            //
            // Compare against THIS booking's window [check_in, check_out] and its cleaning gap [check_out + 30m].
            try {
                $checkOutObj = new DateTime($check_out);
                $checkOutPlus30 = (clone $checkOutObj)->add(new DateInterval('PT30M'));
                $gap_end = $checkOutPlus30->format('Y-m-d H:i:s');

                $reservationExclude = $booking_id_value
                    ? "AND booking_id != :current_bid"
                    : "";

                $overlapReservationSql = "
                    SELECT booking_id, guest_name, reservation_date, duration, duration_unit, promo, status
                    FROM bookings
                    WHERE room_id = :room_id
                      AND booking_type = 'Reservation'
                      AND status IN ('Reserved', 'Confirming', 'Confirmed')
                      AND reservation_date IS NOT NULL
                      {$reservationExclude}
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
                ";

                $overlapReservationStmt = $conn->prepare($overlapReservationSql);
                $overlapReservationStmt->bindParam(':room_id', $room_id);
                $overlapReservationStmt->bindParam(':new_check_in', $check_in);
                $overlapReservationStmt->bindParam(':new_check_out_plus_30', $gap_end);
                if ($booking_id_value) {
                    $overlapReservationStmt->bindParam(':current_bid', $booking_id_value);
                }
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
                    $nextAvailableObj = (clone $resCleanEndObj)->add(new DateInterval('PT1M'));

                    $response['message'] = "Error: Room " . $room_id . " already has a reservation that overlaps this period.\n\n"
                        . "Existing Booking ID: " . ($conflictReservation['booking_id'] ?? 'N/A') . "\n"
                        . "Guest: " . ($conflictReservation['guest_name'] ?? 'N/A') . "\n"
                        . "Status: " . ($conflictReservation['status'] ?? 'N/A') . "\n\n"
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
                error_log('Reservation overlap check (update) failed: ' . $e->getMessage());
            }

        }
        // ── END OVERLAP CHECK ─────────────────────────────────────────────────


        // Preserve downpayment fields from original booking (don't overwrite them)
        // UNLESS they are explicitly provided in the POST data (for updates)
        $downpayment_amount = isset($_POST['downpayment_amount']) ? floatval($_POST['downpayment_amount']) : ($originalBooking['downpayment_amount'] ?? 0);
        $downpayment_cash = isset($_POST['downpayment_cash']) ? floatval($_POST['downpayment_cash']) : ($originalBooking['downpayment_cash'] ?? 0);
        $downpayment_gcash = isset($_POST['downpayment_gcash']) ? floatval($_POST['downpayment_gcash']) : ($originalBooking['downpayment_gcash'] ?? 0);
        $downpayment_maya = isset($_POST['downpayment_maya']) ? floatval($_POST['downpayment_maya']) : ($originalBooking['downpayment_maya'] ?? 0);
        // CRITICAL FIX: Also read Instapay / Online Banking / Airbnb downpayment fields.
        // Previously these were never read from POST, so they were always saved as 0.00
        // even when the user paid via those methods during the Reservation downpayment modal.
        $downpayment_instapay = isset($_POST['downpayment_instapay']) ? floatval($_POST['downpayment_instapay']) : ($originalBooking['downpayment_instapay'] ?? 0);
        $downpayment_online_banking = isset($_POST['downpayment_online_banking']) ? floatval($_POST['downpayment_online_banking']) : ($originalBooking['downpayment_online_banking'] ?? 0);
        $downpayment_airbnb = isset($_POST['downpayment_airbnb']) ? floatval($_POST['downpayment_airbnb']) : ($originalBooking['downpayment_airbnb'] ?? 0);
        $downpayment_gcash_ref = isset($_POST['downpayment_gcash_ref']) ? trim($_POST['downpayment_gcash_ref']) : ($originalBooking['downpayment_gcash_ref'] ?? null);
        $downpayment_maya_ref = isset($_POST['downpayment_maya_ref']) ? trim($_POST['downpayment_maya_ref']) : ($originalBooking['downpayment_maya_ref'] ?? null);
        $downpayment_instapay_ref = isset($_POST['downpayment_instapay_ref']) ? trim($_POST['downpayment_instapay_ref']) : ($originalBooking['downpayment_instapay_ref'] ?? null);
        $downpayment_online_banking_ref = isset($_POST['downpayment_online_banking_ref']) ? trim($_POST['downpayment_online_banking_ref']) : ($originalBooking['downpayment_online_banking_ref'] ?? null);
        $downpayment_airbnb_ref = isset($_POST['downpayment_airbnb_ref']) ? trim($_POST['downpayment_airbnb_ref']) : ($originalBooking['downpayment_airbnb_ref'] ?? null);
        $downpayment_status = isset($_POST['downpayment_status']) ? trim($_POST['downpayment_status']) : ($originalBooking['downpayment_status'] ?? 'None');
        $downpayment_date = isset($_POST['downpayment_date']) ? $_POST['downpayment_date'] : ($originalBooking['downpayment_date'] ?? null);

        // CRITICAL FIX: Preserve payment_status and payment_status_* columns from DB
        // when the Update form sends them as empty/null. This happens for Reservation bookings
        // that were already fully paid via downpayment and the user clicks "Updating..." with ₱0 due.
        // Without this, the "Cash" (or other) payment method disappears after the update.
        if (empty($payment_status) || $payment_status === null) {
            try {
                $existingPsStmt = $conn->prepare(
                    "SELECT payment_status, payment_status_cash, payment_status_g_cash, payment_status_maya,
                            payment_status_instapay, payment_status_online_banking, payment_status_airbnb
                     FROM bookings WHERE id = :booking_id LIMIT 1"
                );
                $existingPsStmt->bindParam(':booking_id', $booking_id);
                $existingPsStmt->execute();
                $existingPs = $existingPsStmt->fetch(PDO::FETCH_ASSOC);
                if ($existingPs) {
                    if (empty($payment_status))
                        $payment_status = $existingPs['payment_status'] ?: null;
                    if (empty($payment_status_cash))
                        $payment_status_cash = $existingPs['payment_status_cash'] ?: null;
                    if (empty($payment_status_g_cash))
                        $payment_status_g_cash = $existingPs['payment_status_g_cash'] ?: null;
                    if (empty($payment_status_maya))
                        $payment_status_maya = $existingPs['payment_status_maya'] ?: null;
                    if (empty($payment_status_instapay))
                        $payment_status_instapay = $existingPs['payment_status_instapay'] ?: null;
                    if (empty($payment_status_online_banking))
                        $payment_status_online_banking = $existingPs['payment_status_online_banking'] ?: null;
                    if (empty($payment_status_airbnb))
                        $payment_status_airbnb = $existingPs['payment_status_airbnb'] ?: null;
                    error_log("=== PAYMENT STATUS PRESERVED FROM DB ===");
                    error_log("payment_status: " . ($payment_status ?? 'NULL'));
                    error_log("payment_status_cash: " . ($payment_status_cash ?? 'NULL'));
                    error_log("=== END PAYMENT STATUS PRESERVE ===");
                }
            } catch (Exception $e) {
                error_log("Failed to preserve payment_status from DB: " . $e->getMessage());
            }
        }

        error_log("=== DOWNPAYMENT VALUES ===");
        error_log("downpayment_amount: " . $downpayment_amount);
        error_log("downpayment_cash: " . $downpayment_cash);
        error_log("downpayment_gcash: " . $downpayment_gcash);
        error_log("downpayment_maya: " . $downpayment_maya);
        error_log("=== END DOWNPAYMENT VALUES ===");

        // If paid_status not explicitly provided, keep the existing one
        if ($paid_status === '' && $originalBooking && isset($originalBooking['paid_status'])) {
            $paid_status = $originalBooking['paid_status'] ?: 'Unpaid';
        } elseif ($paid_status === '') {
            $paid_status = 'Unpaid'; // Default if no existing value
        }

        $hygiene_kit_used = $originalBooking['hygiene_kit_used'] ?? 0;
        $hygiene_kit_price = $originalBooking['hygiene_kit_price'] ?? 0;

        // Separate food and items from additional_charges
        // Start with existing values from database, then process new charges
        // If new charges are provided (even empty), they replace the old ones
        $additional_food = $originalBooking['additional_food'] ?? null;
        $additional_items = $originalBooking['additional_items'] ?? null;
        $additional_paid_status = 'None'; // Default to None

        if ($additional_charges) {
            try {
                $chargesData = json_decode($additional_charges, true);
                if (is_array($chargesData)) {
                    // We have valid data structure, so we should replace existing values
                    $additional_food = null;
                    $additional_items = null;

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
                // Handle parse error - keep original values if parsing failed
            }
        }

        // CRITICAL FIX: Check if payment was confirmed in the UI (from hidden field)
        // If additional_paid_status is explicitly set to 'Paid' in POST, it means user confirmed payment
        $explicitAdditionalPaidStatus = trim($_POST['additional_paid_status'] ?? '');

        // Determine whether any additional charges exist
        $hasAdditionalFoodOrItems = (
            ($additional_food && trim($additional_food) !== '') ||
            ($additional_items && trim($additional_items) !== '')
        );
        $hasAdditionalCharges = (
            $hasAdditionalFoodOrItems ||
            intval($additional_guest) > 0 ||
            intval($additional_pet) > 0
        );

        // Initialize date tracking variables
        $currentTimestamp = date('Y-m-d H:i:s');
        $additional_food_date = null;
        $additional_items_date = null;
        $additional_guest_date = null;
        $additional_pet_date = null;

        // Fetch existing dates from database to preserve history
        $existingDates = [
            'additional_food_date' => null,
            'additional_items_date' => null,
            'additional_guest_date' => null,
            'additional_pet_date' => null
        ];
        try {
            $datesStmt = $conn->prepare("SELECT additional_food_date, additional_items_date, additional_guest_date, additional_pet_date FROM bookings WHERE id = :booking_id");
            $datesStmt->bindParam(':booking_id', $booking_id);
            $datesStmt->execute();
            $datesRow = $datesStmt->fetch(PDO::FETCH_ASSOC);
            if ($datesRow) {
                $existingDates = $datesRow;
            }
        } catch (Exception $e) {
            error_log("Failed to fetch existing dates: " . $e->getMessage());
        }

        // Start with normalized history JSON (supports legacy single datetime values).
        $additional_food_date = normalizeAdditionalDateHistoryForStorage($existingDates['additional_food_date']);
        $additional_items_date = normalizeAdditionalDateHistoryForStorage($existingDates['additional_items_date']);
        $additional_guest_date = normalizeAdditionalDateHistoryForStorage($existingDates['additional_guest_date']);
        $additional_pet_date = normalizeAdditionalDateHistoryForStorage($existingDates['additional_pet_date']);

        $originalFood = $originalBooking['additional_food'] ?? null;
        $originalItems = $originalBooking['additional_items'] ?? null;
        $originalGuest = intval($originalBooking['additional_guest'] ?? 0);
        $originalPet = intval($originalBooking['additional_pet'] ?? 0);

        // Normalize for comparison (treat null and empty string as the same)
        $normalizedOriginalFood = ($originalFood === null || trim($originalFood) === '') ? '' : trim($originalFood);
        $normalizedNewFood = ($additional_food === null || trim($additional_food) === '') ? '' : trim($additional_food);
        $normalizedOriginalItems = ($originalItems === null || trim($originalItems) === '') ? '' : trim($originalItems);
        $normalizedNewItems = ($additional_items === null || trim($additional_items) === '') ? '' : trim($additional_items);

        $foodChanged = ($normalizedNewFood !== $normalizedOriginalFood) && $normalizedNewFood !== '';
        $itemsChanged = ($normalizedNewItems !== $normalizedOriginalItems) && $normalizedNewItems !== '';
        $guestChanged = ($additional_guest !== $originalGuest) && $additional_guest > 0;
        $petChanged = ($additional_pet !== $originalPet) && $additional_pet > 0;
        $anyAdditionalChanged = $foodChanged || $itemsChanged || $guestChanged || $petChanged;

        // Append transaction history whenever additionals are added/changed
        if ($foodChanged) {
            $additional_food_date = appendAdditionalDateHistory($additional_food_date, $currentTimestamp);
            error_log("=== ADDITIONAL FOOD CHANGED ===");
            error_log("Food date: " . ($additional_food_date ?? 'NULL'));
        }
        if ($itemsChanged) {
            $additional_items_date = appendAdditionalDateHistory($additional_items_date, $currentTimestamp);
            error_log("=== ADDITIONAL ITEMS CHANGED ===");
            error_log("Items date: " . ($additional_items_date ?? 'NULL'));
        }
        if ($guestChanged) {
            $additional_guest_date = appendAdditionalDateHistory($additional_guest_date, $currentTimestamp);
            error_log("=== ADDITIONAL GUEST CHANGED ===");
            error_log("Original: {$originalGuest}, New: {$additional_guest}");
            error_log("Guest date: {$additional_guest_date}");
        }
        if ($petChanged) {
            $additional_pet_date = appendAdditionalDateHistory($additional_pet_date, $currentTimestamp);
            error_log("=== ADDITIONAL PET CHANGED ===");
            error_log("Original: {$originalPet}, New: {$additional_pet}");
            error_log("Pet date: {$additional_pet_date}");
        }

        if ($explicitAdditionalPaidStatus === 'Paid') {
            $additional_paid_status = 'Paid';
            error_log("=== ADDITIONAL ITEMS PAYMENT CONFIRMED ===");
            error_log("User confirmed payment, setting additional_paid_status to Paid");
        } elseif ($hasAdditionalCharges) {
            if ($anyAdditionalChanged) {
                $additional_paid_status = 'Unpaid';
                error_log("=== NEW ADDITIONAL CHARGES DETECTED ===");
                error_log("Setting additional_paid_status to Unpaid");
            } else {
                $getStatusStmt = $conn->prepare("SELECT additional_paid_status FROM bookings WHERE id = :booking_id");
                $getStatusStmt->bindParam(':booking_id', $booking_id);
                $getStatusStmt->execute();
                $currentStatus = $getStatusStmt->fetch(PDO::FETCH_ASSOC);
                $currentAdditionalStatus = ($currentStatus && isset($currentStatus['additional_paid_status'])) ? $currentStatus['additional_paid_status'] : 'None';
                $additional_paid_status = $currentAdditionalStatus;
                error_log("=== ADDITIONAL CHARGES UNCHANGED ===");
                error_log("Keeping current status: " . $additional_paid_status);
            }
        } else {
            $additional_paid_status = 'None';
            error_log("=== NO ADDITIONAL CHARGES ===");
            error_log("Setting additional_paid_status to None");
        }

        // Track breakfast date changes
        $breakfast_date = null;
        try {
            $breakfastStmt = $conn->prepare("SELECT breakfast, breakfast_date FROM bookings WHERE id = :booking_id");
            $breakfastStmt->bindParam(':booking_id', $booking_id);
            $breakfastStmt->execute();
            $breakfastRow = $breakfastStmt->fetch(PDO::FETCH_ASSOC);

            if ($breakfastRow) {
                $originalBreakfast = trim($breakfastRow['breakfast'] ?? '');
                $currentBreakfast = trim($breakfast ?? '');
                $breakfast_date = $breakfastRow['breakfast_date'];

                // If breakfast changed, append new timestamp
                if ($originalBreakfast !== $currentBreakfast && !empty($currentBreakfast)) {
                    $breakfast_date = appendAdditionalDateHistory($breakfast_date, $currentTimestamp);
                    error_log("=== BREAKFAST CHANGED ===");
                    error_log("Original: '{$originalBreakfast}', New: '{$currentBreakfast}'");
                    error_log("Breakfast date: {$breakfast_date}");
                }
            }
        } catch (Exception $e) {
            error_log("Failed to track breakfast date: " . $e->getMessage());
        }

        // Calculate room price from room_durations table
        $room_price = 0.0;

        // CRITICAL FIX: If promo is selected, use promo price as room_price
        // "Regular" / "Select Bundle" are NOT promos.
        if ($promo && $promo !== 'Select Promo' && $promo !== 'Select Bundle' && $promo !== 'Regular' && $promo !== 'None' && $promo !== '') {
            require_once 'report_helpers.php';
            $promoMeta = parsePromoSelection($promo);
            $room_price = floatval($promoMeta['price'] ?? 0);
            error_log("=== PROMO PRICE USED IN UPDATE ===");
            error_log("Promo: $promo");
            error_log("Promo price: $room_price");
            error_log("=== END PROMO PRICE ===");
        }

        // If no promo or promo price is 0, calculate from room_durations table
        if ($room_price == 0 && $room_db_id && $duration > 0) {
            $totalHours = ($duration_unit === 'night') ? $duration * 12 : $duration;

            try {
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
                    // If no exact match, pick the next available tier:
                    // - smallest duration >= selected hours (e.g. 3hrs selects 12hrs tier if 3hrs not defined)
                    // - otherwise, fall back to the largest available tier
                    $priceStmt = $conn->prepare("SELECT price FROM room_durations WHERE room_id = :room_id AND duration_hours >= :duration_hours ORDER BY duration_hours ASC LIMIT 1");
                    $priceStmt->execute([
                        ':room_id' => $room_db_id,
                        ':duration_hours' => $totalHours
                    ]);
                    $priceResult = $priceStmt->fetch(PDO::FETCH_ASSOC);
                    if ($priceResult) {
                        $room_price = floatval($priceResult['price']);
                    } else {
                        $priceStmt = $conn->prepare("SELECT price FROM room_durations WHERE room_id = :room_id ORDER BY duration_hours DESC LIMIT 1");
                        $priceStmt->execute([
                            ':room_id' => $room_db_id
                        ]);
                        $priceResult = $priceStmt->fetch(PDO::FETCH_ASSOC);
                        if ($priceResult) {
                            $room_price = floatval($priceResult['price']);
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Failed to get room price: " . $e->getMessage());
                // Fallback to calculateRoomRate if room_durations table doesn't have the price
                if ($room_price == 0 && $room_type) {
                    require_once 'report_helpers.php';
                    $room_price = calculateRoomRate($room_type, $totalHours);
                }
            }
        }

        // Accept explicit room price from the duration dropdown when user changes tier.
        $postedRoomPrice = isset($_POST['room_price']) ? floatval($_POST['room_price']) : 0;
        if ($postedRoomPrice > 0 && ($durationChanged || $promoChanged)) {
            $room_price = $postedRoomPrice;
            error_log("=== ROOM PRICE FROM POST (duration/promo changed) ===");
            error_log("room_price: $room_price");
        }

        // Prefer the higher stored price only when duration/promo are unchanged.
        // Otherwise a downgrade (e.g. 24 Hrs → 12 Hrs) would keep the old ₱1490 rate.
        $originalRoomPrice = floatval($originalBooking['original_room_price'] ?? 0);
        if (!$durationChanged && !$promoChanged && $originalRoomPrice > $room_price) {
            $room_price = $originalRoomPrice;
        }

        $total_amount = computeBookingTotalAmount([
            'room_type' => $room_type,
            'duration' => $duration,
            'duration_unit' => $duration_unit,
            'promo' => $promo,
            'breakfast' => $breakfast,
            'hygiene_kit_used' => $hygiene_kit_used,
            'hygiene_kit_price' => $hygiene_kit_price,
            'room_price' => $room_price,
            'deposit' => $deposit
        ]);

        // Manual discount amount is handled via the $discount_amount assignment from POST at line 285
        // and validated/logged below. Overriding it here broke manual discount entries.

        // Calculate the full booking amount INCLUDING all charges
        // 1. Room rate (from computeBookingTotalAmount)
        $room_total = computeBookingTotalAmount([
            'room_type' => $room_type,
            'duration' => $duration,
            'duration_unit' => $duration_unit,
            'promo' => $promo,
            'breakfast' => $breakfast,
            'hygiene_kit_used' => $hygiene_kit_used,
            'hygiene_kit_price' => $hygiene_kit_price,
            'room_price' => $room_price,
            'deposit' => 0  // Don't deduct deposit to get full amount
        ]);

        // 2. Additional guest charges (₱300 per guest)
        $guest_charges = $additional_guest * 300;

        // 3. Additional pet charges (₱500 per pet)
        $pet_charges = $additional_pet * 500;

        // 4. Additional items/charges (food, etc.)
        $additional_items_total = 0;
        if (!empty($additional_charges)) {
            $charges_array = json_decode($additional_charges, true);
            if (is_array($charges_array)) {
                foreach ($charges_array as $charge) {
                    if (isset($charge['price'])) {
                        $additional_items_total += floatval($charge['price']);
                    }
                }
            }
        }

        // Preserve any active extension hours and guest extension fees when updating
        $extend_price = floatval($originalBooking['extend_price'] ?? 0);
        $extend_additional_guest = intval($originalBooking['extend_additional_guest'] ?? 0);
        $extend_guest_charges = $extend_additional_guest * 300;

        // Full booking amount = room + guests + pets + additional items + extension hours + extension guests
        $full_booking_amount = $room_total + $guest_charges + $pet_charges + $additional_items_total + $extend_price + $extend_guest_charges;

        error_log("=== FULL BOOKING AMOUNT CALCULATION ===");
        error_log("Room total: " . $room_total);
        error_log("Guest charges ($additional_guest guests): " . $guest_charges);
        error_log("Pet charges ($additional_pet pets): " . $pet_charges);
        error_log("Additional items total: " . $additional_items_total);
        error_log("Full booking amount: " . $full_booking_amount);
        error_log("=== BREAKDOWN ===");
        error_log("Room total (includes room rate, breakfast, hygiene kit): " . $room_total);
        error_log("Additional guest charges (300 x $additional_guest): " . $guest_charges);
        error_log("Additional pet charges (500 x $additional_pet): " . $pet_charges);
        error_log("Additional items/food charges: " . $additional_items_total);
        error_log("=== END BREAKDOWN ===");

        // CRITICAL FIX: Use manual discount amount from frontend instead of calculating
        // The discount amount is already set from POST data above (line 158)
        // No need to recalculate here - user enters the exact discount amount they want

        error_log("=== USING MANUAL DISCOUNT AMOUNT ===");
        error_log("Discount amount from frontend: $discount_amount");
        error_log("=== END ===");

        // Apply discount to full booking amount
        $full_booking_amount_after_discount = $full_booking_amount - $discount_amount;

        error_log("=== DISCOUNT APPLICATION ===");
        error_log("Full booking amount (before discount): $full_booking_amount");
        error_log("Discount amount: $discount_amount");
        error_log("Full booking amount (after discount): $full_booking_amount_after_discount");

        // CRITICAL FIX: Check if the original booking was already paid
        // If it was paid and we're just adding additional items, keep paid_status as 'Paid'
        $originalPaidStatus = $originalBooking['paid_status'] ?? 'Unpaid';
        $wasOriginallyPaid = ($originalPaidStatus === 'Paid');

        // Calculate the original booking amount (without new additional items)
        // This is the room total + guest charges + pet charges (but NOT additional items)
        $originalBookingAmount = $room_total + $guest_charges + $pet_charges - $discount_amount;

        // CRITICAL FIX: For Reservations, downpayment and deposit are separate additive components.
        // For Walk-in bookings (non-reservations), we take the max to be safe with existing data patterns.
        $isReservationBooking = strcasecmp(trim((string) ($originalBookingType ?? '')), 'Reservation') === 0
            || strcasecmp(trim((string) ($originalBooking['status'] ?? '')), 'Reserved') === 0;
        if ($isReservationBooking) {
            // Reservation: downpayment (reservation fee) + check-in balance (deposit).
            // deposit_* may be cumulative (already includes downpayment) or incremental (balance only).
            $depositTotal = floatval($deposit_cash) + floatval($deposit_g_cash) + floatval($deposit_maya)
                + floatval($deposit_instapay ?? 0) + floatval($deposit_online_banking ?? 0) + floatval($deposit_airbnb ?? 0);
            $downpaymentTotal = floatval($downpayment_cash) + floatval($downpayment_gcash) + floatval($downpayment_maya)
                + floatval($downpayment_instapay ?? 0) + floatval($downpayment_online_banking ?? 0) + floatval($downpayment_airbnb ?? 0);

            if ($depositTotal >= ($downpaymentTotal - 0.02) && $depositTotal > 0) {
                // Cumulative totals from frontend (e.g. ₱500 DP + ₱460 balance stored as ₱960 deposit_cash).
                $total_payments = $depositTotal;
            } else {
                // Incremental deposit only — add reservation downpayment.
                $total_payments = $downpaymentTotal + $depositTotal;
            }
        } else {
            // Walk-in: Typically only uses one set of columns, so take the max
            $total_payments = max($downpayment_amount, $deposit);
        }

        // If the booking was originally paid, then they must have paid at least the original booking amount.
        // This handles legacy bookings where deposit or downpayment columns might be empty/zero in the DB
        // but the booking was already marked Paid.
        if ($wasOriginallyPaid && $total_payments < $originalBookingAmount) {
            $total_payments = $originalBookingAmount;
            error_log("✓ Adjusted total_payments to originalBookingAmount ($originalBookingAmount) because wasOriginallyPaid is true");
        }

        $total_paid = $total_payments;

        error_log("=== TOTAL PAID CALCULATION ===");
        error_log("booking_type: " . ($originalBookingType ?? ''));
        error_log("isReservationBooking: " . ($isReservationBooking ? 'YES' : 'NO'));
        error_log("downpayment_amount: " . $downpayment_amount);
        error_log("deposit: " . $deposit);
        error_log("total_payments: " . $total_payments);
        error_log("=== END TOTAL PAID CALCULATION ===");

        // CRITICAL FIX: Compare total payments against full booking amount (including additional charges)
        // to determine if booking is fully paid or not
        // Calculate remaining amount due
        $amount_due = $full_booking_amount_after_discount - $total_payments;

        // Scale down deposits and downpayments if they exceed the new total cost (overpaid guest adjustment)
        if ($total_payments > $full_booking_amount_after_discount && $full_booking_amount_after_discount > 0) {
            $scale_ratio = $full_booking_amount_after_discount / $total_payments;
            
            error_log("=== SCALING DOWN OVERPAID BOOKING ===");
            error_log("Old total payments: $total_payments");
            error_log("New total cost: $full_booking_amount_after_discount");
            error_log("Scale ratio: $scale_ratio");
            
            // 1. Scale down the deposit and downpayment values
            $deposit = round($deposit * $scale_ratio, 2);
            $deposit_cash = round($deposit_cash * $scale_ratio, 2);
            $deposit_g_cash = round($deposit_g_cash * $scale_ratio, 2);
            $deposit_maya = round($deposit_maya * $scale_ratio, 2);
            $deposit_instapay = round(($deposit_instapay ?? 0) * $scale_ratio, 2);
            $deposit_online_banking = round(($deposit_online_banking ?? 0) * $scale_ratio, 2);
            $deposit_airbnb = round(($deposit_airbnb ?? 0) * $scale_ratio, 2);

            $downpayment_amount = round($downpayment_amount * $scale_ratio, 2);
            $downpayment_cash = round($downpayment_cash * $scale_ratio, 2);
            $downpayment_gcash = round($downpayment_gcash * $scale_ratio, 2);
            $downpayment_maya = round($downpayment_maya * $scale_ratio, 2);
            $downpayment_instapay = round(($downpayment_instapay ?? 0) * $scale_ratio, 2);
            $downpayment_online_banking = round(($downpayment_online_banking ?? 0) * $scale_ratio, 2);
            $downpayment_airbnb = round(($downpayment_airbnb ?? 0) * $scale_ratio, 2);
            
            $original_deposit_cash = $deposit_cash;
            $original_deposit_g_cash = $deposit_g_cash;
            $original_deposit_maya = $deposit_maya;
            $original_deposit_instapay = $deposit_instapay;
            $original_deposit_online_banking = $deposit_online_banking;
            $original_deposit_airbnb = $deposit_airbnb;
            
            $total_payments = $full_booking_amount_after_discount;
            $total_paid = $total_payments;
            $amount_due = 0.0;
            
            // 2. Rebuild payment status strings using the total method amounts (deposit + downpayment)
            $total_method_cash = $deposit_cash + $downpayment_cash;
            $total_method_gcash = $deposit_g_cash + $downpayment_gcash;
            $total_method_maya = $deposit_maya + $downpayment_maya;
            $total_method_instapay = ($deposit_instapay ?? 0) + ($downpayment_instapay ?? 0);
            $total_method_online_banking = ($deposit_online_banking ?? 0) + ($downpayment_online_banking ?? 0);
            $total_method_airbnb = ($deposit_airbnb ?? 0) + ($downpayment_airbnb ?? 0);

            $paymentStatusParts = [];
            if ($total_method_cash > 0) {
                $payment_status_cash = "Cash (₱" . number_format($total_method_cash, 2, '.', ',') . ")";
                $paymentStatusParts[] = $payment_status_cash;
            } else {
                $payment_status_cash = null;
            }
            if ($total_method_gcash > 0) {
                $payment_status_g_cash = "G-cash (₱" . number_format($total_method_gcash, 2, '.', ',') . ")";
                $paymentStatusParts[] = $payment_status_g_cash;
            } else {
                $payment_status_g_cash = null;
            }
            if ($total_method_maya > 0) {
                $payment_status_maya = "Maya (₱" . number_format($total_method_maya, 2, '.', ',') . ")";
                $paymentStatusParts[] = $payment_status_maya;
            } else {
                $payment_status_maya = null;
            }
            if ($total_method_instapay > 0) {
                $payment_status_instapay = "Instapay (₱" . number_format($total_method_instapay, 2, '.', ',') . ")";
                $paymentStatusParts[] = $payment_status_instapay;
            } else {
                $payment_status_instapay = null;
            }
            if ($total_method_online_banking > 0) {
                $payment_status_online_banking = "Online Banking (₱" . number_format($total_method_online_banking, 2, '.', ',') . ")";
                $paymentStatusParts[] = $payment_status_online_banking;
            } else {
                $payment_status_online_banking = null;
            }
            if ($total_method_airbnb > 0) {
                $payment_status_airbnb = "Airbnb (₱" . number_format($total_method_airbnb, 2, '.', ',') . ")";
                $paymentStatusParts[] = $payment_status_airbnb;
            } else {
                $payment_status_airbnb = null;
            }
            $payment_status = implode(', ', $paymentStatusParts);
            
            // 3. Scale down payment history strings in bookings & reports
            if (!empty($booking_id_value)) {
                try {
                    $histFetchStmt = $conn->prepare("
                        SELECT
                            payment_amount_cash_history,
                            payment_amount_g_cash_history,
                            payment_amount_maya_history,
                            payment_amount_instapay_history,
                            payment_amount_online_banking_history,
                            payment_amount_airbnb_history
                        FROM bookings
                        WHERE id = :booking_id
                        LIMIT 1
                    ");
                    $histFetchStmt->execute([':booking_id' => $booking_id]);
                    $histRow = $histFetchStmt->fetch(PDO::FETCH_ASSOC);
                    if ($histRow) {
                        $scaleHistory = function(?string $history, float $ratio): ?string {
                            if ($history === null || trim($history) === '') {
                                return null;
                            }
                            $parts = explode('|', $history);
                            $scaledParts = [];
                            foreach ($parts as $p) {
                                $val = floatval(trim($p));
                                $scaledParts[] = number_format($val * $ratio, 2, '.', '');
                            }
                            return implode('|', $scaledParts);
                        };
                        
                        $updatedCashHist = $scaleHistory($histRow['payment_amount_cash_history'], $scale_ratio);
                        $updatedGCashHist = $scaleHistory($histRow['payment_amount_g_cash_history'], $scale_ratio);
                        $updatedMayaHist = $scaleHistory($histRow['payment_amount_maya_history'], $scale_ratio);
                        $updatedInstapayHist = $scaleHistory($histRow['payment_amount_instapay_history'], $scale_ratio);
                        $updatedOnlineBankingHist = $scaleHistory($histRow['payment_amount_online_banking_history'], $scale_ratio);
                        $updatedAirbnbHist = $scaleHistory($histRow['payment_amount_airbnb_history'], $scale_ratio);
                        
                        $histUpdateBk = $conn->prepare("
                            UPDATE bookings
                            SET payment_amount_cash_history = :cash,
                                payment_amount_g_cash_history = :gcash,
                                payment_amount_maya_history = :maya,
                                payment_amount_instapay_history = :instapay,
                                payment_amount_online_banking_history = :ob,
                                payment_amount_airbnb_history = :airbnb
                            WHERE id = :booking_id
                        ");
                        $histUpdateBk->execute([
                            ':cash' => $updatedCashHist,
                            ':gcash' => $updatedGCashHist,
                            ':maya' => $updatedMayaHist,
                            ':instapay' => $updatedInstapayHist,
                            ':ob' => $updatedOnlineBankingHist,
                            ':airbnb' => $updatedAirbnbHist,
                            ':booking_id' => $booking_id
                        ]);
                        
                        $histUpdateRep = $conn->prepare("
                            UPDATE reports
                            SET payment_amount_cash_history = :cash,
                                payment_amount_g_cash_history = :gcash,
                                payment_amount_maya_history = :maya,
                                payment_amount_instapay_history = :instapay,
                                payment_amount_online_banking_history = :ob,
                                payment_amount_airbnb_history = :airbnb
                            WHERE booking_id = :booking_id
                        ");
                        $histUpdateRep->execute([
                            ':cash' => $updatedCashHist,
                            ':gcash' => $updatedGCashHist,
                            ':maya' => $updatedMayaHist,
                            ':instapay' => $updatedInstapayHist,
                            ':ob' => $updatedOnlineBankingHist,
                            ':airbnb' => $updatedAirbnbHist,
                            ':booking_id' => $booking_id_value
                        ]);
                        
                        error_log("✓ Scaled payment history in bookings and reports");
                    }
                } catch (Exception $e) {
                    error_log("Failed to scale payment histories: " . $e->getMessage());
                }
            }
        }

        error_log("=== PAYMENT STATUS DETERMINATION ===");
        error_log("full_booking_amount_after_discount: " . $full_booking_amount_after_discount);
        error_log("total_payments (deposit): " . $total_payments);
        error_log("total_paid (max downpayment/deposit): " . $total_paid);
        error_log("amount_due: " . $amount_due);
        error_log("Promo: " . ($promo ?? 'NULL'));
        error_log("Room price: $room_price");
        error_log("Breakfast: " . ($breakfast ?? 'NULL'));
        error_log("Condition check: full_booking_amount_after_discount <= 0? " . ($full_booking_amount_after_discount <= 0 ? 'YES' : 'NO'));
        error_log("Condition check: amount_due <= 0? " . ($amount_due <= 0 ? 'YES' : 'NO'));
        error_log("=== DETAILED BREAKDOWN FOR DEBUGGING ===");
        error_log("Original booking amount (room + guest + pet - discount): " . $originalBookingAmount);
        error_log("Additional items total: " . $additional_items_total);
        error_log("Full booking amount (original + additional items): " . $full_booking_amount);
        error_log("Discount amount: " . $discount_amount);
        error_log("Full booking amount after discount: " . $full_booking_amount_after_discount);
        error_log("Total payments made: " . $total_payments);
        error_log("Amount still due: " . $amount_due);
        error_log("=== END DETAILED BREAKDOWN ===");

        error_log("=== ORIGINAL BOOKING CHECK ===");
        error_log("originalPaidStatus: " . $originalPaidStatus);
        error_log("wasOriginallyPaid: " . ($wasOriginallyPaid ? 'YES' : 'NO'));
        error_log("originalBookingAmount: " . $originalBookingAmount);
        error_log("total_payments: " . $total_payments);
        error_log("=== END ORIGINAL BOOKING CHECK ===");

        // SPECIAL CASE: If amount due is 0 or negative, booking is fully paid/credited
        // CRITICAL FIX: If frontend explicitly sent paid_status='Paid' (user paid full amount in Charges Breakdown),
        // respect that value EXACTLY like update_payment_status.php does (confirmPaymentOptions flow)
        $frontend_paid_status = trim($_POST['paid_status'] ?? '');

        // Use stored total only when duration/promo unchanged; after a tier change the new
        // recalculated amount (e.g. 12 Hrs = ₱960) must be used for paid_status checks.
        $storedTotalAmount = floatval($originalBooking['total_amount'] ?? 0);
        $effectiveTotalForPaidCheck = ($durationChanged || $promoChanged)
            ? $full_booking_amount_after_discount
            : $storedTotalAmount;

        if ($frontend_paid_status === 'Paid' && $amount_due <= 0) {
            // Frontend confirmed payment covers full amount - mark as Paid (EXACTLY like confirmPaymentOptions)
            $paid_status = 'Paid';
            error_log("✓ CONDITION MET: frontend_paid_status='Paid' AND amount_due <= 0");
            error_log("PAID STATUS: Paid (frontend confirmed full payment, EXACTLY like confirmPaymentOptions)");
        } elseif (
            $frontend_paid_status === 'Paid' && $total_payments > 0
            && ($effectiveTotalForPaidCheck <= 0 || $total_payments >= ($effectiveTotalForPaidCheck - 0.02))
        ) {
            // Frontend says Paid, payments exist, and payments cover the applicable total amount.
            // Small float tolerance (0.02) handles rounding differences.
            $paid_status = 'Paid';
            error_log("✓ CONDITION MET: frontend='Paid' + total_payments($total_payments) covers effectiveTotal($effectiveTotalForPaidCheck)");
            error_log("PAID STATUS: Paid (effective total covered)");
        } elseif ($frontend_paid_status === 'Paid' && $wasOriginallyPaid) {
            // CRITICAL FIX: If the booking was originally paid in the database and the frontend still reports it as Paid,
            // keep it Paid. This handles cases where deposit/downpayment fields are 0/empty in the DB but the booking
            // was already marked Paid.
            $paid_status = 'Paid';
            error_log("✓ CONDITION MET: frontend_paid_status='Paid' AND wasOriginallyPaid");
            error_log("PAID STATUS: Paid (originally Paid in DB, and frontend still reports Paid)");
        } elseif ($full_booking_amount_after_discount <= 0) {
            // Amount due is 0 or negative due to discounts/credits/promo - mark as Paid
            $paid_status = 'Paid';
            error_log("✓ CONDITION MET: full_booking_amount_after_discount <= 0");
            error_log("PAID STATUS: Paid (full_booking_amount_after_discount <= 0, nothing to pay)");
        } elseif ($wasOriginallyPaid && $total_payments >= $originalBookingAmount) {
            // CRITICAL FIX: If the original booking was paid and the payment covers the original amount,
            // keep paid_status as 'Paid' even if new additional items were added
            // The additional items will be tracked separately via additional_paid_status
            $paid_status = 'Paid';
            error_log("✓ CONDITION MET: wasOriginallyPaid AND total_payments >= originalBookingAmount");
            error_log("PAID STATUS: Paid (original booking was paid, additional items tracked separately)");
        } elseif ($amount_due <= 0) {
            // Fully paid or overpaid - amount due is 0 or negative
            // No need to check total_paid > 0 because if amount_due <= 0, it means payment covered the cost
            $paid_status = 'Paid';
            error_log("✓ CONDITION MET: amount_due <= 0");
            error_log("PAID STATUS: Paid (amount_due <= 0, fully paid)");
        } else {
            // Not fully paid (partial payment or no payment)
            $paid_status = 'Unpaid';
            error_log("✗ NO CONDITION MET: PAID STATUS: Unpaid");
            error_log("This means: full_booking_amount_after_discount > 0 AND amount_due > 0");
            error_log("PAID STATUS: Unpaid (amount_due: " . $amount_due . ", total_paid: " . $total_paid . ")");
        }
        error_log("=== END PAYMENT STATUS DETERMINATION ===");

        // Keep additional items payment state in sync with remaining balance.
        // If there are add-ons and the booking is now fully settled, mark add-ons as Paid.
        // This prevents stale "Unpaid" badges after user pays new food/items via Update.
        // Also sync when paid_status was already set to Paid (covers the stored-total path).
        if ($hasAdditionalItems && ($amount_due <= 0.01 || $paid_status === 'Paid')) {
            $additional_paid_status = 'Paid';
            error_log("✓ SYNC: booking is Paid, setting additional_paid_status to Paid");
        } elseif (!$hasAdditionalItems) {
            $additional_paid_status = 'None';
            error_log("✓ SYNC: no additional items, setting additional_paid_status to None");
        }

        // CRITICAL FIX: If there are NEW additional items/guests/pets that are
        // explicitly marked as Unpaid (additional_paid_status = 'Unpaid') and
        // there is still an amount due, force paid_status to 'Unpaid' even if
        // the original booking portion was already fully paid. This matches
        // the behaviour of Additional Guest/Pet where any new unpaid charges
        // make the whole booking show as Unpaid until they are settled.
        // NOTE: Only apply this override when paid_status is NOT already confirmed Paid
        // via stored-total check (to avoid re-introducing the recalculation bug).
        if ($additional_paid_status === 'Unpaid' && $amount_due > 0 && $paid_status !== 'Paid') {
            $paid_status = 'Unpaid';
            error_log("✓ OVERRIDE: additional_paid_status is Unpaid with amount_due > 0, forcing paid_status to Unpaid");
        }

        // CRITICAL FIX: Update total_amount to reflect the full booking amount after discount
        // This ensures the database stores the correct total including additional items
        // In this codebase, the reports and export logic expect this to be the full transaction value.
        $total_amount = $full_booking_amount_after_discount;

        // When fully paid, total must reflect the booking charge (not stale pre-change totals).
        if ($paid_status === 'Paid') {
            if ($durationChanged || $promoChanged) {
                $total_amount = $full_booking_amount_after_discount;
            } elseif ($total_payments > 0) {
                $total_amount = max($total_amount, $total_payments);
            }
        }

        error_log("=== PAYMENT CALCULATION DEBUG ===");
        error_log("Full booking amount (after discount): " . $full_booking_amount_after_discount);
        error_log("Downpayment amount: " . $downpayment_amount);
        error_log("Deposit amount: " . $deposit);
        error_log("Total paid: " . $total_paid);
        error_log("Total payments: " . $total_payments);
        error_log("Amount due: " . $amount_due);
        error_log("UPDATED total_amount (for database): " . $total_amount);
        error_log("Paid status: " . $paid_status);

        // Set booking status based on check-in and payment
        // If check_in is set (guest has checked in), status should be Confirmed/Occupied
        // regardless of payment status (they can pay later)

        error_log("=== STATUS UPDATE DEBUG ===");
        error_log("check_in: " . ($check_in ?? 'NULL'));
        error_log("status from POST: " . $status);
        error_log("paid_status: " . $paid_status);

        if ($check_in) {
            // Guest has checked in - ALWAYS set to Confirmed (which maps to Occupied room status)
            // This ensures the room shows as Occupied when guest checks in, regardless of payment
            $status = 'Confirmed';
            error_log("Setting status to Confirmed because check_in is set");
        } else {
            // No check-in yet
            if ($paid_status === 'Paid') {
                // Fully paid but not checked in yet
                if ($status === 'Confirming' || $status === 'Reserved') {
                    $status = 'Confirmed';
                    error_log("Setting status to Confirmed because fully paid");
                }
            }
        }

        error_log("Final status: " . $status);

        // Ensure additional_guest column exists before updating
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_guest'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN additional_guest INT DEFAULT 0 AFTER guest_capacity");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add additional_guest column: " . $e->getMessage());
        }

        // Ensure additional_pet column exists before updating
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_pet'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN additional_pet INT DEFAULT 0 AFTER additional_guest");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add additional_pet column: " . $e->getMessage());
        }

        // Ensure discount columns exist before updating
        try {
            $discountColumns = [
                'discount_enabled' => "TINYINT(1) DEFAULT 0",
                'discount_type' => "VARCHAR(50) NULL DEFAULT 'regular'",
                'sc_pwd_count' => "INT DEFAULT 0",
                'discount_amount' => "DECIMAL(10,2) DEFAULT 0",
                'discount_amount_history' => "TEXT NULL DEFAULT NULL"
            ];

            foreach ($discountColumns as $columnName => $columnDefinition) {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE '$columnName'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec("ALTER TABLE bookings ADD COLUMN $columnName $columnDefinition");
                    error_log("Added $columnName column to bookings table");
                }
                // Also ensure in reports table
                $checkColRep = $conn->query("SHOW COLUMNS FROM reports LIKE '$columnName'");
                if ($checkColRep && $checkColRep->rowCount() == 0) {
                    $conn->exec("ALTER TABLE reports ADD COLUMN $columnName $columnDefinition");
                    error_log("Added $columnName column to reports table");
                }
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add discount columns: " . $e->getMessage());
        }

        // PAYMENT HISTORY: Append new payment timestamp when new payment is made
        // payment_date_time stores multiple timestamps separated by "|"
        // Example: "2026-01-18 10:10:00|2026-01-18 23:04:00"
        $existingPaymentDateTime = null;
        $newPaymentTimestamp = null;
        $oldDepositCash = 0;
        $oldDepositGCash = 0;
        $oldDepositMaya = 0;
        $oldDepositInstapay = 0;
        $oldDepositOnlineBanking = 0;
        $oldDepositAirbnb = 0;

        // Fetch existing payment_date_time and per-method deposits BEFORE this update.
        // Deltas for payment_amount_*_history must use these values; otherwise an Unpaid → Paid
        // transition after partial payments (e.g. already ₱950 on deposit) would compute delta vs 0
        // and store the full cumulative deposit (e.g. ₱1250) instead of the new installment (₱300).
        try {
            $pdtStmt = $conn->prepare("
                SELECT payment_date_time, deposit_cash, deposit_g_cash, deposit_maya, deposit_instapay, deposit_online_banking, deposit_airbnb
                FROM bookings
                WHERE id = :booking_id
                LIMIT 1
            ");
            $pdtStmt->bindParam(':booking_id', $booking_id);
            $pdtStmt->execute();
            $pdtRow = $pdtStmt->fetch(PDO::FETCH_ASSOC);
            $existingPaymentDateTime = $pdtRow['payment_date_time'] ?? null;
            $oldDepositCash = floatval($pdtRow['deposit_cash'] ?? 0);
            $oldDepositGCash = floatval($pdtRow['deposit_g_cash'] ?? 0);
            $oldDepositMaya = floatval($pdtRow['deposit_maya'] ?? 0);
            $oldDepositInstapay = floatval($pdtRow['deposit_instapay'] ?? 0);
            $oldDepositOnlineBanking = floatval($pdtRow['deposit_online_banking'] ?? 0);
            $oldDepositAirbnb = floatval($pdtRow['deposit_airbnb'] ?? 0);
        } catch (PDOException $e) {
            error_log("Failed to fetch payment_date_time / deposits: " . $e->getMessage());
            $existingPaymentDateTime = null;
            $oldDepositCash = 0;
            $oldDepositGCash = 0;
            $oldDepositMaya = 0;
            $oldDepositInstapay = 0;
            $oldDepositOnlineBanking = 0;
            $oldDepositAirbnb = 0;
        }

        // Determine if a new payment was made in this update (deposit actually increased).
        $newPaymentMade = false;
        if ($paid_status === 'Paid') {
            $newTotalDeposit = floatval($deposit_cash) + floatval($deposit_g_cash) + floatval($deposit_maya) + floatval($deposit_instapay ?? 0) + floatval($deposit_online_banking ?? 0) + floatval($deposit_airbnb ?? 0);
            $oldTotalDeposit = $oldDepositCash + $oldDepositGCash + $oldDepositMaya + $oldDepositInstapay + $oldDepositOnlineBanking + $oldDepositAirbnb;
            if ($newTotalDeposit > $oldTotalDeposit + 0.01) {
                $newPaymentMade = true;
                error_log("Payment deposit increase detected: Old=$oldTotalDeposit, New=$newTotalDeposit (wasOriginallyPaid=" . ($originalPaidStatus === 'Paid' ? '1' : '0') . ")");
            }
        }

        // Append new payment timestamp if new payment was made
        if ($newPaymentMade) {
            $newTimestamp = date('Y-m-d H:i:s');
            if (!empty($existingPaymentDateTime)) {
                // Append to existing timestamps
                $existingPaymentDateTime .= '|' . $newTimestamp;
            } else {
                // First payment
                $existingPaymentDateTime = $newTimestamp;
            }
            $newPaymentTimestamp = $newTimestamp;

            // PAYMENT AMOUNT HISTORY: Append method deltas aligned with the new timestamp.
            // These columns are used by export_payment_type_report.php to split per payment.
            if (!empty($booking_id_value)) {
                try {
                    // Ensure history columns exist in reports + bookings (keep both in sync)
                    $histColumns = [
                        'payment_amount_cash_history',
                        'payment_amount_g_cash_history',
                        'payment_amount_maya_history',
                        'payment_amount_instapay_history',
                        'payment_amount_online_banking_history',
                        'payment_amount_airbnb_history'
                    ];
                    foreach ($histColumns as $colName) {
                        $chk = $conn->query("SHOW COLUMNS FROM reports LIKE '" . $colName . "'");
                        if ($chk && $chk->rowCount() == 0) {
                            $conn->exec("ALTER TABLE reports ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
                        }
                        $chkBk = $conn->query("SHOW COLUMNS FROM bookings LIKE '" . $colName . "'");
                        if ($chkBk && $chkBk->rowCount() == 0) {
                            $conn->exec("ALTER TABLE bookings ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
                        }
                    }

                    $isRes = strcasecmp(trim((string) ($originalBookingType ?? '')), 'Reservation') === 0
                        || strcasecmp(trim((string) ($originalBooking['status'] ?? '')), 'Reserved') === 0;

                    $oldGCashBase = $isRes ? max($oldDepositGCash, floatval($originalBooking['downpayment_gcash'] ?? 0)) : $oldDepositGCash;
                    $oldCashBase = $isRes ? max($oldDepositCash, floatval($originalBooking['downpayment_cash'] ?? 0)) : $oldDepositCash;
                    $oldMayaBase = $isRes ? max($oldDepositMaya, floatval($originalBooking['downpayment_maya'] ?? 0)) : $oldDepositMaya;
                    $oldInstapayBase = $isRes ? max($oldDepositInstapay, floatval($originalBooking['downpayment_instapay'] ?? 0)) : $oldDepositInstapay;
                    $oldOnlineBankingBase = $isRes ? max($oldDepositOnlineBanking, floatval($originalBooking['downpayment_online_banking'] ?? 0)) : $oldDepositOnlineBanking;
                    $oldAirbnbBase = $isRes ? max($oldDepositAirbnb, floatval($originalBooking['downpayment_airbnb'] ?? 0)) : $oldDepositAirbnb;

                    // New incremental amounts for this payment event
                    $deltaCash = max(0, floatval($deposit_cash) - $oldCashBase);
                    $deltaGCash = max(0, floatval($deposit_g_cash) - $oldGCashBase);
                    $deltaMaya = max(0, floatval($deposit_maya) - $oldMayaBase);
                    $deltaInstapay = max(0, floatval($deposit_instapay ?? 0) - $oldInstapayBase);
                    $deltaOnlineBanking = max(0, floatval($deposit_online_banking ?? 0) - $oldOnlineBankingBase);
                    $deltaAirbnb = max(0, floatval($deposit_airbnb ?? 0) - $oldAirbnbBase);

                    // Fetch existing histories (if any), then append.
                    $histFetchStmt = $conn->prepare("
                        SELECT
                            payment_amount_cash_history,
                            payment_amount_g_cash_history,
                            payment_amount_maya_history,
                            payment_amount_instapay_history,
                            payment_amount_online_banking_history,
                            payment_amount_airbnb_history
                        FROM reports
                        WHERE booking_id = :booking_id
                        LIMIT 1
                    ");
                    $histFetchStmt->bindParam(':booking_id', $booking_id_value);
                    $histFetchStmt->execute();
                    $histRow = $histFetchStmt->fetch(PDO::FETCH_ASSOC);

                    $existingCashHist = trim((string) ($histRow['payment_amount_cash_history'] ?? ''));
                    $existingGCashHist = trim((string) ($histRow['payment_amount_g_cash_history'] ?? ''));
                    $existingMayaHist = trim((string) ($histRow['payment_amount_maya_history'] ?? ''));
                    $existingInstapayHist = trim((string) ($histRow['payment_amount_instapay_history'] ?? ''));
                    $existingOnlineBankingHist = trim((string) ($histRow['payment_amount_online_banking_history'] ?? ''));
                    $existingAirbnbHist = trim((string) ($histRow['payment_amount_airbnb_history'] ?? ''));

                    $newCashEntry = number_format($deltaCash, 2, '.', '');
                    $newGCashEntry = number_format($deltaGCash, 2, '.', '');
                    $newMayaEntry = number_format($deltaMaya, 2, '.', '');
                    $newInstapayEntry = number_format($deltaInstapay, 2, '.', '');
                    $newOnlineBankingEntry = number_format($deltaOnlineBanking, 2, '.', '');
                    $newAirbnbEntry = number_format($deltaAirbnb, 2, '.', '');
                    $newMayaEntry = number_format($deltaMaya, 2, '.', '');

                    $updatedCashHist = $existingCashHist !== '' ? ($existingCashHist . '|' . $newCashEntry) : $newCashEntry;
                    $updatedGCashHist = $existingGCashHist !== '' ? ($existingGCashHist . '|' . $newGCashEntry) : $newGCashEntry;
                    $updatedMayaHist = $existingMayaHist !== '' ? ($existingMayaHist . '|' . $newMayaEntry) : $newMayaEntry;
                    $updatedInstapayHist = $existingInstapayHist !== '' ? ($existingInstapayHist . '|' . $newInstapayEntry) : $newInstapayEntry;
                    $updatedOnlineBankingHist = $existingOnlineBankingHist !== '' ? ($existingOnlineBankingHist . '|' . $newOnlineBankingEntry) : $newOnlineBankingEntry;
                    $updatedAirbnbHist = $existingAirbnbHist !== '' ? ($existingAirbnbHist . '|' . $newAirbnbEntry) : $newAirbnbEntry;

                    $histUpdateStmt = $conn->prepare("
                        UPDATE reports
                        SET payment_amount_cash_history = :cash_hist,
                            payment_amount_g_cash_history = :gcash_hist,
                            payment_amount_maya_history = :maya_hist,
                            payment_amount_instapay_history = :instapay_hist,
                            payment_amount_online_banking_history = :online_banking_hist,
                            payment_amount_airbnb_history = :airbnb_hist
                        WHERE booking_id = :booking_id
                    ");
                    $histUpdateStmt->bindParam(':cash_hist', $updatedCashHist);
                    $histUpdateStmt->bindParam(':gcash_hist', $updatedGCashHist);
                    $histUpdateStmt->bindParam(':maya_hist', $updatedMayaHist);
                    $histUpdateStmt->bindParam(':instapay_hist', $updatedInstapayHist);
                    $histUpdateStmt->bindParam(':online_banking_hist', $updatedOnlineBankingHist);
                    $histUpdateStmt->bindParam(':airbnb_hist', $updatedAirbnbHist);
                    $histUpdateStmt->bindParam(':booking_id', $booking_id_value);
                    $histUpdateStmt->execute();

                    // Mirror the same history in bookings so DB values match reports.
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
                    $histUpdateBookingStmt->bindParam(':maya_hist', $updatedMayaHist);
                    $histUpdateBookingStmt->bindParam(':booking_id', $booking_id_value);
                    $histUpdateBookingStmt->execute();
                } catch (Exception $e) {
                    error_log("Failed to append payment amount history: " . $e->getMessage());
                }
            }

            error_log("New payment recorded at: $newTimestamp");
            error_log("Updated payment_date_time: $existingPaymentDateTime");
        }

        // ── DISCOUNT HISTORY RECALCULATION REMOVED ────────────────────────────
        // The previous block was causing double-discounting and history wiping.
        // Discount is now handled by the central calculation logic.
        // ── END DISCOUNT HISTORY RECALCULATION ────────────────────────────────

        // Discount changes are stored on the booking row (discount_amount, total_amount).
        // Do NOT subtract discounts from payment_amount_*_history — those columns must
        // reflect actual cash collected (e.g. ₱500 downpayment + ₱900 balance = ₱1400).
        // Subtracting discount from the last history segment caused reports to show
        // ₱1310 (500 + 810) instead of ₱1400 when a ₱90 discount was applied.
        $oldDiscountAmount = floatval($originalBooking['old_discount_amount'] ?? 0);
        $discountDelta = $discount_amount - $oldDiscountAmount;
        if (abs($discountDelta) > 0.001) {
            error_log("=== DISCOUNT CHANGED (history left unchanged) ===");
            error_log("old_discount=$oldDiscountAmount new_discount=$discount_amount delta=$discountDelta");
            error_log("total_amount for reports: $total_amount");
            error_log("=== END DISCOUNT CHANGE ===");
        }

        // ── DISCOUNT AMOUNT HISTORY: Record timestamped discount entry ──────────
        // Format: "amount:YYYY-MM-DD HH:MM:SS|amount:YYYY-MM-DD HH:MM:SS"
        // Each entry represents the incremental discount applied at that time.
        // The sum of all amounts in history should match the current total discount.
        if (abs($discountDelta) > 0.001 && !empty($booking_id_value)) {
            try {
                $discountTimestamp = date('Y-m-d H:i:s');
                // Fetch existing history from reports
                $discHistFetch = $conn->prepare("
                    SELECT discount_amount_history FROM reports WHERE booking_id = :bid LIMIT 1
                ");
                $discHistFetch->execute([':bid' => $booking_id_value]);
                $discHistRow = $discHistFetch->fetch(PDO::FETCH_ASSOC);
                $existingDiscHist = trim((string) ($discHistRow['discount_amount_history'] ?? ''));

                $updatedEntries = $existingDiscHist !== '' ? explode('|', $existingDiscHist) : [];
                $updatedEntries[] = number_format($discountDelta, 2, '.', '') . ':' . $discountTimestamp;

                $updatedDiscHist = !empty($updatedEntries) ? implode('|', $updatedEntries) : NULL;

                // Update both reports and bookings tables
                $discHistUpdateRep = $conn->prepare("
                    UPDATE reports SET discount_amount_history = :hist WHERE booking_id = :bid
                ");
                $discHistUpdateRep->execute([':hist' => $updatedDiscHist, ':bid' => $booking_id_value]);

                $discHistUpdateBk = $conn->prepare("
                    UPDATE bookings SET discount_amount_history = :hist WHERE booking_id = :bid
                ");
                $discHistUpdateBk->execute([':hist' => $updatedDiscHist, ':bid' => $booking_id_value]);

                error_log("Discount history updated (delta=$discountDelta): $updatedDiscHist");
            } catch (Exception $e) {
                error_log("Failed to update discount_amount_history: " . $e->getMessage());
            }
        } elseif ($discount_amount <= 0 && $oldDiscountAmount > 0 && !empty($booking_id_value)) {
            // Discount was fully removed — clear the history
            try {
                $conn->prepare("UPDATE reports SET discount_amount_history = NULL WHERE booking_id = :bid")
                    ->execute([':bid' => $booking_id_value]);
                $conn->prepare("UPDATE bookings SET discount_amount_history = NULL WHERE booking_id = :bid")
                    ->execute([':bid' => $booking_id_value]);
            } catch (Exception $e) {
                error_log("Failed to clear discount_amount_history: " . $e->getMessage());
            }
        }
        // ── END DISCOUNT AMOUNT HISTORY ───────────────────────────────────────

        if ($paid_status !== 'Paid') {
            // Only wipe payment timestamps / amount history when there is truly no collected
            // money left on the booking. If the guest still has deposits (e.g. room was paid,
            // but additional_guest fee is unpaid), we must KEEP the audit trail for exports.
            $totalDepAfterUpdate = floatval($deposit_cash) + floatval($deposit_g_cash) + floatval($deposit_maya)
                + floatval($deposit_instapay ?? 0) + floatval($deposit_online_banking ?? 0) + floatval($deposit_airbnb ?? 0)
                + floatval($downpayment_cash) + floatval($downpayment_gcash) + floatval($downpayment_maya);
            if ($totalDepAfterUpdate < 0.01) {
                $existingPaymentDateTime = null;

                if (!empty($booking_id_value)) {
                    try {
                        $histClearStmt = $conn->prepare("
                            UPDATE reports
                            SET payment_amount_cash_history = NULL,
                                payment_amount_g_cash_history = NULL,
                                payment_amount_maya_history = NULL,
                                payment_amount_instapay_history = NULL,
                                payment_amount_online_banking_history = NULL,
                                payment_amount_airbnb_history = NULL
                            WHERE booking_id = :booking_id
                        ");
                        $histClearStmt->bindParam(':booking_id', $booking_id_value);
                        $histClearStmt->execute();

                        // Keep bookings history columns aligned with reports clear.
                        $histClearBookingStmt = $conn->prepare("
                            UPDATE bookings
                            SET payment_amount_cash_history = NULL,
                                payment_amount_g_cash_history = NULL,
                                payment_amount_maya_history = NULL,
                                payment_amount_instapay_history = NULL,
                                payment_amount_online_banking_history = NULL,
                                payment_amount_airbnb_history = NULL
                            WHERE booking_id = :booking_id
                        ");
                        $histClearBookingStmt->bindParam(':booking_id', $booking_id_value);
                        $histClearBookingStmt->execute();
                    } catch (Exception $e) {
                        error_log("Failed to clear payment amount history: " . $e->getMessage());
                    }
                }
            }
            // else: leave $existingPaymentDateTime as loaded from DB; do not NULL reports history
        }
        // Otherwise, preserve existing payment_date_time

        $stmt = $conn->prepare("
     UPDATE bookings 
    SET guest_name = :guest_name,
        contact_no = :contact_no,
        reason_for_stay = :reason_for_stay,
        address = :address,
        request = :request,
        check_in = :check_in,
        check_out = :check_out,
        reservation_date = :reservation_date,
        duration = :duration,
        duration_unit = :duration_unit,
        hours = :hours,
        status = :status,
        referral_name = :referral_name,
        promo = :promo,
        breakfast = :breakfast,
        breakfast_date = :breakfast_date,
        payment_status = :payment_status,
        payment_status_cash = :payment_status_cash,
        payment_status_g_cash = :payment_status_g_cash,
        payment_status_maya = :payment_status_maya,
        payment_status_instapay = :payment_status_instapay,
        payment_status_online_banking = :payment_status_online_banking,
        payment_status_airbnb = :payment_status_airbnb,
        reference_no = :reference_no,
        reference_no_g_cash = :reference_no_g_cash,
        reference_no_maya = :reference_no_maya,
        reference_no_instapay = :reference_no_instapay,
        reference_no_online_banking = :reference_no_online_banking,
        reference_no_airbnb = :reference_no_airbnb,
        change_amount = :change_amount,
        additional = :additional,
        additional_fees_items = :additional_fees_items,
        paid_status = :paid_status,
        additional_paid_status = :additional_paid_status,
        additional_guest = :additional_guest,
        additional_pet = :additional_pet,
        hygiene_kit_used = :hygiene_kit_used,
        hygiene_kit_price = :hygiene_kit_price,
        supplier = :supplier,
        room_price = :room_price,
        total_amount = :total_amount,
        additional_food = :additional_food,
        additional_items = :additional_items,
        additional_food_date = :additional_food_date,
        additional_items_date = :additional_items_date,
        additional_guest_date = :additional_guest_date,
        additional_pet_date = :additional_pet_date,
        deposit = :deposit,
        deposit_cash = :deposit_cash,
        deposit_g_cash = :deposit_g_cash,
        deposit_maya = :deposit_maya,
        deposit_instapay = :deposit_instapay,
        deposit_online_banking = :deposit_online_banking,
        deposit_airbnb = :deposit_airbnb,
        deposit_details = :deposit_details,
        deposit_gcash_ref = :deposit_gcash_ref,
        deposit_maya_ref = :deposit_maya_ref,
        deposit_instapay_ref = :deposit_instapay_ref,
        deposit_online_banking_ref = :deposit_online_banking_ref,
        deposit_airbnb_ref = :deposit_airbnb_ref,
        check_in_change_amount = :check_in_change_amount,
        extension_withdraw = :extension_withdraw,
        refund_amount_extension = :refund_amount_extension,
        downpayment_amount = :downpayment_amount,
        downpayment_cash = :downpayment_cash,
        downpayment_gcash = :downpayment_gcash,
        downpayment_maya = :downpayment_maya,
        downpayment_instapay = :downpayment_instapay,
        downpayment_online_banking = :downpayment_online_banking,
        downpayment_airbnb = :downpayment_airbnb,
        downpayment_gcash_ref = :downpayment_gcash_ref,
        downpayment_maya_ref = :downpayment_maya_ref,
        downpayment_instapay_ref = :downpayment_instapay_ref,
        downpayment_online_banking_ref = :downpayment_online_banking_ref,
        downpayment_airbnb_ref = :downpayment_airbnb_ref,
        downpayment_status = :downpayment_status,
        downpayment_date = :downpayment_date,
        discount_enabled = :discount_enabled,
        discount_type = :discount_type,
        sc_pwd_count = :sc_pwd_count,
        discount_amount = :discount_amount,
        id_number = :id_number,
        guest_type = :guest_type,
        second_guest_name = :second_guest_name,
        contact_person_name = :contact_person_name,
        number_of_adults = :number_of_adults,
        number_of_children = :number_of_children,
        tin_number = :tin_number,
        email = :email,
        payment_date_time = :payment_date_time,
        encoder = :encoder,
        vehicle_type = :vehicle_type,
        plate_number = :plate_number,
        vehicle_description = :vehicle_description,
        sales_channel = :sales_channel
    WHERE id = :booking_id
");

        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->bindParam(':guest_name', $guest_name);
        $stmt->bindParam(':contact_no', $contact_no);
        $stmt->bindParam(':reason_for_stay', $reason_for_stay);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':request', $request);

        $stmt->bindParam(':check_in', $check_in);
        $stmt->bindParam(':check_out', $check_out);
        if ($reservation_date === null || $reservation_date === '') {
            $stmt->bindValue(':reservation_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':reservation_date', $reservation_date);
        }
        $stmt->bindParam(':duration', $duration);
        $stmt->bindParam(':duration_unit', $duration_unit);
        $stmt->bindParam(':hours', $hours);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':referral_name', $referral_name);
        $stmt->bindParam(':promo', $promo);
        $stmt->bindParam(':breakfast', $breakfast);
        if ($breakfast_date === null) {
            $stmt->bindValue(':breakfast_date', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':breakfast_date', $breakfast_date);
        }
        $stmt->bindParam(':payment_status', $payment_status);

        // CRITICAL FIX: Bind payment_status columns EXACTLY like update_payment_status.php (confirmPaymentOptions)
        if ($payment_status_cash === '') {
            $stmt->bindValue(':payment_status_cash', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_status_cash', $payment_status_cash);
        }
        if ($payment_status_g_cash === '') {
            $stmt->bindValue(':payment_status_g_cash', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_status_g_cash', $payment_status_g_cash);
        }
        if ($payment_status_maya === '') {
            $stmt->bindValue(':payment_status_maya', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_status_maya', $payment_status_maya);
        }
        if ($payment_status_instapay === '') {
            $stmt->bindValue(':payment_status_instapay', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_status_instapay', $payment_status_instapay);
        }
        if ($payment_status_online_banking === '') {
            $stmt->bindValue(':payment_status_online_banking', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_status_online_banking', $payment_status_online_banking);
        }
        if ($payment_status_airbnb === '') {
            $stmt->bindValue(':payment_status_airbnb', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_status_airbnb', $payment_status_airbnb);
        }

        $stmt->bindParam(':reference_no', $reference_no);

        if ($reference_no_g_cash === '') {
            $stmt->bindValue(':reference_no_g_cash', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':reference_no_g_cash', $reference_no_g_cash);
        }
        if ($reference_no_maya === '') {
            $stmt->bindValue(':reference_no_maya', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':reference_no_maya', $reference_no_maya);
        }
        if ($reference_no_instapay === '') {
            $stmt->bindValue(':reference_no_instapay', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':reference_no_instapay', $reference_no_instapay);
        }
        if ($reference_no_online_banking === '') {
            $stmt->bindValue(':reference_no_online_banking', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':reference_no_online_banking', $reference_no_online_banking);
        }
        if ($reference_no_airbnb === '') {
            $stmt->bindValue(':reference_no_airbnb', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':reference_no_airbnb', $reference_no_airbnb);
        }

        $stmt->bindParam(':change_amount', $change_amount);

        $stmt->bindParam(':additional', $additional);
        $stmt->bindParam(':additional_fees_items', $additional_fees_items);
        $stmt->bindParam(':paid_status', $paid_status);
        $stmt->bindParam(':additional_paid_status', $additional_paid_status);
        $stmt->bindParam(':additional_guest', $additional_guest, PDO::PARAM_INT);
        $stmt->bindParam(':additional_pet', $additional_pet, PDO::PARAM_INT);
        $stmt->bindParam(':hygiene_kit_used', $hygiene_kit_used, PDO::PARAM_INT);
        $stmt->bindParam(':hygiene_kit_price', $hygiene_kit_price);
        $stmt->bindParam(':supplier', $supplier);
        $stmt->bindParam(':room_price', $room_price);
        $stmt->bindParam(':total_amount', $total_amount);

        // CRITICAL FIX: For Reserved bookings in Update mode, payment goes to DEPOSIT columns
        // (not downpayment columns) so that it can be used for check-in payment
        // Downpayment columns are preserved from original booking

        $stmt->bindParam(':deposit', $deposit);
        $stmt->bindParam(':deposit_cash', $deposit_cash);
        $stmt->bindParam(':deposit_g_cash', $deposit_g_cash);
        $stmt->bindParam(':deposit_maya', $deposit_maya);
        $stmt->bindParam(':deposit_instapay', $deposit_instapay);
        $stmt->bindParam(':deposit_online_banking', $deposit_online_banking);
        $stmt->bindParam(':deposit_airbnb', $deposit_airbnb);
        $stmt->bindParam(':deposit_details', $deposit_details);
        $stmt->bindParam(':deposit_gcash_ref', $deposit_gcash_ref);
        $stmt->bindParam(':deposit_maya_ref', $deposit_maya_ref);
        $stmt->bindParam(':deposit_instapay_ref', $deposit_instapay_ref);
        $stmt->bindParam(':deposit_online_banking_ref', $deposit_online_banking_ref);
        $stmt->bindParam(':deposit_airbnb_ref', $deposit_airbnb_ref);
        $stmt->bindParam(':check_in_change_amount', $check_in_change_amount);
        $stmt->bindParam(':extension_withdraw', $extension_withdraw, PDO::PARAM_INT);
        $stmt->bindParam(':refund_amount_extension', $refund_amount_extension);

        // Bind downpayment fields (preserve from original booking - don't overwrite)
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

        // Bind discount parameters
        $stmt->bindParam(':discount_enabled', $discount_enabled, PDO::PARAM_INT);
        $stmt->bindParam(':discount_type', $discount_type);
        $stmt->bindParam(':sc_pwd_count', $sc_pwd_count, PDO::PARAM_INT);
        $stmt->bindParam(':sc_pwd_count', $sc_pwd_count, PDO::PARAM_INT);
        $stmt->bindParam(':discount_amount', $discount_amount);
        $stmt->bindParam(':id_number', $id_number);

        $stmt->bindParam(':guest_type', $guest_type);
        $stmt->bindParam(':second_guest_name', $second_guest_name);
        $stmt->bindParam(':contact_person_name', $contact_person_name);
        $stmt->bindParam(':number_of_adults', $number_of_adults, PDO::PARAM_INT);
        $stmt->bindParam(':number_of_children', $number_of_children, PDO::PARAM_INT);
        $stmt->bindParam(':tin_number', $tin_number);
        $stmt->bindParam(':email', $email);

        // Bind vehicle details
        $stmt->bindParam(':vehicle_type', $vehicle_type);
        $stmt->bindParam(':plate_number', $plate_number);
        $stmt->bindParam(':vehicle_description', $vehicle_description);

        // Bind sales channel
        $stmt->bindParam(':sales_channel', $sales_channel);

        // Preserve existing encoder(s) and append current user if new
        $mergedEncoder = mergeEncoderNames($originalBooking['encoder'] ?? '', $encoder);
        $stmt->bindParam(':encoder', $mergedEncoder);
        if ($existingPaymentDateTime === null) {
            $stmt->bindValue(':payment_date_time', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':payment_date_time', $existingPaymentDateTime);
        }

        error_log("=== ABOUT TO EXECUTE UPDATE WITH DISCOUNT DATA ===");
        error_log("discount_enabled value: " . var_export($discount_enabled, true));
        error_log("discount_type value: " . var_export($discount_type, true));
        error_log("sc_pwd_count value: " . var_export($sc_pwd_count, true));
        error_log("discount_amount value: " . var_export($discount_amount, true));
        error_log("=== ADDITIONAL ITEMS DEBUG ===");
        error_log("additional_food: " . var_export($additional_food, true));
        error_log("additional_items: " . var_export($additional_items, true));
        error_log("additional_paid_status: " . var_export($additional_paid_status, true));
        error_log("=== END PRE-UPDATE DEBUG ===");

        // Bind additional_food/additional_items with NULL handling before executing
        if ($additional_food === null) {
            $stmt->bindValue(':additional_food', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':additional_food', $additional_food);
        }
        if ($additional_items === null) {
            $stmt->bindValue(':additional_items', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':additional_items', $additional_items);
        }

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

        if ($stmt->execute()) {
            error_log("=== UPDATE EXECUTED SUCCESSFULLY ===");

            // Handle withdraw extension (last segment only when extension_stack is present)
            if (!empty($withdraw_extension)) {
                $curExtHours = intval($originalBooking['extend_hours'] ?? 0);
                $curExtMinutes = intval($originalBooking['extend_minutes'] ?? 0);
                $curExtPrice = floatval($originalBooking['extend_price'] ?? 0);
                $curExtRegRate = floatval($originalBooking['extend_regular_rate'] ?? 0);
                $curExtBunRate = floatval($originalBooking['extend_bundle_rate'] ?? 0);
$curExtBfast = $originalBooking['extend_bundle_breakfast'] ?? null;
                $curExtGuest = intval($originalBooking['extend_additional_guest'] ?? 0);

                if (is_array($withdraw_popped_segment)) {
                    $pH = intval($withdraw_popped_segment['h'] ?? 0);
                    $pM = intval($withdraw_popped_segment['m'] ?? 0);
                    $pP = floatval($withdraw_popped_segment['price'] ?? 0);
                    $pReg = floatval($withdraw_popped_segment['reg'] ?? 0);
                    $pBun = floatval($withdraw_popped_segment['bun'] ?? 0);
                    $pBf = isset($withdraw_popped_segment['bf']) ? $withdraw_popped_segment['bf'] : null;
                    $pEg = intval($withdraw_popped_segment['eg'] ?? 0);
                } else {
                    $pH = $curExtHours;
                    $pM = $curExtMinutes;
                    $pP = $curExtPrice;
                    $pReg = $curExtRegRate;
                    $pBun = $curExtBunRate;
                    $pBf = $curExtBfast;
                    $pEg = $curExtGuest;
                }

                // Refund for deposit deduction must be THIS segment's total price ($pP + guests fee) — the same amount
                // we add to withdrawn_extend_price ($newWP = prev + $pP). Do NOT use cumulative
                // withdrawn_extend_price from the row to reduce deposit (would double-deduct on a
                // second withdraw). If POST omitted withdraw_refund_amount, infer from segment cost when paid.
                $segmentCost = $pP + ($pEg * 300);
                if ($withdraw_refund_amount < 0.009 && $segmentCost > 0.009) {
                    $ps = strcasecmp(trim((string) ($originalBooking['paid_status'] ?? '')), 'Paid') === 0;
                    $collected = floatval($originalBooking['deposit'] ?? 0) + floatval($originalBooking['downpayment_amount'] ?? 0);
                    if ($ps || $collected >= $segmentCost - 0.02) {
                        $withdraw_refund_amount = $segmentCost;
                        $refund_amount_extension = $segmentCost;
                        error_log('=== EXTENSION WITHDRAW: withdraw_refund_amount inferred from segment cost=' . $segmentCost . ' (POST missing; matches withdrawn_extend_price delta) ===');
                    }
                }

                $prevWH = intval($originalBooking['withdrawn_extend_hours'] ?? 0);
                $prevWM = intval($originalBooking['withdrawn_extend_minutes'] ?? 0);
                $prevWP = floatval($originalBooking['withdrawn_extend_price'] ?? 0);
                $prevWR = floatval($originalBooking['withdrawn_extend_regular_rate'] ?? 0);
                $prevWB = floatval($originalBooking['withdrawn_extend_bundle_rate'] ?? 0);
                $prevWBF = $originalBooking['withdrawn_extend_bundle_breakfast'] ?? null;

                $newWH = $prevWH + $pH;
                $newWM = $prevWM + $pM;
                $newWP = $prevWP + $pP;
                $newWR = $prevWR + $pReg;
                $newWB = $prevWB + $pBun;
                $newWBF = $prevWBF;
                if ($pBf && trim((string) $pBf) !== '' && trim((string) $pBf) !== 'None') {
                    if ($newWBF && trim((string) $newWBF) !== '' && trim((string) $newWBF) !== 'None') {
                        $newWBF = $newWBF . ' | ' . $pBf;
                    } else {
                        $newWBF = $pBf;
                    }
                }

                $remDecoded = booking_extension_stack_decode($withdraw_stack_json_after);
                $remAgg = booking_extension_stack_aggregate_segments($remDecoded);
                $encStackAfter = booking_extension_stack_encode($remDecoded);

                $withdrawExtStmt = $conn->prepare("
                    UPDATE bookings
                    SET extension_withdraw = 1,
                        refund_amount_extension = :refund,
                        withdrawn_extend_hours = :wh,
                        withdrawn_extend_minutes = :wm,
                        withdrawn_extend_price = :wp,
                        withdrawn_extend_regular_rate = :wr,
                        withdrawn_extend_bundle_rate = :wb,
                        withdrawn_extend_bundle_breakfast = :wbf,
                        extend_hours = :eh,
                        extend_minutes = :em,
                        extend_price = :ep,
                        extend_regular_rate = :ereg,
                        extend_bundle_rate = :ebun,
                        extend_bundle_breakfast = :ebf,
                        extend_additional_guest = :eag,
                        extension_stack = :estack
                    WHERE id = :id
                ");
                $ebfVal = $remAgg['bf'];
                $withdrawExtStmt->execute([
                    ':id' => $booking_id,
                    ':refund' => $withdraw_refund_amount,
                    ':wh' => $newWH,
                    ':wm' => $newWM,
                    ':wp' => $newWP,
                    ':wr' => $newWR,
                    ':wb' => $newWB,
                    ':wbf' => $newWBF,
                    ':eh' => $remAgg['h'],
                    ':em' => $remAgg['m'],
                    ':ep' => $remAgg['price'],
                    ':ereg' => $remAgg['reg'],
                    ':ebun' => $remAgg['bun'],
                    ':ebf' => $ebfVal,
                    ':eag' => $remAgg['eg'],
                    ':estack' => $encStackAfter,
                ]);

                if (!empty($booking_id_value)) {
                    $withdrawRepStmt = $conn->prepare("
                        UPDATE reports
                        SET extension_withdraw = 1,
                            refund_amount_extension = :refund,
                            withdrawn_extend_hours = :wh,
                            withdrawn_extend_minutes = :wm,
                            withdrawn_extend_price = :wp,
                            withdrawn_extend_regular_rate = :wr,
                            withdrawn_extend_bundle_rate = :wb,
                            withdrawn_extend_bundle_breakfast = :wbf,
                            extend_hours = :eh,
                            extend_minutes = :em,
                            extend_price = :ep,
                            extend_regular_rate = :ereg,
                            extend_bundle_rate = :ebun,
                            extend_bundle_breakfast = :ebf,
                            extend_additional_guest = :eag,
                            extension_stack = :estack
                        WHERE booking_id = :bid
                    ");
                    $withdrawRepStmt->execute([
                        ':bid' => $booking_id_value,
                        ':refund' => $withdraw_refund_amount,
                        ':wh' => $newWH,
                        ':wm' => $newWM,
                        ':wp' => $newWP,
                        ':wr' => $newWR,
                        ':wb' => $newWB,
                        ':wbf' => $newWBF,
                        ':eh' => $remAgg['h'],
                        ':em' => $remAgg['m'],
                        ':ep' => $remAgg['price'],
                        ':ereg' => $remAgg['reg'],
                        ':ebun' => $remAgg['bun'],
                        ':ebf' => $ebfVal,
                        ':eag' => $remAgg['eg'],
                        ':estack' => $encStackAfter,
                    ]);
                }

                // If customer already paid the extension and a refund is due,
                // deduct the refund from the stored deposit so records stay accurate.
                if ($withdraw_refund_amount > 0) {
                    error_log("=== EXTENSION REFUND: deducting {$withdraw_refund_amount} from deposit ===");

                    // Fetch current deposit breakdown so we can subtract proportionally
                    $getDepStmt = $conn->prepare("SELECT deposit, deposit_cash, deposit_g_cash, deposit_maya, deposit_instapay, deposit_online_banking, deposit_airbnb FROM bookings WHERE id = :id");
                    $getDepStmt->execute([':id' => $booking_id]);
                    $depRow = $getDepStmt->fetch(PDO::FETCH_ASSOC);

                    $curDeposit = floatval($depRow['deposit'] ?? 0);
                    $curDepCash = floatval($depRow['deposit_cash'] ?? 0);
                    $curDepGCash = floatval($depRow['deposit_g_cash'] ?? 0);
                    $curDepMaya = floatval($depRow['deposit_maya'] ?? 0);
                    $curDepInstapay = floatval($depRow['deposit_instapay'] ?? 0);
                    $curDepOnlineBanking = floatval($depRow['deposit_online_banking'] ?? 0);
                    $curDepAirbnb = floatval($depRow['deposit_airbnb'] ?? 0);

                    // Subtract refund from the total deposit
                    $newDeposit = max(0, $curDeposit - $withdraw_refund_amount);

                    // Proportionally reduce breakdown columns
                    $ratio = ($curDeposit > 0) ? ($newDeposit / $curDeposit) : 0;
                    $newDepCash = round($curDepCash * $ratio, 2);
                    $newDepGCash = round($curDepGCash * $ratio, 2);
                    $newDepMaya = round($curDepMaya * $ratio, 2);
                    $newDepInstapay = round($curDepInstapay * $ratio, 2);
                    $newDepOnlineBanking = round($curDepOnlineBanking * $ratio, 2);
                    $newDepAirbnb = round($curDepAirbnb * $ratio, 2);

                    $refundDepStmt = $conn->prepare(
                        "UPDATE bookings
                         SET deposit        = :dep,
                             deposit_cash   = :dep_cash,
                             deposit_g_cash = :dep_gcash,
                             deposit_maya   = :dep_maya,
                             deposit_instapay = :dep_instapay,
                             deposit_online_banking = :dep_online_banking,
                             deposit_airbnb = :dep_airbnb,
                             change_amount  = change_amount + :refund
                         WHERE id = :id"
                    );
                    $refundDepStmt->execute([
                        ':dep' => $newDeposit,
                        ':dep_cash' => $newDepCash,
                        ':dep_gcash' => $newDepGCash,
                        ':dep_maya' => $newDepMaya,
                        ':dep_instapay' => $newDepInstapay,
                        ':dep_online_banking' => $newDepOnlineBanking,
                        ':dep_airbnb' => $newDepAirbnb,
                        ':refund' => $withdraw_refund_amount,
                        ':id' => $booking_id
                    ]);

                    if (!empty($booking_id_value)) {
                        $refundRepStmt = $conn->prepare(
                            "UPDATE reports
                             SET deposit        = :dep,
                                 deposit_cash   = :dep_cash,
                                 deposit_g_cash = :dep_gcash,
                                 deposit_maya   = :dep_maya,
                                 deposit_instapay = :dep_instapay,
                                 deposit_online_banking = :dep_online_banking,
                                 deposit_airbnb = :dep_airbnb,
                                 change_amount  = change_amount + :refund
                             WHERE booking_id = :bid"
                        );
                        $refundRepStmt->execute([
                            ':dep' => $newDeposit,
                            ':dep_cash' => $newDepCash,
                            ':dep_gcash' => $newDepGCash,
                            ':dep_maya' => $newDepMaya,
                            ':dep_instapay' => $newDepInstapay,
                            ':dep_online_banking' => $newDepOnlineBanking,
                            ':dep_airbnb' => $newDepAirbnb,
                            ':refund' => $withdraw_refund_amount,
                            ':bid' => $booking_id_value
                        ]);
                    }

                    error_log("=== EXTENSION REFUND applied: new deposit={$newDeposit}, change_amount +{$withdraw_refund_amount} ===");

                    // CRITICAL FIX: Remove the last payment from payment history
                    // When an extension is withdrawn, the last payment (extension payment) should be removed
                    error_log("=== REMOVING LAST PAYMENT FROM PAYMENT HISTORY ===");

                    $getHistStmt = $conn->prepare("
                        SELECT 
                            payment_date_time,
                            payment_amount_cash_history,
                            payment_amount_g_cash_history,
                            payment_amount_maya_history,
                            payment_amount_instapay_history,
                            payment_amount_online_banking_history,
                            payment_amount_airbnb_history
                        FROM bookings
                        WHERE id = :id
                    ");
                    $getHistStmt->execute([':id' => $booking_id]);
                    $histRow = $getHistStmt->fetch(PDO::FETCH_ASSOC);

                    // Helper function to remove last segment from pipe-delimited string
                    $removeLastSegment = function ($historyString) {
                        if (empty($historyString)) {
                            return null;
                        }
                        $segments = explode('|', $historyString);
                        if (count($segments) <= 1) {
                            return null; // Only one payment, removing it leaves nothing
                        }
                        array_pop($segments); // Remove last segment
                        return implode('|', $segments);
                    };

                    $newPaymentDateTime = $removeLastSegment($histRow['payment_date_time']);
                    $newCashHistory = $removeLastSegment($histRow['payment_amount_cash_history']);
                    $newGCashHistory = $removeLastSegment($histRow['payment_amount_g_cash_history']);
                    $newMayaHistory = $removeLastSegment($histRow['payment_amount_maya_history']);
                    $newInstapayHistory = $removeLastSegment($histRow['payment_amount_instapay_history']);
                    $newOnlineBankingHistory = $removeLastSegment($histRow['payment_amount_online_banking_history']);
                    $newAirbnbHistory = $removeLastSegment($histRow['payment_amount_airbnb_history']);

                    error_log("Old payment_date_time: " . ($histRow['payment_date_time'] ?? 'NULL'));
                    error_log("New payment_date_time: " . ($newPaymentDateTime ?? 'NULL'));
                    error_log("Old cash_history: " . ($histRow['payment_amount_cash_history'] ?? 'NULL'));
                    error_log("New cash_history: " . ($newCashHistory ?? 'NULL'));

                    // Update bookings table
                    $updateHistStmt = $conn->prepare("
                        UPDATE bookings
                        SET payment_date_time = :payment_date_time,
                            payment_amount_cash_history = :cash_hist,
                            payment_amount_g_cash_history = :gcash_hist,
                            payment_amount_maya_history = :maya_hist,
                            payment_amount_instapay_history = :instapay_hist,
                            payment_amount_online_banking_history = :online_banking_hist,
                            payment_amount_airbnb_history = :airbnb_hist
                        WHERE id = :id
                    ");
                    $updateHistStmt->execute([
                        ':payment_date_time' => $newPaymentDateTime,
                        ':cash_hist' => $newCashHistory,
                        ':gcash_hist' => $newGCashHistory,
                        ':maya_hist' => $newMayaHistory,
                        ':instapay_hist' => $newInstapayHistory,
                        ':online_banking_hist' => $newOnlineBankingHistory,
                        ':airbnb_hist' => $newAirbnbHistory,
                        ':id' => $booking_id
                    ]);

                    // Update reports table if exists
                    if (!empty($booking_id_value)) {
                        $updateHistRepStmt = $conn->prepare("
                            UPDATE reports
                            SET payment_date_time = :payment_date_time,
                                payment_amount_cash_history = :cash_hist,
                                payment_amount_g_cash_history = :gcash_hist,
                                payment_amount_maya_history = :maya_hist,
                                payment_amount_instapay_history = :instapay_hist,
                                payment_amount_online_banking_history = :online_banking_hist,
                                payment_amount_airbnb_history = :airbnb_hist
                            WHERE booking_id = :bid
                        ");
                        $updateHistRepStmt->execute([
                            ':payment_date_time' => $newPaymentDateTime,
                            ':cash_hist' => $newCashHistory,
                            ':gcash_hist' => $newGCashHistory,
                            ':maya_hist' => $newMayaHistory,
                            ':instapay_hist' => $newInstapayHistory,
                            ':online_banking_hist' => $newOnlineBankingHistory,
                            ':airbnb_hist' => $newAirbnbHistory,
                            ':bid' => $booking_id_value
                        ]);
                    }

                    error_log("=== PAYMENT HISTORY UPDATED ===");

                    // CRITICAL FIX: Recalculate total_amount after extension withdrawal
                    // The total amount should reflect the reduced extension price
                    $newExtendPrice = $remAgg['price']; // This is the remaining extension price after withdrawal
                    $newExtendAdditionalGuest = $remAgg['eg']; // Remaining extended guests after withdrawal

                    // Recalculate the full booking amount with the new extension price
                    $newRoomTotal = computeBookingTotalAmount([
                        'room_type' => $room_type,
                        'duration' => $duration,
                        'duration_unit' => $duration_unit,
                        'promo' => $promo,
                        'breakfast' => $breakfast,
                        'hygiene_kit_used' => $hygiene_kit_used,
                        'hygiene_kit_price' => $hygiene_kit_price,
                        'room_price' => $room_price,
                        'extend_price' => $newExtendPrice, // Use the new reduced extension price
                        'extend_additional_guest' => $newExtendAdditionalGuest,
                        'deposit' => 0  // Don't deduct deposit to get full amount
                    ]);

                    // Recalculate full booking amount after discount with new extension price
                    $newFullBookingAmount = $newRoomTotal + $guest_charges + $pet_charges + $additional_items_total;
                    $newFullBookingAmountAfterDiscount = $newFullBookingAmount - $discount_amount;

                    // Recalculate amount due with new deposit (after refund)
                    $newAmountDue = $newFullBookingAmountAfterDiscount - ($downpayment_amount + $newDeposit);

                    // Update total_amount to reflect the new full booking amount after discount
                    $total_amount = $newFullBookingAmountAfterDiscount;

                    error_log("=== RECALCULATED TOTAL AMOUNT AFTER EXTENSION WITHDRAWAL ===");
                    error_log("newExtendPrice: " . $newExtendPrice);
                    error_log("newRoomTotal: " . $newRoomTotal);
                    error_log("newFullBookingAmount: " . $newFullBookingAmount);
                    error_log("newFullBookingAmountAfterDiscount: " . $newFullBookingAmountAfterDiscount);
                    error_log("newDeposit (after refund): " . $newDeposit);
                    error_log("newAmountDue: " . $newAmountDue);
                    error_log("Updated total_amount: " . $total_amount);
                    error_log("=== END RECALCULATION ===");

                    // Update the total_amount in both bookings and reports tables
                    $updateTotalStmt = $conn->prepare("UPDATE bookings SET total_amount = :total_amount WHERE id = :id");
                    $updateTotalStmt->execute([':total_amount' => $total_amount, ':id' => $booking_id]);

                    if (!empty($booking_id_value)) {
                        $updateReportsTotalStmt = $conn->prepare("UPDATE reports SET total_amount = :total_amount WHERE booking_id = :booking_id");
                        $updateReportsTotalStmt->execute([':total_amount' => $total_amount, ':booking_id' => $booking_id_value]);
                    }
                }
            }

            // Sync reports table with the latest booking details
            try {
                if ($booking_id_value) {
                    $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
                    $hasReportsTable = $checkReportsTable->rowCount() > 0;

                    if ($hasReportsTable) {
                        $isExtended = ($duration > $originalDuration);

                        // Get additional fees from bookings table
                        $getAdditionalFeesStmt = $conn->prepare("SELECT missing_items_list, missing_items_fees, additional_fees_status, additional_fees_payment_method, additional_fees_reference_no FROM bookings WHERE id = :booking_id");
                        $getAdditionalFeesStmt->bindParam(':booking_id', $booking_id);
                        $getAdditionalFeesStmt->execute();
                        $additionalFeesData = $getAdditionalFeesStmt->fetch(PDO::FETCH_ASSOC);
                        $missing_items_list = $additionalFeesData['missing_items_list'] ?? null;
                        $missing_items_fees = floatval($additionalFeesData['missing_items_fees'] ?? 0);
                        $additional_fees_status = $additionalFeesData['additional_fees_status'] ?? 'None';
                        $additional_fees_payment_method = $additionalFeesData['additional_fees_payment_method'] ?? null;
                        $additional_fees_reference_no = $additionalFeesData['additional_fees_reference_no'] ?? null;

                        $updateReportsStmt = $conn->prepare("
                        UPDATE reports 
                        SET guest_name = :guest_name,
                            reason_for_stay = :reason_for_stay,
                            address = :address,
                            request = :request,
                            check_in = :check_in,
                            check_out = :check_out,
                            reservation_date = :reservation_date,
                            duration = :duration,
                            duration_unit = :duration_unit,
                            hours = :hours,
                            status = :status,
                            promo = :promo,
                            breakfast = :breakfast,
                            breakfast_date = :breakfast_date,
                            payment_status = :payment_status,
                            reference_no = :reference_no,
                            referral_name = :referral_name,
                            supplier = :supplier,
                            additional = :additional,
                            additional_guest = :additional_guest,
                            additional_pet = :additional_pet,
                            additional_food = :additional_food,
                            additional_items = :additional_items,
                            additional_food_date = :additional_food_date,
                            additional_items_date = :additional_items_date,
                            additional_guest_date = :additional_guest_date,
                            additional_pet_date = :additional_pet_date,
                            paid_status = :paid_status,
                            total_amount = :total_amount,
                            hygiene_kit_used = :hygiene_kit_used,
                            hygiene_kit_price = :hygiene_kit_price,
                            room_price = :room_price,
                            missing_items_list = :missing_items_list,
                            missing_items_fees = :missing_items_fees,
                            additional_fees_status = :additional_fees_status,
                            additional_fees_payment_method = :additional_fees_payment_method,
                            additional_fees_reference_no = :additional_fees_reference_no,
                            extended_time = :extended_time,
                            original_duration = :original_duration,
                            deposit_cash = :deposit_cash,
                            deposit_g_cash = :deposit_g_cash,
                            deposit_maya = :deposit_maya,
                            deposit_instapay = :deposit_instapay,
                            deposit_online_banking = :deposit_online_banking,
                            deposit_airbnb = :deposit_airbnb,
                            downpayment_amount = :downpayment_amount,
                            downpayment_cash = :downpayment_cash,
                            downpayment_gcash = :downpayment_gcash,
                            downpayment_maya = :downpayment_maya,
                            downpayment_instapay = :downpayment_instapay,
                            downpayment_online_banking = :downpayment_online_banking,
                            downpayment_airbnb = :downpayment_airbnb,
                            payment_status_cash = :payment_status_cash,
                            payment_status_instapay = :payment_status_instapay,
                            payment_status_online_banking = :payment_status_online_banking,
                            payment_status_airbnb = :payment_status_airbnb,
                            payment_date_time = :payment_date_time,
                            extension_withdraw = :extension_withdraw,
                            refund_amount_extension = :refund_amount_extension,
                            discount_enabled = :discount_enabled,
                            discount_type = :discount_type,
                            sc_pwd_count = :sc_pwd_count,
                            discount_amount = :discount_amount,
                            id_number = :id_number,
                            encoder = :encoder
                        WHERE booking_id = :booking_id
                    ");
                        $updateReportsStmt->bindParam(':guest_name', $guest_name);
                        $updateReportsStmt->bindParam(':reason_for_stay', $reason_for_stay);
                        $updateReportsStmt->bindParam(':address', $address);
                        $updateReportsStmt->bindParam(':request', $request);
                        $updateReportsStmt->bindParam(':check_in', $check_in);
                        $updateReportsStmt->bindParam(':check_out', $check_out);
                        $updateReportsStmt->bindParam(':reservation_date', $reservation_date);
                        $updateReportsStmt->bindParam(':duration', $duration);
                        $updateReportsStmt->bindParam(':duration_unit', $duration_unit);
                        $updateReportsStmt->bindParam(':hours', $hours);
                        $updateReportsStmt->bindParam(':status', $status);
                        $updateReportsStmt->bindParam(':promo', $promo);
                        $updateReportsStmt->bindParam(':breakfast', $breakfast);
                        if ($breakfast_date === null) {
                            $updateReportsStmt->bindValue(':breakfast_date', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':breakfast_date', $breakfast_date);
                        }
                        $updateReportsStmt->bindParam(':payment_status', $payment_status);
                        $updateReportsStmt->bindParam(':reference_no', $reference_no);
                        $updateReportsStmt->bindParam(':referral_name', $referral_name);
                        $updateReportsStmt->bindParam(':supplier', $supplier);
                        $updateReportsStmt->bindParam(':additional', $additional);
                        $updateReportsStmt->bindParam(':additional_guest', $additional_guest, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':additional_pet', $additional_pet, PDO::PARAM_INT);
                        if ($additional_food === null) {
                            $updateReportsStmt->bindValue(':additional_food', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_food', $additional_food);
                        }
                        if ($additional_items === null) {
                            $updateReportsStmt->bindValue(':additional_items', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_items', $additional_items);
                        }

                        // Bind additional date columns
                        if ($additional_food_date === null) {
                            $updateReportsStmt->bindValue(':additional_food_date', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_food_date', $additional_food_date);
                        }
                        if ($additional_items_date === null) {
                            $updateReportsStmt->bindValue(':additional_items_date', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_items_date', $additional_items_date);
                        }
                        if ($additional_guest_date === null) {
                            $updateReportsStmt->bindValue(':additional_guest_date', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_guest_date', $additional_guest_date);
                        }
                        if ($additional_pet_date === null) {
                            $updateReportsStmt->bindValue(':additional_pet_date', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':additional_pet_date', $additional_pet_date);
                        }

                        $updateReportsStmt->bindParam(':paid_status', $paid_status);
                        $updateReportsStmt->bindParam(':total_amount', $total_amount);
                        $updateReportsStmt->bindParam(':hygiene_kit_used', $hygiene_kit_used, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':hygiene_kit_price', $hygiene_kit_price);
                        $updateReportsStmt->bindParam(':room_price', $room_price);
                        $updateReportsStmt->bindParam(':missing_items_list', $missing_items_list);
                        $updateReportsStmt->bindParam(':missing_items_fees', $missing_items_fees);
                        $updateReportsStmt->bindParam(':additional_fees_status', $additional_fees_status);
                        $updateReportsStmt->bindParam(':additional_fees_payment_method', $additional_fees_payment_method);
                        $updateReportsStmt->bindParam(':additional_fees_reference_no', $additional_fees_reference_no);
                        $extendedFlag = $isExtended ? 1 : 0;
                        $updateReportsStmt->bindParam(':extended_time', $extendedFlag, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':original_duration', $originalDuration);
                        $updateReportsStmt->bindParam(':deposit_cash', $original_deposit_cash);
                        $updateReportsStmt->bindParam(':deposit_g_cash', $original_deposit_g_cash);
                        $updateReportsStmt->bindParam(':deposit_maya', $original_deposit_maya);
                        $updateReportsStmt->bindParam(':deposit_instapay', $original_deposit_instapay);
                        $updateReportsStmt->bindParam(':deposit_online_banking', $original_deposit_online_banking);
                        $updateReportsStmt->bindParam(':deposit_airbnb', $original_deposit_airbnb);
                        $updateReportsStmt->bindParam(':downpayment_amount', $downpayment_amount);
                        $updateReportsStmt->bindParam(':downpayment_cash', $downpayment_cash);
                        $updateReportsStmt->bindParam(':downpayment_gcash', $downpayment_gcash);
                        $updateReportsStmt->bindParam(':downpayment_maya', $downpayment_maya);
                        $updateReportsStmt->bindParam(':downpayment_instapay', $downpayment_instapay);
                        $updateReportsStmt->bindParam(':downpayment_online_banking', $downpayment_online_banking);
                        $updateReportsStmt->bindParam(':downpayment_airbnb', $downpayment_airbnb);
                        // Sync per-method payment_status columns so export reports use the correct label
                        if ($payment_status_cash === '') {
                            $updateReportsStmt->bindValue(':payment_status_cash', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_status_cash', $payment_status_cash);
                        }
                        if ($payment_status_instapay === '') {
                            $updateReportsStmt->bindValue(':payment_status_instapay', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_status_instapay', $payment_status_instapay);
                        }
                        if ($payment_status_online_banking === '') {
                            $updateReportsStmt->bindValue(':payment_status_online_banking', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_status_online_banking', $payment_status_online_banking);
                        }
                        if ($payment_status_airbnb === '') {
                            $updateReportsStmt->bindValue(':payment_status_airbnb', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_status_airbnb', $payment_status_airbnb);
                        }
                        if ($existingPaymentDateTime === null) {
                            $updateReportsStmt->bindValue(':payment_date_time', null, PDO::PARAM_NULL);
                        } else {
                            $updateReportsStmt->bindParam(':payment_date_time', $existingPaymentDateTime);
                        }
                        $updateReportsStmt->bindParam(':extension_withdraw', $extension_withdraw, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':refund_amount_extension', $refund_amount_extension);
                        $updateReportsStmt->bindParam(':discount_enabled', $discount_enabled, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':discount_type', $discount_type);
                        $updateReportsStmt->bindParam(':sc_pwd_count', $sc_pwd_count, PDO::PARAM_INT);
                        $updateReportsStmt->bindParam(':discount_amount', $discount_amount);
                        $updateReportsStmt->bindParam(':id_number', $id_number);
                        $updateReportsStmt->bindParam(':encoder', $mergedEncoder);
                        $updateReportsStmt->bindParam(':booking_id', $booking_id_value);

                        error_log("=== UPDATING REPORTS TABLE ===");
                        error_log("booking_id: " . $booking_id_value);
                        error_log("check_in: " . ($check_in ?? 'NULL'));
                        error_log("check_out: " . ($check_out ?? 'NULL'));
                        error_log("duration: " . $duration);
                        error_log("hours: " . ($hours ?? 'NULL'));
                        error_log("=== END REPORTS UPDATE DEBUG ===");

                        $updateReportsStmt->execute();

                        error_log("Reports table updated successfully for booking_id: " . $booking_id_value);
                    }
                }
            } catch (PDOException $e) {
                error_log("Failed to update reports table: " . $e->getMessage());
            }

            // Update room status based on booking status
            try {
                // Get room_id from booking
                $getRoomStmt = $conn->prepare("SELECT room_id, status FROM bookings WHERE id = :booking_id");
                $getRoomStmt->bindParam(':booking_id', $booking_id);
                $getRoomStmt->execute();
                $booking = $getRoomStmt->fetch(PDO::FETCH_ASSOC);

                if ($booking && isset($booking['room_id'])) {
                    $room_id = $booking['room_id'];
                    $booking_status = $booking['status']; // Get the UPDATED status from database

                    // Map booking status to room status
                    $room_status = 'Available';
                    if ($booking_status === 'Reserved') {
                        $room_status = 'Reserved';
                    } elseif ($booking_status === 'Confirming') {
                        $room_status = 'Confirming';
                    } elseif ($booking_status === 'Confirmed' || $booking_status === 'Occupied') {
                        $room_status = 'Occupied';
                    }

                    error_log("=== ROOM STATUS UPDATE DEBUG ===");
                    error_log("Booking ID: " . $booking_id);
                    error_log("Room ID: " . $room_id);
                    error_log("Booking Status from DB: " . $booking_status);
                    error_log("Setting Room Status to: " . $room_status);

                    $updateRoomStmt = $conn->prepare("UPDATE rooms SET status = :room_status WHERE room_id = :room_id");
                    $updateRoomStmt->bindParam(':room_status', $room_status);
                    $updateRoomStmt->bindParam(':room_id', $room_id);
                    $updateRoomStmt->execute();

                    error_log("Room status updated successfully");
                }
            } catch (PDOException $e) {
                // Log error but don't fail the booking update
                error_log("Failed to update room status: " . $e->getMessage());
            }

            $response['success'] = true;
            $response['message'] = 'Booking updated successfully!';
            $response['paid_status'] = $paid_status; // Return calculated paid_status to frontend
            $response['total_amount'] = $total_amount;
            $response['room_price'] = $room_price;
        } else {
            $response['message'] = 'Failed to update booking!';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method!';
}


ob_clean();
echo json_encode($response);
ob_end_flush();
exit;
?>