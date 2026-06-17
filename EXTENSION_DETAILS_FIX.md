# Extension Details Persistence Fix

## Problem
When extending a booking multiple times with payments in between, only the last extension was showing in the withdraw modal. The previous extensions were being lost.

### Scenario:
1. Book a room
2. Extend 12 hours for ₱960
3. Pay the ₱960 (Amount Due becomes ₱0)
4. Extend again 24 hours for ₱1490
5. Open Withdraw Extension modal
6. **Problem**: Only shows "Extension 1: 24 hr (₱1490.00)"
7. **Expected**: Should show both extensions:
   - Extension 1: 12 hr (₱960.00)
   - Extension 2: 24 hr (₱1490.00)

## Root Cause
The SELECT query in `save_extend_duration.php` was NOT retrieving the `extension_details` column from the database. This meant:
- When reading existing booking data, `extension_details` was always NULL
- When appending new extension, it started with an empty array instead of the existing array
- Previous extension history was lost

## Solution
Added `extension_details` to the SELECT query in `save_extend_duration.php`:

### Before:
```php
$stmt = $conn->prepare("SELECT booking_id, room_id, check_in, check_out, duration, duration_unit, 
                        extend_hours, extend_minutes, extend_price, extend_regular_rate, 
                        extend_bundle_rate, extend_bundle_breakfast, total_amount, encoder, 
                        extension_time_at
                        FROM bookings WHERE id = :id");
```

### After:
```php
$stmt = $conn->prepare("SELECT booking_id, room_id, check_in, check_out, duration, duration_unit, 
                        extend_hours, extend_minutes, extend_price, extend_regular_rate, 
                        extend_bundle_rate, extend_bundle_breakfast, total_amount, encoder, 
                        extension_time_at, extension_details
                        FROM bookings WHERE id = :id");
```

## How It Works Now

### Extension Flow:
1. **First Extension (12 hours, ₱960)**:
   - System reads `extension_details` from database (empty/NULL)
   - Creates array: `[]`
   - Appends new extension: `[{"hours": 12, "minutes": 0, "price": 960, "timestamp": "2026-05-18 10:00:00"}]`
   - Saves to database

2. **Payment**:
   - `extend_price` becomes 0 (paid)
   - `extend_hours` and `extend_minutes` remain (36 total)
   - **`extension_details` remains unchanged** (still has the 12 hr record)

3. **Second Extension (24 hours, ₱1490)**:
   - System reads `extension_details` from database: `[{"hours": 12, ...}]`
   - Appends new extension: `[{"hours": 12, ...}, {"hours": 24, "minutes": 0, "price": 1490, "timestamp": "2026-05-18 15:30:00"}]`
   - Saves to database

4. **Withdraw Extension Modal**:
   - Reads `extension_details` from booking object
   - Parses JSON array
   - Shows both extensions:
     - Extension 1: 12 hr (₱960.00) - 05/18/2026 10:00 AM
     - Extension 2: 24 hr (₱1490.00) - 05/18/2026 3:30 PM

## Key Points

1. **`extension_details` is permanent history**:
   - Never cleared by payments
   - Never cleared by withdrawals
   - Only grows with new extensions

2. **`extend_price` is current unpaid amount**:
   - Becomes 0 when paid
   - Shows only the latest unpaid extension price

3. **`extend_hours` and `extend_minutes` are accumulated totals**:
   - Sum of all extensions
   - Used for display and check-out calculation

## Testing

Run the test script to verify:
```bash
php test_extension_details_persistence.php
```

Or visit in browser:
```
http://localhost/HMS/test_extension_details_persistence.php
```

## Manual Testing Steps

1. Create a new booking
2. Extend it (e.g., 12 hours for ₱960)
3. Pay the extension amount
4. Extend it again (e.g., 24 hours for ₱1490)
5. Click "Withdraw Extension" button
6. Verify both extensions are listed:
   - Extension 1: 12 hr (₱960.00)
   - Extension 2: 24 hr (₱1490.00)

## Files Modified

1. `save_extend_duration.php` - Added `extension_details` to SELECT query

## Related Files

- `add_extension_details_column.php` - Migration script
- `Booking.html` - Withdraw extension modal UI
- `test_extension_details_persistence.php` - Test script
- `WITHDRAW_EXTENSION_FEATURE.md` - Feature documentation
