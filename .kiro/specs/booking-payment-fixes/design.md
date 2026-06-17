# Booking Payment System Fixes - Design

## 1. Architecture Overview

### 1.1 System Components
```
┌─────────────────────────────────────────────────────────────┐
│                     Booking.html (Frontend)                  │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────┐   │
│  │  confirmPaymentOptions()                             │   │
│  │  - Calculate change amount                           │   │
│  │  - Deduct change from deposit                        │   │
│  │  - Recalculate breakdown proportionally              │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  addBookingToDatabase()                              │   │
│  │  - Extract payment data from hidden fields           │   │
│  │  - Calculate total payment vs total charges          │   │
│  │  - Set paidStatusOverride to 'Paid' or 'Unpaid'     │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  confirmBooking()                                    │   │
│  │  - Calculate deposit from payment data FIRST         │   │
│  │  - Auto-calculate paid_status based on deposit       │   │
│  │  - Respect paidStatusOverride if provided            │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              confirm_booking.php (Backend)                   │
├─────────────────────────────────────────────────────────────┤
│  - Receive deposit fields from POST                          │
│  - Separate deposit and downpayment based on status          │
│  - Calculate paid_status based on total payments             │
│  - Save to database                                          │
└─────────────────────────────────────────────────────────────┘
```

## 2. Component Design

### 2.1 Change Amount Calculation (Task 12)

**Status:** IMPLEMENTED - Needs verification only

**Location:** `Booking.html` - `confirmPaymentOptions()` function (lines ~1520-1560)

**Current Implementation:**
```javascript
// Calculate change amount
const changeAmount = Math.max(0, totalPayments - totalAmount);

// Calculate actual payment applied (subtract change)
const actualPaymentApplied = totalPayments - changeAmount;

// Recalculate deposit breakdown to reflect actual payment
const depositRatio = totalPayments > 0 ? actualPaymentApplied / totalPayments : 0;
const actualDepositCash = depositCash * depositRatio;
const actualDepositGCash = depositGCash * depositRatio;
const actualDepositMaya = depositMaya * depositRatio;

// Send actualPaymentApplied to backend instead of totalPayments
requestBody += `&deposit=${actualPaymentApplied.toFixed(2)}`;
requestBody += `&deposit_cash=${actualDepositCash.toFixed(2)}`;
requestBody += `&deposit_g_cash=${actualDepositGCash.toFixed(2)}`;
requestBody += `&deposit_maya=${actualDepositMaya.toFixed(2)}`;
```

**Verification Steps:**
1. Create booking with ₱200 balance
2. Pay ₱300 (change ₱100)
3. Verify database shows deposit = 200 (not 300)
4. Verify checkout modal shows "Payment: -₱200.00"

### 2.2 Walk-in Mode Automatic Payment Status (Task 13)

#### 2.2.1 addBookingToDatabase() Function

**Status:** IMPLEMENTED - Needs verification

**Location:** `Booking.html` lines 7950-8020

**Current Implementation:**
```javascript
// Check if payment data exists in hidden fields
const depositDataStr = document.getElementById('depositPaymentData') ? 
    document.getElementById('depositPaymentData').value : 
    (document.getElementById('editDepositPaymentData') ? 
        document.getElementById('editDepositPaymentData').value : '');

let totalPayment = 0;
let hasPaymentData = false;

if (depositDataStr) {
    try {
        const payments = JSON.parse(depositDataStr);
        if (Array.isArray(payments) && payments.length > 0) {
            hasPaymentData = true;
            totalPayment = payments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
        }
    } catch (e) {
        console.error('Error parsing payment data:', e);
    }
}

// Calculate total charges
let totalCharges = parseFloat(booking.room_price) || 0;
// ... add breakfast and guest charges ...

// Determine paid status
let paidStatusOverride = 'Unpaid';
if (hasPaymentData && totalPayment >= totalCharges) {
    paidStatusOverride = 'Paid';
}

// Call confirmBooking with paidStatusOverride
const success = await confirmBooking(id, { 
    bypassConfirmPrompt: true, 
    paidStatusOverride: paidStatusOverride, 
    statusOverride: statusOverride 
});
```

**Issue:** This logic is correct, but `confirmBooking` has a bug that prevents it from working.

#### 2.2.2 confirmBooking() Function - Bug Fix

**Status:** NEEDS FIX

**Location:** `Booking.html` lines 5420-5550

**Problem:**
- `actualDeposit` is referenced at line 5451 before it's defined
- Deposit calculation code exists at lines 5423-5470
- Duplicate auto-calculation code at lines 5471-5520 tries to use `actualDeposit` before it's calculated

