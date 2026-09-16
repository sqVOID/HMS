<?php
/**
 * Test Individual Extension Withdrawal
 * 
 * This script tests the individual extension withdrawal functionality:
 * 1. Creates a test booking
 * 2. Adds multiple extensions (12hr + 24hr)
 * 3. Withdraws only the 24hr extension
 * 4. Verifies that 12hr extension remains active
 */

require_once 'config.php';

echo "<h2>Testing Individual Extension Withdrawal</h2>";

try {
    // Step 1: Create a test booking
    echo "<h3>Step 1: Creating test booking...</h3>";
    
    $testRoomId = 'TEST-ROOM-001';
    $checkIn = date('Y-m-d H:i:s');
    $checkOut = date('Y-m-d H:i:s', strtotime('+12 hours'));
    
    $stmt = $conn->prepare("
        INSERT INTO bookings (
            booking_id, room_id, guest_name, contact_no, check_in, check_out,
            duration, duration_unit, room_price, total_amount, status, paid_status,
            extend_hours, extend_minutes, extend_price, extension_details
        ) VALUES (
            'TEST-BOOKING-001', :room_id, 'Test Guest', '1234567890', :check_in, :check_out,
            12, 'hours', 960, 960, 'Confirmed', 'Paid',
            0, 0, 0, NULL
        )
    ");
    $stmt->bindParam(':room_id', $testRoomId);
    $stmt->bindParam(':check_in', $checkIn);
    $stmt->bindParam(':check_out', $checkOut);
    $stmt->execute();
    
    $bookingId = $conn->lastInsertId();
    echo "<p>✓ Created test booking with ID: $bookingId</p>";
    
    // Step 2: Add first extension (12 hours)
    echo "<h3>Step 2: Adding first extension (12 hours, ₱960)...</h3>";
    
    $extension1 = [
        'hours' => 12,
        'minutes' => 0,
        'price' => 960,
        'regular_rate' => 960,
        'bundle_rate' => 0,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $extensionDetails = json_encode([$extension1]);
    $newCheckOut1 = date('Y-m-d H:i:s', strtotime($checkOut . ' +12 hours'));
    
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET extend_hours = 12,
            extend_minutes = 0,
            extend_price = 960,
            extension_details = :extension_details,
            check_out = :check_out,
            total_amount = 1920
        WHERE id = :id
    ");
    $stmt->bindParam(':extension_details', $extensionDetails);
    $stmt->bindParam(':check_out', $newCheckOut1);
    $stmt->bindParam(':id', $bookingId);
    $stmt->execute();
    
    echo "<p>✓ Added 12-hour extension</p>";
    echo "<p>New checkout: $newCheckOut1</p>";
    
    // Step 3: Add second extension (24 hours)
    echo "<h3>Step 3: Adding second extension (24 hours, ₱1490)...</h3>";
    
    $extension2 = [
        'hours' => 24,
        'minutes' => 0,
        'price' => 1490,
        'regular_rate' => 1490,
        'bundle_rate' => 0,
        'timestamp' => date('Y-m-d H:i:s', strtotime('+1 minute'))
    ];
    
    $extensionDetails = json_encode([$extension1, $extension2]);
    $newCheckOut2 = date('Y-m-d H:i:s', strtotime($newCheckOut1 . ' +24 hours'));
    
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET extend_hours = 36,
            extend_minutes = 0,
            extend_price = 2450,
            extension_details = :extension_details,
            check_out = :check_out,
            total_amount = 3410
        WHERE id = :id
    ");
    $stmt->bindParam(':extension_details', $extensionDetails);
    $stmt->bindParam(':check_out', $newCheckOut2);
    $stmt->bindParam(':id', $bookingId);
    $stmt->execute();
    
    echo "<p>✓ Added 24-hour extension</p>";
    echo "<p>New checkout: $newCheckOut2</p>";
    echo "<p>Total extension: 36 hours, ₱2450</p>";
    
    // Step 4: Verify current state
    echo "<h3>Step 4: Verifying current state...</h3>";
    
    $stmt = $conn->prepare("
        SELECT extend_hours, extend_minutes, extend_price, extension_details,
               withdrawn_extend_hours, withdrawn_extend_minutes, withdrawn_extend_price
        FROM bookings WHERE id = :id
    ");
    $stmt->bindParam(':id', $bookingId);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    echo "Active Extension: {$booking['extend_hours']} hours, ₱{$booking['extend_price']}\n";
    echo "Withdrawn Extension: {$booking['withdrawn_extend_hours']} hours, ₱{$booking['withdrawn_extend_price']}\n";
    echo "Extension Details: {$booking['extension_details']}\n";
    echo "</pre>";
    
    // Step 5: Withdraw the second extension (24 hours, index 1)
    echo "<h3>Step 5: Withdrawing second extension (24 hours, ₱1490)...</h3>";
    
    // Simulate the withdrawal request
    $_POST['booking_id'] = $bookingId;
    $_POST['selected_extension_index'] = 1; // Second extension
    $_POST['selected_extension_hours'] = 24;
    $_POST['selected_extension_minutes'] = 0;
    $_POST['selected_extension_price'] = 1490;
    
    // Include and execute the withdrawal script
    ob_start();
    include 'withdraw_extend_duration.php';
    $result = ob_get_clean();
    
    echo "<p>Withdrawal result:</p>";
    echo "<pre>$result</pre>";
    
    // Step 6: Verify final state
    echo "<h3>Step 6: Verifying final state...</h3>";
    
    $stmt = $conn->prepare("
        SELECT extend_hours, extend_minutes, extend_price, extension_details,
               withdrawn_extend_hours, withdrawn_extend_minutes, withdrawn_extend_price,
               check_out, total_amount
        FROM bookings WHERE id = :id
    ");
    $stmt->bindParam(':id', $bookingId);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    echo "Active Extension: {$booking['extend_hours']} hours, ₱{$booking['extend_price']}\n";
    echo "Withdrawn Extension: {$booking['withdrawn_extend_hours']} hours, ₱{$booking['withdrawn_extend_price']}\n";
    echo "Extension Details: {$booking['extension_details']}\n";
    echo "Check Out: {$booking['check_out']}\n";
    echo "Total Amount: ₱{$booking['total_amount']}\n";
    echo "</pre>";
    
    // Parse extension details
    $details = json_decode($booking['extension_details'], true);
    echo "<p><strong>Remaining Extensions:</strong></p>";
    if ($details && is_array($details)) {
        foreach ($details as $i => $ext) {
            echo "<p>Extension " . ($i + 1) . ": {$ext['hours']} hours, ₱{$ext['price']}</p>";
        }
    } else {
        echo "<p>No remaining extensions</p>";
    }
    
    // Verify expectations
    echo "<h3>Verification:</h3>";
    $passed = true;
    
    if ($booking['extend_hours'] != 12) {
        echo "<p style='color:red;'>✗ FAILED: Expected 12 active hours, got {$booking['extend_hours']}</p>";
        $passed = false;
    } else {
        echo "<p style='color:green;'>✓ PASSED: Active extension is 12 hours</p>";
    }
    
    if ($booking['extend_price'] != 960) {
        echo "<p style='color:red;'>✗ FAILED: Expected ₱960 active price, got ₱{$booking['extend_price']}</p>";
        $passed = false;
    } else {
        echo "<p style='color:green;'>✓ PASSED: Active extension price is ₱960</p>";
    }
    
    if ($booking['withdrawn_extend_hours'] != 24) {
        echo "<p style='color:red;'>✗ FAILED: Expected 24 withdrawn hours, got {$booking['withdrawn_extend_hours']}</p>";
        $passed = false;
    } else {
        echo "<p style='color:green;'>✓ PASSED: Withdrawn extension is 24 hours</p>";
    }
    
    if ($booking['withdrawn_extend_price'] != 1490) {
        echo "<p style='color:red;'>✗ FAILED: Expected ₱1490 withdrawn price, got ₱{$booking['withdrawn_extend_price']}</p>";
        $passed = false;
    } else {
        echo "<p style='color:green;'>✓ PASSED: Withdrawn extension price is ₱1490</p>";
    }
    
    $remainingExtensions = json_decode($booking['extension_details'], true);
    if (!$remainingExtensions || count($remainingExtensions) != 1) {
        echo "<p style='color:red;'>✗ FAILED: Expected 1 remaining extension, got " . count($remainingExtensions) . "</p>";
        $passed = false;
    } else {
        echo "<p style='color:green;'>✓ PASSED: 1 extension remains in extension_details</p>";
    }
    
    if ($passed) {
        echo "<h2 style='color:green;'>✓ ALL TESTS PASSED!</h2>";
    } else {
        echo "<h2 style='color:red;'>✗ SOME TESTS FAILED</h2>";
    }
    
    // Step 7: Cleanup
    echo "<h3>Step 7: Cleaning up test data...</h3>";
    
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = :id");
    $stmt->bindParam(':id', $bookingId);
    $stmt->execute();
    
    echo "<p>✓ Test booking deleted</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
