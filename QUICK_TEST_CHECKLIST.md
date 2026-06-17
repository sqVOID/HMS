# Quick Test Checklist - Extension Payment Fix

## Before Testing
- [ ] Clear browser cache (Ctrl+Shift+Delete)
- [ ] Hard refresh the page (Ctrl+F5)
- [ ] Ensure you're logged in to the system

## Test Case 1: Reservation + ₱200 Extension
**Steps:**
1. [ ] Create a new reservation booking (12 hours, ₱1500)
2. [ ] Add downpayment: ₱500
3. [ ] Confirm the reservation
4. [ ] Pay the remaining ₱1000
5. [ ] Click "Extend Duration"
6. [ ] Add 1 hour extension (₱200)
7. [ ] Click "Submit"
8. [ ] Refresh the page
9. [ ] Open the booking again

**Expected Results:**
- [ ] Payment Status badge shows: **"Unpaid"** (RED)
- [ ] Total Amount Due shows: **"₱200.00"** (RED box at bottom)
- [ ] Charges Breakdown shows:
  - Room Rate: ₱1500.00
  - Extended (1 Hour): ₱200.00
  - Reservation: -₱500.00
  - Payment: -₱1000.00
  - Subtotal: **₱200.00**

## Test Case 2: Reservation + ₱500 Extension
**Steps:**
1. [ ] Create a new reservation booking (12 hours, ₱1500)
2. [ ] Add downpayment: ₱500
3. [ ] Confirm the reservation
4. [ ] Pay the remaining ₱1000
5. [ ] Click "Extend Duration"
6. [ ] Add extension worth ₱500
7. [ ] Click "Submit"
8. [ ] Refresh the page
9. [ ] Open the booking again

**Expected Results:**
- [ ] Payment Status badge shows: **"Unpaid"** (RED)
- [ ] Total Amount Due shows: **"₱500.00"** (RED box at bottom)
- [ ] Subtotal: **₱500.00**

## Test Case 3: Reservation + ₱600 Extension
**Steps:**
1. [ ] Create a new reservation booking (12 hours, ₱1500)
2. [ ] Add downpayment: ₱500
3. [ ] Confirm the reservation
4. [ ] Pay the remaining ₱1000
5. [ ] Click "Extend Duration"
6. [ ] Add extension worth ₱600
7. [ ] Click "Submit"
8. [ ] Refresh the page
9. [ ] Open the booking again

**Expected Results:**
- [ ] Payment Status badge shows: **"Unpaid"** (RED)
- [ ] Total Amount Due shows: **"₱600.00"** (RED box at bottom)
- [ ] Subtotal: **₱600.00**

## Test Case 4: Walk-in + Extension (Control Test)
**Steps:**
1. [ ] Create a new walk-in booking (12 hours, ₱1500)
2. [ ] Pay ₱1500 immediately
3. [ ] Confirm the booking
4. [ ] Click "Extend Duration"
5. [ ] Add 1 hour extension (₱200)
6. [ ] Click "Submit"
7. [ ] Refresh the page
8. [ ] Open the booking again

**Expected Results:**
- [ ] Payment Status badge shows: **"Unpaid"** (RED)
- [ ] Total Amount Due shows: **"₱200.00"** (RED box at bottom)
- [ ] Subtotal: **₱200.00**

## Test Case 5: Walk-in No Extension (Control Test)
**Steps:**
1. [ ] Create a new walk-in booking (12 hours, ₱1500)
2. [ ] Pay ₱1500 immediately
3. [ ] Confirm the booking
4. [ ] Open the booking again

**Expected Results:**
- [ ] Payment Status badge shows: **"Paid"** (GREEN)
- [ ] Total Amount Due: **NOT visible** or shows "₱0.00"
- [ ] Subtotal: **₱0.00**

## Browser Console Checks

Open the browser console (F12) and check for these logs when opening a booking with extension:

```
=== EXTENSION AFTER PAYMENT CHECK ===
hasDownpayment: true
extensionHappenedAfterPayment: false
hasUnpaidExtension: true
shouldForceSubtotalZero: false
```

For reservations with downpayment + extension, these values should match the above.

## Common Issues

### Issue: Still showing "Paid" status
**Solution:**
1. Clear browser cache completely
2. Hard refresh (Ctrl+F5)
3. Check if the files were saved correctly
4. Verify the changes in both Booking.html and save_extend_duration.php

### Issue: Amount Due not showing
**Solution:**
1. Check browser console for JavaScript errors
2. Verify the subtotal calculation in console logs
3. Ensure the modal is displaying the ChargesBreakdownEdit section

### Issue: Wrong amount showing
**Solution:**
1. Check the console logs for the calculation steps
2. Verify the deposit and downpayment amounts in the database
3. Check if the extension price is correct

## Success Criteria

✅ **ALL test cases pass**
✅ **Console logs show correct values**
✅ **No JavaScript errors in console**
✅ **Payment status updates correctly in database**
✅ **Amount Due displays correctly**

## If Tests Fail

1. Document which test case failed
2. Take screenshots of:
   - The booking modal
   - The browser console logs
   - The database values (bookings table)
3. Check the FINAL_EXTENSION_FIX.md for troubleshooting steps
4. Verify both Booking.html and save_extend_duration.php were modified correctly
