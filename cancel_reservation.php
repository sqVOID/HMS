<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    $response['message'] = 'Missing reservation id';
    echo json_encode($response);
    exit;
}

try {
    // Look up the reservation booking row
    $stmt = $conn->prepare("
        SELECT id, booking_id, room_id, booking_type, status 
        FROM bookings 
        WHERE id = :id 
          AND booking_type = 'Reservation'
        LIMIT 1
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $response['message'] = 'Reservation not found';
        echo json_encode($response);
        exit;
    }

    $booking_id = $booking['booking_id'] ?? null;
    $room_id    = $booking['room_id'] ?? null;

    // Mark booking as canceled (keep the row) and clear any rebooked flag
    $updateBooking = $conn->prepare("
        UPDATE bookings 
        SET status = 'Canceled',
            rebooked_flag = 0
        WHERE id = :id 
          AND booking_type = 'Reservation'
        LIMIT 1
    ");
    $updateBooking->bindParam(':id', $id, PDO::PARAM_INT);
    $updateBooking->execute();

    // Sync reports table so cancellations appear correctly in reports
    if ($booking_id) {
        // If a report row already exists, just update its status + canceled_at
        $checkStmt = $conn->prepare("SELECT id FROM reports WHERE booking_id = :booking_id LIMIT 1");
        $checkStmt->bindParam(':booking_id', $booking_id);
        $checkStmt->execute();
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $updateReport = $conn->prepare("
                UPDATE reports
                SET status = 'Canceled',
                    canceled_at = NOW()
                WHERE booking_id = :booking_id
            ");
            $updateReport->bindParam(':booking_id', $booking_id);
            $updateReport->execute();
        } else {
            // No report row yet – insert one snapshot from bookings so it is counted
            $insertReport = $conn->prepare("
                INSERT INTO reports (
                    id, booking_id, room_id, room_type, guest_name, guest_type, contact_person_name, tin_number,
                    reason_for_stay, address, request, promo, breakfast, additional_guest, additional_pet,
                    payment_status, reference_no, referral_name, supplier, additional, additional_food,
                    additional_items, paid_status, check_in, check_out, duration, duration_unit, hours,
                    status, booking_type, room_image, hygiene_kit_used, hygiene_kit_price, total_amount,
                    room_price, confirmed_at, canceled_at, total_amount_reservation
                )
                SELECT
                    id, booking_id, room_id, room_type, guest_name, guest_type, contact_person_name, tin_number,
                    reason_for_stay, address, request, promo, breakfast, additional_guest, additional_pet,
                    payment_status, reference_no, referral_name, supplier, additional, additional_food,
                    additional_items, paid_status, check_in, check_out, duration, duration_unit, hours,
                    'Canceled', booking_type, room_image, hygiene_kit_used, hygiene_kit_price, total_amount,
                    room_price, NOW(), NOW(), total_amount_reservation
                FROM bookings
                WHERE id = :id
                LIMIT 1
            ");
            $insertReport->bindParam(':id', $id, PDO::PARAM_INT);
            $insertReport->execute();
        }
    }

    // Free the room if we know it
    if (!empty($room_id)) {
        try {
            $roomStmt = $conn->prepare("UPDATE rooms SET status = 'Available' WHERE room_id = :room_id");
            $roomStmt->bindParam(':room_id', $room_id);
            $roomStmt->execute();
        } catch (Exception $e) {
            // Non-fatal: room availability sync is best-effort
        }
    }

    $response['success'] = true;
    $response['message'] = 'Reservation canceled and saved to reports.';
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

