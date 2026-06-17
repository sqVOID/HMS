<?php
require_once 'config.php';

try {
    // Check if column exists first
    $stmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'contact_no'");
    if ($stmt->rowCount() == 0) {
        // Add the column
        $sql = "ALTER TABLE bookings ADD COLUMN contact_no VARCHAR(20) AFTER guest_name";
        $conn->exec($sql);
        echo "Column 'contact_no' added successfully.";
    } else {
        echo "Column 'contact_no' already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
