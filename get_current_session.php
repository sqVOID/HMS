<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['session_id']) || !isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'session_status' => 'logged_out',
        'message' => 'No active session'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT login_at, session_status 
        FROM user_sessions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session && $session['session_status'] !== 'logged_out') {
        echo json_encode([
            'success' => true,
            'login_at' => $session['login_at'],
            'session_status' => $session['session_status']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'session_status' => 'logged_out',
            'message' => 'Session terminated'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'session_status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
