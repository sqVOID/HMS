<?php
/**
 * Logout Handler
 * Destroys session and redirects to login page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Record logout time before destroying session
if (isset($_SESSION['session_id']) && isset($_SESSION['user_id'])) {
    try {
        require_once 'config.php';
        require_once 'system_logger.php';

        // Resolve actor name before session is wiped
        $_lf = trim($_SESSION['first_name'] ?? '');
        $_ll = trim($_SESSION['last_name']  ?? '');
        $_lActor = ($_lf !== '' || $_ll !== '') ? trim($_lf . ' ' . $_ll) : trim($_SESSION['username'] ?? 'Unknown');
        $_lSession = $_SESSION['session_id'];

        $stmt = $conn->prepare("
            UPDATE user_sessions
            SET logout_at = NOW(),
                session_status = 'logged_out',
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);

        // Log the logout
        logActivity($conn, 'auth', 'LOGOUT',
            "{$_lActor} logged out | Session ID: #{$_lSession}",
            [
                'user'       => $_lActor,
                'session_id' => $_lSession
            ], $_lSession);

    } catch (PDOException $e) {
        // Log error but continue with logout
        error_log('Logout tracking error: ' . $e->getMessage());
    }
}


// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any session-related cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page with a success message
header('Location: Login.html?logout=success');
exit;
?>
