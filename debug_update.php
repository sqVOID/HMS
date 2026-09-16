<?php
// Debug script to test update_modification.php directly
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate the same request that's failing
$testData = [
    'id' => '2488', // Use the booking ID from your test
    'room' => 'Deluxe Room 104',
    'booking_type' => 'Walk-in',
    'guest_type' => 'Solo',
    'guest_names' => 'Test Guest',
    'reason_for_stay' => '',
    'contact_person_name' => '',
    'contact_no' => '',
    'address' => '',
    'tin_number' => '',
    'request' => '',
    'check_in' => '2026-04-21T14:00',
    'check_out' => '2026-04-22T02:00',
    'duration' => '12',
    'referral_code' => '',
    'promo' => '',
    'breakfast' => '',
    'payment_method' => 'Instapay',
    'gcash_reference' => '',
    'maya_reference' => '',
    'instapay_reference' => 'TEST123',
    'online_banking_reference' => '',
    'airbnb_reference' => '',
    'cash_amount' => 0,
    'gcash_amount' => 0,
    'maya_amount' => 0,
    'instapay_amount' => 800,
    'online_banking_amount' => 0,
    'airbnb_amount' => 0,
    'reservation_cash' => 0,
    'reservation_gcash' => 0,
    'reservation_maya' => 0,
    'reservation_instapay' => 0,
    'reservation_online_banking' => 0,
    'reservation_airbnb' => 0,
    'reservation_gcash_ref' => '',
    'reservation_maya_ref' => '',
    'reservation_instapay_ref' => '',
    'reservation_online_banking_ref' => '',
    'reservation_airbnb_ref' => '',
    'additional_guest' => 0,
    'additional_pet' => 0,
    'additional_data' => '[]',
    'discount_count' => 0,
    'discount_amount' => 0,
    'discount_id' => '',
    'cancellation_reason' => '',
    'cancellation_refund' => 0,
    'modification_reason' => 'Test modification',
    'vehicle_type' => '',
    'plate_number' => '',
    'vehicle_description' => '',
    'transfer_room_from' => '',
    'transfer_refund_amount' => 0
];

echo "<h3>Testing update_modification.php</h3>";
echo "<p>Sending test data...</p>";

// Make a POST request to update_modification.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/HMS/update_modification.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
]);

// Add session cookie if needed
$cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h4>Response (HTTP $httpCode):</h4>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to decode as JSON
$decoded = json_decode($response, true);
if ($decoded === null) {
    echo "<h4>JSON Decode Error:</h4>";
    echo "<p>Response is not valid JSON. This is the problem!</p>";
    echo "<p>The response contains HTML/PHP error output instead of JSON.</p>";
} else {
    echo "<h4>JSON Decoded Successfully:</h4>";
    echo "<pre>" . print_r($decoded, true) . "</pre>";
}
?>