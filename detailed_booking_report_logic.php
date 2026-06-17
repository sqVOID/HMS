<?php
/**
 * Shared data builder for detailed booking report exports.
 * Callable: buildDetailedBookingReportData($conn, $startDate, $endDate)
 * Legacy include: sets $dataRows, $grandTotal, $grandTotalAdditional when $conn/$startDate/$endDate are set.
 */
require_once __DIR__ . '/detailed_booking_report_functions.php';

if (!function_exists('buildDetailedBookingReportData')) {
    function buildDetailedBookingReportData(PDO $conn, string $startDate, string $endDate): array
    {
        try {
    // Ensure payment amount history columns exist.
    $histColumns = [
        'payment_amount_cash_history',
        'payment_amount_g_cash_history',
        'payment_amount_maya_history',
        'payment_amount_instapay_history',
        'payment_amount_online_banking_history',
        'payment_amount_airbnb_history',
        'discount_amount_history'
    ];
    foreach ($histColumns as $colName) {
        try {
            $chk = $conn->query("SHOW COLUMNS FROM reports LIKE '" . $colName . "'");
            if ($chk && $chk->rowCount() == 0) {
                $conn->exec("ALTER TABLE reports ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
            }
        } catch (PDOException $e) {
            // If migration fails, exports will fall back to old heuristic parsing.
        }
    }

    // Fetch all paid reports in the date range
    $stmt = $conn->prepare("
        SELECT 
            r.booking_id,
            r.payment_date_time,
            DATE(COALESCE(NULLIF(TRIM(SUBSTRING_INDEX(r.payment_date_time, '|', 1)), ''), r.check_in)) as payment_date,
            r.check_in,
            r.checked_out_at,
            r.guest_name,
            r.room_id,
            COALESCE(b.status, r.status) AS status,
            COALESCE(r.encoder, b.encoder) AS encoder,
            COALESCE(r.reference_no, b.reference_no) AS reference_no,
            COALESCE(r.reference_no_g_cash, b.reference_no_g_cash) AS reference_no_g_cash,
            COALESCE(r.reference_no_maya, b.reference_no_maya) AS reference_no_maya,
            COALESCE(r.reference_no_instapay, b.reference_no_instapay) AS reference_no_instapay,
            COALESCE(r.reference_no_online_banking, b.reference_no_online_banking) AS reference_no_online_banking,
            COALESCE(r.reference_no_airbnb, b.reference_no_airbnb) AS reference_no_airbnb,
            COALESCE(r.deposit_gcash_ref, b.deposit_gcash_ref) AS deposit_gcash_ref,
            COALESCE(r.deposit_maya_ref, b.deposit_maya_ref) AS deposit_maya_ref,
            COALESCE(r.deposit_instapay_ref, b.deposit_instapay_ref) AS deposit_instapay_ref,
            COALESCE(r.deposit_online_banking_ref, b.deposit_online_banking_ref) AS deposit_online_banking_ref,
            COALESCE(r.deposit_airbnb_ref, b.deposit_airbnb_ref) AS deposit_airbnb_ref,
            COALESCE(r.downpayment_gcash_ref, b.downpayment_gcash_ref) AS downpayment_gcash_ref,
            COALESCE(r.downpayment_maya_ref, b.downpayment_maya_ref) AS downpayment_maya_ref,
            COALESCE(r.downpayment_instapay_ref, b.downpayment_instapay_ref) AS downpayment_instapay_ref,
            COALESCE(r.downpayment_online_banking_ref, b.downpayment_online_banking_ref) AS downpayment_online_banking_ref,
            COALESCE(r.downpayment_airbnb_ref, b.downpayment_airbnb_ref) AS downpayment_airbnb_ref,
            COALESCE(r.modification_updated_at, b.modification_updated_at) AS modification_updated_at,
            COALESCE(r.booking_type, b.booking_type) AS booking_type,
            COALESCE(r.room_type, b.room_type) AS room_type,
            COALESCE(r.guest_type, b.guest_type) AS guest_type,
            COALESCE(r.address, b.address) AS address,
            COALESCE(r.contact_no, b.contact_no) AS contact_no,
            COALESCE(r.sales_channel, b.sales_channel) AS sales_channel,
            COALESCE(r.transfer_room_from, b.transfer_room_from, '') AS transfer_room_from,
            COALESCE(r.vehicle_description, b.vehicle_description) AS vehicle_description,
            COALESCE(r.plate_number, b.plate_number) AS plate_number,
            COALESCE(r.promo, b.promo) AS promo,
            COALESCE(r.breakfast, b.breakfast) AS breakfast,
            COALESCE(r.extend_bundle_breakfast, b.extend_bundle_breakfast) AS extend_bundle_breakfast,
            COALESCE(r.reservation_date, b.reservation_date) AS reservation_date,
            COALESCE(r.duration, b.duration, 0) AS duration,
            COALESCE(r.duration_unit, b.duration_unit, 'hours') AS duration_unit,
            COALESCE(b.extend_hours, r.extend_hours, 0) AS extend_hours,
            COALESCE(b.extend_minutes, r.extend_minutes, 0) AS extend_minutes,
            COALESCE(b.extend_price, r.extend_price, 0) AS extend_price,
            COALESCE(b.extension_time_at, r.extension_time_at) AS extension_time_at,
            GREATEST(COALESCE(r.room_price, 0), COALESCE(b.room_price, 0)) AS room_price,
            COALESCE(r.total_amount_reservation, b.total_amount_reservation, 0) AS total_amount_reservation,
            COALESCE(r.downpayment_amount, b.downpayment_amount, 0) AS downpayment_amount,
            COALESCE(r.deposit_details, b.deposit_details) AS deposit_details,
            r.payment_status,
            r.payment_status_cash,
            r.payment_status_g_cash,
            r.payment_status_maya,
            r.payment_status_instapay,
            r.payment_status_online_banking,
            r.payment_status_airbnb,
            r.payment_amount_cash_history,
            r.payment_amount_g_cash_history,
            r.payment_amount_maya_history,
            r.payment_amount_instapay_history,
            r.payment_amount_online_banking_history,
            r.payment_amount_airbnb_history,
            r.deposit_cash,
            r.deposit_g_cash,
            r.deposit_maya,
            r.deposit_instapay,
            r.deposit_online_banking,
            r.deposit_airbnb,
            r.downpayment_cash,
            r.downpayment_gcash,
            r.downpayment_maya,
            r.downpayment_instapay,
            r.downpayment_online_banking,
            r.downpayment_airbnb,
            r.downpayment_date,
            r.extension_withdraw,
            r.withdrawn_extend_price,
            r.discount_amount,
            COALESCE(r.discount_amount_history, b.discount_amount_history) AS discount_amount_history,
            COALESCE(r.additional_items, b.additional_items) AS additional_items,
            COALESCE(r.additional_food, b.additional_food) AS additional_food,
            GREATEST(COALESCE(r.additional_guest, 0), COALESCE(b.additional_guest, 0)) AS additional_guest,
            GREATEST(COALESCE(r.extend_additional_guest, 0), COALESCE(b.extend_additional_guest, 0)) AS extend_additional_guest,
            GREATEST(COALESCE(r.additional_pet, 0), COALESCE(b.additional_pet, 0)) AS additional_pet,
            COALESCE(r.additional_food_date, b.additional_food_date) AS additional_food_date,
            COALESCE(r.additional_items_date, b.additional_items_date) AS additional_items_date,
            COALESCE(r.additional_guest_date, b.additional_guest_date) AS additional_guest_date,
            COALESCE(r.additional_pet_date, b.additional_pet_date) AS additional_pet_date,
            COALESCE(r.additional_fees_paid_date, b.additional_fees_paid_date) AS additional_fees_paid_date,
            GREATEST(COALESCE(r.penalty_amount, 0), COALESCE(b.penalty_amount, 0)) AS penalty_amount,
            COALESCE(r.penalty_list, b.penalty_list) AS penalty_list,
            GREATEST(COALESCE(r.missing_items_fees, 0), COALESCE(b.missing_items_fees, 0)) AS missing_items_fees,
            COALESCE(r.missing_items_list, b.missing_items_list) AS missing_items_list
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
        WHERE (
                (r.payment_date_time IS NOT NULL AND TRIM(r.payment_date_time) <> '')
                OR (r.downpayment_date IS NOT NULL)
            )
          AND (
                r.paid_status = 'Paid'
                OR (
                    COALESCE(r.deposit_cash, 0) + COALESCE(r.deposit_g_cash, 0) + COALESCE(r.deposit_maya, 0)
                    + COALESCE(r.deposit_instapay, 0) + COALESCE(r.deposit_online_banking, 0) + COALESCE(r.deposit_airbnb, 0)
                    + COALESCE(r.downpayment_cash, 0) + COALESCE(r.downpayment_gcash, 0) + COALESCE(r.downpayment_maya, 0)
                    + COALESCE(r.downpayment_instapay, 0) + COALESCE(r.downpayment_online_banking, 0) + COALESCE(r.downpayment_airbnb, 0)
                ) > 0.005
            )
        ORDER BY COALESCE(r.payment_date_time, r.downpayment_date) ASC, r.booking_id ASC
    ");

    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payments = array_values(array_filter($payments, function ($r) use ($startDate, $endDate) {
        return perPaymentReportRowInDateRange($r, $startDate, $endDate);
    }));

    $dataRows = [];
    $grandTotal = 0;
    $grandTotalAdditional = 0;
    $groupedData = []; // For grouping same payment method and date

    foreach ($payments as $payment) {
        $bookingId = $payment['booking_id'] ?: 'N/A';
        $guestName = $payment['guest_name'] ?: 'N/A';
        $roomId = $payment['room_id'] ?: 'N/A';
        $encoder = $payment['encoder'] ?: 'N/A';
        $checkIn = formatDateTimeDisplay($payment['check_in'] ?? '');
        $checkOut = formatDateTimeDisplay($payment['checked_out_at'] ?? '');
        $bookingMeta = buildBookingMetaFields($payment);
        $isCanceledBooking = isCanceledBookingStatus($payment['status'] ?? '');

        $timestampRows = [];
        if (!empty($payment['payment_date_time'])) {
            $rawTimestamps = explode('|', (string) $payment['payment_date_time']);
            foreach ($rawTimestamps as $ts) {
                $ts = trim($ts);
                if ($ts === '') {
                    continue;
                }
                $timestampRows[] = array_merge(formatTimestampForExport($ts), ['raw' => $ts]);
            }
        }
        if (empty($timestampRows) && !empty($payment['downpayment_date'])) {
            $downpaymentDate = (string) $payment['downpayment_date'];
            $timestampRows[] = array_merge(formatTimestampForExport($downpaymentDate), ['raw' => $downpaymentDate]);
        }

        if (empty($timestampRows)) {
            $timestampRows[] = [
                'date' => $payment['payment_date'] ?: 'N/A',
                'payment_date_time' => 'N/A',
                'raw' => ''
            ];
        }

        $nTimestamps = count($timestampRows);

        // Process Cash
        $depositCash = floatval($payment['deposit_cash'] ?? 0);
        $downpaymentCash = floatval($payment['downpayment_cash'] ?? 0);
        $totalCash = max($depositCash, $downpaymentCash);

        $cashHistoryArr = !empty($payment['payment_amount_cash_history'])
            ? explode('|', (string) $payment['payment_amount_cash_history'])
            : null;

        if (is_array($cashHistoryArr) && count($cashHistoryArr) === $nTimestamps) {
            $cashAmountsByTimestamp = array_map(fn($v) => floatval($v), $cashHistoryArr);
        } else {
            $cashMethodAmounts = parsePaymentAmountsAll($payment['payment_status_cash']);
            $cashAmountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $cashMethodAmounts, $totalCash);
        }

        $cashLikeMethodLabel = 'Cash';
        $cashStatusRaw = (string) ($payment['payment_status_cash'] ?? '');
        if (stripos($cashStatusRaw, 'Instapay') !== false) {
            $cashLikeMethodLabel = 'Instapay';
        } elseif (stripos($cashStatusRaw, 'Online Banking') !== false) {
            $cashLikeMethodLabel = 'Online Banking';
        } elseif (stripos($cashStatusRaw, 'Airbnb') !== false) {
            $cashLikeMethodLabel = 'Airbnb';
        }

        foreach ($timestampRows as $idx => $tsRow) {
            $amt = $cashAmountsByTimestamp[$idx] ?? 0;
            if ($amt > 0 && isDateInRange($tsRow['date'], $startDate, $endDate)) {
                $paymentRaw = (string) ($tsRow['raw'] ?? '');
                $rowDiscount = getDiscountAmountForPaymentTimestamp($payment, $paymentRaw, $nTimestamps);
                $row = array_merge([
                    'booking_id' => $bookingId,
                    'room_id' => $roomId,
                    'encoder' => $encoder,
                    'guest_name' => $guestName,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'discount_amount' => $rowDiscount,
                    'discount_date' => getDiscountDateForPaymentTimestamp($payment, $paymentRaw),
                    'date' => $tsRow['date'],
                    'payment_date_time' => $tsRow['payment_date_time'],
                    'payment_method' => $cashLikeMethodLabel,
                    'reference_no' => getReferenceNoForPaymentMethod($payment, $cashLikeMethodLabel),
                    'amount' => $amt
                ], $bookingMeta, buildDurationDisplays($payment, $idx, $paymentRaw), buildAdditionalFeesForPaymentRow($payment, $paymentRaw, $idx, $nTimestamps, $amt, $startDate, $endDate));
                if ($isCanceledBooking) {
                    $row = clearCanceledBookingRowFinancials($row);
                }
                $dataRows[] = $row;
                $grandTotal += floatval($row['amount'] ?? 0);
            }
        }

        // Process other payment methods
        $paymentMethods = [
            ['name' => 'G-Cash', 'deposit' => 'deposit_g_cash', 'downpayment' => 'downpayment_gcash', 'status' => 'payment_status_g_cash', 'history' => 'payment_amount_g_cash_history'],
            ['name' => 'Maya', 'deposit' => 'deposit_maya', 'downpayment' => 'downpayment_maya', 'status' => 'payment_status_maya', 'history' => 'payment_amount_maya_history'],
            ['name' => 'Instapay', 'deposit' => 'deposit_instapay', 'downpayment' => 'downpayment_instapay', 'status' => 'payment_status_instapay', 'history' => 'payment_amount_instapay_history'],
            ['name' => 'Online Banking', 'deposit' => 'deposit_online_banking', 'downpayment' => 'downpayment_online_banking', 'status' => 'payment_status_online_banking', 'history' => 'payment_amount_online_banking_history'],
            ['name' => 'Airbnb', 'deposit' => 'deposit_airbnb', 'downpayment' => 'downpayment_airbnb', 'status' => 'payment_status_airbnb', 'history' => 'payment_amount_airbnb_history'],
        ];

        foreach ($paymentMethods as $method) {
            $deposit = floatval($payment[$method['deposit']] ?? 0);
            $downpayment = floatval($payment[$method['downpayment']] ?? 0);
            $total = max($deposit, $downpayment);

            $historyArr = !empty($payment[$method['history']])
                ? explode('|', (string) $payment[$method['history']])
                : null;

            if (is_array($historyArr) && count($historyArr) === $nTimestamps) {
                $amountsByTimestamp = array_map(fn($v) => floatval($v), $historyArr);
            } else {
                $methodAmounts = parsePaymentAmountsAll($payment[$method['status']]);
                $amountsByTimestamp = allocateAmountsToPaymentTimestamps($timestampRows, $methodAmounts, $total);
            }

            foreach ($timestampRows as $idx => $tsRow) {
                $amt = $amountsByTimestamp[$idx] ?? 0;
                if ($amt > 0 && isDateInRange($tsRow['date'], $startDate, $endDate)) {
                    $paymentRaw = (string) ($tsRow['raw'] ?? '');
                    $rowDiscount = getDiscountAmountForPaymentTimestamp($payment, $paymentRaw, $nTimestamps);
                    $row = array_merge([
                        'booking_id' => $bookingId,
                        'room_id' => $roomId,
                        'encoder' => $encoder,
                        'guest_name' => $guestName,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'discount_amount' => $rowDiscount,
                        'discount_date' => getDiscountDateForPaymentTimestamp($payment, $paymentRaw),
                        'date' => $tsRow['date'],
                        'payment_date_time' => $tsRow['payment_date_time'],
                        'payment_method' => $method['name'],
                        'reference_no' => getReferenceNoForPaymentMethod($payment, $method['name']),
                        'amount' => $amt
                    ], $bookingMeta, buildDurationDisplays($payment, $idx, $paymentRaw), buildAdditionalFeesForPaymentRow($payment, $paymentRaw, $idx, $nTimestamps, $amt, $startDate, $endDate));
                    if ($isCanceledBooking) {
                        $row = clearCanceledBookingRowFinancials($row);
                    }
                    $dataRows[] = $row;
                    $grandTotal += floatval($row['amount'] ?? 0);
                }
            }
        }
    }

    // Normalize per-payment rows before grouping (duplicate downpayment must not inflate totals).
    $dataRows = normalizeAllReservationPaymentExportRows($dataRows, $payments);

    // Group rows by booking_id, date, and payment_method
    $groupedData = [];
    foreach ($dataRows as $row) {
        $key = $row['booking_id'] . '|' . $row['date'] . '|' . $row['payment_method'];
        
        if (!isset($groupedData[$key])) {
            $groupedData[$key] = $row;
        } else {
            $groupedData[$key]['amount'] += $row['amount'];
            $groupedData[$key] = mergeGroupedAdditionalFees($groupedData[$key], $row);
        }
    }
    
    // Convert grouped data back to indexed array
    $dataRows = array_values($groupedData);

    $grandTotal = sumPaymentRowAmounts($dataRows);
    $grandTotalAdditional = sumPaymentRowAdditionalFees($dataRows);
            return [
                'dataRows' => $dataRows,
                'grandTotal' => $grandTotal,
                'grandTotalAdditional' => $grandTotalAdditional,
            ];
        } catch (PDOException $e) {
            throw $e;
        }
    }
}

if (isset($conn) && isset($startDate) && isset($endDate)) {
    $__detailedBookingResult = buildDetailedBookingReportData($conn, $startDate, $endDate);
    $dataRows = $__detailedBookingResult['dataRows'];
    $grandTotal = $__detailedBookingResult['grandTotal'];
    $grandTotalAdditional = $__detailedBookingResult['grandTotalAdditional'];
}