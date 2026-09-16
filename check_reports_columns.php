<?php
require_once 'config.php';

try {
    echo "<h2>Reports Table Columns</h2>";
    echo "<pre>";
    
    $stmt = $conn->query("SHOW COLUMNS FROM reports");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total columns: " . count($columns) . "\n\n";
    
    // Look for payment history columns
    echo "=== Payment History Columns ===\n";
    foreach ($columns as $col) {
        if (strpos($col['Field'], 'payment_amount_') !== false && strpos($col['Field'], '_history') !== false) {
            echo $col['Field'] . " - " . $col['Type'] . "\n";
        }
    }
    
    echo "\n=== All Columns ===\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
