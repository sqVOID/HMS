<?php
require_once 'config.php';

echo "<h2>Column Count Analysis</h2>";

try {
    // Get actual columns from deleted_data table
    $columnsStmt = $conn->query("SHOW COLUMNS FROM deleted_data");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Actual Columns in deleted_data Table: " . count($columns) . "</h3>";
    echo "<ol>";
    foreach ($columns as $col) {
        echo "<li><strong>" . htmlspecialchars($col['Field']) . "</strong> (" . htmlspecialchars($col['Type']) . ")</li>";
    }
    echo "</ol>";
    
    // Count columns in INSERT statement
    $insertColumns = [
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
    
    echo "<h3>Columns in INSERT Statement: " . count($insertColumns) . "</h3>";
    echo "<ol>";
    foreach ($insertColumns as $col) {
        echo "<li>" . htmlspecialchars($col) . "</li>";
    }
    echo "</ol>";
    
    // Find missing or extra columns
    $tableColumnNames = array_map(function($col) { return $col['Field']; }, $columns);
    
    $missingInInsert = array_diff($tableColumnNames, array_merge(['id', 'deleted_at'], $insertColumns));
    $extraInInsert = array_diff($insertColumns, $tableColumnNames);
    
    if (count($missingInInsert) > 0) {
        echo "<h3 style='color: red;'>Missing in INSERT (exist in table but not in INSERT):</h3>";
        echo "<ul>";
        foreach ($missingInInsert as $col) {
            echo "<li style='color: red;'>" . htmlspecialchars($col) . "</li>";
        }
        echo "</ul>";
    }
    
    if (count($extraInInsert) > 0) {
        echo "<h3 style='color: orange;'>Extra in INSERT (in INSERT but not in table):</h3>";
        echo "<ul>";
        foreach ($extraInInsert as $col) {
            echo "<li style='color: orange;'>" . htmlspecialchars($col) . "</li>";
        }
        echo "</ul>";
    }
    
    if (count($missingInInsert) == 0 && count($extraInInsert) == 0) {
        echo "<h3 style='color: green;'>✓ Columns match perfectly!</h3>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
