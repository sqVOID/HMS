# Final Extension Payment Fix - Complete Solution

## Problem Summary
When a reservation booking with a downpayment had an extension added, the system showed "Paid" status instead of "Unpaid", and did not display the Amount Due for the extension.

### Example Scenarios (ALL BROKEN):
1. Reservation + ₱500 downpayment + ₱1000 payment + ₱200 extension → Showed "Paid" ❌
2. Reservation + ₱500 downpayment + ₱1000 payment + ₱500 extension → Showed "Paid" ❌
3. Reservation + ₱500 downpayment + ₱1000 payment + ₱600 extension → Showed "Unpaid" with ₱100 due ❌

## Root Causes Found

### Issue 1: Frontend Display Logic (Booking.html)
The frontend was incorrectly calculating that extensions were "paid" for reservations because it didn't check if there was a downpayment.

**Location:** `Booking.html` lines ~14600-14640

**Problem Code:**
```javascript
const extensionHappenedAfterPayment = extendSubtotal > 0 && totalPayments >= baseBookingCostWithoutExtend;
const shouldForceSubtotalZero = (!isReservationMode && !isReservedUnpaidBooking && 
                                 totalPayments >= chargesAfterDiscount && 
                                 !extensionHappenedAfterPayment);
```

### Issue 2: Backend Calculation Logic (save_extend_duration.php)
The backend was calculating the total amount by subtracting the full deposit (which includes downpayment + additional payments) from the total charges. This caused the extension to appear as if it was already paid.

**Location:** `save_extend_duration.php` lines ~193-213

**Problem Code:**
```php
$newTotalAmount = computeBookingTotalAmount([
    'extend_price'    => $totalExtendPrice,
    'deposit'         => floatval($booking['deposit'] ?? 0), // This includes downpayment + payments
]);

$newPaidStatus = ($newTotalAmount > 0.01) ? 'Unpaid' : ($booking['paid_status'] ?? 'Paid');
```

**Why it failed:**
- Room price: ₱1500
- Deposit (downpayment ₱500 + payment ₱1000): ₱1500
- Extension: ₱200
- Calculation: (₱1500 + ₱200) - ₱1500 = ₱200 ✓ (Correct amount)
- BUT: If deposit was somehow equal to or greater than the total, it would set status to "Paid"

## Solutions Applied

### Fix 1: Frontend Display Logic (Booking.html)

**Added downpayment check:**
```javascript
const hasDownpayment = downpaymentAmount > 0;
const extensionHappenedAfterPayment = extendSubtotal > 0 && 
                                      !hasDownpayment && 
                                      totalPayments >= baseBookingCostWithoutExtend;
```

**Added unpaid extension check:**
```javascript
const hasUnpaidExtension = extendSubtotal > 0 && hasDownpayment;
const shouldForceSubtotalZero = (!isReservationMode && 
                                 !isReservedUnpaidBooking && 
                                 totalPayments >= chargesAfterDiscount && 
                                 !extensionHappenedAfterPayment && 
                                 !hasUnpaidExtension);
```

**Logic:** For reservations with downpayment, extensions are ALWAYS treated as unpaid.

### Fix 2: Backend Calculation Logic (save_extend_duration.php)

**Separate calculation for reservations with downpayment:**
```php
$hasDownpayment = $downpaymentAmount > 0.01;

if ($hasDownpayment && $totalExtendPrice > 0) {
    // Calculate base booking total (room + breakfast, minus deposit)
    $baseBookingTotal = computeBookingTotalAmount([
        'extend_price'    => 0, // Don't include extension in base calculation
        'deposit'         => $currentDeposit,
    ]);
    
    // Add the unpaid extension on top
    $newTotalAmount = $baseBookingTotal + $totalExtendPrice;
} else {
    // Walk-in or no downpayment: use normal calculation
    $newTotalAmount = computeBookingTotalAmount([
        'extend_price'    => $totalExtendPrice,
        'deposit'         => $currentDeposit,
    ]);
}

// Mark as Unpaid if there's any remaining balance OR if there's an unpaid extension
$hasUnpaidExtension = $hasDownpayment && $totalExtendPrice > 0.01;
$newPaidStatus = ($newTotalAmount > 0.01 || $hasUnpaidExtension) ? 'Unpaid' : ($booking['paid_status'] ?? 'Paid');
```

**Logic:** 
1. For reservations with downpayment, calculate the base booking total separately
2. Add the extension price on top (not included in the base calculation)
3. Always mark as "Unpaid" if there's a downpayment and an extension

## Files Modified

1. **Booking.html** (lines ~14600-14640)
   - Added `hasDownpayment` check
   - Modified `extensionHappenedAfterPayment` logic
   - Added `hasUnpaidExtension` check
   - Modified `shouldForceSubtotalZero` logic

2. **save_extend_duration.php** (lines ~193-240)
   - Added downpayment detection
   - Separate calculation path for reservations with downpayment
   - Modified `newPaidStatus` logic to check for unpaid extensions

## Expected Behavior After Fix

### Reservation Bookings:
- ✅ Reservation + ₱200 extension → Shows "Unpaid" with ₱200 due
- ✅ Reservation + ₱500 extension → Shows "Unpaid" with ₱500 due
- ✅ Reservation + ₱600 extension → Shows "Unpaid" with ₱600 due

### Walk-in Bookings:
- ✅ Walk-in + ₱200 extension → Shows "Unpaid" with ₱200 due
- ✅ Walk-in fully paid, no extension → Shows "Paid" with ₱0.00 due

## Testing Instructions

1. **Clear browser cache** (Ctrl+Shift+Delete)
2. **Hard refresh** the page (Ctrl+F5)
3. Test the scenarios:
   - Create a reservation with ₱500 downpayment
   - Pay the remaining ₱1000
   - Add an extension (₱200, ₱500, or ₱600)
   - Open the booking
   - **Verify:** Status shows "Unpaid" (red badge)
   - **Verify:** Amount Due shows the extension price
   - **Verify:** Subtotal matches the extension price

## Key Points

1. **Reservations with downpayment + extension**: Extension is ALWAYS unpaid
2. **Walk-in bookings**: Continue to work as before
3. **Backend and Frontend**: Both fixed to ensure consistency
4. **Amount Due**: Always shows the extension price for reservations
5. **Payment Status**: Always "Unpaid" when there's an unpaid extension

## Technical Details

### Why the separate calculation?
For reservations with downpayment:
- The downpayment is for the BASE booking (room + breakfast)
- The extension is an ADDITIONAL charge added later
- They should be calculated separately to avoid confusion

### Why check both frontend and backend?
- **Frontend**: Controls what the user sees in the UI
- **Backend**: Controls what's saved to the database
- Both must be consistent to avoid display/data mismatches
