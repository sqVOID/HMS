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
$reservation_date = isset($_POST['reservation_date']) ? trim($_POST['reservation_date']) : '';

if ($id <= 0) {
    $response['message'] = 'Missing reservation id';
    echo json_encode($response);
    exit;
}

if ($reservation_date === '') {
    $response['message'] = 'Reservation date is required';
    echo json_encode($response);
    exit;
}

// Accept either "YYYY-MM-DDTHH:mm" or "YYYY-MM-DD HH:mm:ss"
$reservation_date = str_replace('T', ' ', $reservation_date);
if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $reservation_date)) {
    $reservation_date .= ':00';
}

try {
    // Ensure helper rebooked_flag columns exist (used only for UI)
    try {
        $conn->exec("ALTER TABLE bookings ADD COLUMN rebooked_flag TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Exception $e) {
        // likely already exists
    }
    try {
        $conn->exec("ALTER TABLE reports ADD COLUMN rebooked_flag TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Exception $e) {
        // likely already exists
    }

    // Conflict check: block overlaps based on duration (+30 min cleaning gap).
    // Reservation rows often have no check-in/out yet, so reservation_date is the slot indicator.
    try {
        $roomStmt = $conn->prepare("SELECT room_id, duration, duration_unit, promo FROM bookings WHERE id = :id AND booking_type = 'Reservation' LIMIT 1");
        $roomStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $roomStmt->execute();
        $row = $roomStmt->fetch(PDO::FETCH_ASSOC);
        $room_id = $row['room_id'] ?? null;

        if ($room_id) {
            $duration = intval($row['duration'] ?? 0);
            $duration_unit = strtolower(trim($row['duration_unit'] ?? 'hours'));
            $promo = trim($row['promo'] ?? '');

            $startObj = new DateTime($reservation_date);
            $totalHours = ($duration_unit === 'night' || $duration_unit === 'nights') ? ($duration * 12) : $duration;
            // "Regular" / "Select Bundle" are NOT promos.
            if ($promo !== '' && $promo !== 'None' && $promo !== 'Regular' && $promo !== 'Select Bundle' && $promo !== 'Select Promo') {
                $totalHours += 12;
            }
            if ($totalHours < 0) $totalHours = 0;

            $endObj = clone $startObj;
            if ($totalHours > 0) {
                $endObj->modify('+' . intval($totalHours) . ' hours');
            }
            $endPlus30Obj = (clone $endObj)->add(new DateInterval('PT30M'));

            $new_start = $startObj->format('Y-m-d H:i:s');
            $new_end_plus30 = $endPlus30Obj->format('Y-m-d H:i:s');

            $confStmt = $conn->prepare("
                SELECT id, booking_id, guest_name, reservation_date, status
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
            $confStmt->bindParam(':room_id', $room_id);
            $confStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $confStmt->bindParam(':new_start', $new_start);
            $confStmt->bindParam(':new_end_plus30', $new_end_plus30);
            $confStmt->execute();
            $conflict = $confStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                $response['message'] = "This room already has a reservation that overlaps this slot (including +30 min cleaning gap). "
                    . "Existing Booking ID: " . ($conflict['booking_id'] ?? 'N/A')
                    . " (" . date('m/d/Y h:i A', strtotime($conflict['reservation_date'])) . ").";
                echo json_encode($response);
                exit;
            }
        }
    } catch (Exception $e) {
        // Best-effort only; don't block update if conflict check fails unexpectedly
    }

    // Update bookings reservation_date
    // Keep original booking status; only stamp a lightweight rebooked_flag
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET reservation_date = :reservation_date,
            rebooked_flag = 1
        WHERE id = :id 
          AND booking_type = 'Reservation' 
        LIMIT 1
    ");
    $stmt->bindParam(':reservation_date', $reservation_date);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() <= 0) {
        $response['message'] = 'Reservation not found or no changes applied';
        echo json_encode($response);
        exit;
    }

    // Keep reports table in sync if a report row exists for this booking
    try {
        $bidStmt = $conn->prepare("SELECT booking_id FROM bookings WHERE id = :id LIMIT 1");
        $bidStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $bidStmt->execute();
        $bidRow = $bidStmt->fetch(PDO::FETCH_ASSOC);
        $booking_id = $bidRow['booking_id'] ?? null;

        if ($booking_id) {
            $rStmt = $conn->prepare("
                UPDATE reports 
                SET reservation_date = :reservation_date,
                    rebooked_flag = 1
                WHERE booking_id = :booking_id
            ");
            $rStmt->bindParam(':reservation_date', $reservation_date);
            $rStmt->bindParam(':booking_id', $booking_id);
            $rStmt->execute();
        }
    } catch (Exception $e) {
        // Best-effort sync only
    }

    $response['success'] = true;
    $response['message'] = 'Reservation date updated successfully';
    $response['reservation_date'] = $reservation_date;
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

?>