**Solution:**
1. **Remove duplicate auto-calculation code** (lines ~5471-5520)
2. **Restructure the function** to calculate deposit FIRST, then use it
3. **Respect paidStatusOverride** from `addBookingToDatabase`

**Proposed Structure:**
```javascript
async function confirmBooking(id, options = {}) {
    // ... existing code ...
    
    // STEP 1: Calculate deposit from payment data FIRST
    let actualDeposit = 0;
    let depositGcashRef = '';
    let depositMayaRef = '';
    let depositCash = 0;
    let depositGCash = 0;
    let depositMaya = 0;
    let depositDetailsArray = [];

    const downpaymentDataStr = document.getElementById('downpaymentData') ? 
        document.getElementById('downpaymentData').value : '';
    const depositDataStr = document.getElementById('editDepositPaymentData') ? 
        document.getElementById('editDepositPaymentData').value : 
        (document.getElementById('depositPaymentData') ? 
            document.getElementById('depositPaymentData').value : '');

    const paymentDataStr = downpaymentDataStr || depositDataStr;

    if (paymentDataStr) {
        try {
            const paymentData = JSON.parse(paymentDataStr);
            if (Array.isArray(paymentData)) {
                paymentData.forEach(p => {
                    const amount = parseFloat(p.amount) || 0;
                    actualDeposit += amount;

                    if (p.method === 'Cash') {
                        depositCash += amount;
                        depositDetailsArray.push(`${amount.toFixed(2)} Cash`);
                    }
                    if (p.method === 'G-cash') {
                        depositGCash += amount;
                        if (p.reference) depositGcashRef = p.reference;
                        depositDetailsArray.push(`${amount.toFixed(2)} G-cash`);
                    }
                    if (p.method === 'Maya') {
                        depositMaya += amount;
                        if (p.reference) depositMayaRef = p.reference;
                        depositDetailsArray.push(`${amount.toFixed(2)} Maya`);
                    }
                });
            }
        } catch (e) {
            console.error('Error parsing payment data', e);
            actualDeposit = deposit; // Fallback
        }
    } else {
        actualDeposit = deposit; // Fallback
    }
    
    // STEP 2: Calculate total charges
    let totalCharges = 0;
    if (booking.room_price) {
        totalCharges = parseFloat(booking.room_price) || 0;
    }
    
    // Add breakfast charges
    if (breakfast && breakfast !== 'None' && breakfast !== 'Select Breakfast') {
        const breakfastItems = breakfast.split('|');
        breakfastItems.forEach(item => {
            const trimmedItem = item.trim();
            if (trimmedItem && trimmedItem !== 'None' && trimmedItem !== 'Select Breakfast') {
                const priceMatch = trimmedItem.match(/₱([\d,]+\.?\d*)/);
                if (priceMatch) {
                    totalCharges += parseFloat(priceMatch[1].replace(/,/g, ''));
                }
            }
        });
    }
    
    // Add additional guest charges
    const additionalGuestCount = parseInt(additionalGuest) || 0;
    if (additionalGuestCount > 0) {
        totalCharges += additionalGuestCount * 300;
    }
    
    // STEP 3: Auto-calculate paid_status (respect paidStatusOverride if provided)
    if (paidStatusOverride !== null) {
        finalPaidStatus = paidStatusOverride;
        console.log('✓ Using paidStatusOverride:', paidStatusOverride);
    } else if (actualDeposit >= totalCharges && actualDeposit > 0) {
        finalPaidStatus = 'Paid';
        console.log('✓ Auto-set paid_status to Paid (deposit >= total charges)');
    } else if (actualDeposit > 0 && actualDeposit < totalCharges) {
        finalPaidStatus = 'Unpaid';
        console.log('✓ Auto-set paid_status to Unpaid (partial payment)');
    }
    
    // STEP 4: Set status based on mode and payment
    if (statusOverride !== 'Confirmed' && paidStatusOverride === null && !forceConfirm) {
        // Only ask if no payment was made
        if (actualDeposit === 0) {
            const customerPaid = confirm('Did the customer pay? Click OK for Yes, Cancel for No.');
            finalPaidStatus = customerPaid ? 'Paid' : 'Unpaid';
        }

        // Set status based on mode
        if (isReservationMode) {
            finalStatus = finalPaidStatus === 'Paid' ? 'Reserved' : 'Confirming';
        } else {
            finalStatus = finalPaidStatus === 'Paid' ? 'Confirmed' : 'Confirming';
        }
    } else if (statusOverride === 'Confirmed') {
        finalStatus = 'Confirmed';
    }
    
    // ... rest of the function ...
}
```

### 2.3 Backend - confirm_booking.php

