<?php
require_once 'config.php';
try {
    $stmt = $conn->query("SELECT id, booking_id, guest_name, deposit_details FROM bookings ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
    
    echo "\nReports:\n";
    $stmt2 = $conn->query("SELECT id, booking_id, guest_name FROM reports ORDER BY id DESC LIMIT 5");
    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows2);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
