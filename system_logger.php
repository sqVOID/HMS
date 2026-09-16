<?php
/**
 * HMS System Logger
 * Centralized activity and error logging for the Hotel Management System.
 *
 * Usage:
 *   require_once 'system_logger.php';
 *   logActivity($conn, 'booking', 'CREATE', 'Booking #123 created for John Doe', ['booking_id' => 123]);
 *   logError($conn,   'booking', 'CREATE', 'Failed to create booking: DB error', ['error' => $e->getMessage()]);
 *   logWarning($conn, 'cash',    'DEPOSIT', 'Cash variance detected', ['variance' => -50]);
 */

// ──────────────────────────────────────────────────────────────
// Auto-create the system_logs table (runs once, silently)
// ──────────────────────────────────────────────────────────────
function _ensureSystemLogsTable(PDO $conn): void
{
    static $created = false;
    if ($created) return;
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS system_logs (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                log_type     ENUM('activity','error','warning') NOT NULL DEFAULT 'activity',
                category     VARCHAR(64)  NOT NULL DEFAULT 'general',
                action       VARCHAR(128) NOT NULL DEFAULT '',
                description  TEXT         NOT NULL,
                username     VARCHAR(255) NOT NULL DEFAULT 'system',
                affected_id  VARCHAR(128) NULL DEFAULT NULL,
                metadata     TEXT         NULL DEFAULT NULL COMMENT 'JSON extra data',
                ip_address   VARCHAR(64)  NULL DEFAULT NULL,
                created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_log_type   (log_type),
                INDEX idx_category   (category),
                INDEX idx_created_at (created_at),
                INDEX idx_username   (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created = true;
    } catch (Throwable $e) {
        // Silently ignore — never break the calling script
        error_log('[system_logger] Table creation failed: ' . $e->getMessage());
    }
}

// ──────────────────────────────────────────────────────────────
// Internal: write one row to system_logs
// ──────────────────────────────────────────────────────────────
function _writeLog(
    PDO    $conn,
    string $logType,
    string $category,
    string $action,
    string $description,
    array  $metadata = [],
    string $affectedId = null
): void {
    try {
        _ensureSystemLogsTable($conn);

        // Resolve current user from session (best-effort)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $first = trim($_SESSION['first_name'] ?? '');
        $last  = trim($_SESSION['last_name']  ?? '');
        if ($first !== '' || $last !== '') {
            $username = trim($first . ' ' . $last);
        } else {
            $username = trim($_SESSION['username'] ?? '');
        }

        // Fallback: if username is empty but user_id exists in session, fetch from DB
        if ($username === '' && !empty($_SESSION['user_id'])) {
            try {
                $uStmt = $conn->prepare("SELECT first_name, last_name, username FROM users WHERE id = :id LIMIT 1");
                $uStmt->execute([':id' => $_SESSION['user_id']]);
                if ($uRow = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                    $f = trim($uRow['first_name'] ?? '');
                    $l = trim($uRow['last_name'] ?? '');
                    $username = ($f !== '' || $l !== '') ? trim($f . ' ' . $l) : trim($uRow['username'] ?? '');
                }
            } catch (Throwable $e) {}
        }

        if ($username === '') {
            $username = 'System';
        }

        // IP address
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;
        if ($ip) $ip = substr(trim(explode(',', $ip)[0]), 0, 64);

        // Encode metadata
        $metaJson = empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $conn->prepare("
            INSERT INTO system_logs
                (log_type, category, action, description, username, affected_id, metadata, ip_address, created_at)
            VALUES
                (:log_type, :category, :action, :description, :username, :affected_id, :metadata, :ip_address, NOW())
        ");
        $stmt->execute([
            ':log_type'    => $logType,
            ':category'    => strtolower(substr($category, 0, 64)),
            ':action'      => strtoupper(substr($action, 0, 128)),
            ':description' => substr($description, 0, 65535),
            ':username'    => substr($username, 0, 255),
            ':affected_id' => $affectedId ? substr((string)$affectedId, 0, 128) : null,
            ':metadata'    => $metaJson,
            ':ip_address'  => $ip,
        ]);
    } catch (Throwable $e) {
        // Never break calling code
        error_log('[system_logger] Write failed: ' . $e->getMessage());
    }
}

// ──────────────────────────────────────────────────────────────
// Public API
// ──────────────────────────────────────────────────────────────

/**
 * Log a successful activity / process event.
 *
 * @param PDO    $conn        Active DB connection
 * @param string $category    e.g. 'booking', 'cash', 'menu', 'cancellation', 'auth'
 * @param string $action      e.g. 'CREATE', 'UPDATE', 'DELETE', 'CHECKOUT', 'LOGIN'
 * @param string $description Human-readable description of what happened
 * @param array  $metadata    Optional key/value pairs (booking_id, guest_name, amount, etc.)
 * @param string $affectedId  Optional primary key of the affected record
 */
function logActivity(PDO $conn, string $category, string $action, string $description, array $metadata = [], $affectedId = null): void
{
    _writeLog($conn, 'activity', $category, $action, $description, $metadata, $affectedId);
}

/**
 * Log an error that occurred during a process.
 */
function logError(PDO $conn, string $category, string $action, string $description, array $metadata = [], $affectedId = null): void
{
    _writeLog($conn, 'error', $category, $action, $description, $metadata, $affectedId);
}

/**
 * Log a warning (non-fatal but noteworthy condition).
 */
function logWarning(PDO $conn, string $category, string $action, string $description, array $metadata = [], $affectedId = null): void
{
    _writeLog($conn, 'warning', $category, $action, $description, $metadata, $affectedId);
}
?>
