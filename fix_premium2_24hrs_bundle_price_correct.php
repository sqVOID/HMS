<?php
/**
 * Fix Premium 2 24 Hours Bundle Price
 * 
 * This script ensures that Premium 2's 24-hour bundle price
 * uses the same computation ratio as Premium 1 (approximately 1.26x).
 */

require_once 'config.php';

try {
    $conn->beginTransaction();
    
    // First, get Premium 1's ratio to understand the pricing pattern
    $p1Stmt = $conn->prepare("
        SELECT price_12hrs, price_24hrs 
        FROM promos 
        WHERE LOWER(title) LIKE '%premium 1%' 
        LIMIT 1
    ");
    $p1Stmt->execute();
    $p1 = $p1Stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($p1 && $p1['price_12hrs'] > 0) {
        $ratio = $p1['price_24hrs'] / $p1['price_12hrs'];
        echo "Premium 1 pricing ratio (24hrs/12hrs): " . round($ratio, 4) . "x\n\n";
    } else {
        // Default ratio based on observed pattern
        $ratio = 1.26;
        echo "Using default ratio: " . $ratio . "x\n\n";
    }
    
    // Find Premium 2 promo
    $stmt = $conn->prepare("
        SELECT id, title, price_12hrs, price_24hrs 
        FROM promos 
        WHERE LOWER(title) LIKE '%premium 2%' 
        LIMIT 1
    ");
    $stmt->execute();
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$promo) {
        echo "No Premium 2 promo found.\n";
        $conn->rollBack();
        exit;
    }
    
    $id = $promo['id'];
    $title = $promo['title'];
    $price12hrs = floatval($promo['price_12hrs']);
    $price24hrs = floatval($promo['price_24hrs']);
    
    // Calculate the correct 24-hour price using the same ratio as Premium 1
    $correctPrice24hrs = round($price12hrs * $ratio, 2);
    
    echo "Premium 2 Promo:\n";
    echo "ID: $id\n";
    echo "Title: $title\n";
    echo "Current 12hrs price: ₱" . number_format($price12hrs, 2) . "\n";
    echo "Current 24hrs price: ₱" . number_format($price24hrs, 2) . "\n";
    echo "Correct 24hrs price (using " . round($ratio, 2) . "x ratio): ₱" . number_format($correctPrice24hrs, 2) . "\n\n";
    
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
    
    $conn->commit();
    echo "\nPremium 2 bundle price has been fixed!\n";
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
