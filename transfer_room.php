<?php
require_once 'config.php';
require_once 'report_helpers.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'] ?? null;
    $target_room_id = $_POST['target_room_id'] ?? null;
    $refund_amount_raw = $_POST['refund_amount'] ?? null;

    if (!$booking_id || !$target_room_id) {
        $response['message'] = 'Booking ID and Target Room ID are required.';
        echo json_encode($response);
        exit;
    }

    try {
        // Fetch current booking
        $getStmt = $conn->prepare("SELECT * FROM bookings WHERE id = :booking_id");
        $getStmt->bindParam(':booking_id', $booking_id);
        $getStmt->execute();
        $booking = $getStmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $response['message'] = 'Booking not found.';
            echo json_encode($response);
            exit;
        }

        // Fetch target room details from rooms table
        $getRoomStmt = $conn->prepare("SELECT * FROM rooms WHERE room_id = :target_room_id");
        $getRoomStmt->bindParam(':target_room_id', $target_room_id);
        $getRoomStmt->execute();
        $room = $getRoomStmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            $response['message'] = 'Target room not found.';
            echo json_encode($response);
            exit;
        }

        // Compute how much was already paid for this booking.
        // Reservation transfers have two separate payment buckets:
        // 1) reservation/downpayment
        // 2) check-in payment (deposit/payment_status)
        // Count each bucket only once so the checkout modal does not double
        // the Payment and Total Paid values after a transfer.
        $existingDeposit = floatval($booking['deposit'] ?? 0);
        $depositBreakdownTotal =
            floatval($booking['deposit_cash'] ?? 0) +
            floatval($booking['deposit_g_cash'] ?? 0) +
            floatval($booking['deposit_maya'] ?? 0);
        $depositToPersist = max($existingDeposit, $depositBreakdownTotal);

        $reservationAmount = floatval($booking['downpayment_amount'] ?? 0);
        $reservationBreakdownTotal =
            floatval($booking['downpayment_cash'] ?? 0) +
            floatval($booking['downpayment_gcash'] ?? 0) +
            floatval($booking['downpayment_maya'] ?? 0);
        $reservationPaid = max($reservationAmount, $reservationBreakdownTotal);

        $paymentStatusRaw = (string)($booking['payment_status'] ?? '');
        $paymentStatusPaid = 0.0;
        if ($paymentStatusRaw !== '') {
            if (preg_match_all('/₱\s*([0-9,]+(?:\.[0-9]+)?)/u', $paymentStatusRaw, $m)) {
                foreach (($m[1] ?? []) as $num) {
                    $paymentStatusPaid += floatval(str_replace(',', '', $num));
                }
            }
        }

        // payment_status normally represents the actual check-in payment.
        // Fall back to deposit fields only when payment_status has no amount.
        $checkInPaid = $paymentStatusPaid > 0 ? $paymentStatusPaid : $depositToPersist;
        $amountPaid = $reservationPaid + $checkInPaid;

        // Fallback: if we still can't detect paid amount, use the previous room price
        // for paid bookings, otherwise use 0.
        if ($amountPaid <= 0.0 && (($booking['paid_status'] ?? '') === 'Paid')) {
            $amountPaid = floatval($booking['room_price'] ?? 0);
        }

        // Determine the target room price for the booking duration.
        // Prefer the room_durations table; if no matching duration exists,
        // fall back to the closest available duration (smallest >= hours, else largest).
        $targetRoomDbId = intval($room['id'] ?? 0);
        $durationHours = convertDurationToHours(intval($booking['duration'] ?? 0), $booking['duration_unit'] ?? 'hours');

        $targetRoomPrice = 0.0;
        if ($targetRoomDbId > 0 && $durationHours > 0) {
            $priceStmt = $conn->prepare("
                SELECT price
                FROM room_durations
                WHERE room_id = :room_id AND duration_hours >= :hours
                ORDER BY duration_hours ASC
                LIMIT 1
            ");
            $priceStmt->bindParam(':room_id', $targetRoomDbId, PDO::PARAM_INT);
            $priceStmt->bindParam(':hours', $durationHours, PDO::PARAM_INT);
            $priceStmt->execute();
            $row = $priceStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['price'])) {
                $targetRoomPrice = floatval($row['price']);
            } else {
                $fallbackStmt = $conn->prepare("
                    SELECT price
                    FROM room_durations
                    WHERE room_id = :room_id
                    ORDER BY duration_hours DESC
                    LIMIT 1
                ");
                $fallbackStmt->bindParam(':room_id', $targetRoomDbId, PDO::PARAM_INT);
                $fallbackStmt->execute();
                $row2 = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                if ($row2 && isset($row2['price'])) {
                    $targetRoomPrice = floatval($row2['price']);
                }
            }
        }

        // Last resort: use static room-type pricing
        $target_room_type = $room['type'] ?? $room['room_type'] ?? '';
        if ($targetRoomPrice <= 0 && $durationHours > 0) {
            $targetRoomPrice = calculateRoomRate($target_room_type, $durationHours);
        }

        // Remaining balance and (max) refundable overpayment after transfer.
        $remaining = max(0.0, round($targetRoomPrice - $amountPaid, 2));
        $maxRefundChange = max(0.0, round($amountPaid - $targetRoomPrice, 2));
        $newPaidStatus = ($remaining > 0.0) ? 'Unpaid' : 'Paid';

        // Manual refund amount (optional). If not provided/empty, store 0 (do not auto-refund).
        $manualRefund = 0.0;
        if ($refund_amount_raw !== null && $refund_amount_raw !== '') {
            $manualRefund = floatval($refund_amount_raw);
            if (!is_finite($manualRefund) || $manualRefund < 0) $manualRefund = 0.0;
        }
        // Safety: refund should not exceed computed max overpayment.
        $manualRefund = min($manualRefund, $maxRefundChange);

        // Check if there's an overlap in the target room
        $check_in = $booking['check_in'];
        $check_out = $booking['check_out'];

        if ($check_in && $check_out) {
            $overlapBookingStmt = $conn->prepare("
                SELECT booking_id FROM bookings
                WHERE room_id = :room_id
                  AND status IN ('Confirming', 'Confirmed', 'Occupied')
                  AND check_in < :new_check_out
                  AND check_out > :new_check_in
                LIMIT 1
            ");
            $overlapBookingStmt->bindParam(':room_id', $target_room_id);
            $overlapBookingStmt->bindParam(':new_check_in', $check_in);
            $overlapBookingStmt->bindParam(':new_check_out', $check_out);
            $overlapBookingStmt->execute();
            $conflictBooking = $overlapBookingStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflictBooking) {
                $response['message'] = "The target room is already booked during this period.";
                echo json_encode($response);
                exit;
            }
        }

        // Store the original room_id and room_type before transfer
        $original_room_id = $booking['room_id'];
        $original_room_type = $booking['room_type'];
        $transfer_room_from = $original_room_type . ' ' . $original_room_id;
        $transfer_timestamp = date('Y-m-d H:i:s');
        
        // Update booking with new room_id, room_type, room_image
        // Also update pricing + payment state so remaining balance is reflected.
        // Store transfer_room_from and transfer_at for tracking
        $updateStmt = $conn->prepare("
            UPDATE bookings
            SET room_id = :target_room_id,
                room_type = :target_room_type,
                room_image = :target_room_image,
                room_price = :target_room_price,
                deposit = :deposit_amount,
                transfer_refund_amount = :transfer_refund_amount,
                paid_status = :paid_status,
                transfer_room_from = :transfer_room_from,
                transfer_at = :transfer_at
            WHERE id = :booking_id
        ");
        $updateStmt->bindParam(':target_room_id', $target_room_id);
        $updateStmt->bindParam(':target_room_type', $target_room_type);
        $target_room_image = $room['image'] ?? $room['room_image'] ?? '';
        $updateStmt->bindParam(':target_room_image', $target_room_image);
        $updateStmt->bindValue(':target_room_price', $targetRoomPrice);
        $updateStmt->bindValue(':deposit_amount', $depositToPersist);
        $updateStmt->bindValue(':transfer_refund_amount', $manualRefund);
        $updateStmt->bindParam(':paid_status', $newPaidStatus);
        $updateStmt->bindParam(':transfer_room_from', $transfer_room_from);
        $updateStmt->bindParam(':transfer_at', $transfer_timestamp);
        $updateStmt->bindParam(':booking_id', $booking_id);
        
        if ($updateStmt->execute()) {
            // Update reports if they exist
            try {
                $updateReportsStmt = $conn->prepare("
                    UPDATE reports
                    SET room_id = :target_room_id,
                        room_type = :target_room_type,
                        room_image = :target_room_image,
                        room_price = :target_room_price,
                        transfer_refund_amount = :transfer_refund_amount,
                        paid_status = :paid_status,
                        transfer_room_from = :transfer_room_from,
                        transfer_at = :transfer_at
                    WHERE booking_id = :booking_uid
                ");
                $updateReportsStmt->bindParam(':target_room_id', $target_room_id);
                $updateReportsStmt->bindParam(':target_room_type', $target_room_type);
                $updateReportsStmt->bindParam(':target_room_image', $target_room_image);
                $updateReportsStmt->bindValue(':target_room_price', $targetRoomPrice);
                $updateReportsStmt->bindValue(':transfer_refund_amount', $manualRefund);
                $updateReportsStmt->bindParam(':paid_status', $newPaidStatus);
                $updateReportsStmt->bindParam(':transfer_room_from', $transfer_room_from);
                $updateReportsStmt->bindParam(':transfer_at', $transfer_timestamp);
                $updateReportsStmt->bindParam(':booking_uid', $booking['booking_id']);
                $updateReportsStmt->execute();
            } catch (PDOException $e) {
                // Ignore if report update fails
            }

            $response['success'] = true;
            $response['message'] = 'Room transferred successfully!';
            $response['remaining_balance'] = $remaining;
            $response['refund_change'] = $manualRefund;
            $response['max_refund_available'] = $maxRefundChange;
            $response['paid_status'] = $newPaidStatus;
        } else {
            $response['message'] = 'Failed to update booking.';
        }
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
