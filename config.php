<?php
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$dbname = 'hotel_management';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $conn->setAttribute(PDO::ATTR_TIMEOUT, 30);
    $conn->exec("SET time_zone = '+08:00'");
} catch(PDOException $e) {
    throw $e;
}