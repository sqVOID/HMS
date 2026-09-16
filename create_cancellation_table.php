<?php
// Create cancellation_requests table
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS cancellation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    guest_name VARCHAR(255),
    room_type VARCHAR(100),
    room_id VARCHAR(50),
    check_in DATE,
    check_out DATE,
    duration VARCHAR(50),
    total_amount DECIMAL(10,2),
    payment_amount DECIMAL(10,2),
    reservation_amount DECIMAL(10,2),
    refund_amount DECIMAL(10,2),
    amount_due DECIMAL(10,2),
    amount_paid DECIMAL(10,2),
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    requested_by VARCHAR(100),
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by VARCHAR(100),
    reviewed_at TIMESTAMP NULL,
    admin_notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $conn->exec($sql);
    echo "Table 'cancellation_requests' created successfully or already exists.<br>";
    
    // Add amount_due and amount_paid columns if they don't exist
    try {
        $conn->exec("ALTER TABLE cancellation_requests ADD COLUMN amount_due DECIMAL(10,2) AFTER refund_amount");
        echo "Column 'amount_due' added.<br>";
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    try {
        $conn->exec("ALTER TABLE cancellation_requests ADD COLUMN amount_paid DECIMAL(10,2) AFTER amount_due");
        echo "Column 'amount_paid' added.<br>";
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    echo "Setup complete!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
