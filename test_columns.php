<?php
// Test script to check if all required columns exist
$conn = new mysqli('localhost', 'root', '', 'hotel_management');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "<h3>Checking bookings table columns:</h3>";
$result = $conn->query("DESCRIBE bookings");
$bookingsColumns = [];
while ($row = $result->fetch_assoc()) {
    $bookingsColumns[] = $row['Field'];
}

$requiredColumns = [
    'payment_status_instapay', 'payment_status_online_banking', 'payment_status_airbnb',
    'reference_no_instapay', 'reference_no_online_banking', 'reference_no_airbnb',
    'deposit_instapay', 'deposit_online_banking', 'deposit_airbnb',
    'downpayment_instapay', 'downpayment_online_banking', 'downpayment_airbnb',
    'downpayment_instapay_ref', 'downpayment_online_banking_ref', 'downpayment_airbnb_ref'
];

foreach ($requiredColumns as $col) {
    if (in_array($col, $bookingsColumns)) {
        echo "✅ $col - EXISTS<br>";
    } else {
        echo "❌ $col - MISSING<br>";
    }
}

echo "<h3>Checking reports table columns:</h3>";
$result = $conn->query("DESCRIBE reports");
$reportsColumns = [];
while ($row = $result->fetch_assoc()) {
    $reportsColumns[] = $row['Field'];
}

foreach ($requiredColumns as $col) {
    if (in_array($col, $reportsColumns)) {
        echo "✅ $col - EXISTS<br>";
    } else {
        echo "❌ $col - MISSING<br>";
    }
}

$conn->close();
?>