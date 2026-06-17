# Cash Column Date Filtering Fix

## Problem
When exporting daily sales for a single date (e.g., 14/05/2026 only), the Cash column was showing the FULL payment amount (800) instead of only the payment made on that specific date (450). However, the Total Amount Booking column was correctly showing 450.

### Example Issue:
- Booking has 2 payments: 13/05/2026 (800 Cash) and 14/05/2026 (450 Cash)
- Export 13-14/05/2026: Shows 2 rows (800, 450) ✓ CORRECT
- Export 14/05/2026 only: Shows 1 row with Cash=800 ✗ WRONG (should be 450)
- Total Amount Booking showed 450 (correct), but Cash column showed 800 (wrong)

## Root Cause
The `allocateRangePaymentByMethod()` function was NOT correctly filtering payment history by date range:

1. It built `$timestampsInRange` containing only timestamps within the filter range
2. But it compared `count($cashHistory) === $nTimestamps` where:
   - `$cashHistory` = ALL payment history entries (e.g., ['800', '450'])
   - `$nTimestamps` = count of timestamps IN RANGE (e.g., 1 for 14th only)
3. Since 2 !== 1, it fell back to using deposit + downpayment, which gave the FULL amount

### Example:
```
Booking payments: 13th (800 Cash), 14th (450 Cash)
Filter: 14/05/2026 only

OLD LOGIC:
- $timestampsInRange = [14th] (count = 1)
- $cashHistory = ['800', '450'] (count = 2)
- count($cashHistory) === $nTimestamps? NO (2 !== 1)
- Fallback: Use deposit_cash = 800 ✗ WRONG

NEW LOGIC:
- $allTimestamps = [13th, 14th] (count = 2)
- $timestampIndicesInRange = [1] (only index 1 is in range)
- $cashHistory = ['800', '450'] (count = 2)
- count($cashHistory) === count($allTimestamps)? YES (2 === 2)
- Extract only indices in range: $cashHistory[1] = 450 ✓ CORRECT
```

## Solution
Modified `allocateRangePaymentByMethod()` in `export_daily_sales.php`:

### Changes:
1. **Track ALL timestamps and their indices**:
   - Build `$allTimestamps` array with ALL payment timestamps
   - Build `$timestampIndicesInRange` array with indices of timestamps in the filter range
   - This allows us to map history entries to their correct timestamps

2. **Extract only amounts for timestamps in range**:
   - Created `$extractAmountsInRange()` helper function
   - If history matches all timestamps, extract only the amounts at indices in range
   - Handles edge cases like missing downpayment in history (n-1 entries)

3. **Removed incorrect override**:
   - Removed the fix at line 2165-2177 that was replacing all payment methods with cash
   - That fix was wrong because it assumed all payments were cash

### Code Changes:
```php
// OLD: Only tracked timestamps in range
$timestampsInRange = [];
if ($dateStr >= $filterStart && $dateStr <= $filterEnd) {
    $timestampsInRange[] = $dt;
}
$nTimestamps = count($timestampsInRange);

// NEW: Track ALL timestamps and which are in range
$allTimestamps = [];
$timestampIndicesInRange = [];
$currentIndex = 0;
// ... build both arrays ...
$nTimestamps = count($allTimestamps);
$nTimestampsInRange = count($timestampIndicesInRange);

// NEW: Extract only amounts for timestamps in range
$extractAmountsInRange = function($historyArray) use ($timestampIndicesInRange, $nTimestamps) {
    if (count($historyArray) === $nTimestamps) {
        $sum = 0.0;
        foreach ($timestampIndicesInRange as $idx) {
            if (isset($historyArray[$idx])) {
                $sum += floatval($historyArray[$idx]);
            }
        }
        return $sum;
    }
    // ... handle edge cases ...
};
```

## Testing
Test the following scenarios:

1. **Export 13-14/05/2026**: Should show 2 rows (800, 450) ✓
2. **Export 14/05/2026 only**: Should show 1 row with Cash=450 ✓
3. **Export 13/05/2026 only**: Should show 1 row with Cash=800 ✓
4. **Multiple payment methods**: Verify GCash, Maya, etc. are also filtered correctly
5. **Additional fees**: Verify they still filter by their date columns

## Files Modified
- `c:\xampp\htdocs\HMS\export_daily_sales.php`
  - Modified `allocateRangePaymentByMethod()` function (lines ~355-550)
  - Removed incorrect override at lines ~2165-2177

## Related Issues
- Additional fees date filtering: COMPLETED (separate fix)
- Payment date filtering: COMPLETED (this fix)
- Both fixes work together to ensure accurate date-based reporting
