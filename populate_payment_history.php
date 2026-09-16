<?php
/**
 * Script to populate payment_amount_*_history columns from existing payment data
 * This ensures accurate payment breakdown in reports
 */

require_once 'config.php';

echo "<h2>Populating Payment History Columns</h2>";

try {
    // Ensure payment history columns exist
    $histColumns = [
        'payment_amount_cash_history',
        'payment_amount_g_cash_history',
        'payment_amount_maya_history',
        'payment_amount_instapay_history',
        'payment_amount_online_banking_history',
        'payment_amount_airbnb_history'
    ];
    
    foreach ($histColumns as $colName) {
        try {
            $chk = $conn->query("SHOW COLUMNS FROM reports LIKE '" . $colName . "'");
            if ($chk && $chk->rowCount() == 0) {
                $conn->exec("ALTER TABLE reports ADD COLUMN {$colName} TEXT NULL DEFAULT NULL");
                echo "<p>✓ Created column: {$colName}</p>";
            }
        } catch (PDOException $e) {
            echo "<p>✗ Error creating column {$colName}: " . $e->getMessage() . "</p>";
        }
    }
    
    // Get all reports with payment_date_time
    $stmt = $conn->query("
        SELECT 
            id,
            booking_id,
            payment_date_time,
            downpayment_date,
            deposit_cash,
            deposit_g_cash,
            deposit_maya,
            deposit_instapay,
            deposit_online_banking,
            deposit_airbnb,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya,
            downpayment_instapay,
            downpayment_online_banking,
            downpayment_airbnb,
            payment_status_cash,
            payment_status_g_cash,
            payment_status_maya,
            payment_status_instapay,
            payment_status_online_banking,
            payment_status_airbnb,
            payment_amount_cash_history,
            payment_amount_g_cash_history,
            payment_amount_maya_history
        FROM reports
        WHERE (payment_date_time IS NOT NULL AND payment_date_time != '')
           OR (downpayment_date IS NOT NULL)
    ");
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $skipped = 0;
    
    foreach ($reports as $report) {
        // Always recalculate history (don't skip if already populated)
        // This allows the script to be re-run to fix any issues
        
        // Build list of payment timestamps
        $timestamps = [];
        
        if (!empty($report['downpayment_date'])) {
            $timestamps[] = $report['downpayment_date'];
        }
        
        if (!empty($report['payment_date_time'])) {
            $rawTimestamps = explode('|', $report['payment_date_time']);
            foreach ($rawTimestamps as $ts) {
                $ts = trim($ts);
                if ($ts !== '') {
                    $timestamps[] = $ts;
                }
            }
        }
        
        $nTimestamps = count($timestamps);
        if ($nTimestamps === 0) {
            continue;
        }
        
        // Calculate payment amounts for each method
        $cashAmounts = [];
        $gcashAmounts = [];
        $mayaAmounts = [];
        
        // First timestamp: deposit + downpayment (both can exist)
        $depositCash = floatval($report['deposit_cash'] ?? 0);
        $downpaymentCash = floatval($report['downpayment_cash'] ?? 0);
        $firstCash = $depositCash + $downpaymentCash;
        
        $depositGcash = floatval($report['deposit_g_cash'] ?? 0);
        $downpaymentGcash = floatval($report['downpayment_gcash'] ?? 0);
        $firstGcash = $depositGcash + $downpaymentGcash;
        
        $depositMaya = floatval($report['deposit_maya'] ?? 0);
        $downpaymentMaya = floatval($report['downpayment_maya'] ?? 0);
        $firstMaya = $depositMaya + $downpaymentMaya;
        
        $cashAmounts[] = $firstCash;
        $gcashAmounts[] = $firstGcash;
        $mayaAmounts[] = $firstMaya;
        
        // Subsequent timestamps: parse from payment_status columns
        if ($nTimestamps > 1) {
            // Parse amounts from payment_status columns
            function parseAmount($str) {
                if (!$str) return 0.0;
                if (preg_match('/(?:P|₱)?([0-9,.]+)/', $str, $m)) {
                    return floatval(str_replace(',', '', $m[1]));
                }
                return 0.0;
            }
            
            $totalCash = parseAmount($report['payment_status_cash'] ?? '');
            $totalGcash = parseAmount($report['payment_status_g_cash'] ?? '');
            $totalMaya = parseAmount($report['payment_status_maya'] ?? '');
            
            // Additional payments = total - first payment
            $additionalCash = max(0, $totalCash - $firstCash);
            $additionalGcash = max(0, $totalGcash - $firstGcash);
            $additionalMaya = max(0, $totalMaya - $firstMaya);
            
            // Add additional payments to subsequent timestamps
            for ($i = 1; $i < $nTimestamps; $i++) {
                if ($i === 1) {
                    // Second timestamp gets the additional payment
                    $cashAmounts[] = $additionalCash;
                    $gcashAmounts[] = $additionalGcash;
                    $mayaAmounts[] = $additionalMaya;
                } else {
                    // Any further timestamps get 0 (shouldn't happen in normal flow)
                    $cashAmounts[] = 0;
                    $gcashAmounts[] = 0;
                    $mayaAmounts[] = 0;
                }
            }
        }
        
        // Build history strings
        $cashHistory = implode('|', $cashAmounts);
        $gcashHistory = implode('|', $gcashAmounts);
        $mayaHistory = implode('|', $mayaAmounts);
        
        // Update the report
        $updateStmt = $conn->prepare("
            UPDATE reports 
            SET payment_amount_cash_history = :cash_history,
                payment_amount_g_cash_history = :gcash_history,
                payment_amount_maya_history = :maya_history
            WHERE id = :id
        ");
        
        $updateStmt->execute([
            ':cash_history' => $cashHistory,
            ':gcash_history' => $gcashHistory,
            ':maya_history' => $mayaHistory,
            ':id' => $report['id']
        ]);
        
        $updated++;
        echo "<p>✓ Updated booking {$report['booking_id']}: Cash={$cashHistory}, G-Cash={$gcashHistory}, Maya={$mayaHistory}</p>";
    }
    
    echo "<h3>Summary</h3>";
    echo "<p>Updated: {$updated} bookings</p>";
    echo "<p>Skipped (already populated): {$skipped} bookings</p>";
    echo "<p><strong>Done! Payment history columns have been populated.</strong></p>";
    echo "<p><a href='Report.php'>Go to Reports</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
