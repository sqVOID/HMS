<?php
/**
 * Inventory Helper Functions
 * Handles inventory stock management for bookings
 */

if (!function_exists('restock_inventory_item')) {
    /**
     * Increment stock for a given inventory item.
     * Used when restoring items after booking cancellation.
     * 
     * @param PDO $conn Database connection
     * @param int $inventoryId Inventory item ID
     * @param int $quantity Quantity to add back (default: 1)
     * @return bool Success status
     */
    function restock_inventory_item(PDO $conn, int $inventoryId, int $quantity = 1): bool
    {
        if ($inventoryId <= 0 || $quantity <= 0) {
            error_log("Invalid restock parameters: inventoryId=$inventoryId, quantity=$quantity");
            return false;
        }

        try {
            $stmt = $conn->prepare("
                UPDATE inventory
                SET stock = stock + :qty
                WHERE id = :id
            ");
            $stmt->bindParam(':qty', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $inventoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $rowsAffected = $stmt->rowCount();
            error_log("Restocked inventory ID $inventoryId: +$quantity (rows affected: $rowsAffected)");
            
            return $rowsAffected > 0;
        } catch (PDOException $e) {
            error_log('Failed to restock inventory item: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('consume_inventory_item')) {
    /**
     * Decrement stock for a given inventory item if stock is available.
     * Used when deducting items during checkout for missing items.
     * 
     * @param PDO $conn Database connection
     * @param int $inventoryId Inventory item ID
     * @param int $quantity Quantity to consume (default: 1)
     * @return bool Success status
     */
    function consume_inventory_item(PDO $conn, int $inventoryId, int $quantity = 1): bool
    {
        if ($inventoryId <= 0 || $quantity <= 0) {
            error_log("Invalid consume parameters: inventoryId=$inventoryId, quantity=$quantity");
            return false;
        }

        try {
            // First check if we have enough stock
            $checkStmt = $conn->prepare("SELECT stock FROM inventory WHERE id = :id");
            $checkStmt->bindParam(':id', $inventoryId, PDO::PARAM_INT);
            $checkStmt->execute();
            $currentStock = $checkStmt->fetchColumn();
            
            if ($currentStock === false || $currentStock < $quantity) {
                error_log("Insufficient stock for inventory ID $inventoryId: current=$currentStock, requested=$quantity");
                return false;
            }
            
            // Proceed with consumption
            $stmt = $conn->prepare("
                UPDATE inventory
                SET stock = CASE 
                    WHEN stock >= :qty THEN stock - :qty
                    ELSE stock
                END
                WHERE id = :id AND stock >= :qty
            ");
            $stmt->bindParam(':qty', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $inventoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $rowsAffected = $stmt->rowCount();
            error_log("Consumed inventory ID $inventoryId: -$quantity (rows affected: $rowsAffected)");
            
            return $rowsAffected > 0;
        } catch (PDOException $e) {
            error_log('Failed to consume inventory item: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_inventory_item_by_name')) {
    /**
     * Get inventory item by product name (case-insensitive)
     * 
     * @param PDO $conn Database connection
     * @param string $productName Product name to search for
     * @return array|false Inventory item data or false if not found
     */
    function get_inventory_item_by_name(PDO $conn, string $productName)
    {
        try {
            $normalizedName = strtolower(trim($productName));
            $stmt = $conn->prepare("
                SELECT id, product_name, price, stock, product_image 
                FROM inventory 
                WHERE LOWER(product_name) = :name 
                LIMIT 1
            ");
            $stmt->bindParam(':name', $normalizedName);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Failed to get inventory item by name: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('process_missing_items_inventory')) {
    /**
     * Process missing items and deduct from inventory
     * Used during checkout
     * 
     * @param PDO $conn Database connection
     * @param array $missingItems Array of missing items
     * @return array Array of deducted items with details
     */
    function process_missing_items_inventory(PDO $conn, array $missingItems): array
    {
        $deductedItems = [];
        
        foreach ($missingItems as $item) {
            $itemName = isset($item['name']) ? trim($item['name']) : trim($item);
            
            if (empty($itemName)) {
                continue;
            }
            
            // Find inventory item
            $invItem = get_inventory_item_by_name($conn, $itemName);
            
            if ($invItem && $invItem['stock'] > 0) {
                // Consume 1 unit from inventory
                $success = consume_inventory_item($conn, $invItem['id'], 1);
                
                if ($success) {
                    $deductedItems[] = [
                        'id' => $invItem['id'],
                        'name' => $invItem['product_name'],
                        'previous_stock' => $invItem['stock'],
                        'new_stock' => $invItem['stock'] - 1
                    ];
                    
                    error_log("Successfully deducted {$invItem['product_name']} from inventory");
                }
            } else {
                error_log("Could not deduct $itemName - not found in inventory or out of stock");
            }
        }
        
        return $deductedItems;
    }
}

if (!function_exists('restore_missing_items_inventory')) {
    /**
     * Restore missing items back to inventory
     * Used when booking is canceled/deleted
     * 
     * @param PDO $conn Database connection
     * @param array $missingItems Array of missing items
     * @return array Array of restored items with details
     */
    function restore_missing_items_inventory(PDO $conn, array $missingItems): array
    {
        $restoredItems = [];
        
        foreach ($missingItems as $item) {
            $itemName = isset($item['name']) ? trim($item['name']) : trim($item);
            
            if (empty($itemName)) {
                continue;
            }
            
            // Find inventory item
            $invItem = get_inventory_item_by_name($conn, $itemName);
            
            if ($invItem) {
                // Restock 1 unit to inventory
                $success = restock_inventory_item($conn, $invItem['id'], 1);
                
                if ($success) {
                    $restoredItems[] = [
                        'id' => $invItem['id'],
                        'name' => $invItem['product_name'],
                        'previous_stock' => $invItem['stock'],
                        'new_stock' => $invItem['stock'] + 1
                    ];
                    
                    error_log("Successfully restored {$invItem['product_name']} to inventory");
                }
            } else {
                error_log("Could not restore $itemName - not found in inventory");
            }
        }
        
        return $restoredItems;
    }
}
?>