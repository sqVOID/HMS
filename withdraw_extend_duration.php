<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'report_helpers.php';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $bookingId = $_POST['booking_id'] ?? null;

    if (!$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
        exit;
    }

    ensureReportFinancialColumns($conn);

    $stmt = $conn->prepare("
        SELECT id, booking_id, room_id, check_in, check_out,
               duration, duration_unit,
               extend_hours, extend_minutes, extend_price,
               extend_regular_rate, extend_bundle_rate, extend_bundle_breakfast,
               additional_guest, extend_additional_guest, extension_stack,
               withdrawn_extend_hours, withdrawn_extend_minutes, withdrawn_extend_price,
               withdrawn_extend_regular_rate, withdrawn_extend_bundle_rate, withdrawn_extend_bundle_breakfast,
               total_amount, paid_status, encoder
        FROM bookings WHERE id = :id
    ");
    $stmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    $stack = booking_extension_stack_bootstrap_from_row($booking);
    if (empty($stack)) {
        echo json_encode(['success' => false, 'message' => 'No extension found to withdraw']);
        exit;
    }

    $popped = array_pop($stack);
    $pH = intval($popped['h'] ?? 0);
    $pM = intval($popped['m'] ?? 0);
    $pP = floatval($popped['price'] ?? 0);
    $pReg = floatval($popped['reg'] ?? 0);
    $pBun = floatval($popped['bun'] ?? 0);
    $pBf = isset($popped['bf']) ? $popped['bf'] : null;
    $pEg = intval($popped['eg'] ?? 0);
    $pEp = intval($popped['ep'] ?? 0);

    $isPaid = (($booking['paid_status'] ?? '') === 'Paid');
    $segmentCost = $pP + ($pEg * 300) + ($pEp * 500);
    $refundAmount = $isPaid ? $segmentCost : 0.0;

    $prevWithdrawnHours = intval($booking['withdrawn_extend_hours'] ?? 0);
    $prevWithdrawnMinutes = intval($booking['withdrawn_extend_minutes'] ?? 0);
    $prevWithdrawnPrice = floatval($booking['withdrawn_extend_price'] ?? 0);
    $prevWithdrawnRegRate = floatval($booking['withdrawn_extend_regular_rate'] ?? 0);
    $prevWithdrawnBunRate = floatval($booking['withdrawn_extend_bundle_rate'] ?? 0);
    $prevWithdrawnBfast = $booking['withdrawn_extend_bundle_breakfast'] ?? null;

    $newWithdrawnHours = $prevWithdrawnHours + $pH;
    $newWithdrawnMinutes = $prevWithdrawnMinutes + $pM;
    $newWithdrawnPrice = $prevWithdrawnPrice + $pP;
    $newWithdrawnRegRate = $prevWithdrawnRegRate + $pReg;
    $newWithdrawnBunRate = $prevWithdrawnBunRate + $pBun;
    $newWithdrawnBfast = $prevWithdrawnBfast;
    if ($pBf && trim((string) $pBf) !== '' && strcasecmp(trim((string) $pBf), 'None') !== 0) {
        if ($newWithdrawnBfast && trim((string) $newWithdrawnBfast) !== '' && strcasecmp(trim((string) $newWithdrawnBfast), 'None') !== 0) {
            $newWithdrawnBfast = $newWithdrawnBfast . ' | ' . $pBf;
        } else {
            $newWithdrawnBfast = $pBf;
        }
    }

    $remAgg = booking_extension_stack_aggregate_segments($stack);
    $encStack = booking_extension_stack_encode($stack);

    // Subtract the withdrawn segment's guest/pet delta from additional_guest/additional_pet (they share one field)
    $currentAdditionalGuest = intval($booking['additional_guest'] ?? 0);
    $newAdditionalGuest = max(0, $currentAdditionalGuest - $pEg);

    $currentAdditionalPet = intval($booking['additional_pet'] ?? 0);
    $newAdditionalPet = max(0, $currentAdditionalPet - $pEp);

    $originalCheckout = null;
    if ($booking['check_out']) {
        $dt = new DateTime($booking['check_out']);
        if ($pH > 0) {
            $dt->modify("-{$pH} hours");
        }
        if ($pM > 0) {
            $dt->modify("-{$pM} minutes");
        }
        $originalCheckout = $dt->format('Y-m-d H:i:s');
    }

    $currentTotal = floatval($booking['total_amount'] ?? 0);
    $restoredTotal = max(0, $currentTotal - $segmentCost);

    $updateStmt = $conn->prepare("
        UPDATE bookings
        SET extension_withdraw      = 1,
            refund_amount_extension = :refund_amount_extension,
            withdrawn_extend_hours  = :withdrawn_extend_hours,
            withdrawn_extend_minutes = :withdrawn_extend_minutes,
            withdrawn_extend_price  = :withdrawn_extend_price,
            withdrawn_extend_regular_rate = :withdrawn_extend_regular_rate,
            withdrawn_extend_bundle_rate  = :withdrawn_extend_bundle_rate,
            withdrawn_extend_bundle_breakfast = :withdrawn_extend_bundle_breakfast,
            extend_hours             = :eh,
            extend_minutes           = :em,
            extend_price             = :ep,
            extend_regular_rate      = :ereg,
            extend_bundle_rate       = :ebun,
            extend_bundle_breakfast  = :ebf,
            additional_guest         = :additional_guest,
            additional_pet           = :additional_pet,
            extend_additional_guest  = 0,
            extension_stack          = :estack,
            check_out                = :check_out,
            total_amount             = :total_amount
        WHERE id = :id
    ");
    $updateStmt->execute([
        ':refund_amount_extension' => $refundAmount,
        ':withdrawn_extend_hours' => $newWithdrawnHours,
        ':withdrawn_extend_minutes' => $newWithdrawnMinutes,
        ':withdrawn_extend_price' => $newWithdrawnPrice,
        ':withdrawn_extend_regular_rate' => $newWithdrawnRegRate,
        ':withdrawn_extend_bundle_rate' => $newWithdrawnBunRate,
        ':withdrawn_extend_bundle_breakfast' => $newWithdrawnBfast,
        ':eh' => $remAgg['h'],
        ':em' => $remAgg['m'],
        ':ep' => $remAgg['price'],
        ':ereg' => $remAgg['reg'],
        ':ebun' => $remAgg['bun'],
        ':ebf' => $remAgg['bf'],
        ':additional_guest' => $newAdditionalGuest,
        ':additional_pet' => $newAdditionalPet,
        ':estack' => $encStack,
        ':check_out' => $originalCheckout,
        ':total_amount' => $restoredTotal,
        ':id' => $bookingId,
    ]);

    $bookingIdString = trim((string)($booking['booking_id'] ?? ''));
    $roomIdValue = trim((string)($booking['room_id'] ?? ''));
    $checkInValue = $booking['check_in'] ?? null;

    if ($bookingIdString !== '') {
        $whereClause = "booking_id = :booking_id";
    } else {
        $whereClause = "room_id = :room_id AND check_in = :check_in AND status IN ('Confirming','Confirmed','Occupied')";
    }

    $updateReportStmt = $conn->prepare("
        UPDATE reports
        SET extension_withdraw      = 1,
            refund_amount_extension = :refund_amount_extension,
            withdrawn_extend_hours  = :withdrawn_extend_hours,
            withdrawn_extend_minutes = :withdrawn_extend_minutes,
            withdrawn_extend_price  = :withdrawn_extend_price,
            withdrawn_extend_regular_rate = :withdrawn_extend_regular_rate,
            withdrawn_extend_bundle_rate  = :withdrawn_extend_bundle_rate,
            withdrawn_extend_bundle_breakfast = :withdrawn_extend_bundle_breakfast,
            extend_hours             = :eh,
            extend_minutes           = :em,
            extend_price             = :ep,
            extend_regular_rate      = :ereg,
            extend_bundle_rate       = :ebun,
            extend_bundle_breakfast  = :ebf,
            additional_guest         = :additional_guest,
            additional_pet           = :additional_pet,
            extend_additional_guest  = 0,
            extension_stack          = :estack,
            check_out                = :check_out,
            total_amount             = :total_amount
        WHERE
            $whereClause
    ");

    $params = [
        ':refund_amount_extension' => $refundAmount,
        ':withdrawn_extend_hours' => $newWithdrawnHours,
        ':withdrawn_extend_minutes' => $newWithdrawnMinutes,
        ':withdrawn_extend_price' => $newWithdrawnPrice,
        ':withdrawn_extend_regular_rate' => $newWithdrawnRegRate,
        ':withdrawn_extend_bundle_rate' => $newWithdrawnBunRate,
        ':withdrawn_extend_bundle_breakfast' => $newWithdrawnBfast,
        ':eh' => $remAgg['h'],
        ':em' => $remAgg['m'],
        ':ep' => $remAgg['price'],
        ':ereg' => $remAgg['reg'],
        ':ebun' => $remAgg['bun'],
        ':ebf' => $remAgg['bf'],
        ':additional_guest' => $newAdditionalGuest,
        ':additional_pet' => $newAdditionalPet,
        ':estack' => $encStack,
        ':check_out' => $originalCheckout,
        ':total_amount' => $restoredTotal,
    ];
    if ($bookingIdString !== '') {
        $params[':booking_id'] = $bookingIdString;
    } else {
        $params[':room_id'] = $roomIdValue;
        $params[':check_in'] = $checkInValue;
    }
    $updateReportStmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Extension withdrawn successfully',
        'check_out' => $originalCheckout,
        'formatted_checkout' => date('Y-m-d h:i A', strtotime($originalCheckout)),
        'total_amount' => $restoredTotal,
        'extend_price_removed' => $pP,
        'refund_amount_extension' => $refundAmount,
        'extension_stack' => $encStack,
        'extend_hours' => $remAgg['h'],
        'extend_minutes' => $remAgg['m'],
        'extend_price' => $remAgg['price'],
        'extend_additional_guest' => $remAgg['eg'],
        'extend_additional_pet' => $remAgg['ep'] ?? 0,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
