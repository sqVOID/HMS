<?php
/**
 * Check all promo prices in the database
 */

require_once 'config.php';

try {
    $stmt = $conn->prepare("
        SELECT id, title, price_12hrs, price_24hrs 
        FROM promos 
        ORDER BY id
    ");
    $stmt->execute();
    $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All Promos in Database:\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($promos as $promo) {
        echo "ID: " . $promo['id'] . "\n";
        echo "Title: " . $promo['title'] . "\n";
        echo "12hrs Price: ₱" . number_format($promo['price_12hrs'], 2) . "\n";
        echo "24hrs Price: ₱" . number_format($promo['price_24hrs'], 2) . "\n";
        echo "Ratio (24hrs/12hrs): " . ($promo['price_12hrs'] > 0 ? round($promo['price_24hrs'] / $promo['price_12hrs'], 2) : 'N/A') . "x\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
