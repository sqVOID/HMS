<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (($_SESSION['access_level'] ?? '') !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Super Admin only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$id = (int) $input['id'];
$modificationReason = trim((string) ($input['modification_reason'] ?? ''));

if ($modificationReason === '') {
    echo json_encode(['success' => false, 'message' => 'Modification reason is required']);
    exit;
}

function normalizeDateTime($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $normalized = str_replace('T', ' ', trim((string) $value));
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $normalized)) {
        $normalized .= ':00';
    }
    return $normalized;
}

/**
 * Build payment_status_* strings and history segments from reservation downpayment amounts.
 * Reports/Revenue Overview use these columns (not downpayment_* alone).
 */
function buildReservationPaymentFields(
    float $cash,
    float $gcash,
    float $maya,
    float $instapay,
    float $onlineBanking,
    float $airbnb,
    string $gcashRef,
    string $mayaRef,
    string $instapayRef,
    string $onlineBankingRef,
    string $airbnbRef
): array {
    $fmt = static function (float $amount, string $label): string {
        return $amount > 0 ? $label . ' (₱' . number_format($amount, 2) . ')' : '';
    };

    $paymentStatusCash = $fmt($cash, 'Cash');
    $paymentStatusGcash = $fmt($gcash, 'G-cash');
    $paymentStatusMaya = $fmt($maya, 'Maya');
    $paymentStatusInstapay = $fmt($instapay, 'Instapay');
    $paymentStatusOnlineBanking = $fmt($onlineBanking, 'Online Banking');
    $paymentStatusAirbnb = $fmt($airbnb, 'Airbnb');

    $parts = array_values(array_filter([
        $paymentStatusCash,
        $paymentStatusGcash,
        $paymentStatusMaya,
        $paymentStatusInstapay,
        $paymentStatusOnlineBanking,
        $paymentStatusAirbnb,
    ]));

    $hist = static function (float $amount): string {
        return $amount > 0 ? number_format($amount, 2, '.', '') : '';
    };

    return [
        'payment_status' => implode(', ', $parts),
        'payment_status_cash' => $paymentStatusCash,
        'payment_status_g_cash' => $paymentStatusGcash,
        'payment_status_maya' => $paymentStatusMaya,
        'payment_status_instapay' => $paymentStatusInstapay,
        'payment_status_online_banking' => $paymentStatusOnlineBanking,
        'payment_status_airbnb' => $paymentStatusAirbnb,
        'reference_no_g_cash' => $gcash > 0 ? $gcashRef : '',
        'reference_no_maya' => $maya > 0 ? $mayaRef : '',
        'reference_no_instapay' => $instapay > 0 ? $instapayRef : '',
        'reference_no_online_banking' => $onlineBanking > 0 ? $onlineBankingRef : '',
        'reference_no_airbnb' => $airbnb > 0 ? $airbnbRef : '',
        'reference_no' => '',
        'payment_amount_cash_history' => $hist($cash),
        'payment_amount_g_cash_history' => $hist($gcash),
        'payment_amount_maya_history' => $hist($maya),
        'payment_amount_instapay_history' => $hist($instapay),
        'payment_amount_online_banking_history' => $hist($onlineBanking),
        'payment_amount_airbnb_history' => $hist($airbnb),
    ];
}

