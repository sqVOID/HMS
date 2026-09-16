<?php
session_start();
require_once 'config.php';
require_once 'system_logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['session_id']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No active session']);
    exit;
}

$action = $_POST['action'] ?? '';

// Resolve actor name from session
$_af = trim($_SESSION['first_name'] ?? '');
$_al = trim($_SESSION['last_name'] ?? '');
$_actor = ($_af !== '' || $_al !== '') ? trim($_af . ' ' . $_al) : trim($_SESSION['username'] ?? 'Unknown');

try {
    $sessionId = $_SESSION['session_id'];
    $userId    = $_SESSION['user_id'];

    if ($action === 'turnover') {
        $cashAmount  = floatval($_POST['cash_amount']  ?? 0);
        $totalAmount = floatval($_POST['total_amount'] ?? 0);

        // Update session with turnover time
        $stmt = $conn->prepare("
            UPDATE user_sessions
            SET turnover_at = NOW(),
                session_status = 'turnover',
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);

        // Create turnover_records table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS turnover_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            user_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            cash_amount DECIMAL(10,2) DEFAULT 0,
            total_amount DECIMAL(10,2) NOT NULL,
            turnover_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Insert turnover record
        $stmt = $conn->prepare("
            INSERT INTO turnover_records
            (session_id, user_id, username, cash_amount, total_amount, turnover_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $_SESSION['username'],
            $cashAmount,
            $totalAmount
        ]);

        // Log the turnover activity
        $_logMsg = "{$_actor} submitted cash turnover — Cash: ₱" . number_format($cashAmount, 2);
        $_logMsg .= " | Session ID: #{$sessionId}";

        logActivity($conn, 'auth', 'CASH_TURNOVER', $_logMsg, [
            'user'         => $_actor,
            'cash_amount'  => $cashAmount,
            'total_amount' => $totalAmount,
            'session_id'   => $sessionId
        ], $sessionId);

        echo json_encode([
            'success' => true,
            'message' => 'Turnover recorded successfully',
            'action'  => 'turnover'
        ]);

    } elseif ($action === 'break') {
        // Update session with break time
        $stmt = $conn->prepare("
            UPDATE user_sessions
            SET break_at = NOW(),
                session_status = 'on_break',
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);

        // Log the break activity
        logActivity($conn, 'auth', 'BREAK_START', "{$_actor} started a break | Session ID: #{$sessionId}", [
            'user'       => $_actor,
            'session_id' => $sessionId
        ], $sessionId);

        echo json_encode([
            'success' => true,
            'message' => 'Break recorded successfully',
            'action'  => 'break'
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (PDOException $e) {
    logError($conn ?? null, 'auth', $action ?: 'LOGOUT', "process_logout error for {$_actor}: " . $e->getMessage(), [
        'user'  => $_actor,
        'error' => $e->getMessage()
    ]);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
