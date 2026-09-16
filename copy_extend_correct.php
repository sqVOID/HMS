<?php
/**
 * Copy extend_paid_status to the exact 15 columns shown in the screenshot
 */

require_once 'config.php';

try {
    echo "<h2>Copy extend_paid_status to 15 Columns (From Screenshot)</h2>";
    echo "Connected to database successfully<br><br>";
    
    // Exact 15 columns from the screenshot
    $target_columns = [
        'change_amount',
        'check_in_change_amount',
        'downpayment_amount',
        'downpayment_cash',
        'downpayment_gcash',
        'downpayment_maya',
        'downpayment_gcash_ref',
        'downpayment_maya_ref',
        'downpayment_status',
        'downpayment_date',
        'total_amount_reservation',
        'discount_enabled',
        'discount_type',
        'discount_amount',
        'sc_pwd_count'
    ];
    
    echo "<h3>Copying extend_paid_status to 15 columns...</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>#</th><th>Target Column</th><th>Status</th><th>Rows Affected</th></tr>";
    
    $total_success = 0;
    $total_errors = 0;
    $counter = 1;
    
    foreach ($target_columns as $column) {
        try {
            $update_sql = "UPDATE bookings SET `$column` = `extend_paid_status`";
            $affected_rows = $conn->exec($update_sql);
            
            echo "<tr>";
            echo "<td>$counter</td>";
            echo "<td><strong>$column</strong></td>";
            echo "<td style='color: green;'>✓ Success</td>";
            echo "<td>$affected_rows</td>";
            echo "</tr>";
            $total_success++;
        } catch (PDOException $e) {
            echo "<tr>";
            echo "<td>$counter</td>";
            echo "<td><strong>$column</strong></td>";
            echo "<td style='color: red;'>✗ Error: " . $e->getMessage() . "</td>";
            echo "<td>0</td>";
            echo "</tr>";
            $total_errors++;
        }
        $counter++;
    }
    
    echo "</table><br>";
    
    echo "<h3>Summary:</h3>";
    echo "<p>✓ Successful copies: <strong style='color: green;'>$total_success / 15</strong></p>";
    echo "<p>✗ Failed copies: <strong style='color: red;'>$total_errors / 15</strong></p>";
    
    // Show verification
    echo "<br><h3>Verification - First 3 rows:</h3>";
    $verify_sql = "SELECT id, extend_paid_status, change_amount, check_in_change_amount, 
                   downpayment_amount, downpayment_cash, downpayment_gcash, downpayment_maya,
                   downpayment_gcash_ref, downpayment_maya_ref, downpayment_status, downpayment_date,
                   total_amount_reservation, discount_enabled, discount_type, discount_amount, sc_pwd_count
                   FROM bookings ORDER BY id LIMIT 3";
    $verify_stmt = $conn->query($verify_sql);
    $verify_rows = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($verify_rows) > 0) {
        echo "<div style='overflow-x: auto;'>";
        echo "<table border='1' cellpadding='5' style='font-size: 9px;'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th style='background: #ffff99;'>extend_paid_status</th>";
        echo "<th>change_amount</th>";
        echo "<th>check_in_change</th>";
        echo "<th>down_amount</th>";
        echo "<th>down_cash</th>";
        echo "<th>down_gcash</th>";
        echo "<th>down_maya</th>";
        echo "<th>gcash_ref</th>";
        echo "<th>maya_ref</th>";
        echo "<th>down_status</th>";
        echo "<th>down_date</th>";
        echo "<th>total_amt_res</th>";
        echo "<th>disc_enabled</th>";
        echo "<th>disc_type</th>";
        echo "<th>disc_amount</th>";
        echo "<th>sc_pwd</th>";
        echo "</tr>";
        
        foreach ($verify_rows as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td style='background: #ffff99;'><strong>" . ($row['extend_paid_status'] ?? 'NULL') . "</strong></td>";
            echo "<td>" . ($row['change_amount'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['check_in_change_amount'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_amount'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_cash'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_gcash'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_maya'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_gcash_ref'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_maya_ref'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_status'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['downpayment_date'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['total_amount_reservation'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['discount_enabled'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['discount_type'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['discount_amount'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['sc_pwd_count'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
    
    if ($total_success == 15) {
        echo "<br><h2 style='color: green;'>✓ SUCCESS! All 15 columns updated!</h2>";
    } else {
        echo "<br><h2 style='color: orange;'>⚠ $total_success out of 15 columns updated.</h2>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
