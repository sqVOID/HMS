<?php
require_once 'config.php';

echo "Checking discount columns in bookings table...\n\n";

try {
    $stmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'discount%'");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "❌ NO DISCOUNT COLUMNS FOUND!\n\n";
        echo "Adding discount columns now...\n";
        
        $conn->exec("ALTER TABLE bookings ADD COLUMN discount_enabled TINYINT(1) DEFAULT 0");
        echo "✓ Added discount_enabled\n";
        
        $conn->exec("ALTER TABLE bookings ADD COLUMN discount_type VARCHAR(50) NULL DEFAULT 'regular'");
        echo "✓ Added discount_type\n";
        
        $conn->exec("ALTER TABLE bookings ADD COLUMN sc_pwd_count INT DEFAULT 0");
        echo "✓ Added sc_pwd_count\n";
        
        $conn->exec("ALTER TABLE bookings ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0");
        echo "✓ Added discount_amount\n";
        
        echo "\nColumns added successfully!\n";
    } else {
        echo "✓ Discount columns found:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    }
    
    // Also check if reports table has discount columns
    echo "\n\nChecking discount columns in reports table...\n\n";
    $stmt2 = $conn->query("SHOW COLUMNS FROM reports LIKE 'discount%'");
    $columns2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns2)) {
        echo "❌ NO DISCOUNT COLUMNS IN REPORTS TABLE!\n";
        echo "Adding discount columns to reports table...\n";
        
        $conn->exec("ALTER TABLE reports ADD COLUMN discount_enabled TINYINT(1) DEFAULT 0");
        echo "✓ Added discount_enabled to reports\n";
        
        $conn->exec("ALTER TABLE reports ADD COLUMN discount_type VARCHAR(50) NULL DEFAULT 'regular'");
        echo "✓ Added discount_type to reports\n";
        
        $conn->exec("ALTER TABLE reports ADD COLUMN sc_pwd_count INT DEFAULT 0");
        echo "✓ Added sc_pwd_count to reports\n";
        
        $conn->exec("ALTER TABLE reports ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0");
        echo "✓ Added discount_amount to reports\n";
    } else {
        echo "✓ Discount columns found in reports:\n";
        foreach ($columns2 as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
