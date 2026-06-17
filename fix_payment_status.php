<?php
// Script to fix payment status for bookings where Total Amount Due is 0
require_once 'config.php';
require_once 'report_helpers.php';

try {
    // Get all bookings
    $stmt = $conn->query("
        SELECT 
            id, 
            booking_id,
            room_price, 
            duration, 
            duration_unit, 
            promo, 
            breakfast, 
            hygiene_kit_used, 
            hygiene_kit_price,
            deposit,
            discount_amount,
            additional_guest,
            additional_pet,
            room_type,
            paid_status
        FROM bookings 
        WHERE status IN ('Confirming', 'Confirmed', 'Reserved', 'Occupied', 'Extended')
    ");
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    
    foreach ($bookings as $booking) {
        // Calculate room total
        $room_total = computeBookingTotalAmount([
            'room_type' => $booking['room_type'] ?? '',
            'duration' => intval($booking['duration'] ?? 0),
            'duration_unit' => $booking['duration_unit'] ?? 'hours',
            'promo' => $booking['promo'] ?? null,
            'breakfast' => $booking['breakfast'] ?? null,
            'hygiene_kit_used' => intval($booking['hygiene_kit_used'] ?? 0),
            'hygiene_kit_price' => floatval($booking['hygiene_kit_price'] ?? 0),
            'room_price' => floatval($booking['room_price'] ?? 0),
            'deposit' => 0  // Don't deduct deposit for calculation
        ]);
        
        // Add additional charges
        $guest_charges = intval($booking['additional_guest'] ?? 0) * 300;
        $pet_charges = intval($booking['additional_pet'] ?? 0) * 500;
        
        $full_booking_amount = $room_total + $guest_charges + $pet_charges;
        
        // Apply discount
        $discount_amount = floatval($booking['discount_amount'] ?? 0);
        $full_booking_amount_after_discount = $full_booking_amount - $discount_amount;
        
        // Get deposit (payment)
        $deposit = floatval($booking['deposit'] ?? 0);
        
        // Calculate amount due
        $amount_due = $full_booking_amount_after_discount - $deposit;
        
        // Determine correct paid_status
        $correct_paid_status = 'Unpaid';
        if ($amount_due <= 0 && $deposit > 0) {
            $correct_paid_status = 'Paid';
        }
        
        // Update if status is wrong
        if ($booking['paid_status'] !== $correct_paid_status) {
            $updateStmt = $conn->prepare("UPDATE bookings SET paid_status = :paid_status WHERE id = :id");
            $updateStmt->bindParam(':paid_status', $correct_paid_status);
            $updateStmt->bindParam(':id', $booking['id']);
            $updateStmt->execute();
            
            echo "Updated booking {$booking['booking_id']}: {$booking['paid_status']} -> {$correct_paid_status} (Amount Due: ₱" . number_format($amount_due, 2) . ")<br>";
            $updated++;
        }
    }
    
    echo "<br><strong>Total bookings updated: $updated</strong>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
