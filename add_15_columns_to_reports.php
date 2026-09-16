<?php
/**
 * Add 16 columns from bookings table to reports table
 * Columns: extend_paid_status, check_in_charge_amount, downpayment fields (8 columns), 
 * discount fields (4 columns), deposit, and total_amount_reservation
 */

require_once 'config.php';

try {
    echo "<h2>Adding 16 Columns to Reports Table</h2>";
    echo "<p>Starting migration...</p>";
    
    $columnsToAdd = [
        // 1. Extended payment status
        [
            'name' => 'extend_paid_status',
            'definition' => 'VARCHAR(50) DEFAULT "Unpaid"',
            'description' => 'Status of extended duration payment'
        ],
        // 2. Check-in charge
        [
            'name' => 'check_in_charge_amount',
            'definition' => 'DECIMAL(10,2) DEFAULT 0.00',
            'description' => 'Amount charged at check-in'
        ],
        // 3-10. Downpayment fields (8 columns)
        [
            'name' => 'downpayment_amount',
            'definition' => 'DECIMAL(10,2) DEFAULT 0.00',
            'description' => 'Total downpayment amount'
        ],
        [
            'name' => 'downpayment_cash',
            'definition' => 'DECIMAL(10,2) DEFAULT 0.00',
            'description' => 'Cash portion of downpayment'
        ],
        [
            'name' => 'downpayment_gcash',
            'definition' => 'DECIMAL(10,2) DEFAULT 0.00',
            'description' => 'GCash portion of downpayment'
        ],
        [
            'name' => 'downpayment_maya',
            'definition' => 'DECIMAL(10,2) DEFAULT 0.00',
            'description' => 'Maya portion of downpayment'
        ],
        [
            'name' => 'downpayment_gcash_ref',
            'definition' => 'VARCHAR(255) NULL DEFAULT NULL',
            'description' => 'GCash reference number for downpayment'
        ],
        [
            'name' => 'downpayment_maya_ref',
            'definition' => 'VARCHAR(255) NULL DEFAULT NULL',
            'description' => 'Maya reference number for downpayment'
        ],
        [
            'name' => 'downpayment_status',
            'definition' => 'VARCHAR(50) DEFAULT "None"',
            'description' => 'Status of downpayment (None/Paid/Partial)'
        ],
        [
            'name' => 'downpayment_date',
            'definition' => 'DATETIME NULL DEFAULT NULL',
            'description' => 'Date when downpayment was made'
        ],
        // 11-14. Discount fields (4 columns)
        [
            'name' => 'discount_enabled',
            'definition' => 'TINYINT(1) DEFAULT 0',
            'description' => 'Whether discount is enabled (0 or 1)'
        ],
        [
            'name' => 'discount_type',
            'definition' => 'VARCHAR(50) DEFAULT "regular"',
            'description' => 'Type of discount (regular/multiple)'
        ],
        [
            'name' => 'sc_pwd_count',
            'definition' => 'INT DEFAULT 0',
            'description' => 'Number of Senior Citizen/PWD guests'
        ],
        [
            'name' => 'discount_amount',
            'definition' => 'DECIMAL(10,2) DEFAULT 0.00',
            'description' => 'Calculated discount amount'
        ],
        // 15. Deposit
        [
            'name' => 'deposit',
            'definition' => 'DECIMAL(10,2) DEFAULT 0.00',
            'description' => 'Total deposit amount at check-in'
        ],
        // 16. Total Amount Reservation
        [
            'name' => 'total_amount_reservation',
            'definition' => 'DECIMAL(12,2) DEFAULT 0.00',
            'description' => 'Total reservation amount (full amount before deposit deduction)'
        ]
    ];
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($columnsToAdd as $index => $column) {
        $columnNum = $index + 1;
        $columnName = $column['name'];
        $columnDef = $column['definition'];
        $description = $column['description'];
        
        // Check if column exists
        $checkStmt = $conn->query("SHOW COLUMNS FROM reports LIKE '$columnName'");
        $exists = $checkStmt->fetch();
        
        if (!$exists) {
            // Add the column
            $sql = "ALTER TABLE reports ADD COLUMN $columnName $columnDef";
            $conn->exec($sql);
            echo "<p style='color: green;'>✓ Column $columnNum: <strong>$columnName</strong> added successfully - $description</p>";
            $addedCount++;
        } else {
            echo "<p style='color: orange;'>⊙ Column $columnNum: <strong>$columnName</strong> already exists - $description</p>";
            $skippedCount++;
        }
    }
    
    echo "<hr>";
    echo "<h3>Summary:</h3>";
    echo "<p><strong>Total columns processed:</strong> 16</p>";
    echo "<p><strong>Columns added:</strong> $addedCount</p>";
    echo "<p><strong>Columns skipped (already exist):</strong> $skippedCount</p>";
    
    // Verify all columns now exist
    echo "<hr>";
    echo "<h3>Verification - All Columns in Reports Table:</h3>";
    $allColumns = $conn->query("SHOW COLUMNS FROM reports")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>#</th><th>Column Name</th><th>Type</th><th>Default</th></tr>";
    foreach ($allColumns as $idx => $col) {
        echo "<tr>";
        echo "<td>" . ($idx + 1) . "</td>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✓ Migration completed successfully!</h3>";
    echo "<p>The reports table now has all 16 columns from the bookings table.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Error adding columns to reports: " . $e->getMessage());
}
?>
