<?php
/**
 * Fix Premium 2 24 Hours Bundle Price
 * 
 * This script ensures that Premium 2's 24-hour bundle price
 * uses the same computation as the 12-hour bundle (2x the 12-hour price).
 */

require_once 'config.php';

try {
    $conn->beginTransaction();
    
    // Find all Premium 2 promos
    $stmt = $conn->prepare("
        SELECT id, title, price_12hrs, price_24hrs 
        FROM promos 
        WHERE LOWER(title) LIKE '%premium 2%' 
           OR LOWER(title) LIKE '%premium2%'
           OR LOWER(title) LIKE '%package 2%'
    ");
    $stmt->execute();
    $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($promos)) {
        echo "No Premium 2 promos found.\n";
        $conn->rollBack();
        exit;
    }
    
    echo "Found " . count($promos) . " Premium 2 promo(s):\n\n";
    
    foreach ($promos as $promo) {
        $id = $promo['id'];
        $title = $promo['title'];
        $price12hrs = floatval($promo['price_12hrs']);
        $price24hrs = floatval($promo['price_24hrs']);
        
        // Calculate the correct 24-hour price (2x the 12-hour price)
        $correctPrice24hrs = $price12hrs * 2;
        
        echo "Promo ID: $id\n";
        echo "Title: $title\n";
        echo "Current 12hrs price: ₱" . number_format($price12hrs, 2) . "\n";
        echo "Current 24hrs price: ₱" . number_format($price24hrs, 2) . "\n";
        echo "Correct 24hrs price (2x 12hrs): ₱" . number_format($correctPrice24hrs, 2) . "\n";
        
        if (abs($price24hrs - $correctPrice24hrs) > 0.01) {
            // Update the 24-hour price
            $updateStmt = $conn->prepare("
                UPDATE promos 
                SET price_24hrs = :price_24hrs,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':price_24hrs' => $correctPrice24hrs,
                ':id' => $id
            ]);
            
            echo "✓ UPDATED: 24hrs price changed from ₱" . number_format($price24hrs, 2) . 
                 " to ₱" . number_format($correctPrice24hrs, 2) . "\n";
        } else {
            echo "✓ OK: 24hrs price is already correct\n";
        }
        
        echo "\n";
    }
    
    $conn->commit();
    echo "All Premium 2 bundle prices have been fixed!\n";
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
