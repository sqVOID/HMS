# Extension Withdrawal Report Fix - Summary

## Problem
When an extension was withdrawn from a booking, the reports showed the wrong total amount:
- **Checkout modal**: Correctly showed ₱960 (room price only, after refund)
- **Reports**: Incorrectly showed ₱1,920 (original amount including withdrawn extension)

## Root Cause
The issue had TWO parts:

### Part 1: Extension Charges Calculation
The `getTotalExtensionChargesForBooking()` function in `payment_amount_calculator.php` was not checking if an extension had been withdrawn. It was returning the sum of `extend_regular_rate` + `extend_bundle_rate` even when `extend_price` was 0 and `extension_withdraw` was 1.

### Part 2: Payment History Not Updated
When an extension was withdrawn in `update_booking.php`, the payment history columns were not being updated:
- `payment_date_time` still had 2 timestamps (room payment + extension payment)
- `payment_amount_cash_history` still had 2 amounts (960|960)
- This caused the reports to sum both payments (960 + 960 = 1920) instead of just the room payment (960)

## Solution

### Fix 1: Update `getTotalExtensionChargesForBooking()` Function
**File**: `c:\xampp\htdocs\HMS\payment_amount_calculator.php`

Added a check for `extension_withdraw` flag:
```php
function getTotalExtensionChargesForBooking(array $booking_data): float
{
    // CRITICAL FIX: Check if extension has been withdrawn
    $extensionWithdrawn = intval($booking_data['extension_withdraw'] ?? 0);
    
    // If extension was withdrawn, return 0 (no extension charges apply)
    if ($extensionWithdrawn === 1) {
        return 0.0;
    }
    
    // ... rest of the function
}
```

### Fix 2: Remove Last Payment from Payment History
**File**: `c:\xampp\htdocs\HMS\update_booking.php`

Added logic to remove the last payment timestamp and amounts from payment history when an extension is withdrawn:
```php
// CRITICAL FIX: Remove the last payment from payment history
// When an extension is withdrawn, the last payment (extension payment) should be removed

$getHistStmt = $conn->prepare("
    SELECT 
        payment_date_time,
        payment_amount_cash_history,
        payment_amount_g_cash_history,
        payment_amount_maya_history,
        payment_amount_instapay_history,
        payment_amount_online_banking_history,
        payment_amount_airbnb_history
    FROM bookings
    WHERE id = :id
");
$getHistStmt->execute([':id' => $booking_id]);
$histRow = $getHistStmt->fetch(PDO::FETCH_ASSOC);

// Helper function to remove last segment from pipe-delimited string
$removeLastSegment = function($historyString) {
    if (empty($historyString)) {
        return null;
    }
    $segments = explode('|', $historyString);
    if (count($segments) <= 1) {
        return null;
    }
    array_pop($segments);
    return implode('|', $segments);
};

// Remove last segment from each history column
$newPaymentDateTime = $removeLastSegment($histRow['payment_date_time']);
$newCashHistory = $removeLastSegment($histRow['payment_amount_cash_history']);
// ... (same for other payment methods)

// Update both bookings and reports tables
```

### Fix 3: Manual Fix for Existing Data
**File**: `c:\xampp\htdocs\HMS\fix_payment_history_withdrawal.php`

Created a script to fix the specific booking that already had the issue:
- Removed the last payment timestamp from `payment_date_time`
- Removed the last payment amount from all `payment_amount_*_history` columns
- Updated both `bookings` and `reports` tables

## Testing

### Test Results
**Before Fix**:
- Extension charges: 960 (WRONG - should be 0)
- Net paid amount: 1920 (WRONG - should be 960)
- Payment history: 960|960 (2 payments)

**After Fix**:
- Extension charges: 0 ✓ (CORRECT)
- Net paid amount: 960 ✓ (CORRECT)
- Payment history: 960 (1 payment)

### Test Files Created
1. `test_extension_fix.php` - Tests the bookings table
2. `test_reports_table.php` - Tests the reports table
3. `check_payment_history.php` - Checks payment history columns
4. `fix_reports_deposit.php` - Fixes deposit values in reports table
5. `fix_payment_history_withdrawal.php` - Fixes payment history for withdrawn extensions

## Impact
- **Reports now correctly show ₱960** instead of ₱1,920 after extension withdrawal
- **Future extension withdrawals** will automatically update payment history
- **Checkout modal and reports** now show consistent amounts

## Files Modified
1. `c:\xampp\htdocs\HMS\payment_amount_calculator.php` - Added extension_withdraw check
2. `c:\xampp\htdocs\HMS\update_booking.php` - Added payment history removal logic

## Files Created
1. `c:\xampp\htdocs\HMS\test_extension_fix.php`
2. `c:\xampp\htdocs\HMS\test_reports_table.php`
3. `c:\xampp\htdocs\HMS\check_payment_history.php`
4. `c:\xampp\htdocs\HMS\fix_reports_deposit.php`
5. `c:\xampp\htdocs\HMS\fix_payment_history_withdrawal.php`
6. `c:\xampp\htdocs\HMS\EXTENSION_WITHDRAWAL_FIX_SUMMARY.md` (this file)

## Date
May 18, 2026
