<?php
/**
 * Test Extension Breakfast Display
 * 
 * This script checks if extension breakfast is being properly combined with regular breakfast
 */

require_once 'config.php';

echo "<h2>Extension Breakfast Test</h2>\n";
echo "<pre>\n";

try {
    // Find bookings with extension breakfast
    $stmt = $conn->query("
        SELECT 
            r.booking_id,
            r.guest_name,
            r.breakfast,
            r.extend_bundle_breakfast,
            b.breakfast as booking_breakfast,
            b.extend_bundle_breakfast as booking_extend_breakfast
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id = b.booking_id
        WHERE (r.extend_bundle_breakfast IS NOT NULL AND r.extend_bundle_breakfast != '' AND r.extend_bundle_breakfast != 'NULL')
           OR (b.extend_bundle_breakfast IS NOT NULL AND b.extend_bundle_breakfast != '' AND b.extend_bundle_breakfast != 'NULL')
        ORDER BY r.id DESC
        LIMIT 10
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "No bookings found with extension breakfast.\n";
        echo "Try creating a booking with an extension that includes breakfast.\n";
    } else {
        echo "Found " . count($results) . " booking(s) with extension breakfast:\n\n";
        echo str_repeat("=", 100) . "\n";
        
        foreach ($results as $row) {
            echo "Booking ID: " . $row['booking_id'] . "\n";
            echo "Guest: " . $row['guest_name'] . "\n";
            echo str_repeat("-", 100) . "\n";
            
            // Get breakfast values
            $regularBreakfast = $row['breakfast'] ?? $row['booking_breakfast'] ?? '';
            $extensionBreakfast = $row['extend_bundle_breakfast'] ?? $row['booking_extend_breakfast'] ?? '';
            
            echo "Regular Breakfast:\n";
            echo "  " . ($regularBreakfast ?: '(none)') . "\n\n";
            
            echo "Extension Breakfast:\n";
            echo "  " . ($extensionBreakfast ?: '(none)') . "\n\n";
            
            // Combine breakfast (same logic as export_daily_sales.php)
            $combinedBreakfastList = [];
            if ($regularBreakfast !== '' && $regularBreakfast !== 'None') {
                $combinedBreakfastList[] = $regularBreakfast;
            }
            if ($extensionBreakfast !== '' && $extensionBreakfast !== 'None' && $extensionBreakfast !== 'NULL') {
                $combinedBreakfastList[] = $extensionBreakfast;
            }
            
            if (!empty($combinedBreakfastList)) {
                $fullBreakfastStr = implode('|', $combinedBreakfastList);
                $bItems = explode('|', $fullBreakfastStr);
                $bAggregated = [];
                
                foreach ($bItems as $bItem) {
                    $bItem = trim($bItem);
                    if (empty($bItem)) continue;
                    
                    // Parse breakfast item
                    if (preg_match('/^(\d+)\s+(.*?)\s*-\s*(?:₱|P)?([0-9,.]+)/u', $bItem, $m)) {
                        $qty = intval($m[1]);
                        $name = trim($m[2]);
                        $priceRaw = str_replace(',', '', $m[3]);
                        $price = floatval($priceRaw);
                    } elseif (preg_match('/^(\d+)\s+(.*)$/u', $bItem, $m)) {
                        $qty = intval($m[1]);
                        $name = trim($m[2]);
                        $price = 0.0;
                    } else {
                        $qty = 1;
                        $name = $bItem;
                        $price = 0.0;
                    }
                    
                    $key = strtoupper($name);
                    
                    if (!isset($bAggregated[$key])) {
                        $bAggregated[$key] = [
                            'name' => $name,
                            'qty' => 0,
                            'price' => 0.0
                        ];
                    }
                    $bAggregated[$key]['qty'] += $qty;
                    $bAggregated[$key]['price'] += $price;
                }
                
                // Build display string
                $bParts = [];
                foreach ($bAggregated as $item) {
                    $priceStr = '';
                    if ($item['price'] > 0) {
                        $priceStr = ' - ' . number_format($item['price'], 2);
                    }
                    $bParts[] = $item['qty'] . ' ' . $item['name'] . $priceStr;
                }
                
                echo "Combined Breakfast (as shown in Daily Sales):\n";
                echo "  " . implode(' | ', $bParts) . "\n";
            } else {
                echo "Combined Breakfast: (none)\n";
            }
            
            echo "\n" . str_repeat("=", 100) . "\n\n";
        }
        
        echo "✅ Test complete!\n";
        echo "The combined breakfast above is what should appear in the Daily Sales export.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>
