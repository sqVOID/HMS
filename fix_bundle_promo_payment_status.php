<?php
// Script to fix payment status for Bundle/Promo bookings where Total Amount Due is 0 but paid_status is Unpaid
require_once 'config.php';
require_once 'report_helpers.php';

echo "Starting Bundle/Promo payment status fix...\n\n";

try {
    // Get all bookings with promo that have paid_status = 'Unpaid'
    $stmt = $conn->query("
        SELECT 
            id, 
            booking_id, 
            promo, 
            room_price,
            breakfast,
            hygiene_kit_used,
            hygiene_kit_price,
            deposit,
            downpayment_amount,
            total_amount,
            paid_status,
            discount_enabled,
            discount_amount
        FROM bookings 
        WHERE promo IS NOT NULL 
        AND promo != '' 
        AND promo != 'None'
        AND promo != 'Select Promo'
        ORDER BY id DESC
    ");
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $fixed_count = 0;
    $already_correct = 0;
    
    echo "Found " . count($bookings) . " Bundle/Promo bookings to check.\n\n";
    
    foreach ($bookings as $booking) {
        $booking_id = $booking['booking_id'] ?? 'N/A';
        $promo = $booking['promo'];
        $room_price = floatval($booking['room_price'] ?? 0);
        $breakfast = $booking['breakfast'];
        $hygiene_kit_used = intval($booking['hygiene_kit_used'] ?? 0);
        $hygiene_kit_price = floatval($booking['hygiene_kit_price'] ?? 0);
        $deposit = floatval($booking['deposit'] ?? 0);
        $downpayment_amount = floatval($booking['downpayment_amount'] ?? 0);
        $total_amount = floatval($booking['total_amount'] ?? 0);
        $current_paid_status = $booking['paid_status'];
        $discount_enabled = intval($booking['discount_enabled'] ?? 0);
        $discount_amount = floatval($booking['discount_amount'] ?? 0);
        
        // Parse promo to get price
        $promoMeta = parsePromoSelection($promo);
        $promoPrice = $promoMeta['price'] ?? 0.0;
        
        // Parse breakfast to get price
        $breakfastMeta = parseBreakfastSelection($breakfast);
        $breakfastPrice = $breakfastMeta['price'] ?? 0.0;
        
        // CRITICAL FIX: For promo bookings, hygiene kit and breakfast are included in promo price
        // So their individual prices should be 0 when calculating the total
        $hygieneKitPriceForCalculation = 0; // Always 0 for promo bookings
        
        // Calculate full booking amount (without deposit deduction)
        $full_booking_amount = $promoPrice + $breakfastPrice + $hygieneKitPriceForCalculation;
        
        // Apply discount if enabled
        $full_booking_amount_after_discount = $full_booking_amount - $discount_amount;
        
        // Calculate total payments
        $total_payments = $deposit + $downpayment_amount;
        
        // Calculate amount due
        $amount_due = $full_booking_amount_after_discount - $total_payments;
        
        // Determine correct paid_status
        $correct_paid_status = 'Unpaid';
        if ($full_booking_amount_after_discount <= 0) {
            // Nothing to pay (free promo or fully discounted)
            $correct_paid_status = 'Paid';
        } elseif ($amount_due <= 0) {
            // Fully paid
            $correct_paid_status = 'Paid';
        }
        
        echo "Booking ID: $booking_id\n";
        echo "  Promo: $promo\n";
        echo "  Promo Price: ₱" . number_format($promoPrice, 2) . "\n";
        echo "  Breakfast Price: ₱" . number_format($breakfastPrice, 2) . "\n";
        echo "  Hygiene Kit: ₱" . number_format($hygiene_kit_used ? $hygiene_kit_price : 0, 2) . "\n";
        echo "  Discount: ₱" . number_format($discount_amount, 2) . "\n";
        echo "  Full Amount (after discount): ₱" . number_format($full_booking_amount_after_discount, 2) . "\n";
        echo "  Total Payments: ₱" . number_format($total_payments, 2) . "\n";
        echo "  Amount Due: ₱" . number_format($amount_due, 2) . "\n";
        echo "  Current paid_status: $current_paid_status\n";
        echo "  Correct paid_status: $correct_paid_status\n";
        
        if ($current_paid_status !== $correct_paid_status) {
            // Update the booking
            $updateStmt = $conn->prepare("
                UPDATE bookings 
                SET paid_status = :paid_status 
                WHERE id = :id
            ");
            $updateStmt->bindParam(':paid_status', $correct_paid_status);
            $updateStmt->bindParam(':id', $booking['id'], PDO::PARAM_INT);
            
            if ($updateStmt->execute()) {
                echo "  ✓ FIXED: Updated paid_status from '$current_paid_status' to '$correct_paid_status'\n";
                $fixed_count++;
                
                // Also update reports table if it exists
                try {
                    $updateReportStmt = $conn->prepare("
                        UPDATE reports 
                        SET paid_status = :paid_status 
                        WHERE booking_id = :booking_id
                    ");
                    $updateReportStmt->bindParam(':paid_status', $correct_paid_status);
                    $updateReportStmt->bindParam(':booking_id', $booking_id);
                    $updateReportStmt->execute();
                    echo "  ✓ Also updated reports table\n";
                } catch (PDOException $e) {
                    // Reports table might not exist or no matching record
                }
            } else {
                echo "  ✗ FAILED to update\n";
            }
        } else {
            echo "  ✓ Already correct\n";
            $already_correct++;
        }
        
        echo "\n";
    }
    
    echo "========================================\n";
    echo "SUMMARY:\n";
    echo "  Total Bundle/Promo bookings checked: " . count($bookings) . "\n";
    echo "  Fixed: $fixed_count\n";
    echo "  Already correct: $already_correct\n";
    echo "========================================\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
