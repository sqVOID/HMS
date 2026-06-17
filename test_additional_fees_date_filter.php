<?php
/**
 * Test script to verify additional fees date filtering
 */

require_once 'config.php';

echo "<h2>Additional Fees Date Filtering Test</h2>\n";
echo "<p>This script tests if additional fees are correctly filtered by payment date.</p>\n";

try {
    // Check if column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_fees_paid_date'");
    if ($checkColumn->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Column 'additional_fees_paid_date' does not exist in bookings table!</p>\n";
        echo "<p>Please run: <code>php add_additional_fees_paid_date.php</code></p>\n";
        exit;
    } else {
        echo "<p style='color: green;'>✓ Column 'additional_fees_paid_date' exists in bookings table</p>\n";
    }
    
    // Check reports table
    $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'additional_fees_paid_date'");
    if ($checkReportsColumn->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Column 'additional_fees_paid_date' does not exist in reports table!</p>\n";
        exit;
    } else {
        echo "<p style='color: green;'>✓ Column 'additional_fees_paid_date' exists in reports table</p>\n";
    }
    
    echo "<hr>\n";
    echo "<h3>Sample Bookings with Additional Fees</h3>\n";
    
    // Get bookings with additional fees
    $stmt = $conn->prepare("
        SELECT 
            booking_id,
            check_in,
            additional_fees_paid_date,
            additional_items,
            additional_food,
            additional_guest,
            additional_pet,
            missing_items_fees,
            penalty_amount,
            additional_fees_status
        FROM bookings
        WHERE (
            (additional_items IS NOT NULL AND additional_items != '') OR
            (additional_food IS NOT NULL AND additional_food != '') OR
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
        echo "<p>No bookings with additional fees found.</p>\n";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>\n";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Booking ID</th>";
        echo "<th>Check-in Date</th>";
        echo "<th>Additional Fees Paid Date</th>";
        echo "<th>Has Items/Food</th>";
        echo "<th>Guest/Pet</th>";
        echo "<th>Missing/Penalty</th>";
        echo "<th>Status</th>";
        echo "<th>Date Match</th>";
        echo "</tr>\n";
        
        foreach ($bookings as $booking) {
            $checkIn = $booking['check_in'] ?? 'N/A';
            $paidDate = $booking['additional_fees_paid_date'] ?? 'NULL';
            $hasItems = (!empty($booking['additional_items']) || !empty($booking['additional_food'])) ? 'Yes' : 'No';
            $guestPet = ($booking['additional_guest'] > 0 || $booking['additional_pet'] > 0) ? 
                "G:{$booking['additional_guest']} P:{$booking['additional_pet']}" : 'No';
            $missingPenalty = ($booking['missing_items_fees'] > 0 || $booking['penalty_amount'] > 0) ?
                "M:{$booking['missing_items_fees']} P:{$booking['penalty_amount']}" : 'No';
            $status = $booking['additional_fees_status'] ?? 'None';
            
            // Check if dates match
            $dateMatch = '—';
            if ($paidDate !== 'NULL' && $checkIn !== 'N/A') {
                try {
                    $checkInDate = (new DateTime($checkIn))->format('Y-m-d');
                    $paidDateOnly = (new DateTime($paidDate))->format('Y-m-d');
                    $dateMatch = ($checkInDate === $paidDateOnly) ? 
                        "<span style='color: green;'>✓ Same</span>" : 
                        "<span style='color: orange;'>⚠ Different</span>";
                } catch (Exception $e) {
                    $dateMatch = 'Error';
                }
            }
            
            $statusColor = ($status === 'Paid') ? 'green' : (($status === 'Pending') ? 'orange' : 'gray');
            
            echo "<tr>";
            echo "<td>{$booking['booking_id']}</td>";
            echo "<td>{$checkIn}</td>";
            echo "<td><strong>{$paidDate}</strong></td>";
            echo "<td>{$hasItems}</td>";
            echo "<td>{$guestPet}</td>";
            echo "<td>{$missingPenalty}</td>";
            echo "<td style='color: {$statusColor};'><strong>{$status}</strong></td>";
            echo "<td>{$dateMatch}</td>";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
    }
    
    echo "<hr>\n";
    echo "<h3>Test Scenario</h3>\n";
    echo "<p><strong>Expected Behavior:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>If <code>additional_fees_paid_date</code> is NULL → Additional fees will NOT appear in any export</li>\n";
    echo "<li>If <code>additional_fees_paid_date</code> is set → Additional fees will ONLY appear in exports for that date</li>\n";
    echo "<li>If check-in date ≠ paid date → Additional fees appear on paid date, not check-in date</li>\n";
    echo "</ul>\n";
    
    echo "<hr>\n";
    echo "<h3>How to Test</h3>\n";
    echo "<ol>\n";
    echo "<li>Find a booking with additional fees (see table above)</li>\n";
    echo "<li>Note the <strong>Check-in Date</strong> and <strong>Additional Fees Paid Date</strong></li>\n";
    echo "<li>Export daily sales for the <strong>Check-in Date</strong></li>\n";
    echo "<li>Export daily sales for the <strong>Paid Date</strong></li>\n";
    echo "<li>Verify additional fees appear ONLY in the export for the <strong>Paid Date</strong></li>\n";
    echo "</ol>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>
