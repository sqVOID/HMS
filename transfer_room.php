<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'report_helpers.php';
require_once 'system_logger.php';
header('Content-Type: application/json; charset=utf-8');
$_u_first = trim($_SESSION['first_name'] ?? '');
$_u_last = trim($_SESSION['last_name'] ?? '');
$_actor = ($_u_first !== '' || $_u_last !== '') ? trim($_u_first . ' ' . $_u_last) : trim($_SESSION['username'] ?? 'Unknown');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'] ?? null;
    $target_room_id = $_POST['target_room_id'] ?? null;
    $refund_amount_raw = $_POST['refund_amount'] ?? null;

    if (!$booking_id || !$target_room_id) {
        $response['message'] = 'Booking ID and Target Room ID are required.';
        if (ob_get_length()) {
            ob_clean();
        }
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
            if (ob_get_length()) {
                ob_clean();
            }
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
            if (ob_get_length()) {
                ob_clean();
            }
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

        $paymentStatusRaw = (string) ($booking['payment_status'] ?? '');
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

        // Determine the target room price and duration.
        $targetRoomDbId = intval($room['id'] ?? 0);
        $durationHours = convertDurationToHours(intval($booking['duration'] ?? 0), $booking['duration_unit'] ?? 'hours');
        $target_room_type = $room['type'] ?? $room['room_type'] ?? '';

        $currentPromo = trim($booking['promo'] ?? '');
        $isBundleBooking = ($currentPromo !== ''
            && strcasecmp($currentPromo, 'regular') !== 0
            && strcasecmp($currentPromo, 'none') !== 0
            && strcasecmp($currentPromo, 'select bundle') !== 0
            && strcasecmp($currentPromo, 'select promo') !== 0);

        // Bundle duration is often encoded in the promo label when duration column is 0/stale
        if ($durationHours <= 0 && $isBundleBooking && preg_match('/(\d+)\s*hrs?/i', $currentPromo, $promoHoursMatch)) {
            $durationHours = intval($promoHoursMatch[1]);
        }

        $updatedPromo = $currentPromo;
        $targetRoomPrice = 0.0;
        $hasExactDuration = false;
        $targetDuration = 0;
        $targetDurationUnit = 'hours';
        $targetCheckOut = null;
        $bundleRemapped = false;
        $bundleCleared = false;

        // If booking is a promo/bundle package, find target room type's matching promo bundle price.
        // Promo titles in this system are keyed by room type (e.g. "Premium 2", "Transient").
        if ($isBundleBooking) {
            $promoStmt = $conn->prepare("SELECT * FROM promos WHERE LOWER(TRIM(title)) = LOWER(TRIM(:room_type)) LIMIT 1");
            $promoStmt->bindParam(':room_type', $target_room_type);
            $promoStmt->execute();
            $targetPromo = $promoStmt->fetch(PDO::FETCH_ASSOC);

            if ($targetPromo) {
                $pPrice = 0.0;
                if ($durationHours >= 24 && !empty($targetPromo['price_24hrs']) && floatval($targetPromo['price_24hrs']) > 0) {
                    $pPrice = floatval($targetPromo['price_24hrs']);
                    $updatedPromo = "Package 2 24hrs - ₱" . number_format($pPrice, 2, '.', '');
                    $hasExactDuration = true;
                    $targetDuration = 24;
                    $bundleRemapped = true;
                } elseif ($durationHours >= 12 && !empty($targetPromo['price_12hrs']) && floatval($targetPromo['price_12hrs']) > 0) {
                    $pPrice = floatval($targetPromo['price_12hrs']);
                    $updatedPromo = "Package 1 12hrs - ₱" . number_format($pPrice, 2, '.', '');
                    $hasExactDuration = true;
                    $targetDuration = 12;
                    $bundleRemapped = true;
                } elseif ($durationHours > 0 && !empty($targetPromo['price_12hrs']) && floatval($targetPromo['price_12hrs']) > 0 && $durationHours < 12) {
                    // Keep short stays on 12hr package when that is the only bundle tier
                    $pPrice = floatval($targetPromo['price_12hrs']);
                    $updatedPromo = "Package 1 12hrs - ₱" . number_format($pPrice, 2, '.', '');
                    $hasExactDuration = true;
                    $targetDuration = 12;
                    $bundleRemapped = true;
                }

                if ($pPrice > 0) {
                    $targetRoomPrice = $pPrice;
                }
            }

            // Target room type has no matching bundle — unlock promo so user selects a rate
            // on the new room (same UX as Regular transfer when duration is unavailable).
            if (!$bundleRemapped) {
                $updatedPromo = 'None';
                $bundleCleared = true;
                $hasExactDuration = false;
                $targetRoomPrice = 0.0;
                $targetDuration = 0;
                $targetDurationUnit = 'hours';
                $targetCheckOut = null;
            }
        }

        // Regular-rate transfer (or bundle successfully remapped already handled above):
        // look up exact duration price on the target room. Never keep an old bundle price
        // by falling through to a regular duration match while promo is still attached.
        if (!$bundleCleared && $targetRoomPrice <= 0 && $targetRoomDbId > 0 && $durationHours > 0) {
            $priceStmt = $conn->prepare("
                SELECT price, duration_unit
                FROM room_durations
                WHERE room_id = :room_id AND duration_hours = :hours
                LIMIT 1
            ");
            $priceStmt->bindParam(':room_id', $targetRoomDbId, PDO::PARAM_INT);
            $priceStmt->bindParam(':hours', $durationHours, PDO::PARAM_INT);
            $priceStmt->execute();
            $row = $priceStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['price'])) {
                $hasExactDuration = true;
                $targetDuration = $durationHours;
                $targetDurationUnit = $row['duration_unit'] ?? ($booking['duration_unit'] ?? 'hours');
                $targetRoomPrice = floatval($row['price']);
                // Ensure promo is not a leftover bundle when pricing from regular rates
                if ($isBundleBooking && !$bundleRemapped) {
                    $updatedPromo = 'None';
                    $bundleCleared = true;
                }
            }
        }

        // If target room does not have this duration, reset duration, checkout and room price so user selects it in the new room
        if (!$hasExactDuration) {
            $targetDuration = 0;
            $targetDurationUnit = 'hours';
            $targetRoomPrice = 0.0;
            $targetCheckOut = null;
            if ($isBundleBooking && !$bundleRemapped) {
                $updatedPromo = 'None';
                $bundleCleared = true;
            }
        } else {
            // Recalculate check_out from check_in and targetDuration
            if (!empty($booking['check_in']) && $targetDuration > 0) {
                $checkInTs = strtotime($booking['check_in']);
                if ($checkInTs) {
                    $targetCheckOut = date('Y-m-d H:i:s', $checkInTs + ($targetDuration * 3600));
                }
            }
        }

        // Remaining balance and (max) refundable overpayment after transfer.
        $remaining = max(0.0, round($targetRoomPrice - $amountPaid, 2));
        $maxRefundChange = max(0.0, round($amountPaid - $targetRoomPrice, 2));
        // Bundle cleared / duration reset → always Unpaid so staff re-select rate & settle balance.
        // Exact match with remaining 0 → Paid; otherwise Unpaid.
        if ($bundleCleared || $targetDuration <= 0 || $targetRoomPrice <= 0) {
            $newPaidStatus = 'Unpaid';
            $remaining = max(0.0, round($amountPaid > 0 ? max(0, $targetRoomPrice - $amountPaid) : 0, 2));
            // When price is unknown (0), keep remaining as 0 in DB response but force Unpaid;
            // checkout/edit will recompute once a new rate is selected.
            if ($targetRoomPrice <= 0) {
                $remaining = 0.0;
            }
        } else {
            $newPaidStatus = ($remaining <= 0.0) ? 'Paid' : 'Unpaid';
        }

        // Manual refund amount (optional). If not provided/empty, store 0 (do not auto-refund).
        $manualRefund = 0.0;
        if ($refund_amount_raw !== null && $refund_amount_raw !== '') {
            $manualRefund = floatval($refund_amount_raw);
            if (!is_finite($manualRefund) || $manualRefund < 0)
                $manualRefund = 0.0;
        }
        // Safety: refund should not exceed computed max overpayment.
        $manualRefund = min($manualRefund, $maxRefundChange);

        // Check if there's an overlap in the target room
        $check_in = $booking['check_in'] ?? null;
        $check_out = $targetCheckOut ?? $booking['check_out'] ?? null;

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
                if (ob_get_length()) {
                    ob_clean();
                }
                echo json_encode($response);
                exit;
            }
        }

        // Store the original room_id and room_type before transfer
        $original_room_id = $booking['room_id'] ?? '';
        $original_room_type = $booking['room_type'] ?? '';
        $transfer_room_from = $original_room_type . ' ' . $original_room_id;
        $transfer_timestamp = date('Y-m-d H:i:s');

        // Update booking with new room_id, room_type, room_image, room_price, duration, check_out, promo
        // Also update pricing + payment state so remaining balance is reflected.
        // Store transfer_room_from and transfer_at for tracking
        $updateStmt = $conn->prepare("
            UPDATE bookings
            SET room_id = :target_room_id,
                room_type = :target_room_type,
                room_image = :target_room_image,
                room_price = :target_room_price,
                duration = :target_duration,
                duration_unit = :target_duration_unit,
                check_out = :target_check_out,
                promo = :promo,
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
        $updateStmt->bindValue(':target_duration', $targetDuration, PDO::PARAM_INT);
        $updateStmt->bindParam(':target_duration_unit', $targetDurationUnit);
        $updateStmt->bindParam(':target_check_out', $targetCheckOut);
        $updateStmt->bindParam(':promo', $updatedPromo);
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
                        duration = :target_duration,
                        duration_unit = :target_duration_unit,
                        check_out = :target_check_out,
                        promo = :promo,
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
                $updateReportsStmt->bindValue(':target_duration', $targetDuration, PDO::PARAM_INT);
                $updateReportsStmt->bindParam(':target_duration_unit', $targetDurationUnit);
                $updateReportsStmt->bindParam(':target_check_out', $targetCheckOut);
                $updateReportsStmt->bindParam(':promo', $updatedPromo);
                $updateReportsStmt->bindValue(':transfer_refund_amount', $manualRefund);
                $updateReportsStmt->bindParam(':paid_status', $newPaidStatus);
                $updateReportsStmt->bindParam(':transfer_room_from', $transfer_room_from);
                $updateReportsStmt->bindParam(':transfer_at', $transfer_timestamp);
                $booking_uid = $booking['booking_id'] ?? '';
                $updateReportsStmt->bindParam(':booking_uid', $booking_uid);
                $updateReportsStmt->execute();
            } catch (PDOException $e) {
                // Ignore if report update fails
            }

            $response['success'] = true;
            $response['message'] = $bundleCleared
                ? 'Room transferred successfully! The previous bundle is not available on the new room — please select a new rate and settle the balance.'
                : 'Room transferred successfully!';
            $response['remaining_balance'] = $remaining;
            $response['refund_change'] = $manualRefund;
            $response['max_refund_available'] = $maxRefundChange;
            $response['paid_status'] = $newPaidStatus;
            $response['bundle_cleared'] = $bundleCleared;
            $response['promo'] = $updatedPromo;

            try {
                $guestName = $booking['guest_name'] ?? $booking['guest_names'] ?? 'Unknown';
                logActivity(
                    $conn,
                    'update',
                    'ROOM_TRANSFER',
                    "Booking #{$booking_id} (Guest: {$guestName}) transferred from Room {$original_room_id} to Room {$target_room_id} by {$_actor}",
                    ['booking_id' => $booking_id, 'from_room' => $original_room_id, 'to_room' => $target_room_id, 'refund' => $manualRefund, 'new_status' => $newPaidStatus, 'transferred_by' => $_actor]
                );
            } catch (Throwable $e) {
                // Ignore logging errors
            }
        } else {
            $response['message'] = 'Failed to update booking.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    } catch (Throwable $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

if (ob_get_length()) {
    ob_clean();
}
echo json_encode($response);
exit;
?>