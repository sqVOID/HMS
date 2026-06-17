<?php
// Add reservation_date column to bookings and reports tables

require_once 'config.php';

echo "Connected successfully\n";

try {
    // Add reservation_date column to bookings table
    $sql1 = "ALTER TABLE bookings ADD COLUMN reservation_date DATETIME NULL";
    
    try {
        $conn->exec($sql1);
        echo "Column reservation_date added to bookings table successfully\n";
    } catch(PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Column reservation_date already exists in bookings table\n";
        } else {
            echo "Error adding column to bookings table: " . $e->getMessage() . "\n";
        }
    }

    // Add reservation_date column to reports table
    $sql2 = "ALTER TABLE reports ADD COLUMN reservation_date DATETIME NULL";
    
    try {
        $conn->exec($sql2);
        echo "Column reservation_date added to reports table successfully\n";
    } catch(PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Column reservation_date already exists in reports table\n";
        } else {
            echo "Error adding column to reports table: " . $e->getMessage() . "\n";
        }
    }

    echo "\nMigration completed!\n";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
