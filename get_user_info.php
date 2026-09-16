<?php
/**
 * Get User Info API
 * Returns current logged-in user's information
 */

require_once 'auth.php';

header('Content-Type: application/json');

try {
    $firstName = $_SESSION['first_name'] ?? '';
    $lastName  = $_SESSION['last_name']  ?? '';
    $username  = $_SESSION['username']   ?? 'User';
    $fullName  = trim($firstName . ' ' . $lastName);
    $response = [
        'success'      => true,
        'username'     => $username,
        'first_name'   => $firstName,
        'last_name'    => $lastName,
        'full_name'    => $fullName !== '' ? $fullName : $username,
        'display_name' => $firstName !== '' ? $firstName : $username,
        'access_level' => $_SESSION['access_level'] ?? 'user',
        'user_id'      => $_SESSION['user_id'] ?? null
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving user information'
    ]);
}
?>
