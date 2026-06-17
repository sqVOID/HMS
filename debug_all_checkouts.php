<?php
require_once 'config.php';

header('Content-Type: text/plain');

try {
    // Get ALL checked-out reports with all details
    $stmt = $conn->query("
        SELECT 
            id,
            booking_id,
            status,
            payment_status_cash,
            payment_status_g_cash,
            payment_status_maya,
            deposit_details,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya,
            checked_out_at,
            check_out
        FROM reports
        ORDER BY id DESC
    ");
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total reports: " . count($reports) . "\n\n";
    
    foreach ($reports as $row) {
        echo "ID: " . $row['id'] . "\n";
        echo "Booking ID: " . $row['booking_id'] . "\n";
        echo "Status: " . $row['status'] . "\n";
        echo "Payment Cash: " . ($row['payment_status_cash'] ?? 'NULL') . "\n";
        echo "Payment G-Cash: " . ($row['payment_status_g_cash'] ?? 'NULL') . "\n";
        echo "Payment Maya: " . ($row['payment_status_maya'] ?? 'NULL') . "\n";
        echo "Deposit Details: " . ($row['deposit_details'] ?? 'NULL') . "\n";
        echo "DP Cash: " . ($row['downpayment_cash'] ?? '0') . "\n";
        echo "DP G-Cash: " . ($row['downpayment_gcash'] ?? '0') . "\n";
        echo "DP Maya: " . ($row['downpayment_maya'] ?? '0') . "\n";
        echo "Checked Out At: " . ($row['checked_out_at'] ?? 'NULL') . "\n";
        echo "Check Out: " . ($row['check_out'] ?? 'NULL') . "\n";
        echo "---\n\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
