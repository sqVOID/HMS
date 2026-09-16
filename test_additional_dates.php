<?php
/**
 * Test page to verify additional fees date tracking is working
 */

require_once 'config.php';

echo "<!DOCTYPE html><html><head>";
echo "<title>Additional Fees Date Tracking Test</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #4CAF50; color: white; }
tr:nth-child(even) { background-color: #f2f2f2; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { background: #e7f3fe; border-left: 4px solid #2196F3; padding: 10px; margin: 10px 0; }
</style>";
echo "</head><body>";

echo "<h1>🧪 Additional Fees Date Tracking Test</h1>";

try {
    // Check if all columns exist
    echo "<h2>1. Database Schema Check</h2>";
    $columns = [
        'additional_food_date',
        'additional_items_date',
        'additional_guest_date',
        'additional_pet_date',
        'additional_fees_paid_date'
    ];
    
    echo "<table>";
    echo "<tr><th>Column Name</th><th>Bookings Table</th><th>Reports Table</th></tr>";
    
    foreach ($columns as $col) {
        $bookingsCheck = $conn->query("SHOW COLUMNS FROM bookings LIKE '{$col}'");
        $reportsCheck = $conn->query("SHOW COLUMNS FROM reports LIKE '{$col}'");
        
        $bookingsExists = $bookingsCheck->rowCount() > 0;
        $reportsExists = $reportsCheck->rowCount() > 0;
        
        $bookingsStatus = $bookingsExists ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>";
        $reportsStatus = $reportsExists ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>";
        
        echo "<tr><td><strong>{$col}</strong></td><td>{$bookingsStatus}</td><td>{$reportsStatus}</td></tr>";
    }
    echo "</table>";
    
    // Check for bookings with additional fees and their dates
    echo "<h2>2. Sample Bookings with Additional Fees</h2>";
    
    $stmt = $conn->prepare("
        SELECT 
            booking_id,
            check_in,
            additional_food,
            additional_food_date,
            additional_items,
            additional_items_date,
            additional_guest,
            additional_guest_date,
            additional_pet,
            additional_pet_date,
            missing_items_fees,
            penalty_amount,
            additional_fees_paid_date
        FROM bookings
        WHERE (
            (additional_food IS NOT NULL AND additional_food != '') OR
            (additional_items IS NOT NULL AND additional_items != '') OR
            additional_guest > 0 OR
            additional_pet > 0 OR
            missing_items_fees > 0 OR
            penalty_amount > 0
        )
        ORDER BY id DESC
        LIMIT 10
    ");
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) == 0) {
        echo "<div class='info'>ℹ️ No bookings with additional fees found. Add some additionals to a booking to test!</div>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>Booking ID</th>";
        echo "<th>Check-in</th>";
        echo "<th>Food</th>";
        echo "<th>Food Date</th>";
        echo "<th>Items</th>";
        echo "<th>Items Date</th>";
        echo "<th>Guest</th>";
        echo "<th>Guest Date</th>";
        echo "<th>Pet</th>";
        echo "<th>Pet Date</th>";
        echo "<th>Checkout Fees</th>";
        echo "<th>Checkout Date</th>";
        echo "</tr>";
        
        foreach ($bookings as $booking) {
            $hasFood = !empty($booking['additional_food']);
            $hasItems = !empty($booking['additional_items']);
            $hasGuest = $booking['additional_guest'] > 0;
            $hasPet = $booking['additional_pet'] > 0;
            $hasCheckoutFees = ($booking['missing_items_fees'] > 0 || $booking['penalty_amount'] > 0);
            
            echo "<tr>";
            echo "<td><strong>{$booking['booking_id']}</strong></td>";
            echo "<td>" . ($booking['check_in'] ?? 'N/A') . "</td>";
            
            // Food
            echo "<td>" . ($hasFood ? "✓ Yes" : "—") . "</td>";
            $foodDateClass = ($hasFood && empty($booking['additional_food_date'])) ? 'warning' : 'success';
            $foodDateText = $booking['additional_food_date'] ?? ($hasFood ? 'NULL ⚠️' : '—');
            echo "<td class='{$foodDateClass}'>{$foodDateText}</td>";
            
            // Items
            echo "<td>" . ($hasItems ? "✓ Yes" : "—") . "</td>";
            $itemsDateClass = ($hasItems && empty($booking['additional_items_date'])) ? 'warning' : 'success';
            $itemsDateText = $booking['additional_items_date'] ?? ($hasItems ? 'NULL ⚠️' : '—');
            echo "<td class='{$itemsDateClass}'>{$itemsDateText}</td>";
            
            // Guest
            echo "<td>" . ($hasGuest ? "✓ {$booking['additional_guest']}" : "—") . "</td>";
            $guestDateClass = ($hasGuest && empty($booking['additional_guest_date'])) ? 'warning' : 'success';
            $guestDateText = $booking['additional_guest_date'] ?? ($hasGuest ? 'NULL ⚠️' : '—');
            echo "<td class='{$guestDateClass}'>{$guestDateText}</td>";
            
            // Pet
            echo "<td>" . ($hasPet ? "✓ {$booking['additional_pet']}" : "—") . "</td>";
            $petDateClass = ($hasPet && empty($booking['additional_pet_date'])) ? 'warning' : 'success';
            $petDateText = $booking['additional_pet_date'] ?? ($hasPet ? 'NULL ⚠️' : '—');
            echo "<td class='{$petDateClass}'>{$petDateText}</td>";
            
            // Checkout Fees
            $checkoutFeesText = [];
            if ($booking['missing_items_fees'] > 0) $checkoutFeesText[] = "Missing: ₱" . number_format($booking['missing_items_fees'], 2);
            if ($booking['penalty_amount'] > 0) $checkoutFeesText[] = "Penalty: ₱" . number_format($booking['penalty_amount'], 2);
            echo "<td>" . ($hasCheckoutFees ? implode("<br>", $checkoutFeesText) : "—") . "</td>";
            $checkoutDateClass = ($hasCheckoutFees && empty($booking['additional_fees_paid_date'])) ? 'warning' : 'success';
            $checkoutDateText = $booking['additional_fees_paid_date'] ?? ($hasCheckoutFees ? 'NULL ⚠️' : '—');
            echo "<td class='{$checkoutDateClass}'>{$checkoutDateText}</td>";
            
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='info'>";
        echo "<strong>Legend:</strong><br>";
        echo "✓ = Has additional fees<br>";
        echo "— = No additional fees<br>";
        echo "<span class='warning'>NULL ⚠️</span> = Has fees but date not set (needs update)<br>";
        echo "<span class='success'>Date shown</span> = Properly tracked!";
        echo "</div>";
    }
    
    // Instructions
    echo "<h2>3. How to Test</h2>";
    echo "<div class='info'>";
    echo "<ol>";
    echo "<li><strong>Add Additional Items:</strong> Open a booking, add food/items, save → Check if date is set</li>";
    echo "<li><strong>Add Additional Guest/Pet:</strong> Increase guest/pet count, save → Check if date is set</li>";
    echo "<li><strong>Mark Checkout Fees as Paid:</strong> Add missing items/penalties, mark as paid → Check if date is set</li>";
    echo "<li><strong>Export Daily Sales:</strong> Export for the date when additionals were added → Should appear in report</li>";
    echo "<li><strong>Export Different Date:</strong> Export for a different date → Should NOT appear</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h2>4. Next Steps</h2>";
    echo "<div class='info'>";
    echo "✅ <strong>If all columns exist:</strong> System is ready! Test by adding additionals to a booking.<br>";
    echo "⚠️ <strong>If columns are missing:</strong> Run the migration scripts:<br>";
    echo "<code>C:\\xampp\\php\\php.exe add_additional_items_dates.php</code><br>";
    echo "<code>C:\\xampp\\php\\php.exe add_additional_fees_paid_date.php</code>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
