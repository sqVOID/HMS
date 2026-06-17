<?php
require_once 'config.php';

try {
    echo "DEBUGGING DATA MATCHING...\n";
    
    $stm = $conn->query("SELECT id, booking_id, LENGTH(booking_id) as len, deposit_details FROM bookings ORDER BY id DESC LIMIT 5");
    $bookings = $stm->fetchAll(PDO::FETCH_ASSOC);
    echo "Bookings (last 5):\n";
    print_r($bookings);
    
    $stm2 = $conn->query("SELECT id, booking_id, LENGTH(booking_id) as len, deposit_details FROM reports ORDER BY id DESC LIMIT 5");
    $reports = $stm2->fetchAll(PDO::FETCH_ASSOC);
    echo "Reports (last 5):\n";
    print_r($reports);
    
    // Check specific match
    $target = 'B-01/12/26-316'; // From previous output
    
    $b = $conn->prepare("SELECT booking_id, deposit_details FROM bookings WHERE booking_id = ?");
    $b->execute([$target]);
    $bRes = $b->fetch(PDO::FETCH_ASSOC);
    echo "Booking lookup for '$target': " . ($bRes ? 'Found' : 'Not Found') . "\n";
    if($bRes) print_r($bRes);
    
    $r = $conn->prepare("SELECT booking_id, deposit_details FROM reports WHERE booking_id = ?");
    $r->execute([$target]);
    $rRes = $r->fetch(PDO::FETCH_ASSOC);
    echo "Report lookup for '$target': " . ($rRes ? 'Found (updateable)' : 'Not Found') . "\n";
    
    // Test update
    if ($bRes && $rRes) {
        $up = $conn->prepare("UPDATE reports SET deposit_details = ? WHERE booking_id = ?");
        $up->execute([$bRes['deposit_details'], $target]);
        echo "Test Update affected rows: " . $up->rowCount() . "\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
