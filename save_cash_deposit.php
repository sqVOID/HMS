<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
header('Content-Type: application/json');

// Resolve logged-in user from session
$_cd_first = trim($_SESSION['first_name'] ?? '');
$_cd_last  = trim($_SESSION['last_name'] ?? '');
$_cd_user  = ($_cd_first !== '' || $_cd_last !== '') ? trim($_cd_first . ' ' . $_cd_last) : trim($_SESSION['username'] ?? 'Unknown');


// Auto-create cash_deposits table
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS cash_deposits (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            shift_date     DATE NOT NULL,
            shift_start    DATETIME NOT NULL,
            shift_end      DATETIME NOT NULL,
            deposit_date   DATE NULL DEFAULT NULL,
            cash_expected  DECIMAL(12,2) NOT NULL,
            cash_deposited DECIMAL(12,2) NOT NULL,
            variance       DECIMAL(12,2) NOT NULL,
            status         ENUM('exact','short','over') NOT NULL,
            reason         TEXT NULL DEFAULT NULL,
            notes          TEXT NULL DEFAULT NULL,
            breakdown      TEXT NULL DEFAULT NULL,
            created_by     VARCHAR(255) NOT NULL DEFAULT 'Unknown',
            created_at     DATETIME NOT NULL,
            updated_at     DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Add breakdown column if it doesn't exist
    try {
        $conn->exec("ALTER TABLE cash_deposits ADD COLUMN breakdown TEXT NULL DEFAULT NULL");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    // Add deposit_date column if it doesn't exist
    try {
        $conn->exec("ALTER TABLE cash_deposits ADD COLUMN deposit_date DATE NULL DEFAULT NULL");
        $conn->exec("UPDATE cash_deposits SET deposit_date = shift_date WHERE deposit_date IS NULL");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
} catch (PDOException $e) {
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // ── Save new deposit ──────────────────────────────────────
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data))
        $data = $_POST;

    $shiftDate = trim($data['shift_date'] ?? '');
    $shiftStart = trim($data['shift_start'] ?? '');
    $shiftEnd = trim($data['shift_end'] ?? '');
    $depositDate = trim($data['deposit_date'] ?? '');
    $cashExpected = floatval($data['cash_expected'] ?? 0);
    $cashDeposited = floatval($data['cash_deposited'] ?? 0);
    $reason = trim($data['reason'] ?? '');
    $notes = trim($data['notes'] ?? '');
    $createdBy = trim($data['created_by'] ?? 'Unknown');
    $breakdown = isset($data['breakdown']) && is_array($data['breakdown']) ? json_encode($data['breakdown']) : null;

    if (empty($shiftDate) || empty($shiftStart) || empty($shiftEnd)) {
        echo json_encode(['success' => false, 'error' => 'Shift details are required.']);
        exit;
    }
    if ($cashDeposited < 0) {
        echo json_encode(['success' => false, 'error' => 'Cash deposited cannot be negative.']);
        exit;
    }
    if (empty($depositDate)) {
        $depositDate = $shiftDate;
    }

    $variance = round($cashDeposited - $cashExpected, 2);
    if ($variance > 0.01)
        $status = 'over';
    elseif ($variance < -0.01)
        $status = 'short';
    else
        $status = 'exact';

    try {
        $stmt = $conn->prepare("
            INSERT INTO cash_deposits
                (shift_date, shift_start, shift_end, deposit_date, cash_expected, cash_deposited, variance, status, reason, notes, breakdown, created_by, created_at)
            VALUES
                (:shift_date, :shift_start, :shift_end, :deposit_date, :cash_expected, :cash_deposited, :variance, :status, :reason, :notes, :breakdown, :created_by, NOW())
        ");
        $stmt->execute([
            ':shift_date' => $shiftDate,
            ':shift_start' => $shiftStart,
            ':shift_end' => $shiftEnd,
            ':deposit_date' => $depositDate,
            ':cash_expected' => $cashExpected,
            ':cash_deposited' => $cashDeposited,
            ':variance' => $variance,
            ':status' => $status,
            ':reason' => $reason ?: null,
            ':notes' => $notes ?: null,
            ':breakdown' => $breakdown,
            ':created_by' => $createdBy,
        ]);
        $newId = (int) $conn->lastInsertId();
        logActivity($conn, 'cash', 'DEPOSIT_CREATE',
            "Cash deposit of ₱" . number_format($cashDeposited, 2) . " saved by {$_cd_user} for shift {$shiftDate} (Status: {$status}, Variance: ₱" . number_format($variance, 2) . ")",
            [
                'deposit_id'     => $newId,
                'shift_date'     => $shiftDate,
                'cash_expected'  => $cashExpected,
                'cash_deposited' => $cashDeposited,
                'variance'       => $variance,
                'status'         => $status,
                'created_by'     => $createdBy,
                'saved_by'       => $_cd_user
            ], $newId);
        echo json_encode(['success' => true, 'id' => $newId, 'status' => $status, 'variance' => $variance]);
    } catch (PDOException $e) {
        logError($conn, 'cash', 'DEPOSIT', "Failed to save cash deposit: " . $e->getMessage(), [
            'cash_deposited' => $cashDeposited ?? 0,
            'error'          => $e->getMessage()
        ]);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($method === 'PUT') {
    // ── Update existing deposit ──────────────────────────────
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $id = intval($data['id'] ?? 0);
    $shiftDate = trim($data['shift_date'] ?? '');
    $shiftStart = trim($data['shift_start'] ?? '');
    $shiftEnd = trim($data['shift_end'] ?? '');
    $depositDate = trim($data['deposit_date'] ?? '');
    $cashExpected = floatval($data['cash_expected'] ?? 0);
    $cashDeposited = floatval($data['cash_deposited'] ?? 0);
    $reason = trim($data['reason'] ?? '');
    $notes = trim($data['notes'] ?? '');
    $breakdown = isset($data['breakdown']) && is_array($data['breakdown']) ? json_encode($data['breakdown']) : null;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid deposit ID.']);
        exit;
    }
    if (empty($shiftDate) || empty($shiftStart) || empty($shiftEnd)) {
        echo json_encode(['success' => false, 'error' => 'Shift details are required.']);
        exit;
    }
    if ($cashDeposited < 0) {
        echo json_encode(['success' => false, 'error' => 'Cash deposited cannot be negative.']);
        exit;
    }
    if (empty($depositDate)) {
        $depositDate = $shiftDate;
    }

    $variance = round($cashDeposited - $cashExpected, 2);
    if ($variance > 0.01)
        $status = 'over';
    elseif ($variance < -0.01)
        $status = 'short';
    else
        $status = 'exact';

    try {
        $stmt = $conn->prepare("
            UPDATE cash_deposits
            SET shift_date = :shift_date,
                shift_start = :shift_start,
                shift_end = :shift_end,
                deposit_date = :deposit_date,
                cash_expected = :cash_expected,
                cash_deposited = :cash_deposited,
                variance = :variance,
                status = :status,
                reason = :reason,
                notes = :notes,
                breakdown = :breakdown,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':shift_date' => $shiftDate,
            ':shift_start' => $shiftStart,
            ':shift_end' => $shiftEnd,
            ':deposit_date' => $depositDate,
            ':cash_expected' => $cashExpected,
            ':cash_deposited' => $cashDeposited,
            ':variance' => $variance,
            ':status' => $status,
            ':reason' => $reason ?: null,
            ':notes' => $notes ?: null,
            ':breakdown' => $breakdown,
        ]);

        if ($stmt->rowCount() > 0) {
            logActivity($conn, 'cash', 'DEPOSIT_UPDATE',
                "Cash deposit #{$id} updated by {$_cd_user} for shift {$shiftDate} — Deposited: ₱" . number_format($cashDeposited, 2) . ", Status: {$status}, Variance: ₱" . number_format($variance, 2),
                [
                    'deposit_id'     => $id,
                    'shift_date'     => $shiftDate,
                    'cash_expected'  => $cashExpected,
                    'cash_deposited' => $cashDeposited,
                    'variance'       => $variance,
                    'status'         => $status,
                    'updated_by'     => $_cd_user
                ], $id);
            echo json_encode(['success' => true, 'id' => $id, 'status' => $status, 'variance' => $variance]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No changes made or deposit not found.']);
        }
    } catch (PDOException $e) {
        logError($conn, 'cash', 'DEPOSIT_UPDATE',
            "Failed to update cash deposit #{$id}: " . $e->getMessage(),
            ['deposit_id' => $id, 'updated_by' => $_cd_user, 'error' => $e->getMessage()]
        );
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($method === 'DELETE') {
    // ── Delete a deposit ─────────────────────────────────────
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id = intval($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
        exit;
    }
    try {
        // Fetch deposit details before deleting for the log
        $delStmt = $conn->prepare("SELECT shift_date, cash_deposited, status FROM cash_deposits WHERE id = :id");
        $delStmt->execute([':id' => $id]);
        $delRow = $delStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("DELETE FROM cash_deposits WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($delRow) {
            logActivity($conn, 'cash', 'DEPOSIT_DELETE',
                "Cash deposit #{$id} deleted by {$_cd_user} — Shift: {$delRow['shift_date']}, Deposited: ₱" . number_format($delRow['cash_deposited'], 2) . ", Status: {$delRow['status']}",
                [
                    'deposit_id'     => $id,
                    'shift_date'     => $delRow['shift_date'],
                    'cash_deposited' => $delRow['cash_deposited'],
                    'status'         => $delRow['status'],
                    'deleted_by'     => $_cd_user
                ], $id);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        logError($conn, 'cash', 'DEPOSIT_DELETE',
            "Failed to delete cash deposit #{$id}: " . $e->getMessage(),
            ['deposit_id' => $id, 'deleted_by' => $_cd_user, 'error' => $e->getMessage()]
        );
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
