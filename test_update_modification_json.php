<?php
/**
 * Test script to verify update_modification.php returns proper JSON
 * This simulates a modification update request
 */

// Test data - modify this with actual booking ID from your database
$testData = [
    'id' => '1', // Change this to an actual booking ID
    'room' => 'Standard Room 101',
    'check_in' => '2026-05-15 14:00:00',
    'check_out' => '2026-05-16 12:00:00',
    'guest_names' => 'Test Guest',
    'contact_no' => '09123456789',
    'payment_method' => 'Cash',
    'cash_amount' => 1500,
    'modification_reason' => 'Testing JSON response'
];

// Make the request
$ch = curl_init('http://localhost/HMS/update_modification.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response:\n";
echo $response . "\n\n";

// Try to parse as JSON
$decoded = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✅ Valid JSON response!\n";
    echo "Success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
    if (isset($decoded['error'])) {
        echo "Error: " . $decoded['error'] . "\n";
    }
    if (isset($decoded['message'])) {
        echo "Message: " . $decoded['message'] . "\n";
    }
} else {
    echo "❌ Invalid JSON response!\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "This means PHP is outputting HTML errors instead of JSON.\n";
}
?>
