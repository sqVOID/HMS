<?php
require_once 'config.php';
session_start();

echo "<h2>Testing INSERT into deleted_data</h2>";

try {
    $deletedBy = $_SESSION['username'] ?? 'TestUser';
    
    echo "<p>Attempting to insert test record...</p>";
    
    // Count placeholders
    $columns = [
        'original_table', 'original_id', 'booking_id', 'room_id', 'room_no', 'guest_names', 'guest_type',
        'contact_no', 'address', 'vehicle_type', 'plate_number', 'vehicle_description', 'referral', 'reason',
        'check_in', 'check_out', 'duration', 'encoder', 'encoder_checkin', 'encoder_checkout',
        'booking_type', 'promo', 'breakfast', 'additional_guest', 'additional_pet',
        'discount_type', 'discount_id_number', 'discount_amount', 'discount_applied',
        'hygiene_kit_inventory_id', 'hygiene_kit_restocked', 'tissue_inventory_id', 'tissue_used',
        'missing_items_list', 'additional_charges', 'payment_method', 'amount_paid', 'change_amount',
        'payment_status', 'payment_history', 'downpayment_amount', 'downpayment_date',
        'additional_fees_paid_date', 'cancellation_reason', 'cancellation_date', 'cancellation_status',
        'extend_hours', 'extend_minutes', 'extend_price', 'extend_regular_rate', 'extend_bundle_rate',
        'extend_bundle_breakfast', 'extend_additional_item', 'extend_breakfast_date',
        'extend_additional_item_date', 'status', 'deleted_by'
    ];
    
    $values = [
        'bookings',              // original_table
        999,                     // original_id
        'TEST-3699',            // booking_id
        'R101',                 // room_id
        '101',                  // room_no
        'Test Guest',           // guest_names
        'Walk-in',              // guest_type
        '09123456789',          // contact_no
        'Test Address',         // address
        'Car',                  // vehicle_type
        'ABC123',               // plate_number
        'Red Honda',            // vehicle_description
        'Friend',               // referral
        'Vacation',             // reason
        '2026-07-29 14:00:00',  // check_in
        '2026-07-30 12:00:00',  // check_out
        12,                     // duration
        'admin',                // encoder
        'admin',                // encoder_checkin
        null,                   // encoder_checkout
        'Walk-in',              // booking_type
        'None',                 // promo
        'None',                 // breakfast
        0,                      // additional_guest
        0,                      // additional_pet
        'None',                 // discount_type
        null,                   // discount_id_number
        0.00,                   // discount_amount
        0,                      // discount_applied
        null,                   // hygiene_kit_inventory_id
        0,                      // hygiene_kit_restocked
        null,                   // tissue_inventory_id
        0,                      // tissue_used
        null,                   // missing_items_list
        null,                   // additional_charges
        'Cash',                 // payment_method
        2500.00,                // amount_paid
        0.00,                   // change_amount
        'Paid',                 // payment_status
        null,                   // payment_history
        null,                   // downpayment_amount
        null,                   // downpayment_date
        null,                   // additional_fees_paid_date
        null,                   // cancellation_reason
        null,                   // cancellation_date
        null,                   // cancellation_status
        null,                   // extend_hours
        null,                   // extend_minutes
        null,                   // extend_price
        null,                   // extend_regular_rate
        null,                   // extend_bundle_rate
        null,                   // extend_bundle_breakfast
        null,                   // extend_additional_item
        null,                   // extend_breakfast_date
        null,                   // extend_additional_item_date
        'Checked-out',          // status
        $deletedBy              // deleted_by
    ];
    
    echo "<p>Column count: " . count($columns) . "</p>";
    echo "<p>Value count: " . count($values) . "</p>";
    
    if (count($columns) !== count($values)) {
        echo "<p style='color: red;'><strong>ERROR: Column count doesn't match value count!</strong></p>";
        exit;
    }
    
    $columnList = implode(', ', $columns);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    
    $insertSQL = "INSERT INTO deleted_data ($columnList) VALUES ($placeholders)";
    
    echo "<p>Placeholders: " . substr_count($placeholders, '?') . "</p>";
    
    $insertStmt = $conn->prepare($insertSQL);
    
    $result = $insertStmt->execute($values);
    
    if ($result) {
        $insertId = $conn->lastInsertId();
        echo "<p style='color: green;'>✓ Test record inserted successfully! Insert ID: " . $insertId . "</p>";
        
        // Verify the inserted data
        $verifyStmt = $conn->prepare("SELECT * FROM deleted_data WHERE id = ?");
        $verifyStmt->execute([$insertId]);
        $record = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Inserted Record:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($record as $key => $value) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<hr>";
        echo "<p style='color: green;'><strong>✓ INSERT works correctly!</strong></p>";
        echo "<p>Now we can update delete_modification.php to use this same approach.</p>";
        
    } else {
        echo "<p style='color: red;'>✗ INSERT failed but no exception thrown</p>";
    }
    
    // Show all records
    $allStmt = $conn->query("SELECT id, original_table, original_id, booking_id, guest_names, deleted_by, deleted_at FROM deleted_data ORDER BY deleted_at DESC");
    $allRecords = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Records in deleted_data (" . count($allRecords) . " total):</h3>";
    if (count($allRecords) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Table</th><th>Original ID</th><th>Booking ID</th><th>Guest</th><th>Deleted By</th><th>Deleted At</th></tr>";
        foreach ($allRecords as $row) {
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
    } else {
        echo "<p>No records found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
