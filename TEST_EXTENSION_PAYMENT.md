# Testing Guide: Extension Payment Status Fix

## Quick Test Steps

### Test 1: Reservation with ₱200 Extension
1. Create a new reservation booking
2. Set room rate: ₱1500 (12 hours)
3. Add downpayment: ₱500
4. Confirm the reservation
5. Open the booking and click "Extend Duration"
6. Add 1 hour extension (₱200)
7. Click "Submit"
8. Open the booking again
9. **Expected Result:**
   - Payment Status: **Unpaid** (red badge)
   - Amount Due: **₱200.00** should be visible
   - Subtotal: **₱200.00**

### Test 2: Reservation with ₱500 Extension
1. Create a new reservation booking
2. Set room rate: ₱1500 (12 hours)
3. Add downpayment: ₱500
4. Confirm the reservation
5. Pay the remaining ₱1000
6. Open the booking and click "Extend Duration"
7. Add extension worth ₱500
8. Click "Submit"
9. Open the booking again
10. **Expected Result:**
    - Payment Status: **Unpaid** (red badge)
    - Amount Due: **₱500.00** should be visible
    - Subtotal: **₱500.00**

### Test 3: Reservation with ₱600 Extension
1. Create a new reservation booking
2. Set room rate: ₱1500 (12 hours)
3. Add downpayment: ₱500
4. Confirm the reservation
5. Pay the remaining ₱1000
6. Open the booking and click "Extend Duration"
7. Add extension worth ₱600
8. Click "Submit"
9. Open the booking again
10. **Expected Result:**
    - Payment Status: **Unpaid** (red badge)
    - Amount Due: **₱600.00** should be visible
    - Subtotal: **₱600.00**

### Test 4: Walk-in with Extension (Control Test)
1. Create a new walk-in booking
2. Set room rate: ₱1500 (12 hours)
3. Pay ₱1500 immediately
4. Confirm the booking
5. Open the booking and click "Extend Duration"
6. Add 1 hour extension (₱200)
7. Click "Submit"
8. Open the booking again
9. **Expected Result:**
   - Payment Status: **Unpaid** (red badge)
   - Amount Due: **₱200.00** should be visible
   - Subtotal: **₱200.00**

### Test 5: Walk-in without Extension (Control Test)
1. Create a new walk-in booking
2. Set room rate: ₱1500 (12 hours)
3. Pay ₱1500 immediately
4. Confirm the booking
5. Open the booking again
6. **Expected Result:**
   - Payment Status: **Paid** (green badge)
   - Amount Due: **NOT visible** (or ₱0.00)
   - Subtotal: **₱0.00**

## What Was Fixed

### Before the Fix:
- Reservation + ₱200 extension → Showed "Paid" ❌
- Reservation + ₱500 extension → Showed "Paid" ❌
- Reservation + ₱600 extension → Showed "Unpaid" with ₱100 due ❌

### After the Fix:
- Reservation + ₱200 extension → Shows "Unpaid" with ₱200 due ✅
- Reservation + ₱500 extension → Shows "Unpaid" with ₱500 due ✅
- Reservation + ₱600 extension → Shows "Unpaid" with ₱600 due ✅

## Key Points

1. **Reservations with downpayment + extension**: Extension is ALWAYS unpaid
2. **Walk-in bookings**: Work as before (no changes)
3. **Amount Due**: Should always show the extension price for reservations
4. **Payment Status**: Should be "Unpaid" when there's an unpaid extension

## Console Logs to Check

When opening a booking with extension, check the browser console for:
```
=== EXTENSION AFTER PAYMENT CHECK ===
hasDownpayment: true
extensionHappenedAfterPayment: false
hasUnpaidExtension: true
shouldForceSubtotalZero: false
```

For reservations with downpayment + extension, these values should match the above.

## Troubleshooting

If the fix doesn't work:
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh the page (Ctrl+F5)
3. Check browser console for JavaScript errors
4. Verify the changes in Booking.html were saved correctly
5. Check the console logs match the expected values above
