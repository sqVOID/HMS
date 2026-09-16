# Booking Payment System Fixes - Tasks

## Task 1: Verify Change Amount Calculation (Task 12)
**Status:** Testing Required

### Description
Verify that the change amount calculation fix is working correctly. The implementation is already in place, we just need to test it.

### Steps
1. Read the `confirmPaymentOptions` function in `Booking.html` (lines ~1376-1600)
2. Verify the change calculation logic is present:
   - `changeAmount = totalPayments - totalAmount`
   - `actualPaymentApplied = totalPayments - changeAmount`
   - Deposit breakdown recalculation using `depositRatio`
3. Test the functionality:
   - Create a booking with ₱200 balance
   - Pay ₱300 Cash (change ₱100)
   - Check database: deposit should be 200 (not 300)
   - Check UI: "Payment: -₱200.00"

### Acceptance Criteria
- [ ] Change calculation code is present in `confirmPaymentOptions`
- [ ] Database shows correct deposit amount (actual payment, not including change)
- [ ] Checkout modal shows correct Payment amount
- [ ] Deposit breakdown is proportionally adjusted

---

## Task 2: Fix confirmBooking Function Structure (Task 13 - Part 1)
**Status:** Not Started

### Description
Fix the `confirmBooking` function to eliminate the `ReferenceError: actualDeposit is not defined` error by restructuring the code to calculate deposit FIRST before using it.

### Steps
1. Read the `confirmBooking` function in `Booking.html` (lines 5420-5550)
2. Identify the duplicate auto-calculation code that references `actualDeposit` before it's defined
3. Remove the duplicate code (estimated lines ~5471-5520)
4. Ensure deposit calculation happens at the beginning of the function
5. Ensure the function respects `paidStatusOverride` parameter

### Implementation Details

**Current Structure (BROKEN):**
```javascript
function confirmBooking(id, options = {}) {
    // ... setup code ...
    
    // Line 5451: Uses actualDeposit before it's defined
    if (actualDeposit >= totalCharges) { ... }
    
    // Lines 5423-5470: Deposit calculation (happens AFTER usage)
    let actualDeposit = 0;
    // ... calculate actualDeposit ...
    
    // Lines 5471-5520: Duplicate auto-calculation code
    // ... more code that uses actualDeposit ...
}
```

**Target Structure (FIXED):**
```javascript
function confirmBooking(id, options = {}) {
    // ... setup code ...
    
    // STEP 1: Calculate deposit FIRST
    let actualDeposit = 0;
    let depositGcashRef = '';
    let depositMayaRef = '';
    let depositCash = 0;
    let depositGCash = 0;
    let depositMaya = 0;
    let depositDetailsArray = [];

    const paymentDataStr = /* extract from hidden fields */;
    if (paymentDataStr) {
        // Parse and calculate actualDeposit
    }
    
    // STEP 2: Calculate total charges
    let totalCharges = /* calculate from room + breakfast + guests */;
    
    // STEP 3: Auto-calculate paid_status (respect paidStatusOverride)
    if (paidStatusOverride !== null) {
        finalPaidStatus = paidStatusOverride;
    } else if (actualDeposit >= totalCharges && actualDeposit > 0) {
        finalPaidStatus = 'Paid';
    } else if (actualDeposit > 0) {
        finalPaidStatus = 'Unpaid';
    }
    
    // STEP 4: Set status based on mode
    // ... rest of logic ...
}
```

### Acceptance Criteria
- [ ] No `ReferenceError: actualDeposit is not defined` error
- [ ] Deposit is calculated before being used
- [ ] Duplicate auto-calculation code is removed
- [ ] Function respects `paidStatusOverride` parameter
- [ ] Console logs show correct calculation flow

---

## Task 3: Test Walk-in Mode Automatic Payment Status (Task 13 - Part 2)
**Status:** Not Started
**Depends On:** Task 2

### Description
Test that the Walk-in Mode automatic payment status setting works correctly after fixing the `confirmBooking` function.

### Steps
1. Verify `addBookingToDatabase` function has payment data extraction logic (lines 7950-8020)
2. Test the complete flow:
   - Click "Regular" or "Bundle" button in Walk-in Mode
   - Enter payment: ₱1300 Cash
   - Click "Confirm Payment"
   - Click "Ok" button
3. Verify database:
   - `paid_status = 'Paid'`
   - `deposit = 1300`
   - `deposit_cash = 1300`
   - `deposit_details = "1300.00 Cash"`
4. Open checkout modal
5. Verify UI shows "Payment: -₱1300.00"

### Test Cases

#### Test Case 1: Full Payment
- Room: ₱1300
- Payment: ₱1300 Cash
- Expected: paid_status = 'Paid', deposit = 1300

