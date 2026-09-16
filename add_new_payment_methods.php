<?php
/**
 * Migration Script: Add New Payment Methods
 * 
 * This script adds support for Instapay, Online Banking, and Airbnb payment methods
 * to both bookings and reports tables.
 * 
 * Run this script once by accessing it in your browser:
 * http://localhost/HMS/add_new_payment_methods.php
 */

require_once 'config.php';

// Set execution time limit for large databases
set_time_limit(300);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Payment Methods - Migration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
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
            border-bottom: 3px solid #10b981;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        .status-error {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-exists {
            background: #e0e7ff;
            color: #3730a3;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Add New Payment Methods Migration</h1>
        <div class="info">
            <strong>Migration Purpose:</strong> This script adds booking confirmation tracking, downpayment refund tracking, payment history tracking, and new payment methods (<strong>Instapay</strong>, <strong>Online Banking</strong>, and <strong>Airbnb</strong>) to your Hotel Management System.
        </div>

<?php

$results = [
    'bookings' => [],
    'reports' => []
];

$errors = [];

// Define columns to add
$columns = [
    // Booking confirmation timestamp
    'confirmed_at' => [
        'type' => 'DATETIME NULL DEFAULT NULL',
        'after' => 'created_at',
        'description' => 'Timestamp when booking was confirmed'
    ],
    
    // Downpayment refund tracking
    'downpayment_nonrefundable' => [
        'type' => 'DECIMAL(10,2) DEFAULT 0',
        'after' => 'downpayment',
        'description' => 'Non-refundable portion of downpayment'
    ],
    'downpayment_refund_status' => [
        'type' => 'VARCHAR(50) NULL DEFAULT NULL',
        'after' => 'downpayment_nonrefundable',
        'description' => 'Refund status: pending, approved, refunded, rejected'
    ],
    
    // Base payment history columns (if they don't exist)
    'payment_amount_cash_history' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_date_time',
        'description' => 'Cash payment history'
    ],
    'payment_amount_g_cash_history' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_amount_cash_history',
        'description' => 'GCash payment history'
    ],
    'payment_amount_maya_history' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_amount_g_cash_history',
        'description' => 'Maya payment history'
    ],
    
    // Payment status columns
    'payment_status_instapay' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_status_maya',
        'description' => 'Instapay payment details'
    ],
    'payment_status_online_banking' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_status_instapay',
        'description' => 'Online Banking payment details'
    ],
    'payment_status_airbnb' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_status_online_banking',
        'description' => 'Airbnb payment details'
    ],
    
    // Deposit breakdown columns
    'deposit_instapay' => [
        'type' => 'DECIMAL(10,2) DEFAULT 0',
        'after' => 'deposit_maya',
        'description' => 'Instapay deposit amount'
    ],
    'deposit_online_banking' => [
        'type' => 'DECIMAL(10,2) DEFAULT 0',
        'after' => 'deposit_instapay',
        'description' => 'Online Banking deposit amount'
    ],
    'deposit_airbnb' => [
        'type' => 'DECIMAL(10,2) DEFAULT 0',
        'after' => 'deposit_online_banking',
        'description' => 'Airbnb deposit amount'
    ],
    
    // Reference number columns
    'deposit_instapay_ref' => [
        'type' => 'VARCHAR(255) NULL DEFAULT NULL',
        'after' => 'deposit_maya_ref',
        'description' => 'Instapay reference number'
    ],
    'deposit_online_banking_ref' => [
        'type' => 'VARCHAR(255) NULL DEFAULT NULL',
        'after' => 'deposit_instapay_ref',
        'description' => 'Online Banking reference number'
    ],
    'deposit_airbnb_ref' => [
        'type' => 'VARCHAR(255) NULL DEFAULT NULL',
        'after' => 'deposit_online_banking_ref',
        'description' => 'Airbnb reference number'
    ],
    
    // Payment history columns
    'payment_amount_instapay_history' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_amount_maya_history',
        'description' => 'Instapay payment history'
    ],
    'payment_amount_online_banking_history' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_amount_instapay_history',
        'description' => 'Online Banking payment history'
    ],
    'payment_amount_airbnb_history' => [
        'type' => 'TEXT NULL DEFAULT NULL',
        'after' => 'payment_amount_online_banking_history',
        'description' => 'Airbnb payment history'
    ],
    
    // Reference number tracking columns
    'reference_no_instapay' => [
        'type' => 'VARCHAR(255) NULL DEFAULT NULL',
        'after' => 'reference_no_maya',
        'description' => 'Instapay transaction reference'
    ],
    'reference_no_online_banking' => [
        'type' => 'VARCHAR(255) NULL DEFAULT NULL',
        'after' => 'reference_no_instapay',
        'description' => 'Online Banking transaction reference'
    ],
    'reference_no_airbnb' => [
        'type' => 'VARCHAR(255) NULL DEFAULT NULL',
        'after' => 'reference_no_online_banking',
        'description' => 'Airbnb transaction reference'
    ]
];

