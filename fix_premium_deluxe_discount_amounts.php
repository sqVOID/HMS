<?php
// Fix Premium 2 and Deluxe 24-hour discount amounts in existing bookings
require_once 'db_connection.php';

header('Content-Type: application/json');

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Find all bookings with discount enabled for Premium 2 or Deluxe at 24 hours
    $sql = "SELECT id, room_type, room_price, duration, duration_unit, discount_enabled, 
                   discount_type, sc_pwd_count, discount_amount
            FROM bookings 
            WHERE discount_enabled = 1 
            AND duration = 24 
            AND duration_unit = 'hours'
            AND discount_type = 'regular'
            AND sc_pwd_count > 0
            AND (LOWER(room_type) LIKE '%premium 2%' 
                 OR LOWER(room_type) LIKE '%premium2%'
                 OR LOWER(room_type) LIKE '%deluxe%')";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $fixed_bookings = [];
    $skipped_bookings = [];
    
    while ($booking = $result->fetch_assoc()) {
        $roomTypeLower = strtolower(trim($booking['room_type']));
        $isPremium2 = strpos($roomTypeLower, 'premium 2') !== false || strpos($roomTypeLower, 'premium2') !== false;
        $isDeluxe = strpos($roomTypeLower, 'deluxe') !== false;
        
        $correctDiscount = 0;
        $roomTypeLabel = '';
        
        if ($isPremium2) {
            $correctDiscount = 125;
            $roomTypeLabel = 'Premium 2';
        } else if ($isDeluxe) {
            $correctDiscount = 133;
            $roomTypeLabel = 'Deluxe';
        }
        
        // Only update if the discount amount is incorrect
        if ($correctDiscount > 0 && $booking['discount_amount'] != $correctDiscount) {
            $updateSql = "UPDATE bookings 
                         SET discount_amount = ? 
                         WHERE id = ?";
            
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("di", $correctDiscount, $booking['id']);
            
            if ($stmt->execute()) {
                $fixed_bookings[] = [
                    'id' => $booking['id'],
                    'room_type' => $booking['room_type'],
                    'old_discount' => $booking['discount_amount'],
                    'new_discount' => $correctDiscount,
                    'room_type_label' => $roomTypeLabel
                ];
            }
            
            $stmt->close();
        } else {
            $skipped_bookings[] = [
                'id' => $booking['id'],
                'room_type' => $booking['room_type'],
                'discount_amount' => $booking['discount_amount'],
                'reason' => 'Already correct'
            ];
        }
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Discount amounts fixed successfully',
        'fixed_count' => count($fixed_bookings),
        'skipped_count' => count($skipped_bookings),
        'fixed_bookings' => $fixed_bookings,
        'skipped_bookings' => $skipped_bookings
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