#### Test Case 2: Partial Payment
- Room: ₱1300
- Payment: ₱500 Cash
- Expected: paid_status = 'Unpaid', deposit = 500

#### Test Case 3: No Payment
- Room: ₱1300
- Payment: (none)
- Expected: paid_status = 'Unpaid', deposit = 0

#### Test Case 4: Overpayment
- Room: ₱1300
- Payment: ₱1500 Cash
- Expected: paid_status = 'Paid', deposit = 1500

### Acceptance Criteria
- [ ] Full payment sets paid_status to 'Paid'
- [ ] Partial payment sets paid_status to 'Unpaid'
- [ ] No payment sets paid_status to 'Unpaid'
- [ ] Deposit amount is correctly saved to database
- [ ] Deposit breakdown is correctly saved
- [ ] Checkout modal shows correct Payment amount
- [ ] Payment Status badge shows correct status in table

---

## Task 4: Test Multiple Payment Methods
**Status:** Not Started
**Depends On:** Task 2, Task 3

### Description
Test that the system correctly handles multiple payment methods (Cash + G-Cash + Maya) in Walk-in Mode.

### Steps
1. Click "Regular" button in Walk-in Mode
2. Enter payment:
   - ₱500 Cash
   - ₱500 G-Cash (ref: GC123)
   - ₱300 Maya (ref: MY456)
   - Total: ₱1300
3. Click "Confirm Payment"
4. Click "Ok" button
5. Verify database:
   - `deposit = 1300`
   - `deposit_cash = 500`
   - `deposit_g_cash = 500`
   - `deposit_maya = 300`
   - `deposit_details = "500.00 Cash, 500.00 G-cash, 300.00 Maya"`
   - `deposit_gcash_ref = "GC123"`
   - `deposit_maya_ref = "MY456"`
   - `paid_status = 'Paid'`

### Acceptance Criteria
- [ ] Multiple payment methods are correctly parsed
- [ ] Deposit breakdown is correctly calculated
- [ ] Reference numbers are correctly saved
- [ ] deposit_details string is correctly formatted
- [ ] Total deposit equals sum of all methods

---

## Task 5: Integration Testing
**Status:** Not Started
**Depends On:** Task 2, Task 3, Task 4

### Description
Perform end-to-end integration testing of the complete booking payment flow.

### Test Scenarios

#### Scenario 1: Walk-in Full Payment
1. Walk-in Mode → Regular button
2. Fill booking details (guest name, etc.)
3. Enter payment: ₱1300 Cash
4. Click "Confirm Payment" → Click "Ok"
5. Verify booking appears in table with "Paid" status
6. Click "Checkout" button
7. Verify checkout modal shows correct amounts
8. Complete checkout
9. Verify reports table has correct data

#### Scenario 2: Walk-in Partial Payment + Additional Payment
1. Walk-in Mode → Regular button
2. Enter payment: ₱500 Cash
3. Click "Confirm Payment" → Click "Ok"
4. Verify booking shows "Unpaid" status
5. Click "Payment Options" button
6. Enter additional payment: ₱800 Cash
7. Verify total paid = ₱1300
8. Verify status changes to "Paid"

#### Scenario 3: Walk-in Overpayment with Change
1. Walk-in Mode → Regular button (₱1300 room)
2. Enter payment: ₱1500 Cash
3. Click "Confirm Payment" → Click "Ok"
4. Verify deposit = ₱1500 (overpayment allowed)
5. Verify paid_status = 'Paid'

### Acceptance Criteria
- [ ] All scenarios complete without errors
- [ ] Database data is consistent across all tables
- [ ] UI displays correct information at each step
- [ ] Payment calculations are accurate
- [ ] Status transitions are correct

---

## Task 6: Documentation and Cleanup
**Status:** Not Started
**Depends On:** Task 5

### Description
Document the fixes and clean up any debug code.

### Steps
1. Add comments to the fixed code sections
2. Remove or comment out excessive console.log statements
3. Update any relevant documentation files
4. Create a summary of changes made

### Deliverables
- [ ] Code comments added to key sections
- [ ] Debug logging reduced to essential messages
- [ ] Summary document created (optional)

---

## Summary

**Total Tasks:** 6
**Completed:** 0
**In Progress:** 0
**Not Started:** 6

**Critical Path:**
1. Task 2 (Fix confirmBooking structure) - MUST BE DONE FIRST
2. Task 3 (Test Walk-in Mode) - Depends on Task 2
3. Task 4 (Test multiple payment methods) - Depends on Task 2, 3
4. Task 5 (Integration testing) - Depends on all previous tasks

**Task 1 (Verify change calculation)** can be done independently and in parallel.
