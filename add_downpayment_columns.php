<?php
/**
 * Add Downpayment Columns to Bookings Table
 * This script adds columns to store downpayment information
 * Run this once to update the database
 */

require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Check if bookings table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if ($checkTable->rowCount() > 0) {
        $messages[] = "✓ Bookings table found";
        
        // Get existing columns
        $columnsQuery = $conn->query("SHOW COLUMNS FROM bookings");
        $existingColumns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
        
        $columnsToAdd = [
            'downpayment_amount' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total downpayment amount'",
            'downpayment_cash' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cash payment amount'",
            'downpayment_gcash' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'G-Cash payment amount'",
            'downpayment_maya' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Maya payment amount'",
            'downpayment_gcash_ref' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'G-Cash reference number'",
            'downpayment_maya_ref' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Maya reference number'",
            'downpayment_status' => "VARCHAR(50) DEFAULT 'None' COMMENT 'Downpayment status: None, Partial, Paid'",
            'downpayment_date' => "DATETIME NULL DEFAULT NULL COMMENT 'Date when downpayment was received'"
        ];
        
        $addedCount = 0;
        $skippedCount = 0;
        
        foreach ($columnsToAdd as $columnName => $columnDefinition) {
            if (!in_array($columnName, $existingColumns)) {
                try {
                    $sql = "ALTER TABLE bookings ADD COLUMN $columnName $columnDefinition";
                    $conn->exec($sql);
                    $messages[] = "✓ Added column: <strong>$columnName</strong>";
                    $addedCount++;
                } catch (PDOException $e) {
                    $errors[] = "✗ Failed to add column $columnName: " . $e->getMessage();
                }
            } else {
                $messages[] = "ℹ Column already exists: $columnName";
                $skippedCount++;
            }
        }
        
        if ($addedCount > 0) {
            $messages[] = "<br><strong>✅ Successfully added $addedCount new column(s)!</strong>";
        }
        
        if ($skippedCount > 0) {
            $messages[] = "ℹ Skipped $skippedCount existing column(s)";
        }
        
        // Show current table structure
        $messages[] = "<br><strong>Current Downpayment Columns:</strong>";
        $downpaymentColumns = $conn->query("SHOW COLUMNS FROM bookings WHERE Field LIKE 'downpayment%'");
        $dpCols = $downpaymentColumns->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($dpCols) > 0) {
            foreach ($dpCols as $col) {
                $messages[] = "  • <code>" . $col['Field'] . "</code> - " . $col['Type'];
            }
        } else {
            $messages[] = "  No downpayment columns found";
        }
        
        // Test data insertion
        $messages[] = "<br><strong>Testing Downpayment Data:</strong>";
        try {
            // Check if we can insert/update downpayment data
            $testQuery = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE downpayment_amount > 0");
            $result = $testQuery->fetch(PDO::FETCH_ASSOC);
            $messages[] = "✓ Current bookings with downpayment: " . $result['count'];
        } catch (PDOException $e) {
            $errors[] = "✗ Error checking downpayment data: " . $e->getMessage();
        }
        
    } else {
        $errors[] = "✗ Bookings table does not exist!";
    }
    
} catch (PDOException $e) {
    $errors[] = "✗ Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Downpayment Columns</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 900px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .message {
            padding: 10px 15px;
            margin-bottom: 6px;
            border-radius: 6px;
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
            font-size: 14px;
            line-height: 1.6;
        }
        .error {
            padding: 10px 15px;
            margin-bottom: 6px;
            border-radius: 6px;
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
            font-size: 14px;
            line-height: 1.6;
        }
        .info {
            margin-top: 30px;
            padding: 20px;
            background: #d1fae5;
            border-radius: 6px;
            border-left: 4px solid #10b981;
        }
        .info h3 {
            color: #065f46;
            margin-bottom: 10px;
        }
        .info p {
            color: #555;
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .info ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        .info li {
            color: #555;
            margin-bottom: 5px;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            margin-right: 10px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
        }
        .button.secondary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        strong {
            font-weight: 600;
        }
        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 Add Downpayment Columns</h1>
        <p class="subtitle">Database update to store downpayment information</p>
        
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message"><?php echo $msg; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="error"><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($errors)): ?>
            <div class="info">
                <h3>✅ Database Updated Successfully!</h3>
                <p><strong>New Columns Added:</strong></p>
                <table>
                    <thead>
                        <tr>
                            <th>Column Name</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>downpayment_amount</code></td>
                            <td>Total downpayment amount</td>
                        </tr>
                        <tr>
                            <td><code>downpayment_cash</code></td>
                            <td>Cash payment amount</td>
                        </tr>
                        <tr>
                            <td><code>downpayment_gcash</code></td>
                            <td>G-Cash payment amount</td>
                        </tr>
                        <tr>
                            <td><code>downpayment_maya</code></td>
                            <td>Maya payment amount</td>
                        </tr>
                        <tr>
                            <td><code>downpayment_gcash_ref</code></td>
                            <td>G-Cash reference number</td>
                        </tr>
                        <tr>
                            <td><code>downpayment_maya_ref</code></td>
                            <td>Maya reference number</td>
                        </tr>
                        <tr>
                            <td><code>downpayment_status</code></td>
                            <td>Status: None, Partial, Paid</td>
                        </tr>
                        <tr>
                            <td><code>downpayment_date</code></td>
                            <td>Date when downpayment was received</td>
                        </tr>
                    </tbody>
                </table>
                
                <p style="margin-top: 20px;"><strong>How it works:</strong></p>
                <ul>
                    <li>When customer makes a reservation with downpayment</li>
                    <li>System stores payment method(s): Cash, G-Cash, Maya</li>
                    <li>Can accept multiple payment methods (e.g., Cash + G-Cash)</li>
                    <li>Reference numbers stored for G-Cash and Maya payments</li>
                    <li>Total downpayment amount calculated automatically</li>
                </ul>
                
                <p style="margin-top: 15px;"><strong>Next Steps:</strong></p>
                <ul>
                    <li>The frontend (Booking.html) already collects this data</li>
                    <li>Backend (confirm_booking.php) needs to be updated to save it</li>
                    <li>Data will be stored when customer confirms downpayment</li>
                </ul>
            </div>
            
            <a href="Booking.html" class="button">Go to Booking Page</a>
            <a href="get_bookings.php" class="button secondary">View Bookings Data</a>
        <?php else: ?>
            <div class="info" style="background: #fee2e2; border-left-color: #dc2626;">
                <h3 style="color: #991b1b;">⚠️ Action Required</h3>
                <p>Please check the errors above and ensure:</p>
                <ul>
                    <li>MySQL/MariaDB is running in XAMPP</li>
                    <li>The bookings table exists in your database</li>
                    <li>You have proper database permissions</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