// Process both tables
foreach (['bookings', 'reports'] as $table) {
    echo "<h2>📋 Processing Table: <code>{$table}</code></h2>";
    echo "<table>";
    echo "<thead><tr><th>Column Name</th><th>Description</th><th>Status</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($columns as $columnName => $columnInfo) {
        $status = '';
        $statusClass = '';
        
        try {
            // Check if column exists
            $checkStmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$columnName}'");
            
            if ($checkStmt->rowCount() > 0) {
                $status = 'Already Exists';
                $statusClass = 'status-exists';
                $results[$table][$columnName] = 'exists';
            } else {
                // Check if the AFTER column exists before using it
                $afterClause = "";
                if (isset($columnInfo['after'])) {
                    $afterCheckStmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$columnInfo['after']}'");
                    if ($afterCheckStmt->rowCount() > 0) {
                        $afterClause = " AFTER `{$columnInfo['after']}`";
                    }
                }
                
                // Add the column
                $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$columnName}` {$columnInfo['type']}{$afterClause}";
                
                $conn->exec($sql);
                
                $status = 'Added Successfully';
                $statusClass = 'status-success';
                $results[$table][$columnName] = 'added';
            }
        } catch (PDOException $e) {
            $status = 'Error: ' . $e->getMessage();
            $statusClass = 'status-error';
            $results[$table][$columnName] = 'error';
            $errors[] = "Table: {$table}, Column: {$columnName}, Error: " . $e->getMessage();
        }
        
        echo "<tr>";
        echo "<td><code>{$columnName}</code></td>";
        echo "<td>{$columnInfo['description']}</td>";
        echo "<td><span class='status-badge {$statusClass}'>{$status}</span></td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
}

// Summary
echo "<h2>📊 Migration Summary</h2>";

$bookingsAdded = count(array_filter($results['bookings'], fn($v) => $v === 'added'));
$bookingsExists = count(array_filter($results['bookings'], fn($v) => $v === 'exists'));
$bookingsErrors = count(array_filter($results['bookings'], fn($v) => $v === 'error'));

$reportsAdded = count(array_filter($results['reports'], fn($v) => $v === 'added'));
$reportsExists = count(array_filter($results['reports'], fn($v) => $v === 'exists'));
$reportsErrors = count(array_filter($results['reports'], fn($v) => $v === 'error'));

echo "<table>";
echo "<thead><tr><th>Table</th><th>Added</th><th>Already Existed</th><th>Errors</th><th>Total</th></tr></thead>";
echo "<tbody>";
echo "<tr>";
echo "<td><strong>bookings</strong></td>";
echo "<td>{$bookingsAdded}</td>";
echo "<td>{$bookingsExists}</td>";
echo "<td>{$bookingsErrors}</td>";
echo "<td>" . count($columns) . "</td>";
echo "</tr>";
echo "<tr>";
echo "<td><strong>reports</strong></td>";
echo "<td>{$reportsAdded}</td>";
echo "<td>{$reportsExists}</td>";
echo "<td>{$reportsErrors}</td>";
echo "<td>" . count($columns) . "</td>";
echo "</tr>";
echo "</tbody></table>";

// Display errors if any
if (!empty($errors)) {
    echo "<h2>⚠️ Errors Encountered</h2>";
    foreach ($errors as $error) {
        echo "<div class='error'>{$error}</div>";
    }
} else {
    if ($bookingsAdded > 0 || $reportsAdded > 0) {
        echo "<div class='success'>";
        echo "<strong>✅ Migration Completed Successfully!</strong><br>";
        echo "Added {$bookingsAdded} columns to <code>bookings</code> table and {$reportsAdded} columns to <code>reports</code> table.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>ℹ️ No Changes Needed</strong><br>";
        echo "All columns already exist in both tables. Your database is up to date!";
        echo "</div>";
    }
}

// Next steps
echo "<h2>🎯 Next Steps</h2>";
echo "<div class='info'>";
echo "<ol>";
echo "<li>The <code>confirmed_at</code> column is now available to track booking confirmation timestamps</li>";
echo "<li>The <code>downpayment_nonrefundable</code> and <code>downpayment_refund_status</code> columns are now available for refund tracking</li>";
echo "<li>The base payment history columns (Cash, GCash, Maya) are now available</li>";
echo "<li>The new payment methods (Instapay, Online Banking, Airbnb) are now available in your system</li>";
echo "<li>Users can select these payment methods when creating or editing bookings</li>";
echo "<li>Reference numbers will be captured for each digital payment</li>";
echo "<li>Payment history will be tracked automatically</li>";
echo "<li>You can safely delete this migration file after successful execution</li>";
echo "</ol>";
echo "</div>";

echo "<div class='warning'>";
echo "<strong>⚠️ Important:</strong> This migration script can be run multiple times safely. It will only add columns that don't already exist.";
echo "</div>";

?>

    </div>
</body>
</html>
