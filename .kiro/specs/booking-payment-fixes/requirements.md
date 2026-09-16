# Booking Payment System Fixes - Requirements

## 1. Overview
Fix remaining issues with the booking payment system, specifically:
- Change amount calculation when overpaying
- Walk-in Mode automatic payment status setting
- Deposit saving when clicking Ok button in Walk-in Mode

## 2. User Stories

### 2.1 Change Amount Deduction (Task 12)
**As a** hotel staff member  
**I want** the system to correctly calculate and save the actual payment amount when there's change  
**So that** the Payment field shows the correct amount that was applied to the booking

**Acceptance Criteria:**
- When user pays ₱300 on ₱200 balance (change ₱100), system saves ₱200 to deposit
- Payment breakdown is recalculated proportionally based on actual payment applied
- Change amount is subtracted from total payment before saving to database
- Checkout modal shows "Payment: -₱200.00" (not -₱300.00)

### 2.2 Walk-in Mode Automatic Payment Status (Task 13)
**As a** hotel staff member  
**I want** the system to automatically set payment status to "Paid" when I confirm full payment in Walk-in Mode  
**So that** I don't have to manually update the payment status after booking

**Acceptance Criteria:**
- When clicking "Regular" or "Bundle" button in Walk-in Mode
- And confirming payment (e.g., ₱1300 Cash) in the payment modal
- And clicking "Ok" button
- Then booking is saved with `paid_status = 'Paid'` if payment >= total charges
- And booking is saved with `paid_status = 'Unpaid'` if payment < total charges
- And checkout modal shows "Payment: -₱1300.00" in Charges Breakdown

### 2.3 Deposit Saving in Walk-in Mode
**As a** hotel staff member  
**I want** the deposit amount and breakdown to be saved when I click Ok in Walk-in Mode  
**So that** the payment information is correctly stored in the database

**Acceptance Criteria:**
- Payment data from `depositPaymentData` or `editDepositPaymentData` hidden fields is extracted
- Deposit amount, breakdown (cash/gcash/maya), and reference numbers are sent to backend
- `confirm_booking.php` correctly saves deposit fields to database
- Checkout modal displays the saved deposit amount

## 3. Technical Context

### 3.1 Current Issues

#### Issue 1: Change Calculation (FIXED - needs verification)
- Location: `Booking.html` lines ~1520-1560 in `confirmPaymentOptions` function
- Fix already implemented: Calculate `actualPaymentApplied = totalPayments - changeAmount`
- Recalculate deposit breakdown using ratio
- Status: **IMPLEMENTED - NEEDS TESTING**

#### Issue 2: Walk-in Mode Payment Status
- Location: `Booking.html` lines 7950-8020 in `addBookingToDatabase` function
- Problem: Logic to check payment data and set `paidStatusOverride` is implemented
- But `confirmBooking` function has duplicate code causing `ReferenceError: actualDeposit is not defined`
- Location of error: `Booking.html` line 5451

#### Issue 3: Duplicate Code in confirmBooking
- Location: `Booking.html` lines 5420-5550
- Problem: `actualDeposit` is referenced at line 5451 before it's defined
- Deposit calculation code exists at lines 5423-5470
- Duplicate auto-calculation code at lines 5471-5520 tries to use `actualDeposit` before it's calculated

### 3.2 Files Involved
- `Booking.html` - Frontend JavaScript functions
- `confirm_booking.php` - Backend booking creation
- `checkout_booking.php` - Backend checkout process

## 4. Dependencies
- Task 12 fix (change calculation) is already implemented
- Task 13 requires fixing the `confirmBooking` function structure

## 5. Out of Scope
- Editing existing bookings (covered in previous tasks)
- Reservation mode payment (covered in previous tasks)
- Payment Options modal (covered in Task 10)
