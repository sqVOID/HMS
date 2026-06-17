<?php
/**
 * Test Extension History Feature
 * 
 * This page demonstrates how extension_time_at now stores multiple extension records
 */

require_once 'config.php';
require_once 'extension_history_helpers.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extension History Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #FF9800;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #FF9800;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .extension-history {
            background: #fff3e0;
            padding: 8px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.6;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-warning {
            background: #FF9800;
            color: white;
        }
        .badge-info {
            background: #2196F3;
            color: white;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-box {
            background: #fff3e0;
            border-left: 4px solid #FF9800;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d32f2f;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: #fff3e0;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #FF9800;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #FF9800;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⏰ Extension History Feature - Test Page</h1>
        
        <div class="success-box">
            <strong>✓ Feature Active:</strong> The extension_time_at column now supports multiple extension records!<br>
            Each extension is recorded with a timestamp and stored in format: <code>2026-01-18 10:10:00|2026-01-18 23:04:00</code>
        </div>

        <?php
        try {
            // Get extension statistics
            $statsStmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_bookings,
                    COUNT(CASE WHEN extension_time_at IS NOT NULL THEN 1 END) as bookings_with_extensions,
                    COUNT(CASE WHEN extension_time_at LIKE '%|%' THEN 1 END) as bookings_with_multiple_extensions
                FROM bookings
            ");
            $statsStmt->execute();
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            echo '<div class="stats-grid">';
            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . $stats['total_bookings'] . '</div>';
            echo '<div class="stat-label">Total Bookings</div>';
            echo '</div>';
            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . $stats['bookings_with_extensions'] . '</div>';
            echo '<div class="stat-label">With Extensions</div>';
            echo '</div>';
            echo '<div class="stat-card">';
            echo '<div class="stat-number">' . $stats['bookings_with_multiple_extensions'] . '</div>';
            echo '<div class="stat-label">Multiple Extensions</div>';
            echo '</div>';
            echo '</div>';
            
        } catch (PDOException $e) {
            // Stats not critical, continue
        }
        ?>

        <h2>📊 Recent Bookings with Extension History</h2>
        
        <?php
        try {
            // Fetch recent bookings with extension history
            $stmt = $conn->prepare("
                SELECT 
                    booking_id,
                    guest_name,
                    room_id,
                    extend_hours,
                    extend_minutes,
                    extend_price,
                    extension_time_at,
                    check_in,
                    check_out,
                    total_amount,
                    created_at
                FROM bookings
                WHERE extension_time_at IS NOT NULL
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($bookings) > 0) {
                echo '<table>';
                echo '<thead>';
                echo '<tr>';
                echo '<th>Booking ID</th>';
                echo '<th>Guest Name</th>';
                echo '<th>Room</th>';
                echo '<th>Extension Count</th>';
                echo '<th>Total Extended</th>';
                echo '<th>Extension History</th>';
                echo '<th>Extension Cost</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($bookings as $booking) {
                    $extensionCount = getExtensionCount($booking['extension_time_at']);
                    $extensionHistory = formatExtensionHistory($booking['extension_time_at']);
                    
                    // Format total extended time
                    $totalExtended = '';
                    if ($booking['extend_hours'] > 0) $totalExtended .= $booking['extend_hours'] . 'h ';
                    if ($booking['extend_minutes'] > 0) $totalExtended .= $booking['extend_minutes'] . 'm';
                    $totalExtended = trim($totalExtended) ?: '-';
                    
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($booking['booking_id']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($booking['guest_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['room_id']) . '</td>';
                    echo '<td><span class="badge badge-warning">' . $extensionCount . ' extension(s)</span></td>';
                    echo '<td>' . $totalExtended . '</td>';
                    echo '<td><div class="extension-history">' . $extensionHistory . '</div></td>';
                    echo '<td>₱' . number_format($booking['extend_price'], 2) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<div class="info-box">';
                echo '<strong>ℹ️ No extension records found yet.</strong><br>';
                echo 'Extend a booking to see the extension history feature in action.';
                echo '</div>';
            }
            
        } catch (PDOException $e) {
            echo '<div style="background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 20px 0; border-radius: 4px;">';
            echo '<strong>✗ Error:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>

        <h2>🔧 How It Works</h2>
        <div class="info-box">
            <strong>Extension Recording Logic:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>First Extension:</strong> When you extend a booking for the first time, the current timestamp is recorded</li>
                <li><strong>Additional Extensions:</strong> When you extend the same booking again, a new timestamp is appended</li>
                <li><strong>Multiple Extensions:</strong> All extension timestamps are stored separated by "|" character</li>
                <li><strong>submitExtendDuration():</strong> Every time this function is called and Submit button is clicked, a new timestamp is recorded</li>
            </ul>
        </div>

        <h2>📝 Example Extension History</h2>
        <div class="info-box">
            <strong>Database Value:</strong><br>
            <code>2026-01-18 10:10:00|2026-01-18 23:04:00|2026-01-19 14:30:00</code>
            <br><br>
            <strong>Displayed As:</strong><br>
            <div class="extension-history">
                1. 01/18/2026 10:10 AM<br>
                2. 01/18/2026 11:04 PM<br>
                3. 01/19/2026 02:30 PM
            </div>
        </div>

        <h2>🎯 Helper Functions Available</h2>
        <div class="info-box">
            The <code>extension_history_helpers.php</code> file provides these functions:
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><code>parseExtensionHistory($extension_time_at)</code> - Parse into array of DateTime objects</li>
                <li><code>formatExtensionHistory($extension_time_at)</code> - Format for display</li>
                <li><code>getExtensionCount($extension_time_at)</code> - Get number of extensions</li>
                <li><code>getFirstExtensionDate($extension_time_at)</code> - Get first extension date</li>
                <li><code>getLastExtensionDate($extension_time_at)</code> - Get most recent extension</li>
                <li><code>appendExtensionTimestamp($existing, $new)</code> - Add new extension timestamp</li>
                <li><code>formatExtensionHistoryForExport($extension_time_at)</code> - Format for reports/export</li>
                <li><code>getExtensionHistoryJSON($extension_time_at)</code> - Get as JSON array</li>
                <li><code>formatExtensionHistoryWithDetails($extension_time_at, $details)</code> - Format with duration/price details</li>
                <li><code>hasRecentExtension($extension_time_at, $period)</code> - Check if extended recently</li>
            </ul>
        </div>

        <h2>🚀 Integration Example</h2>
        <div class="info-box">
            <strong>Display extension history in your booking system:</strong>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;"><code>&lt;?php
require_once 'extension_history_helpers.php';

// Get booking data
$booking = getBookingById($id);

// Display extension count
$extensionCount = getExtensionCount($booking['extension_time_at']);
echo "Extensions: $extensionCount";

// Display extension history
echo formatExtensionHistory($booking['extension_time_at']);

// Check if recently extended
if (hasRecentExtension($booking['extension_time_at'], '1 hour')) {
    echo "Recently extended!";
}
?&gt;</code></pre>
        </div>
    </div>
</body>
</html>