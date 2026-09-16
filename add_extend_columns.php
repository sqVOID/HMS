<?php
// Database connection
$host = 'localhost';
$dbname = 'hotel_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n\n";
    
    // Add columns to bookings table
    echo "Adding columns to 'bookings' table...\n";
    
    $bookingsColumns = [
        "ALTER TABLE bookings ADD COLUMN extend_regular_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_price",
        "ALTER TABLE bookings ADD COLUMN extend_bundle_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_regular_rate",
        "ALTER TABLE bookings ADD COLUMN extend_bundle_breakfast TEXT DEFAULT NULL AFTER extend_bundle_rate"
    ];
    
    foreach ($bookingsColumns as $sql) {
        try {
            $pdo->exec($sql);
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
    echo "Adding columns to 'reports' table...\n";
    
    $reportsColumns = [
        "ALTER TABLE reports ADD COLUMN extend_regular_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_price",
        "ALTER TABLE reports ADD COLUMN extend_bundle_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_regular_rate",
        "ALTER TABLE reports ADD COLUMN extend_bundle_breakfast TEXT DEFAULT NULL AFTER extend_bundle_rate"
    ];
    
    foreach ($reportsColumns as $sql) {
        try {
            $pdo->exec($sql);
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
    echo "1. extend_regular_rate (DECIMAL 10,2) - Stores regular rate for extend duration\n";
    echo "2. extend_bundle_rate (DECIMAL 10,2) - Stores bundle rate for extend duration\n";
    echo "3. extend_bundle_breakfast (TEXT) - Stores breakfast selections as JSON for extend duration\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>
