<?php
/**
 * Update Session Status API
 * Handles break_at and turnover_at timestamps
 */

session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Get action from POST data
$action = $_POST['action'] ?? '';

if (!in_array($action, ['break', 'turnover', 'resume'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}

try {
    $sessionId = $_SESSION['session_id'];
    $userId = $_SESSION['user_id'];
    
    if ($action === 'break') {
        // Record break time
        $stmt = $conn->prepare("
            UPDATE user_sessions 
            SET break_at = NOW(), 
                session_status = 'on_break',
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Break time recorded',
            'action' => 'break'
        ]);
        
    } elseif ($action === 'turnover') {
        // Record turnover time
        $stmt = $conn->prepare("
            UPDATE user_sessions 
            SET turnover_at = NOW(), 
                session_status = 'turnover',
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Turnover time recorded',
            'action' => 'turnover'
        ]);
        
    } elseif ($action === 'resume') {
        // Resume from break - set status back to active
        $stmt = $conn->prepare("
            UPDATE user_sessions 
            SET session_status = 'active',
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Resumed from break',
            'action' => 'resume'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
