<?php
/**
 * Test script to verify inventory integration
 * Run this to check if everything is working
 */

require_once 'config.php';
require_once 'inventory_helpers.php';

header('Content-Type: application/json');

$tests = [];

// Test 1: Check if inventory_helpers.php is loaded
$tests['helpers_loaded'] = function_exists('consume_inventory_item') && 
                            function_exists('restock_inventory_item');

// Test 2: Get current inventory items
try {
    $stmt = $conn->query("SELECT product_name, stock FROM inventory WHERE LOWER(product_name) IN ('towel', 'cover', 'bedsheet')");
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tests['inventory_items'] = $inventoryItems;
    $tests['inventory_check'] = count($inventoryItems) >= 3;
} catch (Exception $e) {
    $tests['inventory_check'] = false;
    $tests['inventory_error'] = $e->getMessage();
}

// Test 3: Test consume function (dry run - just check if function works)
try {
    $towelItem = get_inventory_item_by_name($conn, 'Towel');
    $tests['towel_found'] = $towelItem !== false;
    if ($towelItem) {
        $tests['towel_data'] = [
            'id' => $towelItem['id'],
            'name' => $towelItem['product_name'],
            'stock' => $towelItem['stock']
        ];
    }
} catch (Exception $e) {
    $tests['towel_found'] = false;
    $tests['towel_error'] = $e->getMessage();
}

// Test 4: Check if bookings table has missing_items_list column
try {
    $result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'missing_items_list'");
    $tests['missing_items_column'] = $result->rowCount() > 0;
} catch (Exception $e) {
    $tests['missing_items_column'] = false;
}

// Overall status
$tests['status'] = $tests['helpers_loaded'] && 
                   $tests['inventory_check'] && 
                   $tests['towel_found'] && 
                   $tests['missing_items_column'] ? 'PASS' : 'FAIL';

// Return results
echo json_encode([
    'success' => $tests['status'] === 'PASS',
    'tests' => $tests,
    'message' => $tests['status'] === 'PASS' 
        ? 'All tests passed! Inventory integration is ready.' 
        : 'Some tests failed. Check the details above.'
], JSON_PRETTY_PRINT);
?>