try {
    require_once 'config.php';

    $existingStmt = $conn->prepare("
        SELECT id, booking_id, room_id, duration, duration_unit, promo, modification_reason, downpayment_date
        FROM bookings
        WHERE id = :id AND booking_type = 'Reservation'
        LIMIT 1
    ");
    $existingStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $existingStmt->execute();
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit;
    }

    $roomId = trim((string) ($input['room_id'] ?? $existing['room_id'] ?? ''));
    $roomType = trim((string) ($input['room_type'] ?? ''));
    $guestName = trim((string) ($input['guest_name'] ?? ''));
    $guestType = trim((string) ($input['guest_type'] ?? 'Solo'));
    $contactNo = trim((string) ($input['contact_no'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $reasonForStay = trim((string) ($input['reason_for_stay'] ?? ''));
    $request = trim((string) ($input['request'] ?? ''));
    $referralName = trim((string) ($input['referral_name'] ?? ''));
    $promo = trim((string) ($input['promo'] ?? ''));
    $duration = (int) ($input['duration'] ?? 0);
    $durationUnit = trim((string) ($input['duration_unit'] ?? 'hours'));
    $reservationDate = normalizeDateTime($input['reservation_date'] ?? null);
    $checkIn = normalizeDateTime($input['check_in'] ?? null);
    $checkOut = normalizeDateTime($input['check_out'] ?? null);

    $downpaymentCash = (float) ($input['downpayment_cash'] ?? 0);
    $downpaymentGcash = (float) ($input['downpayment_gcash'] ?? 0);
    $downpaymentMaya = (float) ($input['downpayment_maya'] ?? 0);
    $downpaymentInstapay = (float) ($input['downpayment_instapay'] ?? 0);
    $downpaymentOnlineBanking = (float) ($input['downpayment_online_banking'] ?? 0);
    $downpaymentAirbnb = (float) ($input['downpayment_airbnb'] ?? 0);
    $downpaymentAmount = $downpaymentCash + $downpaymentGcash + $downpaymentMaya
        + $downpaymentInstapay + $downpaymentOnlineBanking + $downpaymentAirbnb;

    $downpaymentGcashRef = trim((string) ($input['downpayment_gcash_ref'] ?? ''));
    $downpaymentMayaRef = trim((string) ($input['downpayment_maya_ref'] ?? ''));
    $downpaymentInstapayRef = trim((string) ($input['downpayment_instapay_ref'] ?? ''));
    $downpaymentOnlineBankingRef = trim((string) ($input['downpayment_online_banking_ref'] ?? ''));
    $downpaymentAirbnbRef = trim((string) ($input['downpayment_airbnb_ref'] ?? ''));

    $totalAmountReservation = (float) ($input['total_amount_reservation'] ?? 0);
    $downpaymentStatus = $downpaymentAmount > 0 ? 'Paid' : 'None';
    $modificationUpdatedAt = date('Y-m-d H:i:s');

    $paymentFields = buildReservationPaymentFields(
        $downpaymentCash,
        $downpaymentGcash,
        $downpaymentMaya,
        $downpaymentInstapay,
        $downpaymentOnlineBanking,
        $downpaymentAirbnb,
        $downpaymentGcashRef,
        $downpaymentMayaRef,
        $downpaymentInstapayRef,
        $downpaymentOnlineBankingRef,
        $downpaymentAirbnbRef
    );

    if ($reservationDate === null || $reservationDate === '') {
        echo json_encode(['success' => false, 'message' => 'Reservation date is required']);
        exit;
    }

    if ($guestName === '') {
        echo json_encode(['success' => false, 'message' => 'Guest name is required']);
        exit;
    }

    if ($roomId === '' || $roomType === '') {
        echo json_encode(['success' => false, 'message' => 'Room type and room ID are required']);
        exit;
    }

    // Conflict check when reservation date or room changes.
    $durationForConflict = $duration > 0 ? $duration : (int) ($existing['duration'] ?? 0);
    $durationUnitForConflict = $durationUnit !== '' ? $durationUnit : ($existing['duration_unit'] ?? 'hours');
    $promoForConflict = $promo !== '' ? $promo : trim((string) ($existing['promo'] ?? ''));

    $startObj = new DateTime($reservationDate);
    $totalHours = (strtolower($durationUnitForConflict) === 'night' || strtolower($durationUnitForConflict) === 'nights')
        ? ($durationForConflict * 12)
        : $durationForConflict;
    if ($promoForConflict !== '' && !in_array($promoForConflict, ['None', 'Regular', 'Select Bundle', 'Select Promo'], true)) {
        $totalHours += 12;
    }
    if ($totalHours < 0) {
        $totalHours = 0;
    }
    $endObj = clone $startObj;
    if ($totalHours > 0) {
        $endObj->modify('+' . (int) $totalHours . ' hours');
    }
    $endPlus30Obj = (clone $endObj)->add(new DateInterval('PT30M'));
    $newStart = $startObj->format('Y-m-d H:i:s');
    $newEndPlus30 = $endPlus30Obj->format('Y-m-d H:i:s');

    $confStmt = $conn->prepare("
        SELECT id, booking_id, guest_name, reservation_date
        FROM bookings
        WHERE room_id = :room_id
          AND booking_type = 'Reservation'
          AND id <> :id
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
    $confStmt->bindParam(':room_id', $roomId);
    $confStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $confStmt->bindParam(':new_start', $newStart);
    $confStmt->bindParam(':new_end_plus30', $newEndPlus30);
    $confStmt->execute();
    $conflict = $confStmt->fetch(PDO::FETCH_ASSOC);

    if ($conflict) {
        echo json_encode([
            'success' => false,
            'message' => 'This room already has a reservation that overlaps this slot (including +30 min cleaning gap). '
                . 'Existing Booking ID: ' . ($conflict['booking_id'] ?? 'N/A')
                . ' (' . date('m/d/Y h:i A', strtotime($conflict['reservation_date'])) . ').',
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE bookings SET
            room_id = :room_id,
            room_type = :room_type,
            guest_name = :guest_name,
            guest_type = :guest_type,
            contact_no = :contact_no,
            address = :address,
            reason_for_stay = :reason_for_stay,
            request = :request,
            referral_name = :referral_name,
            reservation_date = :reservation_date,
            check_in = :check_in,
            check_out = :check_out,
            duration = :duration,
            duration_unit = :duration_unit,
            promo = :promo,
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
            total_amount_reservation = :total_amount_reservation,
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
            payment_amount_cash_history = :payment_amount_cash_history,
            payment_amount_g_cash_history = :payment_amount_g_cash_history,
            payment_amount_maya_history = :payment_amount_maya_history,
            payment_amount_instapay_history = :payment_amount_instapay_history,
            payment_amount_online_banking_history = :payment_amount_online_banking_history,
            payment_amount_airbnb_history = :payment_amount_airbnb_history,
            deposit = 0,
            deposit_cash = 0,
            deposit_g_cash = 0,
            deposit_maya = 0,
            deposit_instapay = 0,
            deposit_online_banking = 0,
            deposit_airbnb = 0,
            modification_reason = :modification_reason,
            modification_updated_at = :modification_updated_at,
            rebooked_flag = 1
        WHERE id = :id
          AND booking_type = 'Reservation'
        LIMIT 1
    ");

    $stmt->bindParam(':room_id', $roomId);
    $stmt->bindParam(':room_type', $roomType);
    $stmt->bindParam(':guest_name', $guestName);
    $stmt->bindParam(':guest_type', $guestType);
    $stmt->bindParam(':contact_no', $contactNo);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':reason_for_stay', $reasonForStay);
    $stmt->bindParam(':request', $request);
    $stmt->bindParam(':referral_name', $referralName);
    $stmt->bindParam(':reservation_date', $reservationDate);
    $stmt->bindValue(':check_in', $checkIn, $checkIn ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':check_out', $checkOut, $checkOut ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindParam(':duration', $duration, PDO::PARAM_INT);
    $stmt->bindParam(':duration_unit', $durationUnit);
    $stmt->bindParam(':promo', $promo);
    $stmt->bindParam(':downpayment_amount', $downpaymentAmount);
    $stmt->bindParam(':downpayment_cash', $downpaymentCash);
    $stmt->bindParam(':downpayment_gcash', $downpaymentGcash);
    $stmt->bindParam(':downpayment_maya', $downpaymentMaya);
    $stmt->bindParam(':downpayment_instapay', $downpaymentInstapay);
    $stmt->bindParam(':downpayment_online_banking', $downpaymentOnlineBanking);
    $stmt->bindParam(':downpayment_airbnb', $downpaymentAirbnb);
    $stmt->bindParam(':downpayment_gcash_ref', $downpaymentGcashRef);
    $stmt->bindParam(':downpayment_maya_ref', $downpaymentMayaRef);
    $stmt->bindParam(':downpayment_instapay_ref', $downpaymentInstapayRef);
    $stmt->bindParam(':downpayment_online_banking_ref', $downpaymentOnlineBankingRef);
    $stmt->bindParam(':downpayment_airbnb_ref', $downpaymentAirbnbRef);
    $stmt->bindParam(':downpayment_status', $downpaymentStatus);
    $stmt->bindParam(':total_amount_reservation', $totalAmountReservation);
    $stmt->bindParam(':payment_status', $paymentFields['payment_status']);
    $stmt->bindParam(':payment_status_cash', $paymentFields['payment_status_cash']);
    $stmt->bindParam(':payment_status_g_cash', $paymentFields['payment_status_g_cash']);
    $stmt->bindParam(':payment_status_maya', $paymentFields['payment_status_maya']);
    $stmt->bindParam(':payment_status_instapay', $paymentFields['payment_status_instapay']);
    $stmt->bindParam(':payment_status_online_banking', $paymentFields['payment_status_online_banking']);
    $stmt->bindParam(':payment_status_airbnb', $paymentFields['payment_status_airbnb']);
    $stmt->bindParam(':reference_no', $paymentFields['reference_no']);
    $stmt->bindParam(':reference_no_g_cash', $paymentFields['reference_no_g_cash']);
    $stmt->bindParam(':reference_no_maya', $paymentFields['reference_no_maya']);
    $stmt->bindParam(':reference_no_instapay', $paymentFields['reference_no_instapay']);
    $stmt->bindParam(':reference_no_online_banking', $paymentFields['reference_no_online_banking']);
    $stmt->bindParam(':reference_no_airbnb', $paymentFields['reference_no_airbnb']);
    $stmt->bindParam(':payment_amount_cash_history', $paymentFields['payment_amount_cash_history']);
    $stmt->bindParam(':payment_amount_g_cash_history', $paymentFields['payment_amount_g_cash_history']);
    $stmt->bindParam(':payment_amount_maya_history', $paymentFields['payment_amount_maya_history']);
    $stmt->bindParam(':payment_amount_instapay_history', $paymentFields['payment_amount_instapay_history']);
    $stmt->bindParam(':payment_amount_online_banking_history', $paymentFields['payment_amount_online_banking_history']);
    $stmt->bindParam(':payment_amount_airbnb_history', $paymentFields['payment_amount_airbnb_history']);
    $stmt->bindParam(':modification_reason', $modificationReason);
    $stmt->bindParam(':modification_updated_at', $modificationUpdatedAt);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() <= 0) {
        echo json_encode(['success' => false, 'message' => 'No changes applied']);
        exit;
    }

    $bookingId = $existing['booking_id'] ?? null;
    if ($bookingId) {
        try {
            $reportStmt = $conn->prepare("
                UPDATE reports SET
                    room_id = :room_id,
                    room_type = :room_type,
                    guest_name = :guest_name,
                    guest_type = :guest_type,
                    contact_no = :contact_no,
                    address = :address,
                    reason_for_stay = :reason_for_stay,
                    request = :request,
                    referral_name = :referral_name,
                    reservation_date = :reservation_date,
                    check_in = :check_in,
                    check_out = :check_out,
                    duration = :duration,
                    duration_unit = :duration_unit,
                    promo = :promo,
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
                    total_amount_reservation = :total_amount_reservation,
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
                    payment_amount_cash_history = :payment_amount_cash_history,
                    payment_amount_g_cash_history = :payment_amount_g_cash_history,
                    payment_amount_maya_history = :payment_amount_maya_history,
                    payment_amount_instapay_history = :payment_amount_instapay_history,
                    payment_amount_online_banking_history = :payment_amount_online_banking_history,
                    payment_amount_airbnb_history = :payment_amount_airbnb_history,
                    deposit = 0,
                    deposit_cash = 0,
                    deposit_g_cash = 0,
                    deposit_maya = 0,
                    deposit_instapay = 0,
                    deposit_online_banking = 0,
                    deposit_airbnb = 0,
                    modification_reason = :modification_reason,
                    modification_updated_at = :modification_updated_at,
                    rebooked_flag = 1
                WHERE booking_id = :booking_id
            ");
            $reportStmt->bindParam(':booking_id', $bookingId);
            $reportStmt->bindParam(':room_id', $roomId);
            $reportStmt->bindParam(':room_type', $roomType);
            $reportStmt->bindParam(':guest_name', $guestName);
            $reportStmt->bindParam(':guest_type', $guestType);
            $reportStmt->bindParam(':contact_no', $contactNo);
            $reportStmt->bindParam(':address', $address);
            $reportStmt->bindParam(':reason_for_stay', $reasonForStay);
            $reportStmt->bindParam(':request', $request);
            $reportStmt->bindParam(':referral_name', $referralName);
            $reportStmt->bindParam(':reservation_date', $reservationDate);
            $reportStmt->bindValue(':check_in', $checkIn, $checkIn ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $reportStmt->bindValue(':check_out', $checkOut, $checkOut ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $reportStmt->bindParam(':duration', $duration, PDO::PARAM_INT);
            $reportStmt->bindParam(':duration_unit', $durationUnit);
            $reportStmt->bindParam(':promo', $promo);
            $reportStmt->bindParam(':downpayment_amount', $downpaymentAmount);
            $reportStmt->bindParam(':downpayment_cash', $downpaymentCash);
            $reportStmt->bindParam(':downpayment_gcash', $downpaymentGcash);
            $reportStmt->bindParam(':downpayment_maya', $downpaymentMaya);
            $reportStmt->bindParam(':downpayment_instapay', $downpaymentInstapay);
            $reportStmt->bindParam(':downpayment_online_banking', $downpaymentOnlineBanking);
            $reportStmt->bindParam(':downpayment_airbnb', $downpaymentAirbnb);
            $reportStmt->bindParam(':downpayment_gcash_ref', $downpaymentGcashRef);
            $reportStmt->bindParam(':downpayment_maya_ref', $downpaymentMayaRef);
            $reportStmt->bindParam(':downpayment_instapay_ref', $downpaymentInstapayRef);
            $reportStmt->bindParam(':downpayment_online_banking_ref', $downpaymentOnlineBankingRef);
            $reportStmt->bindParam(':downpayment_airbnb_ref', $downpaymentAirbnbRef);
            $reportStmt->bindParam(':downpayment_status', $downpaymentStatus);
            $reportStmt->bindParam(':total_amount_reservation', $totalAmountReservation);
            $reportStmt->bindParam(':payment_status', $paymentFields['payment_status']);
            $reportStmt->bindParam(':payment_status_cash', $paymentFields['payment_status_cash']);
            $reportStmt->bindParam(':payment_status_g_cash', $paymentFields['payment_status_g_cash']);
            $reportStmt->bindParam(':payment_status_maya', $paymentFields['payment_status_maya']);
            $reportStmt->bindParam(':payment_status_instapay', $paymentFields['payment_status_instapay']);
            $reportStmt->bindParam(':payment_status_online_banking', $paymentFields['payment_status_online_banking']);
            $reportStmt->bindParam(':payment_status_airbnb', $paymentFields['payment_status_airbnb']);
            $reportStmt->bindParam(':reference_no', $paymentFields['reference_no']);
            $reportStmt->bindParam(':reference_no_g_cash', $paymentFields['reference_no_g_cash']);
            $reportStmt->bindParam(':reference_no_maya', $paymentFields['reference_no_maya']);
            $reportStmt->bindParam(':reference_no_instapay', $paymentFields['reference_no_instapay']);
            $reportStmt->bindParam(':reference_no_online_banking', $paymentFields['reference_no_online_banking']);
            $reportStmt->bindParam(':reference_no_airbnb', $paymentFields['reference_no_airbnb']);
            $reportStmt->bindParam(':payment_amount_cash_history', $paymentFields['payment_amount_cash_history']);
            $reportStmt->bindParam(':payment_amount_g_cash_history', $paymentFields['payment_amount_g_cash_history']);
            $reportStmt->bindParam(':payment_amount_maya_history', $paymentFields['payment_amount_maya_history']);
            $reportStmt->bindParam(':payment_amount_instapay_history', $paymentFields['payment_amount_instapay_history']);
            $reportStmt->bindParam(':payment_amount_online_banking_history', $paymentFields['payment_amount_online_banking_history']);
            $reportStmt->bindParam(':payment_amount_airbnb_history', $paymentFields['payment_amount_airbnb_history']);
            $reportStmt->bindParam(':modification_reason', $modificationReason);
            $reportStmt->bindParam(':modification_updated_at', $modificationUpdatedAt);
            $reportStmt->execute();
        } catch (Exception $e) {
            // Best-effort sync only
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reservation details updated successfully',
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
