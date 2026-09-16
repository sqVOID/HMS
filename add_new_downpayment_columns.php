<?php
/**
 * Add New Downpayment Columns (InstaPay, Online Banking, Airbnb)
 * This script adds new payment method columns to bookings and reports tables
 * Run this once to update the database
 */

require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Define new columns to add
    $newColumns = [
        'downpayment_instapay' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'InstaPay payment amount'",
        'downpayment_online_banking' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Online Banking payment amount'",
        'downpayment_airbnb' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Airbnb payment amount'",
        'downpayment_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'InstaPay reference number'",
        'downpayment_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Online Banking reference number'",
        'downpayment_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Airbnb reference number'"
    ];
    
    $tables = ['bookings', 'reports'];
    
    foreach ($tables as $tableName) {
        // Check if table exists
        $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
        
        if ($checkTable->rowCount() > 0) {
            $messages[] = "✓ Table <strong>$tableName</strong> found";
            
            // Get existing columns
            $columnsQuery = $conn->query("SHOW COLUMNS FROM $tableName");
            $existingColumns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
            
            $addedCount = 0;
            $skippedCount = 0;
            
            foreach ($newColumns as $columnName => $columnDefinition) {
                if (!in_array($columnName, $existingColumns)) {
                    try {
                        $sql = "ALTER TABLE $tableName ADD COLUMN $columnName $columnDefinition";
                        $conn->exec($sql);
                        $messages[] = "✓ Added to <strong>$tableName</strong>: <code>$columnName</code>";
                        $addedCount++;
                    } catch (PDOException $e) {
                        $errors[] = "✗ Failed to add $columnName to $tableName: " . $e->getMessage();
                    }
                } else {
                    $messages[] = "ℹ Column already exists in $tableName: <code>$columnName</code>";
                    $skippedCount++;
                }
            }
            
            if ($addedCount > 0) {
                $messages[] = "<strong>✅ Successfully added $addedCount new column(s) to $tableName!</strong>";
            }
            
            if ($skippedCount > 0) {
                $messages[] = "ℹ Skipped $skippedCount existing column(s) in $tableName";
            }
            
            $messages[] = "<br>";
            
        } else {
            $errors[] = "✗ Table <strong>$tableName</strong> does not exist!";
        }
    }
    
    // Show all downpayment columns for verification
    if (empty($errors)) {
        $messages[] = "<br><strong>📊 All Downpayment Columns in BOOKINGS Table:</strong>";
        $bookingsColumns = $conn->query("SHOW COLUMNS FROM bookings WHERE Field LIKE 'downpayment%'");
        $bookingsCols = $bookingsColumns->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($bookingsCols) > 0) {
            foreach ($bookingsCols as $col) {
                $messages[] = "  • <code>" . $col['Field'] . "</code> - " . $col['Type'];
            }
        }
        
        $messages[] = "<br><strong>📊 All Downpayment Columns in REPORTS Table:</strong>";
        $reportsColumns = $conn->query("SHOW COLUMNS FROM reports WHERE Field LIKE 'downpayment%'");
        $reportsCols = $reportsColumns->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($reportsCols) > 0) {
            foreach ($reportsCols as $col) {
                $messages[] = "  • <code>" . $col['Field'] . "</code> - " . $col['Type'];
            }
        }
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
    <title>Add New Downpayment Columns</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            max-width: 1000px;
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
            background: #e0e7ff;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }
        .info h3 {
            color: #4338ca;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .payment-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        .payment-card h4 {
            color: #667eea;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .payment-card p {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .new-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💳 Add New Downpayment Columns</h1>
        <p class="subtitle">Adding InstaPay, Online Banking, and Airbnb payment methods</p>
        
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
                <p><strong>New Payment Methods Added:</strong></p>
                
                <div class="payment-grid">
                    <div class="payment-card">
                        <h4>💰 InstaPay <span class="new-badge">NEW</span></h4>
                        <p><code>downpayment_instapay</code> - Amount</p>
                        <p><code>downpayment_instapay_ref</code> - Reference</p>
                    </div>
                    
                    <div class="payment-card">
                        <h4>🏦 Online Banking <span class="new-badge">NEW</span></h4>
                        <p><code>downpayment_online_banking</code> - Amount</p>
                        <p><code>downpayment_online_banking_ref</code> - Reference</p>
                    </div>
                    
                    <div class="payment-card">
                        <h4>🏠 Airbnb <span class="new-badge">NEW</span></h4>
                        <p><code>downpayment_airbnb</code> - Amount</p>
                        <p><code>downpayment_airbnb_ref</code> - Reference</p>
                    </div>
                </div>
                
                <p style="margin-top: 25px;"><strong>📋 Complete Payment Methods List:</strong></p>
                <table>
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Amount Column</th>
                            <th>Reference Column</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>💵 Cash</td>
                            <td><code>downpayment_cash</code></td>
                            <td>-</td>
                            <td>Existing</td>
                        </tr>
                        <tr>
                            <td>📱 GCash</td>
                            <td><code>downpayment_gcash</code></td>
                            <td><code>downpayment_gcash_ref</code></td>
                            <td>Existing</td>
                        </tr>
                        <tr>
                            <td>💳 Maya</td>
                            <td><code>downpayment_maya</code></td>
                            <td><code>downpayment_maya_ref</code></td>
                            <td>Existing</td>
                        </tr>
                        <tr style="background: #d1fae5;">
                            <td>💰 InstaPay</td>
                            <td><code>downpayment_instapay</code></td>
                            <td><code>downpayment_instapay_ref</code></td>
                            <td><strong>NEW</strong></td>
                        </tr>
                        <tr style="background: #d1fae5;">
                            <td>🏦 Online Banking</td>
                            <td><code>downpayment_online_banking</code></td>
                            <td><code>downpayment_online_banking_ref</code></td>
                            <td><strong>NEW</strong></td>
                        </tr>
                        <tr style="background: #d1fae5;">
                            <td>🏠 Airbnb</td>
                            <td><code>downpayment_airbnb</code></td>
                            <td><code>downpayment_airbnb_ref</code></td>
                            <td><strong>NEW</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <p style="margin-top: 20px;"><strong>📍 Tables Updated:</strong></p>
                <ul>
                    <li>✅ <strong>bookings</strong> table - All 6 new columns added</li>
                    <li>✅ <strong>reports</strong> table - All 6 new columns added</li>
                </ul>
                
                <p style="margin-top: 20px;"><strong>🔧 Next Steps:</strong></p>
                <ul>
                    <li>Update frontend forms to include new payment options</li>
                    <li>Update backend scripts to handle new payment methods</li>
                    <li>Update reports to display new payment columns</li>
                    <li>Test booking flow with new payment methods</li>
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
                    <li>The bookings and reports tables exist in your database</li>
                    <li>You have proper database permissions</li>
                    <li>config.php is properly configured</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
