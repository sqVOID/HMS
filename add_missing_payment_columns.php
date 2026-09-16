<?php
// Script to add missing payment columns for Instapay, Online Banking, and Airbnb
$conn = new mysqli('localhost', 'root', '', 'hotel_management');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "<h3>Adding missing payment columns...</h3>";

$alterQueries = [
    // Bookings table
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status_instapay TEXT",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status_online_banking TEXT",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status_airbnb TEXT",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS reference_no_instapay VARCHAR(255) DEFAULT ''",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS reference_no_online_banking VARCHAR(255) DEFAULT ''",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS reference_no_airbnb VARCHAR(255) DEFAULT ''",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS deposit_instapay DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS deposit_online_banking DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS deposit_airbnb DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS downpayment_instapay DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS downpayment_online_banking DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS downpayment_airbnb DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS downpayment_instapay_ref VARCHAR(255) DEFAULT ''",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS downpayment_online_banking_ref VARCHAR(255) DEFAULT ''",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS downpayment_airbnb_ref VARCHAR(255) DEFAULT ''",
    
    // Reports table
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS payment_status_instapay TEXT",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS payment_status_online_banking TEXT",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS payment_status_airbnb TEXT",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS reference_no_instapay VARCHAR(255) DEFAULT ''",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS reference_no_online_banking VARCHAR(255) DEFAULT ''",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS reference_no_airbnb VARCHAR(255) DEFAULT ''",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS deposit_instapay DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS deposit_online_banking DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS deposit_airbnb DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS downpayment_instapay DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS downpayment_online_banking DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS downpayment_airbnb DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS downpayment_instapay_ref VARCHAR(255) DEFAULT ''",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS downpayment_online_banking_ref VARCHAR(255) DEFAULT ''",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS downpayment_airbnb_ref VARCHAR(255) DEFAULT ''"
];

foreach ($alterQueries as $query) {
    if ($conn->query($query)) {
        echo "✅ " . substr($query, 0, 80) . "...<br>";
    } else {
        echo "❌ Error: " . $conn->error . " - " . substr($query, 0, 80) . "...<br>";
    }
}

echo "<h3>Done! All payment columns have been added.</h3>";
echo "<p><a href='test_columns.php'>Test columns again</a></p>";

$conn->close();
?>