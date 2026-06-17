<?php
session_start();
require_once 'auth.php';

header('Content-Type: application/json');

if (($_SESSION['access_level'] ?? '') !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing reservation id']);
    exit;
}

try {
    require_once 'config.php';

    $stmt = $conn->prepare("
        SELECT
            id,
            booking_id,
            room_id,
            room_type,
            guest_name,
            guest_type,
            contact_no,
            address,
            reason_for_stay,
            request,
            referral_name,
            check_in,
            check_out,
            duration,
            duration_unit,
            reservation_date,
            promo,
            status,
            booking_type,
            downpayment_amount,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya,
            downpayment_instapay,
            downpayment_online_banking,
            downpayment_airbnb,
            downpayment_gcash_ref,
            downpayment_maya_ref,
            downpayment_instapay_ref,
            downpayment_online_banking_ref,
            downpayment_airbnb_ref,
            downpayment_status,
            downpayment_date,
            total_amount_reservation,
            total_amount,
            paid_status,
            COALESCE(modification_reason, '') AS modification_reason,
            COALESCE(rebooked_flag, 0) AS rebooked_flag
        FROM bookings
        WHERE id = :id
          AND booking_type = 'Reservation'
        LIMIT 1
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $row]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
