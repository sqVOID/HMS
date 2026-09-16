<?php
require_once 'config.php';

echo "<h2>Testing deleted_data Table</h2>";

try {
    // Create deleted_data table
    $createTableSQL = "CREATE TABLE IF NOT EXISTS deleted_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_table VARCHAR(50) NOT NULL,
        original_id INT NOT NULL,
        booking_id VARCHAR(50),
        room_id VARCHAR(50),
        room_no VARCHAR(50),
        guest_names TEXT,
        guest_type VARCHAR(50),
        contact_no VARCHAR(50),
        address TEXT,
        vehicle_type VARCHAR(100),
        plate_number VARCHAR(50),
        vehicle_description TEXT,
        referral VARCHAR(100),
        reason TEXT,
        check_in DATETIME,
        check_out DATETIME,
        duration INT,
        encoder VARCHAR(100),
        encoder_checkin VARCHAR(100),
        encoder_checkout VARCHAR(100),
        booking_type VARCHAR(50),
        promo VARCHAR(100),
        breakfast TEXT,
        additional_guest INT,
        additional_pet INT,
        discount_type VARCHAR(50),
        discount_id_number VARCHAR(100),
        discount_amount DECIMAL(10,2),
        discount_applied TINYINT(1),
        hygiene_kit_inventory_id INT,
        hygiene_kit_restocked TINYINT(1),
        tissue_inventory_id INT,
        tissue_used INT,
        missing_items_list TEXT,
        additional_charges TEXT,
        payment_method VARCHAR(50),
        amount_paid DECIMAL(10,2),
        change_amount DECIMAL(10,2),
        payment_status VARCHAR(50),
        payment_history TEXT,
        downpayment_amount DECIMAL(10,2),
        downpayment_date DATETIME,
        additional_fees_paid_date DATETIME,
        cancellation_reason TEXT,
        cancellation_date DATETIME,
        cancellation_status VARCHAR(50),
        extend_hours INT,
        extend_minutes INT,
        extend_price DECIMAL(10,2),
        extend_regular_rate DECIMAL(10,2),
        extend_bundle_rate DECIMAL(10,2),
        extend_bundle_breakfast TEXT,
        extend_additional_item TEXT,
        extend_breakfast_date DATETIME,
        extend_additional_item_date DATETIME,
        status VARCHAR(50),
        deleted_by VARCHAR(100),
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_original_table (original_table),
        INDEX idx_original_id (original_id),
        INDEX idx_booking_id (booking_id),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($createTableSQL);
    
    echo "<p style='color: green;'>✓ Table 'deleted_data' created successfully (or already exists)</p>";
    
    // Check table structure
    $columnsStmt = $conn->query("SHOW COLUMNS FROM deleted_data");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>#</th><th>Column Name</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $idx => $col) {
        echo "<tr>";
        echo "<td>" . ($idx + 1) . "</td>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if there's any data
    $countStmt = $conn->query("SELECT COUNT(*) as total FROM deleted_data");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Records in deleted_data: " . $count['total'] . "</h3>";
    
    if ($count['total'] > 0) {
        $dataStmt = $conn->query("SELECT * FROM deleted_data ORDER BY deleted_at DESC LIMIT 10");
        $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Last 10 Deleted Records:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Table</th><th>Original ID</th><th>Booking ID</th><th>Guest Names</th><th>Deleted By</th><th>Deleted At</th></tr>";
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['original_table']) . "</td>";
            echo "<td>" . htmlspecialchars($row['original_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['booking_id'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['guest_names'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['deleted_by']) . "</td>";
            echo "<td>" . htmlspecialchars($row['deleted_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr>";
    echo "<p style='color: green;'><strong>✓ Test completed successfully!</strong></p>";
    echo "<p><a href='Modification.php'>Go to Modification Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
