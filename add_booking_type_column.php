<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $messages = [];
    
    // Add booking_type to bookings table
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'booking_type'");
    
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN booking_type VARCHAR(20) DEFAULT 'Walk-in' AFTER status");
        $messages[] = 'booking_type column added to bookings table';
    } else {
        $messages[] = 'booking_type column already exists in bookings table';
    }
    
    // Add booking_type to reports table
    $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'booking_type'");
    
    if ($checkReportsColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN booking_type VARCHAR(20) DEFAULT 'Walk-in' AFTER status");
        $messages[] = 'booking_type column added to reports table';
    } else {
        $messages[] = 'booking_type column already exists in reports table';
    }
    
    echo json_encode([
        'success' => true,
        'message' => implode('. ', $messages)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
