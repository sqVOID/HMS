<?php
/**
 * Test Extension Stack Functionality
 * This file tests the extension stack implementation
 */

require_once 'config.php';
require_once 'report_helpers.php';
require_once 'extension_stack_manager.php';

echo "<h1>Extension Stack Test Suite</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
    .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    .info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
</style>";

$manager = new ExtensionStackManager($conn);

// Test 1: Check if extension_stack column exists
echo "<div class='test-section info'>";
echo "<h2>Test 1: Database Schema Check</h2>";

try {
    $stmt = $conn->query("SHOW COLUMNS FROM bookings LIKE 'extension_stack'");
    $bookingsHasColumn = $stmt->rowCount() > 0;
    
    $stmt = $conn->query("SHOW COLUMNS FROM reports LIKE 'extension_stack'");
    $reportsHasColumn = $stmt->rowCount() > 0;
    
    if ($bookingsHasColumn && $reportsHasColumn) {
        echo "<p class='success'>✅ extension_stack column exists in both bookings and reports tables</p>";
    } else {
        echo "<p class='error'>❌ extension_stack column missing:</p>";
        echo "<ul>";
        if (!$bookingsHasColumn) echo "<li>Missing in bookings table</li>";
        if (!$reportsHasColumn) echo "<li>Missing in reports table</li>";
        echo "</ul>";
        echo "<p><strong>Run add_extension_stack_column.php to create the columns</strong></p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 2: Test extension stack functions
echo "<div class='test-section info'>";
echo "<h2>Test 2: Extension Stack Functions</h2>";

// Test encoding/decoding
$testStack = [
    ['h' => 12, 'm' => 0, 'price' => 960.00, 'reg' => 800.00, 'bun' => 160.00, 'bf' => '2 Rice (Promo)'],
    ['h' => 24, 'm' => 0, 'price' => 1920.00, 'reg' => 1600.00, 'bun' => 320.00, 'bf' => null]
];

$encoded = booking_extension_stack_encode($testStack);
$decoded = booking_extension_stack_decode($encoded);
$aggregated = booking_extension_stack_aggregate_segments($testStack);

echo "<h3>Encoding Test:</h3>";
echo "<pre>" . htmlspecialchars($encoded) . "</pre>";

echo "<h3>Decoding Test:</h3>";
echo "<pre>" . print_r($decoded, true) . "</pre>";

echo "<h3>Aggregation Test:</h3>";
echo "<pre>" . print_r($aggregated, true) . "</pre>";

if ($decoded === $testStack) {
    echo "<p class='success'>✅ Encoding/Decoding works correctly</p>";
} else {
    echo "<p class='error'>❌ Encoding/Decoding failed</p>";
}

if ($aggregated['h'] === 36 && $aggregated['price'] === 2880.00) {
    echo "<p class='success'>✅ Aggregation works correctly</p>";
} else {
    echo "<p class='error'>❌ Aggregation failed</p>";
}
echo "</div>";

// Test 3: Find bookings with extensions
echo "<div class='test-section info'>";
echo "<h2>Test 3: Current Extension Data</h2>";

try {
    $stmt = $conn->query("
        SELECT id, booking_id, guest_name, room_id, 
               extend_hours, extend_minutes, extend_price, 
               extension_stack, extension_time_at
        FROM bookings 
        WHERE (extend_hours > 0 OR extend_minutes > 0 OR extend_price > 0)
        ORDER BY id DESC 
        LIMIT 10
    ");
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        echo "<p class='success'>Found " . count($bookings) . " bookings with extensions:</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Booking ID</th><th>Guest</th><th>Room</th><th>Extension</th><th>Price</th><th>Stack Status</th></tr>";
        
        foreach ($bookings as $booking) {
            $hasStack = !empty($booking['extension_stack']);
            $stackStatus = $hasStack ? "✅ Has Stack" : "❌ No Stack";
            
            echo "<tr>";
            echo "<td>{$booking['id']}</td>";
            echo "<td>{$booking['booking_id']}</td>";
            echo "<td>{$booking['guest_name']}</td>";
            echo "<td>{$booking['room_id']}</td>";
            echo "<td>{$booking['extend_hours']}h {$booking['extend_minutes']}m</td>";
            echo "<td>₱" . number_format($booking['extend_price'], 2) . "</td>";
            echo "<td>{$stackStatus}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No bookings with extensions found in the database</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 4: Test ExtensionStackManager
echo "<div class='test-section info'>";
echo "<h2>Test 4: Extension Stack Manager</h2>";

// Get statistics
$statsResult = $manager->getExtensionStatistics();
if ($statsResult['success']) {
    $stats = $statsResult['statistics'];
    echo "<h3>Extension Statistics:</h3>";
    echo "<ul>";
    echo "<li><strong>Total bookings with extensions:</strong> {$stats['total_bookings_with_extensions']}</li>";
    echo "<li><strong>Total extension revenue:</strong> ₱" . number_format($stats['total_extension_revenue'], 2) . "</li>";
    echo "<li><strong>Total extension hours:</strong> {$stats['total_extension_hours']}</li>";
    echo "<li><strong>Average extensions per booking:</strong> {$stats['average_extensions_per_booking']}</li>";
    echo "</ul>";
    
    if (count($stats['bookings']) > 0) {
        echo "<h3>Recent Extension Bookings:</h3>";
        echo "<table>";
        echo "<tr><th>Booking ID</th><th>Guest</th><th>Room</th><th>Segments</th><th>Hours</th><th>Revenue</th></tr>";
        
        foreach (array_slice($stats['bookings'], 0, 5) as $booking) {
            echo "<tr>";
            echo "<td>{$booking['booking_id']}</td>";
            echo "<td>{$booking['guest_name']}</td>";
            echo "<td>{$booking['room_id']}</td>";
            echo "<td>{$booking['extension_segments']}</td>";
            echo "<td>{$booking['total_extension_hours']}</td>";
            echo "<td>₱" . number_format($booking['total_extension_price'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<p class='success'>✅ Extension Stack Manager working correctly</p>";
} else {
    echo "<p class='error'>❌ Extension Stack Manager error: " . htmlspecialchars($statsResult['message']) . "</p>";
}
echo "</div>";

// Test 5: Test specific booking if ID provided
if (isset($_GET['booking_id'])) {
    $bookingId = intval($_GET['booking_id']);
    
    echo "<div class='test-section info'>";
    echo "<h2>Test 5: Specific Booking Analysis (ID: {$bookingId})</h2>";
    
    $stackResult = $manager->getExtensionStack($bookingId);
    if ($stackResult['success']) {
        echo "<h3>Extension Stack:</h3>";
        echo "<pre>" . print_r($stackResult['stack'], true) . "</pre>";
        
        echo "<h3>Aggregated Data:</h3>";
        echo "<pre>" . print_r($stackResult['aggregated'], true) . "</pre>";
        
        $historyResult = $manager->getExtensionHistory($bookingId);
        if ($historyResult['success']) {
            echo "<h3>Extension History:</h3>";
            echo "<table>";
            echo "<tr><th>Segment</th><th>Duration</th><th>Price</th><th>Breakfast</th><th>Timestamp</th></tr>";
            
            foreach ($historyResult['history'] as $segment) {
                echo "<tr>";
                echo "<td>{$segment['segment_number']}</td>";
                echo "<td>{$segment['formatted_duration']}</td>";
                echo "<td>{$segment['formatted_price']}</td>";
                echo "<td>" . ($segment['breakfast'] ?: 'None') . "</td>";
                echo "<td>{$segment['timestamp']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<p class='success'>✅ Booking analysis completed</p>";
    } else {
        echo "<p class='error'>❌ Error analyzing booking: " . htmlspecialchars($stackResult['message']) . "</p>";
    }
    echo "</div>";
}

// Test 6: API endpoints test
echo "<div class='test-section info'>";
echo "<h2>Test 6: API Endpoints</h2>";
echo "<p>Test the following API endpoints:</p>";
echo "<ul>";
echo "<li><a href='extension_stack_manager.php?action=get_statistics' target='_blank'>Get Statistics (JSON)</a></li>";
echo "<li><a href='extension_stack_report.php?action=json_report' target='_blank'>JSON Report</a></li>";
echo "<li><a href='extension_stack_report.php?action=html_report' target='_blank'>HTML Report</a></li>";
echo "<li><a href='extension_stack_report.php?action=csv_export' target='_blank'>CSV Export</a></li>";
echo "</ul>";

if (isset($_GET['booking_id'])) {
    $bookingId = intval($_GET['booking_id']);
    echo "<p>Booking-specific endpoints:</p>";
    echo "<ul>";
    echo "<li><a href='extension_stack_manager.php?action=get_stack&booking_id={$bookingId}' target='_blank'>Get Stack for Booking {$bookingId}</a></li>";
    echo "<li><a href='extension_stack_manager.php?action=get_history&booking_id={$bookingId}' target='_blank'>Get History for Booking {$bookingId}</a></li>";
    echo "</ul>";
}
echo "</div>";

// Instructions
echo "<div class='test-section info'>";
echo "<h2>Usage Instructions</h2>";
echo "<p>To test with a specific booking, add <code>?booking_id=123</code> to the URL (replace 123 with actual booking ID).</p>";
echo "<p>Example: <code>test_extension_stack.php?booking_id=123</code></p>";

echo "<h3>Files Created:</h3>";
echo "<ul>";
echo "<li><strong>add_extension_stack_column.php</strong> - Database migration script</li>";
echo "<li><strong>extension_stack.sql</strong> - SQL schema and queries</li>";
echo "<li><strong>extension_stack_manager.php</strong> - Core management class and API</li>";
echo "<li><strong>extension_stack_report.php</strong> - Report generator</li>";
echo "<li><strong>test_extension_stack.php</strong> - This test file</li>";
echo "</ul>";

echo "<h3>Integration with Existing System:</h3>";
echo "<p>The extension stack functionality integrates with your existing extension system:</p>";
echo "<ul>";
echo "<li>Uses existing <code>save_extend_duration.php</code> and <code>withdraw_extend_duration.php</code></li>";
echo "<li>Leverages existing helper functions in <code>report_helpers.php</code></li>";
echo "<li>Compatible with current database schema</li>";
echo "<li>Maintains backward compatibility</li>";
echo "</ul>";
echo "</div>";

echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>