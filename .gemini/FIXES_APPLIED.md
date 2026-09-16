# Booking Modal Charges Breakdown Fixes

## Date: 2026-01-28

## Issues Fixed

### Issue 1: Auto-display of payment amount when selecting duration
**Problem**: When editing a booking with status "Reserved" or "Reservation" mode, selecting a duration would automatically display a payment amount (like ₱1000.00) instead of remaining at ₱0.00.

**Root Cause**: The `updateEditChargesBreakdown` function was incorrectly retrieving deposit/downpayment values from old database records instead of checking if a payment was actually confirmed through the payment modal.

**Solution**: Modified the deposit calculation logic in `updateEditChargesBreakdown` (lines 7311-7374) to:
- Check if deposit/downpayment was confirmed via the payment modal
- For Reservation mode: Only use downpayment if `downpaymentStatus` input has value "confirmed"
- For Walk-in mode (Available/Confirming): Only use deposit if `depositPaymentStatus` input has value "confirmed"
- Force deposit to 0 for new bookings until payment is actually confirmed

### Issue 2: Incorrect Subtotal calculation when editing payment
**Problem**: When editing the payment amount (e.g., changing from auto-displayed ₱1000.00 to ₱500.00), the subtotal would be incorrect.

**Root Cause**: The subtotal was calculated using stale deposit data instead of the actual confirmed payment amount.

**Solution**: 
1. Enhanced the deposit calculation to properly parse confirmed payment data from modal inputs
2. Added automatic charges breakdown updates when payment is confirmed:
   - `confirmDepositPayment` now calls `updateEditChargesBreakdown` after payment confirmation
   - `confirmDownpayment` now calls `updateReservationBreakdownSubtotal` after payment confirmation

## Files Modified

- **c:\xampp\htdocs\HMS\Booking.html**
  - Lines 7311-7374: Enhanced `updateEditChargesBreakdown` deposit calculation logic
  - Lines 10084-10096: Added charges breakdown update to `confirmDepositPayment`
  - Lines 10291-10299: Added reservation breakdown update to `confirmDownpayment`

## Testing Recommendations

1. **Reservation Mode Test**:
   - Create new reservation with Available status
   - Select duration - confirm Payment shows ₱0.00
   - Confirm downpayment via modal
   - Verify subtotal updates correctly

2. **Walk-in Mode Test**:
   - Edit booking with Available/Confirming status
   - Select duration - confirm Payment shows ₱0.00
   - Confirm deposit via modal with amount (e.g., ₱500.00)
   - Verify subtotal = (Room Rate + Breakfast + Additional) - ₱500.00

3. **Reserved Status Test**:
   - Edit existing Reserved booking
   - Verify existing downpayment displays correctly
   - Verify subtotal calculation is accurate

## Expected Behavior After Fix

✅ Selecting duration shows Payment: ₱0.00 by default
✅ Payment amount only appears after confirming via payment modal
✅ Subtotal = (Room Rate + Extras) - Confirmed Payment
✅ Editing payment amount immediately updates subtotal
✅ No auto-population of amounts from old database records
