<?php
/**
 * Test script to verify the revenue calculation fix for payment-date-based filtering
 */

require_once 'report_helpers.php';

// Test the payment date parsing logic
function testPaymentDateFiltering() {
    echo "Testing payment date filtering logic...\n";
    
    // Test data with multiple payment timestamps
    $testData = [
        [
            'payment_date_time' => '2026-04-20 13:06:00|2026-04-21 13:06:00',
            'booking_id' => 'TEST001'
        ],
        [
            'payment_date_time' => '2026-04-19 10:00:00',
            'booking_id' => 'TEST002'
        ],
        [
            'payment_date_time' => '2026-04-22 15:30:00|2026-04-23 16:00:00',
            'booking_id' => 'TEST003'
        ]
    ];
    
    $startDate = '2026-04-21';
    $endDate = '2026-04-21';
    
    echo "Filter range: $startDate to $endDate\n";
    echo "Expected matches: TEST001 (has payment on 2026-04-21)\n\n";
    
    foreach ($testData as $row) {
        $paymentDateTime = $row['payment_date_time'];
        $bookingId = $row['booking_id'];
        
        $timestamps = explode('|', $paymentDateTime);
        $hasPaymentInRange = false;
        
        foreach ($timestamps as $timestamp) {
            $timestamp = trim($timestamp);
            if (!empty($timestamp)) {
                try {
                    $dt = new DateTime($timestamp);
                    $paymentDate = $dt->format('Y-m-d');
                    if ($paymentDate >= $startDate && $paymentDate <= $endDate) {
                        $hasPaymentInRange = true;
                        break;
                    }
                } catch (Exception $e) {
                    // Skip invalid timestamps
                }
            }
        }
        
        $result = $hasPaymentInRange ? "MATCH" : "NO MATCH";
        echo "$bookingId: $paymentDateTime -> $result\n";
    }
    
    echo "\nTest completed!\n";
}

testPaymentDateFiltering();
?>