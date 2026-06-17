<?php
require_once 'config.php';

try {
    echo "=== VERIFYING COLUMNS ===\n\n";
    
    // Check bookings table
    echo "BOOKINGS TABLE:\n";
    $stmt = $conn->query("SHOW COLUMNS FROM bookings WHERE Field IN ('guest_type', 'contact_person_name', 'tin_number')");
    $bookingsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bookingsColumns as $col) {
        echo "  - {$col['Field']}: {$col['Type']}\n";
    }
    
    echo "\nREPORTS TABLE:\n";
    $stmt = $conn->query("SHOW COLUMNS FROM reports WHERE Field IN ('guest_type', 'contact_person_name', 'tin_number')");
    $reportsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reportsColumns as $col) {
        echo "  - {$col['Field']}: {$col['Type']}\n";
    }
    
    echo "\n=== VERIFICATION COMPLETE ===\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
