<?php
/**
 * Get User Sessions API
 * Returns user session logs with filters
 */

require_once 'auth.php';
require_once 'config.php';

header('Content-Type: application/json');

// Check if user has permission (only super_admin and admin can view all sessions)
$userLevel = $_SESSION['access_level'] ?? 'user';
$currentUserId = $_SESSION['user_id'] ?? null;

try {
    // Get filter parameters
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $status = $_GET['status'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    
    // Build query based on permissions
    $sql = "SELECT 
                s.id,
                s.user_id,
                s.username,
                s.login_at,
                s.logout_at,
                s.break_at,
                s.turnover_at,
                s.session_status,
                u.first_name,
                u.last_name,
                u.access_level,
                TIMESTAMPDIFF(MINUTE, s.login_at, COALESCE(s.logout_at, NOW())) as session_duration_minutes
            FROM user_sessions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // If not super_admin or admin, only show own sessions
    if (!in_array($userLevel, ['super_admin', 'admin'])) {
        $sql .= " AND s.user_id = ?";
        $params[] = $currentUserId;
    } elseif ($userId) {
        // Admin/Super Admin can filter by specific user
        $sql .= " AND s.user_id = ?";
        $params[] = $userId;
    }
    
    // Date filters
    if ($startDate) {
        $sql .= " AND DATE(s.login_at) >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $sql .= " AND DATE(s.login_at) <= ?";
        $params[] = $endDate;
    }
    
    // Status filter
    if ($status && in_array($status, ['active', 'logged_out', 'on_break', 'turnover'])) {
        $sql .= " AND s.session_status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY s.login_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'count' => count($sessions)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
