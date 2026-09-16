<?php
/**
 * Test Payment History Feature
 * 
 * This page demonstrates how payment_date_time now stores multiple payment records
 */

require_once 'config.php';
require_once 'payment_history_helpers.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History Test</title>
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
            border-bottom: 3px solid #4CAF50;
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
            background-color: #4CAF50;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .payment-history {
            background: #e8f5e9;
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
        .badge-success {
            background: #4CAF50;
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
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>💳 Payment History Feature - Test Page</h1>
        
        <div class="success-box">
            <strong>✓ Feature Active:</strong> The payment_date_time column now supports multiple payment records!<br>
            Each payment is recorded with a timestamp and stored in format: <code>2026-01-18 10:10:00|2026-01-18 23:04:00</code>
        </div>

        <h2>📊 Recent Bookings with Payment History</h2>
        
        <?php
        try {
            // Fetch recent bookings with payment history
            $stmt = $conn->prepare("
                SELECT 
                    booking_id,
                    guest_name,
                    room_id,
                    paid_status,
                    payment_date_time,
                    deposit,
                    total_amount,
                    created_at
                FROM bookings
                WHERE payment_date_time IS NOT NULL
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
                echo '<th>Status</th>';
                echo '<th>Payment Count</th>';
                echo '<th>Payment History</th>';
                echo '<th>Total Amount</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($bookings as $booking) {
                    $paymentCount = getPaymentCount($booking['payment_date_time']);
                    $paymentHistory = formatPaymentHistory($booking['payment_date_time']);
                    
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($booking['booking_id']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($booking['guest_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['room_id']) . '</td>';
                    echo '<td><span class="badge badge-success">' . htmlspecialchars($booking['paid_status']) . '</span></td>';
                    echo '<td><span class="badge badge-info">' . $paymentCount . ' payment(s)</span></td>';
                    echo '<td><div class="payment-history">' . $paymentHistory . '</div></td>';
                    echo '<td>₱' . number_format($booking['total_amount'], 2) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<div class="info-box">';
                echo '<strong>ℹ️ No payment records found yet.</strong><br>';
                echo 'Make a booking with payment to see the payment history feature in action.';
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
            <strong>Payment Recording Logic:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>New Booking:</strong> When you confirm a booking with payment, the current timestamp is recorded</li>
                <li><strong>Additional Payment:</strong> When you update a booking and add more payment, a new timestamp is appended</li>
                <li><strong>Multiple Payments:</strong> All payment timestamps are stored separated by "|" character</li>
                <li><strong>Display:</strong> The system shows each payment with its date and time</li>
            </ul>
        </div>

        <h2>📝 Example Payment History</h2>
        <div class="info-box">
            <strong>Database Value:</strong><br>
            <code>2026-01-18 10:10:00|2026-01-18 23:04:00|2026-01-19 14:30:00</code>
            <br><br>
            <strong>Displayed As:</strong><br>
            <div class="payment-history">
                1. 01/18/2026 10:10 AM<br>
                2. 01/18/2026 11:04 PM<br>
                3. 01/19/2026 02:30 PM
            </div>
        </div>

        <h2>🎯 Helper Functions Available</h2>
        <div class="info-box">
            The <code>payment_history_helpers.php</code> file provides these functions:
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><code>parsePaymentHistory($payment_date_time)</code> - Parse into array of DateTime objects</li>
                <li><code>formatPaymentHistory($payment_date_time)</code> - Format for display</li>
                <li><code>getPaymentCount($payment_date_time)</code> - Get number of payments</li>
                <li><code>getFirstPaymentDate($payment_date_time)</code> - Get first payment date</li>
                <li><code>getLastPaymentDate($payment_date_time)</code> - Get most recent payment</li>
                <li><code>appendPaymentTimestamp($existing, $new)</code> - Add new payment timestamp</li>
                <li><code>formatPaymentHistoryForExport($payment_date_time)</code> - Format for reports/export</li>
                <li><code>getPaymentHistoryJSON($payment_date_time)</code> - Get as JSON array</li>
            </ul>
        </div>
    </div>
</body>
</html>
