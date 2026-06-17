<?php
session_start();

header('Content-Type: application/json');

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
    'access_level' => $_SESSION['access_level'] ?? 'user'
];

echo json_encode($response);
?>
