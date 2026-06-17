<?php
require_once 'config.php';

// Copy data from extend_paid_status to discount_amounts
$sql = "UPDATE bookings 
        SET discount_amounts = extend_paid_status 
        WHERE extend_paid_status IS NOT NULL 
        AND extend_paid_status != ''
        AND extend_paid_status != 0";

try {
    $result = $conn->query($sql);
    
    if ($result) {
        $affected_rows = $conn->affected_rows;
        echo "Success! Copied data from extend_paid_status to discount_amounts.\n";
        echo "Total rows updated: " . $affected_rows . "\n";
        
        // Show the updated records
        $check_sql = "SELECT id, extend_paid_status, discount_amounts 
                      FROM bookings 
                      WHERE discount_amounts IS NOT NULL 
                      AND discount_amounts != ''
                      ORDER BY id";
        
        $check_result = $conn->query($check_sql);
        
        if ($check_result && $check_result->num_rows > 0) {
            echo "\nUpdated records:\n";
            echo str_repeat("-", 60) . "\n";
            echo sprintf("%-10s %-25s %-25s\n", "ID", "extend_paid_status", "discount_amounts");
            echo str_repeat("-", 60) . "\n";
            
            while ($row = $check_result->fetch_assoc()) {
                echo sprintf("%-10s %-25s %-25s\n", 
                    $row['id'], 
                    $row['extend_paid_status'], 
                    $row['discount_amounts']
                );
            }
        }
    } else {
        echo "Error: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>
