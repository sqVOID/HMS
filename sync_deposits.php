<?php
require_once 'config.php';

try {
    echo "<h1>Syncing Deposit Data...</h1>";
    echo "<pre>";
    
    // 1. Ensure columns exist in reports table
    $cols = [
        'deposit' => "DECIMAL(10,2) DEFAULT 0",
        'deposit_details' => "TEXT NULL DEFAULT NULL",
        'deposit_gcash_ref' => "VARCHAR(255) NULL DEFAULT NULL",
        'deposit_maya_ref' => "VARCHAR(255) NULL DEFAULT NULL"
    ];
    
    foreach ($cols as $col => $def) {
        try {
            $check = $conn->query("SHOW COLUMNS FROM reports LIKE '$col'");
            if ($check->rowCount() == 0) {
                echo "Adding column $col to reports table...\n";
                $conn->exec("ALTER TABLE reports ADD COLUMN $col $def");
            } else {
                echo "Column $col already exists in reports.\n";
            }
        } catch(PDOException $e) {
            echo "Error checking/adding $col: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Sync data based on booking_id
    echo "\nStarting synchronization from bookings to reports...\n";
    
    $stmt = $conn->query("
        SELECT booking_id, deposit, deposit_details, deposit_gcash_ref, deposit_maya_ref 
        FROM bookings 
        WHERE deposit > 0 OR deposit_details IS NOT NULL OR deposit_gcash_ref IS NOT NULL OR deposit_maya_ref IS NOT NULL
    ");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($bookings) . " bookings with deposit information.\n";
    
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
        // Trim booking_id to ensure match
        $bid = trim($b['booking_id']);
        
        $updateStmt->execute([
            ':deposit' => $b['deposit'],
            ':details' => $b['deposit_details'],
            ':gcash' => $b['deposit_gcash_ref'],
            ':maya' => $b['deposit_maya_ref'],
            ':bid' => $bid
        ]);
        
        if ($updateStmt->rowCount() > 0) {
            $updated++;
        }
    }
    
    echo "Successfully updated $updated records in reports table.\n";
    echo "</pre>";
    echo "<h2>Sync Complete! You can now generate the report.</h2>";
    
} catch(PDOException $e) {
    echo "Global Error: " . $e->getMessage();
}
?>
