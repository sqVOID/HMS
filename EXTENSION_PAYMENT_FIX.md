# Extension Payment Status Fix

## Problem Description

When a reservation booking with a downpayment (e.g., ₱500) had an extension added (e.g., ₱200), the system incorrectly showed the payment status as "Paid" instead of "Unpaid", and did not display the Amount Due.

### Specific Issues:
1. **Reservation + ₱500 downpayment + ₱200 extension** → Showed "Paid" (WRONG)
2. **Reservation + ₱500 downpayment + ₱500 extension** → Showed "Paid" (WRONG)
3. **Reservation + ₱500 downpayment + ₱600 extension** → Showed "Unpaid" with ₱100 due (CORRECT, but inconsistent)
4. **Walk-in bookings** → Worked correctly

## Root Cause

The issue was in the `updateEditChargesBreakdown()` function in `Booking.html`. The logic that determined if an extension was paid or unpaid had two problems:

### Problem 1: `extensionHappenedAfterPayment` Logic
```javascript
// OLD (WRONG):
const extensionHappenedAfterPayment = extendSubtotal > 0 && totalPayments >= baseBookingCostWithoutExtend;
```

This logic incorrectly treated extensions as "paid" for reservations because:
- `totalPayments` = downpayment (₱500) + remaining payment (₱1000) = ₱1500
- `baseBookingCostWithoutExtend` = room rate (₱1500)
- Since ₱1500 >= ₱1500, it thought the extension was already paid

### Problem 2: `shouldForceSubtotalZero` Logic
```javascript
// OLD (WRONG):
const shouldForceSubtotalZero = (!isReservationMode && !isReservedUnpaidBooking && 
                                 totalPayments >= chargesAfterDiscount && 
                                 !extensionHappenedAfterPayment);
```

This logic forced the subtotal to zero when total payments covered the charges, even when there was an unpaid extension for reservations.

## Solution

### Fix 1: Check for Downpayment
```javascript
// NEW (CORRECT):
const hasDownpayment = downpaymentAmount > 0;
const extensionHappenedAfterPayment = extendSubtotal > 0 && 
                                      !hasDownpayment && 
                                      totalPayments >= baseBookingCostWithoutExtend;
```

**Logic**: For reservations with a downpayment, extensions are ALWAYS unpaid because:
- The downpayment is for the base booking (room rate)
- The extension is an additional charge added later
- Only walk-in bookings (no downpayment) can have extensions treated as "paid" if the payment covers the base cost

### Fix 2: Add Unpaid Extension Check
```javascript
// NEW (CORRECT):
const hasUnpaidExtension = extendSubtotal > 0 && hasDownpayment;
const shouldForceSubtotalZero = (!isReservationMode && 
                                 !isReservedUnpaidBooking && 
                                 totalPayments >= chargesAfterDiscount && 
                                 !extensionHappenedAfterPayment && 
                                 !hasUnpaidExtension);
```

**Logic**: Don't force subtotal to zero if there's an unpaid extension (reservation with downpayment + extension).

## Test Scenarios

| Scenario | Room Rate | Downpayment | Deposit | Extension | Expected Subtotal | Expected Status |
|----------|-----------|-------------|---------|-----------|-------------------|-----------------|
| Reservation + ₱200 ext | ₱1500 | ₱500 | ₱1000 | ₱200 | ₱200 | Unpaid |
| Reservation + ₱500 ext | ₱1500 | ₱500 | ₱1000 | ₱500 | ₱500 | Unpaid |
| Reservation + ₱600 ext | ₱1500 | ₱500 | ₱1000 | ₱600 | ₱600 | Unpaid |
| Walk-in + ₱200 ext | ₱1500 | ₱0 | ₱1500 | ₱200 | ₱200 | Unpaid |
| Walk-in, no ext | ₱1500 | ₱0 | ₱1500 | ₱0 | ₱0 | Paid |

## Files Modified

1. **Booking.html** (lines ~14580-14620)
   - Modified `extensionHappenedAfterPayment` calculation
   - Modified `shouldForceSubtotalZero` calculation

## Testing

To test the fix:
1. Open `test_extension_payment_logic.html` in a browser
2. Verify all test scenarios pass
3. Test in the actual booking system:
   - Create a reservation with downpayment
   - Add an extension
   - Verify "Unpaid" status and correct Amount Due display

## Impact

- **Reservations with extensions**: Now correctly show "Unpaid" status and display Amount Due
- **Walk-in bookings**: No change, continue to work correctly
- **Backend**: No changes needed, the fix is purely frontend display logic
