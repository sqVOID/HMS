<?php
// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

try {
    require_once 'config.php';

    // Check if searching by booking ID or date range
    $bookingId = isset($_GET['booking_id']) ? trim($_GET['booking_id']) : '';
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    // Legacy support for single date filter
    $filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';

    if (empty($bookingId) && empty($filterDate) && empty($dateFrom) && empty($dateTo)) {
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'No search criteria provided',
            'modifications' => []
        ]);
        ob_end_flush();
        exit;
    }

    // Build dynamic WHERE conditions
    $bookingsWhere = ["(status IS NULL OR status != 'Checked Out')"];
    $reportsWhere = ["status = 'Checked Out'"];
    $params = [];

    if (!empty($bookingId)) {
        // IMPORTANT:
        // `booking_id` is used by the Edit modal to fetch a *single* record.
        // Using LIKE here can return multiple rows (e.g., "2324" matches "2324" and "23245"),
        // which makes the modal load the "first" result and causes the wrong booking to be updated.
        //
        // Use exact matching for booking lookups.
        $bookingsWhere[] = "(CAST(id AS CHAR) = :bid1 OR CAST(COALESCE(booking_id,'') AS CHAR) = :bid2 OR CAST(room_id AS CHAR) = :bid3)";
        $reportsWhere[]  = "(CAST(id AS CHAR) = :bid4 OR CAST(COALESCE(booking_id,'') AS CHAR) = :bid5 OR CAST(room_id AS CHAR) = :bid6)";
        $params['bid1'] = $bookingId;
        $params['bid2'] = $bookingId;
        $params['bid3'] = $bookingId;
        $params['bid4'] = $bookingId;
        $params['bid5'] = $bookingId;
        $params['bid6'] = $bookingId;
    }

    // Handle date range filtering
    if (!empty($dateFrom) && !empty($dateTo)) {
        // Both dates provided - filter between dates
        $bookingsWhere[] = "DATE(check_in) BETWEEN :date_from1 AND :date_to1";
        $reportsWhere[] = "DATE(check_in) BETWEEN :date_from2 AND :date_to2";
        $params['date_from1'] = $dateFrom;
        $params['date_to1'] = $dateTo;
        $params['date_from2'] = $dateFrom;
        $params['date_to2'] = $dateTo;
    } elseif (!empty($dateFrom)) {
        // Only from date - filter from date onwards
        $bookingsWhere[] = "DATE(check_in) >= :date_from1";
        $reportsWhere[] = "DATE(check_in) >= :date_from2";
        $params['date_from1'] = $dateFrom;
        $params['date_from2'] = $dateFrom;
    } elseif (!empty($dateTo)) {
        // Only to date - filter up to date
        $bookingsWhere[] = "DATE(check_in) <= :date_to1";
        $reportsWhere[] = "DATE(check_in) <= :date_to2";
        $params['date_to1'] = $dateTo;
        $params['date_to2'] = $dateTo;
    } elseif (!empty($filterDate)) {
        // Legacy single date filter
        $bookingsWhere[] = "DATE(check_in) = :filter_date1";
        $reportsWhere[] = "DATE(check_in) = :filter_date2";
        $params['filter_date1'] = $filterDate;
        $params['filter_date2'] = $filterDate;
    }

    $bookingsWhereStr = implode(' AND ', $bookingsWhere);
    $reportsWhereStr  = implode(' AND ', $reportsWhere);

    // Search for bookings from BOOKINGS table and REPORTS table
    $query = "SELECT 
                CAST(id AS CHAR) as id,
                CAST(COALESCE(booking_id, '') AS CHAR) as display_booking_id,
                CAST(room_type AS CHAR) as room_type,
                CAST(room_id AS CHAR) as room_id,
                CAST(guest_name AS CHAR) as guest_name,
                CAST(booking_type AS CHAR) as booking_type,
                CAST(guest_type AS CHAR) as guest_type,
                CAST(reason_for_stay AS CHAR) as reason_for_stay,
                CAST(contact_person_name AS CHAR) as contact_person_name,
                CAST(contact_no AS CHAR) as contact_no,
                CAST(address AS CHAR) as address,
                CAST(tin_number AS CHAR) as tin_number,
                CAST(request AS CHAR) as request,
                CAST(check_in AS CHAR) as check_in,
                CAST(duration AS CHAR) as duration,
                CAST(COALESCE(duration_unit, 'hours') AS CHAR) as duration_unit,
                CAST(COALESCE(room_price, 0) AS CHAR) as room_price,
                CAST(check_out AS CHAR) as check_out,
                CAST(referral_name AS CHAR) as referral_name,
                CAST(promo AS CHAR) as promo,
                CAST(breakfast AS CHAR) as breakfast,
                CAST(
                    CASE 
                        WHEN payment_status IS NOT NULL AND payment_status != '' THEN payment_status
                        WHEN (payment_status_cash IS NOT NULL AND payment_status_cash != '') AND (payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '') AND (payment_status_maya IS NOT NULL AND payment_status_maya != '') THEN 'Cash, G-cash & Maya'
                        WHEN (payment_status_cash IS NOT NULL AND payment_status_cash != '') AND (payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '') THEN 'Cash & G-cash'
                        WHEN (payment_status_cash IS NOT NULL AND payment_status_cash != '') AND (payment_status_maya IS NOT NULL AND payment_status_maya != '') THEN 'Cash & Maya'
                        WHEN (payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '') AND (payment_status_maya IS NOT NULL AND payment_status_maya != '') THEN 'G-cash & Maya'
                        WHEN payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '' THEN 'G-cash'
                        WHEN payment_status_maya IS NOT NULL AND payment_status_maya != '' THEN 'Maya'
                        WHEN payment_status_cash IS NOT NULL AND payment_status_cash != '' THEN 'Cash'
                        ELSE 'Cash'
                    END AS CHAR
                ) as payment_method,
                CAST(COALESCE(additional_guest, 0) AS CHAR) as additional_guest,
                CAST(COALESCE(additional_pet, 0) AS CHAR) as additional_pet,
                CAST(COALESCE(additional_food, '') AS CHAR) as additional_food,
                CAST(COALESCE(additional_items, '') AS CHAR) as additional_items,
                CAST(COALESCE(additional_guest_date, '') AS CHAR) as additional_guest_date,
                CAST(COALESCE(additional_pet_date, '') AS CHAR) as additional_pet_date,
                CAST(COALESCE(additional_food_date, '') AS CHAR) as additional_food_date,
                CAST(COALESCE(additional_items_date, '') AS CHAR) as additional_items_date,
                CAST(
                    CASE 
                        WHEN paid_status = 'Paid' THEN 'Paid'
                        WHEN payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '' THEN 'Paid'
                        WHEN payment_status_maya IS NOT NULL AND payment_status_maya != '' THEN 'Paid'
                        WHEN payment_status_cash IS NOT NULL AND payment_status_cash != '' THEN 'Paid'
                        ELSE COALESCE(paid_status, 'Unpaid')
                    END AS CHAR
                ) as paid_status,
                CAST(COALESCE(additional_paid_status, 'None') AS CHAR) as additional_paid_status,
                CAST(COALESCE(status, 'Available') AS CHAR) as status,
                CAST(room_image AS CHAR) as room_image,
                CAST(COALESCE(total_amount, 0) AS CHAR) as total_amount,
                CAST(COALESCE(payment_status_cash, '') AS CHAR) as payment_status_cash,
                CAST(COALESCE(payment_status_g_cash, '') AS CHAR) as payment_status_g_cash,
                CAST(COALESCE(payment_status_maya, '') AS CHAR) as payment_status_maya,
                CAST(COALESCE(deposit_cash, 0) AS CHAR) as deposit_cash,
                CAST(COALESCE(deposit_g_cash, 0) AS CHAR) as deposit_g_cash,
                CAST(COALESCE(deposit_maya, 0) AS CHAR) as deposit_maya,
                CAST(COALESCE(downpayment_cash, 0) AS CHAR) as downpayment_cash,
                CAST(COALESCE(downpayment_gcash, 0) AS CHAR) as downpayment_gcash,
                CAST(COALESCE(downpayment_maya, 0) AS CHAR) as downpayment_maya,
                CAST(COALESCE(downpayment_gcash_ref, '') AS CHAR) as downpayment_gcash_ref,
                CAST(COALESCE(downpayment_maya_ref, '') AS CHAR) as downpayment_maya_ref,
                CAST(COALESCE(downpayment_instapay, 0) AS CHAR) as downpayment_instapay,
                CAST(COALESCE(downpayment_online_banking, 0) AS CHAR) as downpayment_online_banking,
                CAST(COALESCE(downpayment_airbnb, 0) AS CHAR) as downpayment_airbnb,
                CAST(COALESCE(downpayment_instapay_ref, '') AS CHAR) as downpayment_instapay_ref,
                CAST(COALESCE(downpayment_online_banking_ref, '') AS CHAR) as downpayment_online_banking_ref,
                CAST(COALESCE(downpayment_airbnb_ref, '') AS CHAR) as downpayment_airbnb_ref,
                CAST(COALESCE(payment_status_instapay, '') AS CHAR) as payment_status_instapay,
                CAST(COALESCE(payment_status_online_banking, '') AS CHAR) as payment_status_online_banking,
                CAST(COALESCE(payment_status_airbnb, '') AS CHAR) as payment_status_airbnb,
                CAST(COALESCE(deposit_instapay, 0) AS CHAR) as deposit_instapay,
                CAST(COALESCE(deposit_online_banking, 0) AS CHAR) as deposit_online_banking,
                CAST(COALESCE(deposit_airbnb, 0) AS CHAR) as deposit_airbnb,
                CAST(COALESCE(payment_date_time, '') AS CHAR) as payment_date_time,
                CAST(COALESCE(payment_amount_cash_history, '') AS CHAR) as payment_amount_cash_history,
                CAST(COALESCE(payment_amount_g_cash_history, '') AS CHAR) as payment_amount_g_cash_history,
                CAST(COALESCE(payment_amount_maya_history, '') AS CHAR) as payment_amount_maya_history,
                CAST(COALESCE(payment_amount_instapay_history, '') AS CHAR) as payment_amount_instapay_history,
                CAST(COALESCE(payment_amount_online_banking_history, '') AS CHAR) as payment_amount_online_banking_history,
                CAST(COALESCE(payment_amount_airbnb_history, '') AS CHAR) as payment_amount_airbnb_history,
                CAST(
                    CASE 
                        WHEN reference_no_g_cash IS NOT NULL AND reference_no_g_cash != '' THEN reference_no_g_cash 
                        ELSE COALESCE(deposit_gcash_ref, '') 
                    END AS CHAR
                ) as reference_no_g_cash,
                CAST(
                    CASE 
                        WHEN reference_no_maya IS NOT NULL AND reference_no_maya != '' THEN reference_no_maya 
                        ELSE COALESCE(deposit_maya_ref, '') 
                    END AS CHAR
                ) as reference_no_maya,
                CAST(
                    CASE 
                        WHEN reference_no_instapay IS NOT NULL AND reference_no_instapay != '' THEN reference_no_instapay 
                        ELSE COALESCE(deposit_instapay_ref, COALESCE(downpayment_instapay_ref, ''))
                    END AS CHAR
                ) as reference_no_instapay,
                CAST(
                    CASE 
                        WHEN reference_no_online_banking IS NOT NULL AND reference_no_online_banking != '' THEN reference_no_online_banking 
                        ELSE COALESCE(deposit_online_banking_ref, COALESCE(downpayment_online_banking_ref, ''))
                    END AS CHAR
                ) as reference_no_online_banking,
                CAST(
                    CASE 
                        WHEN reference_no_airbnb IS NOT NULL AND reference_no_airbnb != '' THEN reference_no_airbnb 
                        ELSE COALESCE(deposit_airbnb_ref, COALESCE(downpayment_airbnb_ref, ''))
                    END AS CHAR
                ) as reference_no_airbnb,
                CAST(COALESCE(discount_enabled, 0) AS CHAR) as discount_enabled,
                CAST(COALESCE(sc_pwd_count, 0) AS CHAR) as sc_pwd_count,
                CAST(COALESCE(discount_amount, 0) AS CHAR) as discount_amount,
                CAST(COALESCE(discount_amount_history, '') AS CHAR) as discount_amount_history,
                CAST(COALESCE(id_number, '') AS CHAR) as id_number,
                CAST(COALESCE(extend_hours, 0) AS CHAR) as extend_hours,
                CAST(COALESCE(extend_minutes, 0) AS CHAR) as extend_minutes,
                CAST(COALESCE(extend_price, 0) AS CHAR) as extend_price,
                CAST(COALESCE(extend_regular_rate, 0) AS CHAR) as extend_regular_rate,
                CAST(COALESCE(extend_bundle_rate, 0) AS CHAR) as extend_bundle_rate,
                CAST(COALESCE(extend_bundle_breakfast, 0) AS CHAR) as extend_bundle_breakfast,
                CAST(COALESCE(cancellation_reason, '') AS CHAR) as cancellation_reason,
                CAST(COALESCE(refund_amount, 0) AS CHAR) as refund_amount,
                CAST(COALESCE(modification_reason, '') AS CHAR) as modification_reason,
                CAST(COALESCE(modification_updated_at, '') AS CHAR) as modification_updated_at,
                CAST(COALESCE(encoder, '') AS CHAR) as encoder,
                CAST(COALESCE(encoder_checkout, '') AS CHAR) as encoder_checkout,
                CAST(COALESCE(vehicle_type, '') AS CHAR) as vehicle_type,
                CAST(COALESCE(plate_number, '') AS CHAR) as plate_number,
                CAST(COALESCE(vehicle_description, '') AS CHAR) as vehicle_description,
                CAST(COALESCE(transfer_room_from, '') AS CHAR) as transfer_room_from,
                CAST(COALESCE(transfer_refund_amount, 0) AS CHAR) as transfer_refund_amount,
                'bookings' as source_table
              FROM bookings 
              WHERE {$bookingsWhereStr}
              
              UNION ALL
              
              SELECT 
                CAST(id AS CHAR) as id,
                CAST(booking_id AS CHAR) as display_booking_id,
                CAST(room_type AS CHAR) as room_type,
                CAST(room_id AS CHAR) as room_id,
                CAST(guest_name AS CHAR) as guest_name,
                CAST(booking_type AS CHAR) as booking_type,
                CAST(guest_type AS CHAR) as guest_type,
                CAST(reason_for_stay AS CHAR) as reason_for_stay,
                CAST(contact_person_name AS CHAR) as contact_person_name,
                CAST(contact_no AS CHAR) as contact_no,
                CAST(address AS CHAR) as address,
                CAST(tin_number AS CHAR) as tin_number,
                CAST(request AS CHAR) as request,
                CAST(check_in AS CHAR) as check_in,
                CAST(duration AS CHAR) as duration,
                CAST(COALESCE(duration_unit, 'hours') AS CHAR) as duration_unit,
                CAST(COALESCE(room_price, 0) AS CHAR) as room_price,
                CAST(check_out AS CHAR) as check_out,
                CAST(reference_no AS CHAR) as referral_name,
                CAST(promo AS CHAR) as promo,
                CAST(breakfast AS CHAR) as breakfast,
                CAST(
                    CASE 
                        WHEN payment_status IS NOT NULL AND payment_status != '' THEN payment_status
                        WHEN (payment_status_cash IS NOT NULL AND payment_status_cash != '') AND (payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '') AND (payment_status_maya IS NOT NULL AND payment_status_maya != '') THEN 'Cash, G-cash & Maya'
                        WHEN (payment_status_cash IS NOT NULL AND payment_status_cash != '') AND (payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '') THEN 'Cash & G-cash'
                        WHEN (payment_status_cash IS NOT NULL AND payment_status_cash != '') AND (payment_status_maya IS NOT NULL AND payment_status_maya != '') THEN 'Cash & Maya'
                        WHEN (payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '') AND (payment_status_maya IS NOT NULL AND payment_status_maya != '') THEN 'G-cash & Maya'
                        WHEN payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '' THEN 'G-cash'
                        WHEN payment_status_maya IS NOT NULL AND payment_status_maya != '' THEN 'Maya'
                        WHEN payment_status_cash IS NOT NULL AND payment_status_cash != '' THEN 'Cash'
                        ELSE 'Cash'
                    END AS CHAR
                ) as payment_method,
                CAST(COALESCE(additional_guest, 0) AS CHAR) as additional_guest,
                CAST(COALESCE(additional_pet, 0) AS CHAR) as additional_pet,
                CAST(COALESCE(additional_food, '') AS CHAR) as additional_food,
                CAST(COALESCE(additional_items, '') AS CHAR) as additional_items,
                CAST(COALESCE(additional_guest_date, '') AS CHAR) as additional_guest_date,
                CAST(COALESCE(additional_pet_date, '') AS CHAR) as additional_pet_date,
                CAST(COALESCE(additional_food_date, '') AS CHAR) as additional_food_date,
                CAST(COALESCE(additional_items_date, '') AS CHAR) as additional_items_date,
                CAST(
                    CASE 
                        WHEN paid_status = 'Paid' THEN 'Paid'
                        WHEN payment_status_g_cash IS NOT NULL AND payment_status_g_cash != '' THEN 'Paid'
                        WHEN payment_status_maya IS NOT NULL AND payment_status_maya != '' THEN 'Paid'
                        WHEN payment_status_cash IS NOT NULL AND payment_status_cash != '' THEN 'Paid'
                        ELSE COALESCE(paid_status, 'Unpaid')
                    END AS CHAR
                ) as paid_status,
                CAST(COALESCE(additional_fees_status, 'None') AS CHAR) as additional_paid_status,
                'Completed' as status,
                CAST(room_image AS CHAR) as room_image,
                CAST(COALESCE(total_amount, 0) AS CHAR) as total_amount,
                CAST(COALESCE(payment_status_cash, '') AS CHAR) as payment_status_cash,
                CAST(COALESCE(payment_status_g_cash, '') AS CHAR) as payment_status_g_cash,
                CAST(COALESCE(payment_status_maya, '') AS CHAR) as payment_status_maya,
                CAST(COALESCE(deposit_cash, 0) AS CHAR) as deposit_cash,
                CAST(COALESCE(deposit_g_cash, 0) AS CHAR) as deposit_g_cash,
                CAST(COALESCE(deposit_maya, 0) AS CHAR) as deposit_maya,
                CAST(COALESCE(downpayment_cash, 0) AS CHAR) as downpayment_cash,
                CAST(COALESCE(downpayment_gcash, 0) AS CHAR) as downpayment_gcash,
                CAST(COALESCE(downpayment_maya, 0) AS CHAR) as downpayment_maya,
                CAST(COALESCE(downpayment_gcash_ref, '') AS CHAR) as downpayment_gcash_ref,
                CAST(COALESCE(downpayment_maya_ref, '') AS CHAR) as downpayment_maya_ref,
                CAST(COALESCE(downpayment_instapay, 0) AS CHAR) as downpayment_instapay,
                CAST(COALESCE(downpayment_online_banking, 0) AS CHAR) as downpayment_online_banking,
                CAST(COALESCE(downpayment_airbnb, 0) AS CHAR) as downpayment_airbnb,
                CAST(COALESCE(downpayment_instapay_ref, '') AS CHAR) as downpayment_instapay_ref,
                CAST(COALESCE(downpayment_online_banking_ref, '') AS CHAR) as downpayment_online_banking_ref,
                CAST(COALESCE(downpayment_airbnb_ref, '') AS CHAR) as downpayment_airbnb_ref,
                CAST(COALESCE(payment_status_instapay, '') AS CHAR) as payment_status_instapay,
                CAST(COALESCE(payment_status_online_banking, '') AS CHAR) as payment_status_online_banking,
                CAST(COALESCE(payment_status_airbnb, '') AS CHAR) as payment_status_airbnb,
                CAST(COALESCE(deposit_instapay, 0) AS CHAR) as deposit_instapay,
                CAST(COALESCE(deposit_online_banking, 0) AS CHAR) as deposit_online_banking,
                CAST(COALESCE(deposit_airbnb, 0) AS CHAR) as deposit_airbnb,
                CAST(COALESCE(payment_date_time, '') AS CHAR) as payment_date_time,
                CAST(COALESCE(payment_amount_cash_history, '') AS CHAR) as payment_amount_cash_history,
                CAST(COALESCE(payment_amount_g_cash_history, '') AS CHAR) as payment_amount_g_cash_history,
                CAST(COALESCE(payment_amount_maya_history, '') AS CHAR) as payment_amount_maya_history,
                CAST(COALESCE(payment_amount_instapay_history, '') AS CHAR) as payment_amount_instapay_history,
                CAST(COALESCE(payment_amount_online_banking_history, '') AS CHAR) as payment_amount_online_banking_history,
                CAST(COALESCE(payment_amount_airbnb_history, '') AS CHAR) as payment_amount_airbnb_history,
                CAST(
                    CASE 
                        WHEN reference_no_g_cash IS NOT NULL AND reference_no_g_cash != '' THEN reference_no_g_cash 
                        ELSE COALESCE(deposit_gcash_ref, '') 
                    END AS CHAR
                ) as reference_no_g_cash,
                CAST(
                    CASE 
                        WHEN reference_no_maya IS NOT NULL AND reference_no_maya != '' THEN reference_no_maya 
                        ELSE COALESCE(deposit_maya_ref, '') 
                    END AS CHAR
                ) as reference_no_maya,
                CAST(
                    CASE 
                        WHEN reference_no_instapay IS NOT NULL AND reference_no_instapay != '' THEN reference_no_instapay 
                        ELSE COALESCE(deposit_instapay_ref, COALESCE(downpayment_instapay_ref, ''))
                    END AS CHAR
                ) as reference_no_instapay,
                CAST(
                    CASE 
                        WHEN reference_no_online_banking IS NOT NULL AND reference_no_online_banking != '' THEN reference_no_online_banking 
                        ELSE COALESCE(deposit_online_banking_ref, COALESCE(downpayment_online_banking_ref, ''))
                    END AS CHAR
                ) as reference_no_online_banking,
                CAST(
                    CASE 
                        WHEN reference_no_airbnb IS NOT NULL AND reference_no_airbnb != '' THEN reference_no_airbnb 
                        ELSE COALESCE(deposit_airbnb_ref, COALESCE(downpayment_airbnb_ref, ''))
                    END AS CHAR
                ) as reference_no_airbnb,
                CAST(COALESCE(discount_enabled, 0) AS CHAR) as discount_enabled,
                CAST(COALESCE(sc_pwd_count, 0) AS CHAR) as sc_pwd_count,
                CAST(COALESCE(discount_amount, 0) AS CHAR) as discount_amount,
                CAST(COALESCE(discount_amount_history, '') AS CHAR) as discount_amount_history,
                CAST(COALESCE(id_number, '') AS CHAR) as id_number,
                CAST(COALESCE(extend_hours, 0) AS CHAR) as extend_hours,
                CAST(COALESCE(extend_minutes, 0) AS CHAR) as extend_minutes,
                CAST(COALESCE(extend_price, 0) AS CHAR) as extend_price,
                CAST(COALESCE(extend_regular_rate, 0) AS CHAR) as extend_regular_rate,
                CAST(COALESCE(extend_bundle_rate, 0) AS CHAR) as extend_bundle_rate,
                CAST(COALESCE(extend_bundle_breakfast, 0) AS CHAR) as extend_bundle_breakfast,
                CAST(COALESCE(cancellation_reason, '') AS CHAR) as cancellation_reason,
                CAST(COALESCE(refund_amount, 0) AS CHAR) as refund_amount,
                CAST(COALESCE(modification_reason, '') AS CHAR) as modification_reason,
                CAST(COALESCE(modification_updated_at, '') AS CHAR) as modification_updated_at,
                CAST(COALESCE(encoder, '') AS CHAR) as encoder,
                CAST(COALESCE(encoder_checkout, '') AS CHAR) as encoder_checkout,
                CAST(COALESCE(vehicle_type, '') AS CHAR) as vehicle_type,
                CAST(COALESCE(plate_number, '') AS CHAR) as plate_number,
                CAST(COALESCE(vehicle_description, '') AS CHAR) as vehicle_description,
                CAST(COALESCE(transfer_room_from, '') AS CHAR) as transfer_room_from,
                CAST(COALESCE(transfer_refund_amount, 0) AS CHAR) as transfer_refund_amount,
                'reports' as source_table
              FROM reports 
              WHERE {$reportsWhereStr}
              
              ORDER BY check_in DESC 
              LIMIT 100";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Prepare failed');
    }
    
    // Bind params using PDO
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $stmt->execute();
    
    $modifications = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format additional info
        $addList = [];
        if (isset($row['additional_guest']) && $row['additional_guest'] > 0) {
            $addList[] = $row['additional_guest'] . ' Guest(s)';
        }
        if (isset($row['additional_pet']) && $row['additional_pet'] > 0) {
            $addList[] = $row['additional_pet'] . ' Pet(s)';
        }
        
        // Parse food
        if (!empty($row['additional_food'])) {
            $foodStr = $row['additional_food'];
            if (strpos(trim($foodStr), '[') === 0) {
                $fArr = json_decode($foodStr, true);
                if (is_array($fArr)) {
                    foreach ($fArr as $item) {
                        $qty = $item['quantity'] ?? 1;
                        $name = $item['selectedItem'] ?? 'Food';
                        $addList[] = $qty . ' ' . trim($name);
                    }
                }
            } else {
                $lines = explode("\n", $foodStr);
                foreach ($lines as $line) {
                    if (preg_match('/^(\d+)\s+(.*?)\s*(?:-|=)\s*(?:P|₱)/i', trim($line), $fMatch)) {
                        $addList[] = $fMatch[1] . ' ' . trim($fMatch[2]);
                    } elseif (trim($line) != '') {
                        $addList[] = trim($line); // fallback loosely
                    }
                }
            }
        }
        
        // Parse items
        if (!empty($row['additional_items'])) {
            $itemStr = $row['additional_items'];
            if (strpos(trim($itemStr), '[') === 0) {
                $iArr = json_decode($itemStr, true);
                if (is_array($iArr)) {
                    foreach ($iArr as $item) {
                        $qty = $item['quantity'] ?? 1;
                        $name = $item['selectedItem'] ?? 'Item';
                        $addList[] = $qty . ' ' . trim($name);
                    }
                }
            } else {
                $lines = explode("\n", $itemStr);
                foreach ($lines as $line) {
                    if (preg_match('/^(\d+)\s+(.*?)\s*(?:-|=)\s*(?:P|₱)/i', trim($line), $iMatch)) {
                        $addList[] = $iMatch[1] . ' ' . trim($iMatch[2]);
                    } elseif (trim($line) != '') {
                        $addList[] = trim($line); // fallback loosely
                    }
                }
            }
        }
        
        $additional = !empty($addList) ? implode('<br>', $addList) : '-';
        
        // Calculate payment breakdown by method
        $paymentBreakdown = [];
        $totalPaid = 0;
        $finalCashAmount = 0;
        $finalGcashAmount = 0;
        $finalMayaAmount = 0;
        $finalInstapayAmount = 0;
        $finalOnlineBankingAmount = 0;
        $finalAirbnbAmount = 0;
        
        // Extract amount from payment_status_cash (format: "Cash (₱1,234.56)")
        if (!empty($row['payment_status_cash'])) {
            if (preg_match('/(?:P|₱)?([0-9,]+\.?[0-9]*)/', $row['payment_status_cash'], $matches)) {
                $cashAmount = floatval(str_replace(',', '', $matches[1]));
                if ($cashAmount > 0) {
                    $paymentBreakdown[] = 'Cash: ₱' . number_format($cashAmount, 2);
                    $totalPaid += $cashAmount;
                    $finalCashAmount = $cashAmount;
                }
            }
        }
        
        // Extract amount from payment_status_g_cash
        if (!empty($row['payment_status_g_cash'])) {
            if (preg_match('/(?:P|₱)?([0-9,]+\.?[0-9]*)/', $row['payment_status_g_cash'], $matches)) {
                $gcashAmount = floatval(str_replace(',', '', $matches[1]));
                if ($gcashAmount > 0) {
                    $paymentBreakdown[] = 'G-cash: ₱' . number_format($gcashAmount, 2);
                    $totalPaid += $gcashAmount;
                    $finalGcashAmount = $gcashAmount;
                }
            }
        }
        
        // Extract amount from payment_status_maya
        if (!empty($row['payment_status_maya'])) {
            if (preg_match('/(?:P|₱)?([0-9,]+\.?[0-9]*)/', $row['payment_status_maya'], $matches)) {
                $mayaAmount = floatval(str_replace(',', '', $matches[1]));
                if ($mayaAmount > 0) {
                    $paymentBreakdown[] = 'Maya: ₱' . number_format($mayaAmount, 2);
                    $totalPaid += $mayaAmount;
                    $finalMayaAmount = $mayaAmount;
                }
            }
        }
        
        // Extract amount from payment_status_instapay
        if (!empty($row['payment_status_instapay'])) {
            if (preg_match('/(?:P|₱)?([0-9,]+\.?[0-9]*)/', $row['payment_status_instapay'], $matches)) {
                $instapayAmount = floatval(str_replace(',', '', $matches[1]));
                if ($instapayAmount > 0) {
                    $paymentBreakdown[] = 'Instapay: ₱' . number_format($instapayAmount, 2);
                    $totalPaid += $instapayAmount;
                    $finalInstapayAmount = $instapayAmount;
                }
            }
        }
        
        // Extract amount from payment_status_online_banking
        if (!empty($row['payment_status_online_banking'])) {
            if (preg_match('/(?:P|₱)?([0-9,]+\.?[0-9]*)/', $row['payment_status_online_banking'], $matches)) {
                $onlineBankingAmount = floatval(str_replace(',', '', $matches[1]));
                if ($onlineBankingAmount > 0) {
                    $paymentBreakdown[] = 'Online Banking: ₱' . number_format($onlineBankingAmount, 2);
                    $totalPaid += $onlineBankingAmount;
                    $finalOnlineBankingAmount = $onlineBankingAmount;
                }
            }
        }
        
        // Extract amount from payment_status_airbnb
        if (!empty($row['payment_status_airbnb'])) {
            if (preg_match('/(?:P|₱)?([0-9,]+\.?[0-9]*)/', $row['payment_status_airbnb'], $matches)) {
                $airbnbAmount = floatval(str_replace(',', '', $matches[1]));
                if ($airbnbAmount > 0) {
                    $paymentBreakdown[] = 'Airbnb: ₱' . number_format($airbnbAmount, 2);
                    $totalPaid += $airbnbAmount;
                    $finalAirbnbAmount = $airbnbAmount;
                }
            }
        }
        
        // If no payment_status amounts found, fall back to deposit columns only
        if ($totalPaid == 0) {
            $depositCash = floatval($row['deposit_cash'] ?? 0);
            $depositGcash = floatval($row['deposit_g_cash'] ?? 0);
            $depositMaya = floatval($row['deposit_maya'] ?? 0);
            $depositInstapay = floatval($row['deposit_instapay'] ?? 0);
            $depositOnlineBanking = floatval($row['deposit_online_banking'] ?? 0);
            $depositAirbnb = floatval($row['deposit_airbnb'] ?? 0);
            
            if ($depositCash > 0) {
                $paymentBreakdown[] = 'Cash: ₱' . number_format($depositCash, 2);
                $totalPaid += $depositCash;
                $finalCashAmount = $depositCash;
            }
            if ($depositGcash > 0) {
                $paymentBreakdown[] = 'G-cash: ₱' . number_format($depositGcash, 2);
                $totalPaid += $depositGcash;
                $finalGcashAmount = $depositGcash;
            }
            if ($depositMaya > 0) {
                $paymentBreakdown[] = 'Maya: ₱' . number_format($depositMaya, 2);
                $totalPaid += $depositMaya;
                $finalMayaAmount = $depositMaya;
            }
            if ($depositInstapay > 0) {
                $paymentBreakdown[] = 'Instapay: ₱' . number_format($depositInstapay, 2);
                $totalPaid += $depositInstapay;
                $finalInstapayAmount = $depositInstapay;
            }
            if ($depositOnlineBanking > 0) {
                $paymentBreakdown[] = 'Online Banking: ₱' . number_format($depositOnlineBanking, 2);
                $totalPaid += $depositOnlineBanking;
                $finalOnlineBankingAmount = $depositOnlineBanking;
            }
            if ($depositAirbnb > 0) {
                $paymentBreakdown[] = 'Airbnb: ₱' . number_format($depositAirbnb, 2);
                $totalPaid += $depositAirbnb;
                $finalAirbnbAmount = $depositAirbnb;
            }
        }
        
        // Format payment amount display
        $paymentAmountDisplay = $totalPaid > 0 ? implode(' + ', $paymentBreakdown) : '₱0.00';
        
        // Build payment method string based on which payment methods have amounts
        $paymentMethods = [];
        if ($finalCashAmount > 0) $paymentMethods[] = 'Cash';
        if ($finalGcashAmount > 0) $paymentMethods[] = 'G-cash';
        if ($finalMayaAmount > 0) $paymentMethods[] = 'Maya';
        if ($finalInstapayAmount > 0) $paymentMethods[] = 'Instapay';
        if ($finalOnlineBankingAmount > 0) $paymentMethods[] = 'Online Banking';
        if ($finalAirbnbAmount > 0) $paymentMethods[] = 'Airbnb';
        
        // Build the payment method string
        if (count($paymentMethods) === 3 && in_array('Cash', $paymentMethods) && in_array('G-cash', $paymentMethods) && in_array('Maya', $paymentMethods)) {
            $paymentMethod = 'Cash, G-cash & Maya';
        } elseif (count($paymentMethods) === 2) {
            $paymentMethod = implode(' & ', $paymentMethods);
        } elseif (count($paymentMethods) === 1) {
            $paymentMethod = $paymentMethods[0];
        } elseif (count($paymentMethods) > 3) {
            // More than 3 methods - just join with &
            $paymentMethod = implode(' & ', $paymentMethods);
        } else {
            $paymentMethod = 'Cash'; // Default fallback
        }
        
        $modifications[] = [
            'id' => $row['id'] ?? '-',
            'display_booking_id' => $row['display_booking_id'] ?? '-',
            'room' => $row['room_type'] ?? '-',
            'room_id' => $row['room_id'] ?? '-',
            'booking_type' => $row['booking_type'] ?? 'Walk-in',
            'guest_type' => $row['guest_type'] ?? 'Solo',
            'reason_for_stay' => $row['reason_for_stay'] ?? '',
            'contact_person_name' => $row['contact_person_name'] ?? '',
            'contact_no' => $row['contact_no'] ?? '',
            'address' => $row['address'] ?? '',
            'tin_number' => $row['tin_number'] ?? '',
            'guest_names' => $row['guest_name'] ?? '-',
            'request' => $row['request'] ?? '-',
            'check_in' => $row['check_in'] ?? '-',
            'duration' => $row['duration'] ?? '-',
            'duration_unit' => $row['duration_unit'] ?? 'hours',
            'room_price' => $row['room_price'] ?? '0',
            'check_out' => $row['check_out'] ?? '-',
            'referral_code' => $row['referral_name'] ?? '-',
            'promo' => $row['promo'] ?? '-',
            'breakfast' => $row['breakfast'] ?? 'None',
            'payment_method' => $paymentMethod,
            'payment_amount' => $paymentAmountDisplay,
            'cash_amount' => $finalCashAmount,
            'gcash_amount' => $finalGcashAmount,
            'maya_amount' => $finalMayaAmount,
            'instapay_amount' => $finalInstapayAmount,
            'online_banking_amount' => $finalOnlineBankingAmount,
            'airbnb_amount' => $finalAirbnbAmount,
            // Add payment_status columns for formatPaymentMethodDisplay function
            'payment_status_cash' => $row['payment_status_cash'] ?? '',
            'payment_status_g_cash' => $row['payment_status_g_cash'] ?? '',
            'payment_status_maya' => $row['payment_status_maya'] ?? '',
            'payment_status_instapay' => $row['payment_status_instapay'] ?? '',
            'payment_status_online_banking' => $row['payment_status_online_banking'] ?? '',
            'payment_status_airbnb' => $row['payment_status_airbnb'] ?? '',
            // Add deposit columns for formatPaymentMethodDisplay function
            'deposit_cash' => $row['deposit_cash'] ?? '0',
            'deposit_g_cash' => $row['deposit_g_cash'] ?? '0',
            'deposit_maya' => $row['deposit_maya'] ?? '0',
            'deposit_instapay' => $row['deposit_instapay'] ?? '0',
            'deposit_online_banking' => $row['deposit_online_banking'] ?? '0',
            'deposit_airbnb' => $row['deposit_airbnb'] ?? '0',
            'deposit_details' => $row['deposit_details'] ?? '',
            'additional' => $additional,
            'additional_guest' => $row['additional_guest'] ?? '0',
            'additional_pet' => $row['additional_pet'] ?? '0',
            'additional_food' => $row['additional_food'] ?? '',
            'additional_items' => $row['additional_items'] ?? '',
            'additional_guest_date' => $row['additional_guest_date'] ?? '',
            'additional_pet_date' => $row['additional_pet_date'] ?? '',
            'additional_food_date' => $row['additional_food_date'] ?? '',
            'additional_items_date' => $row['additional_items_date'] ?? '',
            'payment_status' => $row['paid_status'] ?? 'Unpaid',
            'additional_paid_status' => $row['additional_paid_status'] ?? 'None',
            'status' => $row['status'] ?? 'Available',
            'reference_no_g_cash' => $row['reference_no_g_cash'] ?? '',
            'reference_no_maya'   => $row['reference_no_maya']   ?? '',
            'reference_no_instapay' => $row['reference_no_instapay'] ?? '',
            'reference_no_online_banking' => $row['reference_no_online_banking'] ?? '',
            'reference_no_airbnb' => $row['reference_no_airbnb'] ?? '',
            'instapay_reference' => $row['reference_no_instapay'] ?? '',
            'online_banking_reference' => $row['reference_no_online_banking'] ?? '',
            'airbnb_reference' => $row['reference_no_airbnb'] ?? '',
            'downpayment_cash'    => $row['downpayment_cash'] ?? '0',
            'downpayment_gcash'   => $row['downpayment_gcash'] ?? '0',
            'downpayment_maya'    => $row['downpayment_maya'] ?? '0',
            'downpayment_instapay' => $row['downpayment_instapay'] ?? '0',
            'downpayment_online_banking' => $row['downpayment_online_banking'] ?? '0',
            'downpayment_airbnb' => $row['downpayment_airbnb'] ?? '0',
            'downpayment_gcash_ref' => $row['downpayment_gcash_ref'] ?? '',
            'downpayment_maya_ref'  => $row['downpayment_maya_ref'] ?? '',
            'downpayment_instapay_ref' => $row['downpayment_instapay_ref'] ?? '',
            'downpayment_online_banking_ref' => $row['downpayment_online_banking_ref'] ?? '',
            'downpayment_airbnb_ref' => $row['downpayment_airbnb_ref'] ?? '',
            'sc_pwd_count'        => (!empty($row['discount_amount']) && $row['discount_amount'] > 0) ? ($row['sc_pwd_count'] ?? '0') : '0',
            'discount_amount'     => $row['discount_amount'] ?? '0',
            'discount_amount_history' => $row['discount_amount_history'] ?? '',
            'id_number'           => $row['id_number'] ?? '',
            'extend_hours'        => $row['extend_hours'] ?? '0',
            'extend_minutes'      => $row['extend_minutes'] ?? '0',
            'extend_price'        => $row['extend_price'] ?? '0',
            'extend_regular_rate' => $row['extend_regular_rate'] ?? '0',
            'extend_bundle_rate'  => $row['extend_bundle_rate'] ?? '0',
            'extend_bundle_breakfast' => $row['extend_bundle_breakfast'] ?? '0',
            'cancellation_reason' => $row['cancellation_reason'] ?? '',
            'refund_amount'       => $row['refund_amount'] ?? '0',
            'modification_reason' => $row['modification_reason'] ?? '',
            'modification_updated_at' => $row['modification_updated_at'] ?? '',
            'encoder'             => $row['encoder'] ?? '',
            'encoder_checkout'    => $row['encoder_checkout'] ?? '',
            'vehicle_type'        => $row['vehicle_type'] ?? '',
            'plate_number'        => $row['plate_number'] ?? '',
            'vehicle_description' => $row['vehicle_description'] ?? '',
            'transfer_room_from'  => $row['transfer_room_from'] ?? '',
            'transfer_refund_amount' => $row['transfer_refund_amount'] ?? '0',
            'source' => $row['source_table'] ?? 'unknown',
            'payment_date_time' => $row['payment_date_time'] ?? '',
            'payment_amount_cash_history' => $row['payment_amount_cash_history'] ?? '',
            'payment_amount_g_cash_history' => $row['payment_amount_g_cash_history'] ?? '',
            'payment_amount_maya_history' => $row['payment_amount_maya_history'] ?? '',
            'payment_amount_instapay_history' => $row['payment_amount_instapay_history'] ?? '',
            'payment_amount_online_banking_history' => $row['payment_amount_online_banking_history'] ?? '',
            'payment_amount_airbnb_history' => $row['payment_amount_airbnb_history'] ?? ''
        ];
    }

    // Clear output buffer
    if (ob_get_length()) ob_clean();

    echo json_encode([
        'success' => true,
        'count' => count($modifications),
        'search_term' => $bookingId,
        'modifications' => $modifications
    ]);
    
    ob_end_flush();

} catch (Exception $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Get modifications error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    
    ob_end_flush();
}
?>
