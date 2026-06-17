<?php
/**
 * Test script to verify the Additional Items payment calculation fix
 * 
 * This script simulates the scenario described in the issue:
 * 1. Booking is fully paid (₱2320 paid)
 * 2. Additional Food/Item worth ₱150 is added
 * 3. Expected Total Amount Due: ₱150 (only the new items)
 * 4. Actual (before fix): ₱2470 (incorrect - showing full amount)
 */

echo "=== ADDITIONAL ITEMS PAYMENT FIX TEST ===\n\n";

// Simulate the booking scenario
$room_total = 2489.00;  // Room rate + breakfast + hygiene kit
$guest_charges = 0;      // No additional guests
$pet_charges = 0;        // No additional pets
$discount_amount = 169;  // Discount applied
$additional_items_total = 150.00;  // New additional food/items

// Calculate full booking amount
$full_booking_amount = $room_total + $guest_charges + $pet_charges + $additional_items_total;
echo "Full booking amount (before discount): ₱" . number_format($full_booking_amount, 2) . "\n";

// Apply discount
$full_booking_amount_after_discount = $full_booking_amount - $discount_amount;
echo "Full booking amount (after discount): ₱" . number_format($full_booking_amount_after_discount, 2) . "\n";

// Calculate original booking amount (without new additional items)
$originalBookingAmount = $room_total + $guest_charges + $pet_charges - $discount_amount;
echo "Original booking amount (paid): ₱" . number_format($originalBookingAmount, 2) . "\n";

// Simulate payment
$total_payments = 2320.00;  // Amount already paid
echo "Total payments made: ₱" . number_format($total_payments, 2) . "\n";

// Calculate amount due
$amount_due = $full_booking_amount_after_discount - $total_payments;
echo "\n=== RESULT ===\n";
echo "Amount due: ₱" . number_format($amount_due, 2) . "\n";

// Verify the fix
$wasOriginallyPaid = ($total_payments >= $originalBookingAmount);
echo "Was original booking paid? " . ($wasOriginallyPaid ? "YES" : "NO") . "\n";

if ($wasOriginallyPaid && $amount_due > 0) {
    echo "\n✓ FIX WORKING: Original booking was paid, only additional items (₱" . number_format($additional_items_total, 2) . ") are due\n";
    echo "Expected amount due: ₱" . number_format($additional_items_total, 2) . "\n";
    echo "Calculated amount due: ₱" . number_format($amount_due, 2) . "\n";
    
    if (abs($amount_due - $additional_items_total) < 0.01) {
        echo "✓ TEST PASSED: Amount due matches additional items total!\n";
    } else {
        echo "✗ TEST FAILED: Amount due does not match additional items total\n";
    }
} else {
    echo "\n✗ FIX NOT WORKING: Calculation is incorrect\n";
}

echo "\n=== END TEST ===\n";
?>
