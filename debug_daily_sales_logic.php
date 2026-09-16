<?php
require_once 'config.php';
require_once 'report_helpers.php';

header('Content-Type: application/json');

try {
    // Get all checked-out reports
    $stmt = $conn->query("
        SELECT 
            booking_id,
            status,
            payment_status_cash,
            payment_status_g_cash,
            payment_status_maya,
            deposit_details,
            deposit_gcash_ref,
            deposit_maya_ref,
            downpayment_amount,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya,
            change_amount,
            checked_out_at,
            check_out
        FROM reports
        WHERE status = 'Checked Out'
        ORDER BY COALESCE(checked_out_at, check_out) DESC
    ");
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Helper to parse amount
    function parseAmount($str) {
        if (!$str) return 0.0;
        if (preg_match('/(?:P|₱)?([0-9,.]+)/', $str, $m)) {
            return floatval(str_replace(',', '', $m[1]));
        }
        return 0.0;
    }
    
    $cashCount = 0;
    $gcashCount = 0;
    $mayaCount = 0;
    
    $cashTotal = 0;
    $gcashTotal = 0;
    $mayaTotal = 0;
    
    $debugData = [];
    
    foreach ($reports as $row) {
        // Checkout Payments (same logic as export_daily_sales.php)
        $cash = parseAmount($row['payment_status_cash']);
        $gcash = parseAmount($row['payment_status_g_cash']);
        $maya = parseAmount($row['payment_status_maya']);
        
        // Parse deposit
        $depositAmt = 0.0;
        $depositDetails = $row['deposit_details'] ?? '';
        if (!empty($depositDetails)) {
            if (preg_match('/₱\s*([0-9,]+\.?[0-9]*)/', $depositDetails, $m)) {
                $depositAmt = floatval(str_replace(',', '', $m[1]));
            }
        }
        
        // Parse downpayment
        $downpaymentAmt = floatval($row['downpayment_amount'] ?? 0);
        
        // Count logic from export_daily_sales.php (lines 206-208)
        if ($cash > 0) $cashCount++;
        if ($gcash > 0) $gcashCount++;
        if ($maya > 0) $mayaCount++;
        
        // Total logic
        $cashTotal += $cash;
        $gcashTotal += $gcash;
        $mayaTotal += $maya;
        
        $debugData[] = [
            'booking_id' => $row['booking_id'],
            'cash_checkout' => $cash,
            'gcash_checkout' => $gcash,
            'maya_checkout' => $maya,
            'deposit' => $depositAmt,
            'downpayment' => $downpaymentAmt,
            'counted_cash' => $cash > 0,
            'counted_gcash' => $gcash > 0,
            'counted_maya' => $maya > 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'counts' => [
            'cash' => $cashCount,
            'gcash' => $gcashCount,
            'maya' => $mayaCount
        ],
        'totals' => [
            'cash' => $cashTotal,
            'gcash' => $gcashTotal,
            'maya' => $mayaTotal
        ],
        'debug_data' => $debugData
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