**Status:** ALREADY CORRECT - No changes needed

**Current Implementation:**
- Lines 488-505: Calculate `full_booking_amount` and set `paid_status` based on `total_payments >= full_booking_amount`
- Lines 632-695: Separate deposit and downpayment based on `$isReservation` flag
- For Walk-in mode: Use deposit columns
- For Reservation mode: Use downpayment columns

**This logic is correct and doesn't need changes.**

## 3. Data Flow

### 3.1 Walk-in Mode Payment Flow

```
User clicks "Regular" or "Bundle"
    ↓
Payment modal opens
    ↓
User enters payment (e.g., ₱1300 Cash)
    ↓
User clicks "Confirm Payment"
    ↓
confirmDepositPayment() stores payment in hidden field
    ↓
User clicks "Ok" button
    ↓
addBookingToDatabase() extracts payment data
    ↓
addBookingToDatabase() calculates paidStatusOverride
    ↓
confirmBooking() receives paidStatusOverride
    ↓
confirmBooking() calculates actualDeposit from payment data
    ↓
confirmBooking() respects paidStatusOverride
    ↓
confirm_booking.php saves deposit to database
    ↓
Booking created with correct paid_status
```

### 3.2 Change Amount Flow

```
User pays ₱300 on ₱200 balance
    ↓
confirmPaymentOptions() calculates changeAmount = ₱100
    ↓
actualPaymentApplied = ₱300 - ₱100 = ₱200
    ↓
depositRatio = ₱200 / ₱300 = 0.6667
    ↓
Recalculate breakdown:
  - actualDepositCash = depositCash * 0.6667
  - actualDepositGCash = depositGCash * 0.6667
  - actualDepositMaya = depositMaya * 0.6667
    ↓
Send actualPaymentApplied (₱200) to backend
    ↓
update_payment_status.php saves ₱200 to deposit
    ↓
Checkout modal shows "Payment: -₱200.00"
```

## 4. Error Handling

### 4.1 Payment Data Parsing Errors
- Wrap JSON.parse in try-catch
- Fallback to booking.deposit if parsing fails
- Log error to console for debugging

### 4.2 Missing Payment Data
- Check if hidden fields exist before accessing
- Default to 0 if no payment data found
- Set paid_status to 'Unpaid' if no payment

### 4.3 Invalid Payment Amounts
- Validate payment amounts are positive numbers
- Use parseFloat with fallback to 0
- Prevent negative deposits

## 5. Testing Strategy

### 5.1 Task 12 Verification (Change Calculation)
1. Create booking with ₱200 balance
2. Pay ₱300 Cash (change ₱100)
3. Verify database: deposit = 200, deposit_cash = 200
4. Verify UI: "Payment: -₱200.00"

### 5.2 Task 13 Testing (Walk-in Mode)
1. Walk-in Mode → Regular button
2. Enter payment: ₱1300 Cash
3. Click "Confirm Payment"
4. Click "Ok" button
5. Verify database: paid_status = 'Paid', deposit = 1300
6. Open checkout modal
7. Verify UI: "Payment: -₱1300.00"

### 5.3 Edge Cases
- No payment entered (should be Unpaid)
- Partial payment (should be Unpaid)
- Overpayment with change (should deduct change)
- Multiple payment methods (should calculate correctly)

## 6. Correctness Properties

### Property 1: Change Deduction
**Validates: Requirements 2.1**

For all bookings where payment > balance:
- actualPaymentApplied = payment - change
- deposit_saved = actualPaymentApplied
- deposit_saved <= balance

### Property 2: Paid Status Accuracy
**Validates: Requirements 2.2**

For all Walk-in bookings:
- IF total_payment >= total_charges THEN paid_status = 'Paid'
- IF total_payment < total_charges THEN paid_status = 'Unpaid'
- IF total_payment = 0 THEN paid_status = 'Unpaid'

### Property 3: Deposit Persistence
**Validates: Requirements 2.3**

For all Walk-in bookings with payment:
- deposit_in_db = actualPaymentApplied
- deposit_cash + deposit_gcash + deposit_maya = deposit_in_db
- deposit_details contains formatted payment string

## 7. Implementation Notes

### 7.1 Code Removal
Remove duplicate auto-calculation code in `confirmBooking` function (lines ~5471-5520) that references `actualDeposit` before it's defined.

### 7.2 Variable Scope
Ensure `actualDeposit` is calculated at the beginning of `confirmBooking` function before any code tries to use it.

### 7.3 Override Precedence
The `paidStatusOverride` parameter should take precedence over auto-calculated paid_status.

### 7.4 Logging
Add console.log statements for debugging:
- Payment data extraction
- Paid status calculation
- Override application
