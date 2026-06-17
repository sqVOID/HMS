# Withdraw Extension Feature - Implementation Summary

## Overview
Enhanced the withdraw extension functionality to show individual extensions that were actually made, allowing users to select which specific extension to withdraw.

## Changes Made

### 1. Database Schema
**File:** `add_extension_details_column.php`
- Added `extension_details` column to `bookings` table
- Added `extension_details` column to `reports` table
- Stores individual extension records as JSON array
- Format: `[{"hours": 12, "minutes": 0, "price": 960, "regular_rate": 0, "bundle_rate": 0, "timestamp": "2026-01-18 10:10:00"}, ...]`

### 2. Backend Updates
**File:** `save_extend_duration.php`
- Added logic to store each extension as a separate entry in `extension_details` JSON array
- Each extension record includes:
  - `hours`: Extension hours
  - `minutes`: Extension minutes
  - `price`: Extension price
  - `regular_rate`: Regular rate if applicable
  - `bundle_rate`: Bundle rate if applicable
  - `timestamp`: When the extension was made
- Updated both `bookings` and `reports` table UPDATE statements to include `extension_details`

### 3. Frontend Updates
**File:** `Booking.html`

#### Modal UI Changes:
- Updated withdraw extension modal to show:
  - Total extension summary at the top
  - Radio button list of individual extensions
  - Each extension shows:
    - Extension number (Extension 1, Extension 2, etc.)
    - Duration (e.g., "12 hr", "24 hr")
    - Price (e.g., "₱960.00")
    - Timestamp when extension was made
  - Live preview section showing:
    - Selected duration to withdraw
    - Refund amount
    - Current check-out time
    - New check-out time after withdrawal
  - Disabled "Withdraw Extension" button until selection is made

#### JavaScript Functions:
- `openWithdrawExtensionModal()`: Opens modal and loads extension history
- `generateWithdrawExtensionOptionsFromHistory()`: Creates radio options from actual extension records
- `createWithdrawOption()`: Creates individual radio button option with extension details
- `selectWithdrawOption()`: Handles selection and updates preview
- `confirmWithdrawExtension()`: Confirms withdrawal of selected extension
- `withdrawExtension()`: Updated to accept selected extension and calculate remaining values

#### Withdrawal Logic:
- When an extension is withdrawn:
  - The selected extension's hours/minutes/price are subtracted from totals
  - Remaining extension values are calculated
  - Check-out time is adjusted accordingly
  - Withdrawn amounts are tracked in `withdrawn_extend_*` fields

## How It Works

### Extension Recording:
1. User extends a booking (e.g., 12 hours for ₱960)
2. System stores in `extension_details`: `[{"hours": 12, "minutes": 0, "price": 960, "timestamp": "2026-05-18 10:00:00"}]`
3. User extends again (e.g., 24 hours for ₱1920)
4. System appends: `[{"hours": 12, ...}, {"hours": 24, "minutes": 0, "price": 1920, "timestamp": "2026-05-18 15:30:00"}]`

### Extension Withdrawal:
1. User clicks "Withdraw Extension" button
2. Modal shows list of actual extensions:
   - Extension 1: 12 hr (₱960.00) - 05/18/2026 10:00 AM
   - Extension 2: 24 hr (₱1920.00) - 05/18/2026 3:30 PM
3. User selects Extension 1
4. Preview shows:
   - Withdrawing: 12 hr
   - Amount to Refund: ₱960.00
   - Current Check-out: 05/19/2026, 3:30 PM
   - New Check-out: 05/19/2026, 3:30 AM (12 hours earlier)
5. User confirms withdrawal
6. System updates:
   - Total extension: 36 hr → 24 hr
   - Total price: ₱2880 → ₱1920
   - Check-out time adjusted back by 12 hours

## Benefits

1. **Transparency**: Users can see exactly which extensions were made and when
2. **Flexibility**: Can withdraw specific extensions instead of all-or-nothing
3. **Accuracy**: Shows actual extension history, not calculated portions
4. **Audit Trail**: Timestamps provide clear record of when extensions occurred
5. **Better UX**: Visual selection with preview before confirming

## Fallback Behavior

If `extension_details` is empty or not available (for old bookings):
- System shows the total extension as a single option
- Maintains backward compatibility
- User can still withdraw the total extension

## Testing

To test the feature:
1. Create a booking
2. Extend it multiple times with different durations (e.g., 12 hr, then 24 hr)
3. Open Edit Booking modal
4. Click "Withdraw Extension" button
5. Verify that individual extensions are listed
6. Select one extension and verify preview
7. Confirm withdrawal and verify totals are updated correctly

## Files Modified

1. `add_extension_details_column.php` - New migration script
2. `save_extend_duration.php` - Backend extension storage
3. `Booking.html` - Frontend modal and JavaScript logic

## Database Migration

Run the migration script to add the new column:
```bash
php add_extension_details_column.php
```

Or via browser:
```
http://localhost/HMS/add_extension_details_column.php
```

## Notes

- The `extension_details` column stores JSON, making it easy to add more fields in the future
- Old bookings without `extension_details` will still work with fallback behavior
- The system maintains both accumulated totals and individual extension records
- Withdrawal updates both in-memory booking object and database via `update_booking.php`
