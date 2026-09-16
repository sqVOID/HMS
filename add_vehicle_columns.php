<?php
// Add vehicle columns to bookings and reports tables
require_once 'config.php';

// Verify connection exists
if (!isset($conn)) {
    die("Error: Database connection not established. Please check config.php\n");
}

try {
    echo "Connected to database successfully.\n\n";
    
    // Add columns to bookings table
    echo "Adding vehicle columns to 'bookings' table...\n";
    
    $bookingsColumns = [
        "ALTER TABLE bookings ADD COLUMN vehicle_type VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE bookings ADD COLUMN plate_number VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE bookings ADD COLUMN vehicle_description TEXT DEFAULT NULL"
    ];
    
    foreach ($bookingsColumns as $sql) {
        try {
            $conn->exec($sql);
            echo "✓ Successfully added column to bookings table\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "- Column already exists in bookings table\n";
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n";
    
    // Add columns to reports table
    echo "Adding vehicle columns to 'reports' table...\n";
    
    $reportsColumns = [
        "ALTER TABLE reports ADD COLUMN vehicle_type VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE reports ADD COLUMN plate_number VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE reports ADD COLUMN vehicle_description TEXT DEFAULT NULL"
    ];
    
    foreach ($reportsColumns as $sql) {
        try {
            $conn->exec($sql);
            echo "✓ Successfully added column to reports table\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "- Column already exists in reports table\n";
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Migration completed successfully! ===\n";
    echo "\nColumns added:\n";
    echo "1. vehicle_type (VARCHAR 50) - Stores type of vehicle (Sedan, SUV, etc.)\n";
    echo "2. plate_number (VARCHAR 20) - Stores vehicle plate number\n";
    echo "3. vehicle_description (TEXT) - Stores additional vehicle details\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>
