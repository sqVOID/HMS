<?php
// Add contact_no column to reports table

try {
    $conn = new mysqli('localhost', 'root', '', 'hotel_management');
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to database successfully.\n\n";
    
    // Check if contact_no column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'contact_no'");
    
    if ($checkColumn->num_rows == 0) {
        echo "Adding contact_no column to reports table...\n";
        
        // Add contact_no column after contact_person_name
        $sql = "ALTER TABLE reports ADD COLUMN contact_no VARCHAR(20) NULL DEFAULT NULL AFTER contact_person_name";
        
        if ($conn->query($sql) === TRUE) {
            echo "✓ Successfully added contact_no column to reports table\n";
        } else {
            echo "✗ Error adding contact_no column: " . $conn->error . "\n";
        }
    } else {
        echo "✓ contact_no column already exists in reports table\n";
    }
    
    echo "\nMigration completed!\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
