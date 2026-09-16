<?php
require_once 'connection.php';

// Get the booking that has payments on both 13th and 14th
$sql = "SELECT 
    id,
    guest_name,
    payment_date_time,
    downpayment_date,
    payment_amount_cash_history,
    payment_amount_g_cash_history,
    payment_amount_maya_history,
    payment_status_cash,
    payment_status_g_cash,
    payment_status_maya,
    deposit_cash,
    deposit_g_cash,
    deposit_maya,
    downpayment_cash,
    downpayment_gcash,
    downpayment_maya
FROM reports 
WHERE payment_date_time LIKE '%2026-05-13%' 
  AND payment_date_time LIKE '%2026-05-14%'
LIMIT 1";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    
    echo "<h2>Booking ID: " . $row['id'] . " - " . htmlspecialchars($row['guest_name']) . "</h2>";
    
    echo "<h3>Payment Timestamps:</h3>";
    echo "<pre>payment_date_time: " . htmlspecialchars($row['payment_date_time']) . "</pre>";
    echo "<pre>downpayment_date: " . htmlspecialchars($row['downpayment_date']) . "</pre>";
    
    echo "<h3>Payment History Columns:</h3>";
    echo "<pre>payment_amount_cash_history: " . htmlspecialchars($row['payment_amount_cash_history']) . "</pre>";
    echo "<pre>payment_amount_g_cash_history: " . htmlspecialchars($row['payment_amount_g_cash_history']) . "</pre>";
    echo "<pre>payment_amount_maya_history: " . htmlspecialchars($row['payment_amount_maya_history']) . "</pre>";
    
    echo "<h3>Payment Status Columns:</h3>";
    echo "<pre>payment_status_cash: " . htmlspecialchars($row['payment_status_cash']) . "</pre>";
    echo "<pre>payment_status_g_cash: " . htmlspecialchars($row['payment_status_g_cash']) . "</pre>";
    echo "<pre>payment_status_maya: " . htmlspecialchars($row['payment_status_maya']) . "</pre>";
    
    echo "<h3>Deposit Columns:</h3>";
    echo "<pre>deposit_cash: " . htmlspecialchars($row['deposit_cash']) . "</pre>";
    echo "<pre>deposit_g_cash: " . htmlspecialchars($row['deposit_g_cash']) . "</pre>";
    echo "<pre>deposit_maya: " . htmlspecialchars($row['deposit_maya']) . "</pre>";
    
    echo "<h3>Downpayment Columns:</h3>";
    echo "<pre>downpayment_cash: " . htmlspecialchars($row['downpayment_cash']) . "</pre>";
    echo "<pre>downpayment_gcash: " . htmlspecialchars($row['downpayment_gcash']) . "</pre>";
    echo "<pre>downpayment_maya: " . htmlspecialchars($row['downpayment_maya']) . "</pre>";
    
    // Parse the timestamps
    echo "<h3>Parsed Timestamps:</h3>";
    $allTimestamps = [];
    if (!empty($row['downpayment_date'])) {
        $dt = new DateTime($row['downpayment_date']);
        $allTimestamps[] = $dt->format('Y-m-d H:i:s');
    }
    if (!empty($row['payment_date_time'])) {
        $rawTimestamps = explode('|', $row['payment_date_time']);
        foreach ($rawTimestamps as $ts) {
            $ts = trim($ts);
            if ($ts === '') continue;
            $dt = new DateTime($ts);
            $allTimestamps[] = $dt->format('Y-m-d H:i:s');
        }
    }
    echo "<pre>" . print_r($allTimestamps, true) . "</pre>";
    
    // Parse cash history
    echo "<h3>Parsed Cash History:</h3>";
    if (!empty($row['payment_amount_cash_history'])) {
        $cashHistory = explode('|', $row['payment_amount_cash_history']);
        echo "<pre>" . print_r($cashHistory, true) . "</pre>";
    } else {
        echo "<pre>NULL or empty</pre>";
    }
    
    // Test the filter logic
    echo "<h3>Filter Test for 2026-05-14 only:</h3>";
    $filterStart = '2026-05-14';
    $filterEnd = '2026-05-14';
    
    $timestampIndicesInRange = [];
    $currentIndex = 0;
    
    if (!empty($row['downpayment_date'])) {
        $dt = new DateTime($row['downpayment_date']);
        $dateStr = $dt->format('Y-m-d');
        echo "<pre>Index $currentIndex: $dateStr - " . ($dateStr >= $filterStart && $dateStr <= $filterEnd ? 'IN RANGE' : 'OUT OF RANGE') . "</pre>";
        if ($dateStr >= $filterStart && $dateStr <= $filterEnd) {
            $timestampIndicesInRange[] = $currentIndex;
        }
        $currentIndex++;
    }
    
    if (!empty($row['payment_date_time'])) {
        $rawTimestamps = explode('|', $row['payment_date_time']);
        foreach ($rawTimestamps as $ts) {
            $ts = trim($ts);
            if ($ts === '') continue;
            $dt = new DateTime($ts);
            $dateStr = $dt->format('Y-m-d');
            echo "<pre>Index $currentIndex: $dateStr - " . ($dateStr >= $filterStart && $dateStr <= $filterEnd ? 'IN RANGE' : 'OUT OF RANGE') . "</pre>";
            if ($dateStr >= $filterStart && $dateStr <= $filterEnd) {
                $timestampIndicesInRange[] = $currentIndex;
            }
            $currentIndex++;
        }
    }
    
    echo "<pre>Indices in range: " . print_r($timestampIndicesInRange, true) . "</pre>";
    
    // Calculate what cash amount should be
    if (!empty($row['payment_amount_cash_history'])) {
        $cashHistory = explode('|', $row['payment_amount_cash_history']);
        $cashSum = 0.0;
        foreach ($timestampIndicesInRange as $idx) {
            if (isset($cashHistory[$idx])) {
                $cashSum += floatval($cashHistory[$idx]);
                echo "<pre>Adding cashHistory[$idx] = " . $cashHistory[$idx] . "</pre>";
            }
        }
        echo "<pre><strong>Expected Cash for 14/05/2026: $cashSum</strong></pre>";
    }
    
} else {
    echo "No booking found with payments on both 13th and 14th";
}

mysqli_close($conn);
?>
