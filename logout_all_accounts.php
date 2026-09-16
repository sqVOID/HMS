<?php
/**
 * logout_all_accounts.php
 * Force logs out all active user sessions across the entire system.
 * Triggered from SystemLog.php (or admin/super_admin tools).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'system_logger.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Resolve actor name before clearing sessions
$_af = trim($_SESSION['first_name'] ?? '');
$_al = trim($_SESSION['last_name']  ?? '');
$actor = ($_af !== '' || $_al !== '') ? trim($_af . ' ' . $_al) : trim($_SESSION['username'] ?? 'System Admin');

try {
    // 1. Update all active/on_break/turnover sessions in user_sessions table to logged_out
    $stmt = $conn->prepare("
        UPDATE user_sessions
        SET logout_at = NOW(),
            session_status = 'logged_out',
            updated_at = NOW()
        WHERE session_status != 'logged_out'
    ");
    $stmt->execute();
    $affectedCount = $stmt->rowCount();

    // 2. Log activity in system_logs
    $_logMsg = "ALL ACCOUNT LOGOUT executed by {$actor} — {$affectedCount} active session(s) terminated";
    logActivity($conn, 'auth', 'LOGOUT_ALL', $_logMsg, [
        'user'                => $actor,
        'terminated_sessions' => $affectedCount,
        'action_type'         => 'FORCE_ALL_LOGOUT'
    ]);

    // 3. Delete all server PHP session files to invalidate all connected browsers immediately
    $savePath = session_save_path();
    if (empty($savePath)) {
        $savePath = sys_get_temp_dir();
    }
    if ($savePath && is_dir($savePath)) {
        $files = glob($savePath . '/sess_*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    // 4. Unset and destroy current PHP session
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();

    echo json_encode([
        'success' => true,
        'count'   => $affectedCount,
        'message' => "All accounts logged out successfully ({$affectedCount} session(s) terminated)."
    ]);

} catch (PDOException $e) {
    logError($conn ?? null, 'auth', 'LOGOUT_ALL_FAILED', "Logout all accounts failed: " . $e->getMessage(), [
        'user'  => $actor ?? 'Unknown',
        'error' => $e->getMessage()
    ]);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
