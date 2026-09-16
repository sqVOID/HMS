<?php
require_once 'config.php';
header('Content-Type: text/plain');
try {
    // Check specific booking ID from report
    $targetId = 'B-01/12/26-316';
    
    echo "Checking for ID: $targetId\n";
    
    $stmt = $conn->prepare("SELECT id, booking_id, guest_name, deposit_details FROM bookings WHERE booking_id LIKE :id");
    $stmt->execute([':id' => "%316%"]); // flexible search
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Bookings matches: " . count($rows) . "\n";
    print_r($rows);
    
    $stmt2 = $conn->prepare("SELECT id, booking_id, guest_name FROM reports WHERE booking_id = :id");
    $stmt2->execute([':id' => $targetId]);
    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "Reports matches: " . count($rows2) . "\n";
    print_r($rows2);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
