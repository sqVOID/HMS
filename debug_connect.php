<?php
// Debug script - uses config.php for connection
require_once 'config.php';

try {
    echo "Connected successfully\n";
    
    $stmt = $conn->query("SELECT count(*) FROM bookings");
    $count = $stmt->fetchColumn();
    echo "Bookings count: $count\n";
    
    if ($count > 0) {
        $stmt = $conn->query("SELECT booking_id, deposit, deposit_details, deposit_gcash_ref, deposit_maya_ref FROM bookings");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updateStmt = $conn->prepare("
            UPDATE reports 
            SET deposit = :deposit, 
                deposit_details = :details,
                deposit_gcash_ref = :gcash,
                deposit_maya_ref = :maya
            WHERE booking_id = :bid
        ");
        
        $updated = 0;
        foreach ($bookings as $b) {
            $bid = trim($b['booking_id']);
             // Update logic... same as before
             $updateStmt->execute([
                ':deposit' => $b['deposit'],
                ':details' => $b['deposit_details'], 
                ':gcash' => $b['deposit_gcash_ref'],
                ':maya' => $b['deposit_maya_ref'],
                ':bid' => $bid
            ]);
            $updated += $updateStmt->rowCount();
        }
        echo "Updated $updated reports.\n";
    }
    
    // Show databases
    $dbs = $conn->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases: " . implode(", ", $dbs) . "\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
