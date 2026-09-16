<?php
require_once 'config.php';

echo "<h2>Migrating deleted_data Table</h2>";

try {
    // Check if old table exists
    $checkStmt = $conn->query("SHOW TABLES LIKE 'deleted_data'");
    $exists = $checkStmt->fetch();
    
    if ($exists) {
        echo "<p style='color: orange;'>⚠ Old deleted_data table found. Dropping it...</p>";
        $conn->exec("DROP TABLE deleted_data");
        echo "<p style='color: green;'>✓ Old table dropped successfully</p>";
    }
    
    // Create new table with all columns
    echo "<p>Creating new deleted_data table with all booking columns...</p>";
    
    $createTableSQL = "CREATE TABLE deleted_data (
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
    
    echo "<p style='color: green;'>✓ New deleted_data table created successfully!</p>";
    
    // Show table structure
    $columnsStmt = $conn->query("SHOW COLUMNS FROM deleted_data");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>New Table Structure (" . count($columns) . " columns):</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; font-size: 12px;'>";
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
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✓ Migration completed successfully!</h3>";
    echo "<p>The deleted_data table now has ALL booking columns.</p>";
    echo "<p><strong>You can now delete bookings from Modification.php and all details will be saved!</strong></p>";
    echo "<p><a href='Modification.php'>Go to Modification Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
