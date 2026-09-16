<?php
// Add discount columns to bookings table
require_once 'config.php';

try {
    // Check and add discount_enabled column
    $stmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'discount_enabled'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN discount_enabled TINYINT(1) DEFAULT 0 AFTER paid_status");
        echo "Added discount_enabled column\n";
    } else {
        echo "discount_enabled column already exists\n";
    }
    
    // Check and add discount_type column
    $stmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'discount_type'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN discount_type VARCHAR(50) NULL DEFAULT 'regular' AFTER discount_enabled");
        echo "Added discount_type column\n";
    } else {
        echo "discount_type column already exists\n";
    }
    
    // Check and add sc_pwd_count column
    $stmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'sc_pwd_count'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN sc_pwd_count INT DEFAULT 0 AFTER discount_type");
        echo "Added sc_pwd_count column\n";
    } else {
        echo "sc_pwd_count column already exists\n";
    }
    
    // Check and add discount_amount column
    $stmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'discount_amount'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0 AFTER sc_pwd_count");
        echo "Added discount_amount column\n";
    } else {
        echo "discount_amount column already exists\n";
    }
    
    echo "\nAll discount columns have been added successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
