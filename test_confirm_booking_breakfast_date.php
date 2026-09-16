<?php
/**
 * Test script to verify breakfast_date implementation in confirm_booking.php
 * This checks if the SQL queries are properly structured
 */

echo "===========================================\n";
echo "CONFIRM_BOOKING.PHP BREAKFAST_DATE TEST\n";
echo "===========================================\n\n";

$file = 'confirm_booking.php';
$content = file_get_contents($file);

if ($content === false) {
    echo "✗ Failed to read {$file}\n";
    exit(1);
}

echo "1. Checking breakfast_date initialization...\n";
if (preg_match('/\$breakfast_date\s*=\s*\(!empty\(\$breakfast\)/', $content)) {
    echo "   ✓ breakfast_date initialization found\n";
} else {
    echo "   ✗ breakfast_date initialization NOT found\n";
}

echo "\n2. Checking INSERT INTO bookings query...\n";
if (strpos($content, 'breakfast, breakfast_date, payment_status') !== false) {
    echo "   ✓ breakfast_date column in INSERT bookings query\n";
} else {
    echo "   ✗ breakfast_date column NOT in INSERT bookings query\n";
}

if (strpos($content, ':breakfast, :breakfast_date, :payment_status') !== false) {
    echo "   ✓ breakfast_date parameter in VALUES bookings clause\n";
} else {
    echo "   ✗ breakfast_date parameter NOT in VALUES bookings clause\n";
}

echo "\n3. Checking bookings parameter binding...\n";
if (preg_match('/bindParam\([\'\"]:breakfast_date/', $content) || preg_match('/bindValue\([\'\"]:breakfast_date/', $content)) {
    echo "   ✓ breakfast_date parameter binding found\n";
} else {
    echo "   ✗ breakfast_date parameter binding NOT found\n";
}

echo "\n4. Checking INSERT INTO reports query...\n";
if (strpos($content, 'breakfast, breakfast_date, additional_guest') !== false) {
    echo "   ✓ breakfast_date column in INSERT reports query\n";
} else {
    echo "   ✗ breakfast_date column NOT in INSERT reports query\n";
}

if (strpos($content, ':breakfast, :breakfast_date, :additional_guest') !== false) {
    echo "   ✓ breakfast_date parameter in VALUES reports clause\n";
} else {
    echo "   ✗ breakfast_date parameter NOT in VALUES reports clause\n";
}

echo "\n5. Checking reports parameter binding...\n";
// Count all breakfast_date bindings
$bindCount = preg_match_all('/bind(Param|Value)\([\'\"]:breakfast_date/', $content, $matches);
if ($bindCount >= 2) {
    echo "   ✓ Found {$bindCount} breakfast_date parameter bindings (bookings + reports)\n";
} else {
    echo "   ✗ Expected at least 2 breakfast_date bindings, found {$bindCount}\n";
}

echo "\n6. Checking existing booking data fetch...\n";
if (preg_match('/\$breakfast_date\s*=\s*\$existingBooking\[[\'"]\breakfast_date[\'"]\]/', $content)) {
    echo "   ✓ breakfast_date fetched from existing booking\n";
} else {
    echo "   ✗ breakfast_date NOT fetched from existing booking\n";
}

echo "\n7. Checking JSON format...\n";
if (strpos($content, 'json_encode([$currentTimestamp])') !== false) {
    echo "   ✓ Using JSON array format for date tracking\n";
} else {
    echo "   ✗ JSON array format NOT found (should use json_encode)\n";
}

echo "\n===========================================\n";
echo "TEST COMPLETED!\n";
echo "===========================================\n\n";

echo "Summary:\n";
echo "- breakfast_date should be initialized when breakfast is not empty\n";
echo "- breakfast_date should be in both INSERT queries (bookings & reports)\n";
echo "- breakfast_date should have proper NULL handling in bindings\n";
echo "- breakfast_date should use JSON array format: json_encode([\$timestamp])\n";
echo "- breakfast_date should be fetched from existing reservations\n\n";
?>
