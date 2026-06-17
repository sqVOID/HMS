<?php
require_once 'config.php';
header('Content-Type: application/json');

// Auto-create cash_deposits table
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS cash_deposits (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            shift_date     DATE NOT NULL,
            shift_start    DATETIME NOT NULL,
            shift_end      DATETIME NOT NULL,
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
} catch (PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // ── Save new deposit ──────────────────────────────────────
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;

    $shiftDate     = trim($data['shift_date']     ?? '');
    $shiftStart    = trim($data['shift_start']    ?? '');
    $shiftEnd      = trim($data['shift_end']      ?? '');
    $cashExpected  = floatval($data['cash_expected']  ?? 0);
    $cashDeposited = floatval($data['cash_deposited'] ?? 0);
    $reason        = trim($data['reason'] ?? '');
    $notes         = trim($data['notes']  ?? '');
    $createdBy     = trim($data['created_by'] ?? 'Unknown');
    $breakdown     = isset($data['breakdown']) && is_array($data['breakdown']) ? json_encode($data['breakdown']) : null;

    if (empty($shiftDate) || empty($shiftStart) || empty($shiftEnd)) {
        echo json_encode(['success' => false, 'error' => 'Shift details are required.']);
        exit;
    }
    if ($cashDeposited < 0) {
        echo json_encode(['success' => false, 'error' => 'Cash deposited cannot be negative.']);
        exit;
    }

    $variance = round($cashDeposited - $cashExpected, 2);
    if ($variance >  0.01) $status = 'over';
    elseif ($variance < -0.01) $status = 'short';
    else                       $status = 'exact';

    try {
        $stmt = $conn->prepare("
            INSERT INTO cash_deposits
                (shift_date, shift_start, shift_end, cash_expected, cash_deposited, variance, status, reason, notes, breakdown, created_by, created_at)
            VALUES
                (:shift_date, :shift_start, :shift_end, :cash_expected, :cash_deposited, :variance, :status, :reason, :notes, :breakdown, :created_by, NOW())
        ");
        $stmt->execute([
            ':shift_date'     => $shiftDate,
            ':shift_start'    => $shiftStart,
            ':shift_end'      => $shiftEnd,
            ':cash_expected'  => $cashExpected,
            ':cash_deposited' => $cashDeposited,
            ':variance'       => $variance,
            ':status'         => $status,
            ':reason'         => $reason   ?: null,
            ':notes'          => $notes    ?: null,
            ':breakdown'      => $breakdown,
            ':created_by'     => $createdBy,
        ]);
        echo json_encode(['success' => true, 'id' => (int)$conn->lastInsertId(), 'status' => $status, 'variance' => $variance]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($method === 'PUT') {
    // ── Update existing deposit ──────────────────────────────
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    $id            = intval($data['id'] ?? 0);
    $shiftDate     = trim($data['shift_date']     ?? '');
    $shiftStart    = trim($data['shift_start']    ?? '');
    $shiftEnd      = trim($data['shift_end']      ?? '');
    $cashExpected  = floatval($data['cash_expected']  ?? 0);
    $cashDeposited = floatval($data['cash_deposited'] ?? 0);
    $reason        = trim($data['reason'] ?? '');
    $notes         = trim($data['notes']  ?? '');
    $breakdown     = isset($data['breakdown']) && is_array($data['breakdown']) ? json_encode($data['breakdown']) : null;

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

    $variance = round($cashDeposited - $cashExpected, 2);
    if ($variance >  0.01) $status = 'over';
    elseif ($variance < -0.01) $status = 'short';
    else                       $status = 'exact';

    try {
        $stmt = $conn->prepare("
            UPDATE cash_deposits
            SET shift_date = :shift_date,
                shift_start = :shift_start,
                shift_end = :shift_end,
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
            ':id'             => $id,
            ':shift_date'     => $shiftDate,
            ':shift_start'    => $shiftStart,
            ':shift_end'      => $shiftEnd,
            ':cash_expected'  => $cashExpected,
            ':cash_deposited' => $cashDeposited,
            ':variance'       => $variance,
            ':status'         => $status,
            ':reason'         => $reason   ?: null,
            ':notes'          => $notes    ?: null,
            ':breakdown'      => $breakdown,
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'id' => $id, 'status' => $status, 'variance' => $variance]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No changes made or deposit not found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($method === 'DELETE') {
    // ── Delete a deposit ─────────────────────────────────────
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id   = intval($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
        exit;
    }
    try {
        $stmt = $conn->prepare("DELETE FROM cash_deposits WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
