<?php
// Test script to simulate payment method change
header('Content-Type: application/json');

// Test data - simulating changing from Instapay to Cash
$testData = [
    'id' => '1', // Replace with actual booking ID
    'room' => 'Deluxe 101',
    'guest_names' => 'Test Guest',
    'check_in' => '2024-12-01 14:00:00',
    'check_out' => '2024-12-02 12:00:00',
    'payment_method' => 'Cash',
    'cash_amount' => 800,
    'gcash_amount' => 0,
    'maya_amount' => 0,
    'instapay_amount' => 0,
    'online_banking_amount' => 0,
    'airbnb_amount' => 0,
    'modification_reason' => 'Changed payment method from Instapay to Cash'
];

echo "Test data to be sent:\n";
echo json_encode($testData, JSON_PRETTY_PRINT);
echo "\n\n";

// Simulate the POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HMS/update_modification_clean.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response;
?>