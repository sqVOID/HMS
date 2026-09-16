<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Safety net: catch PHP fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode([
            'success' => false,
            'error' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . $error['file']
        ]);
    }
});

if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'system';
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data['id'])) {
        throw new Exception('Missing booking ID parameter');
    }

    $id = $data['id'];

    require_once 'config.php'; // Provides PDO $conn connection

    // 1. Check reports and bookings tables for the record
    $stmtR = $conn->prepare("SELECT * FROM reports WHERE id = :id1 OR booking_id = :id2 LIMIT 1");
    $stmtR->execute([':id1' => $id, ':id2' => $id]);
    $reportRow = $stmtR->fetch(PDO::FETCH_ASSOC);

    $stmtB = $conn->prepare("SELECT * FROM bookings WHERE id = :id1 OR booking_id = :id2 LIMIT 1");
    $stmtB->execute([':id1' => $id, ':id2' => $id]);
    $bookingRow = $stmtB->fetch(PDO::FETCH_ASSOC);

    if (!$reportRow && !$bookingRow) {
        throw new Exception('Booking record not found.');
    }

    $targetRow = $reportRow ?: $bookingRow;
    $numericId = $targetRow['id'];
    $bookingId = $targetRow['booking_id'] ?? $numericId;
    $roomId = $targetRow['room_id'] ?? null;

    // 2. Update status and clear checked_out_at in reports table if present
    if ($reportRow) {
        $upReport = $conn->prepare("UPDATE reports SET status = 'Confirmed', checked_out_at = NULL WHERE id = :id");
        $upReport->execute([':id' => $reportRow['id']]);
    }

    // 3. Update or restore in bookings table
    if ($bookingRow) {
        $upBooking = $conn->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = :id");
        $upBooking->execute([':id' => $bookingRow['id']]);
    } else if ($reportRow) {
        // Restore row from reports back into bookings table
        $colStmt = $conn->query("SHOW COLUMNS FROM bookings");
        $bookingsCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

        $insertData = [];
        $insertCols = [];
        $placeholders = [];

        foreach ($bookingsCols as $col) {
            if ($col === 'status') {
                $insertCols[] = "`status`";
                $placeholders[] = ":col_status";
                $insertData[':col_status'] = 'Confirmed';
            } else if (array_key_exists($col, $reportRow)) {
                $insertCols[] = "`{$col}`";
                $placeholders[] = ":col_{$col}";
                $insertData[":col_{$col}"] = $reportRow[$col];
            }
        }

        if (!empty($insertCols)) {
            $sql = "INSERT INTO bookings (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $insStmt = $conn->prepare($sql);
            $insStmt->execute($insertData);
        }
    }

    // 4. Update room status in rooms table
    if (!empty($roomId)) {
        $upRoom = $conn->prepare("UPDATE rooms SET status = 'Confirmed' WHERE room_id = :room_id");
        $upRoom->execute([':room_id' => $roomId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Check-out canceled successfully! Booking status is now Occupied.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
