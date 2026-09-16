# Individual Extension Withdrawal Implementation

## Overview
This document describes the implementation of individual extension withdrawal functionality, allowing users to withdraw specific extensions rather than all extensions at once.

## Problem Statement
Previously, when a booking had multiple extensions (e.g., 12 hours + 24 hours = 36 hours total), withdrawing an extension would withdraw ALL extensions. The requirement was to allow withdrawing individual extensions while keeping others active.

## Solution Architecture

### 1. Database Schema
- **Column**: `extension_details` (TEXT, JSON format)
- **Location**: Both `bookings` and `reports` tables
- **Format**: JSON array storing individual extension records
```json
[
  {
    "hours": 12,
    "minutes": 0,
    "price": 960,
    "regular_rate": 960,
    "bundle_rate": 0,
    "timestamp": "2026-01-18 10:10:00"
  },
  {
    "hours": 24,
    "minutes": 0,
    "price": 1490,
    "regular_rate": 1490,
    "bundle_rate": 0,
    "timestamp": "2026-01-18 23:04:00"
  }
]
```

### 2. Backend Changes

#### A. `save_extend_duration.php`
- **Purpose**: Store each extension as a separate record in `extension_details`
- **Changes**:
  - Reads existing `extension_details` from database
  - Parses JSON array
  - Appends new extension with timestamp
  - Saves updated JSON back to database
  - Maintains backward compatibility with `extend_hours`, `extend_minutes`, `extend_price` columns

#### B. `withdraw_extend_duration.php`
- **Purpose**: Handle individual extension withdrawals
- **New Parameters**:
  - `selected_extension_index`: Index of extension to withdraw
  - `selected_extension_hours`: Hours of selected extension
  - `selected_extension_minutes`: Minutes of selected extension
  - `selected_extension_price`: Price of selected extension
- **Logic**:
  1. Fetch booking with `extension_details`
  2. Parse JSON array of extensions
  3. If `selected_extension_index` provided:
     - Extract selected extension from array
     - Remove it from `extension_details`
     - Calculate remaining totals
  4. If no index provided (backward compatibility):
     - Withdraw all extensions
  5. Update database:
     - Set `extend_hours/minutes/price` to remaining values
     - Add withdrawn amounts to `withdrawn_extend_hours/minutes/price`
     - Update `extension_details` with remaining extensions
     - Adjust `check_out` time
     - Calculate refund if booking was paid
  6. Return detailed response with remaining and withdrawn values

#### C. `get_bookings.php`
- **Purpose**: Include extension details in booking data
- **Changes**:
  - Added `extension_details` to SELECT query
  - Added `withdrawn_extend_hours`, `withdrawn_extend_minutes`, `withdrawn_extend_price` to SELECT
  - Added `extension_withdraw`, `refund_amount_extension` to SELECT
  - Ensures all extension-related columns are available to frontend

### 3. Frontend Changes

#### A. Withdraw Extension Modal (`Booking.html`)
- **Function**: `openWithdrawExtensionModal()`
  - Parses `extension_details` from booking
  - Displays individual extensions with timestamps
  - Shows radio buttons for selection
  
- **Function**: `generateWithdrawExtensionOptionsFromHistory()`
  - Creates option for each extension in `extension_details`
  - Displays: "Extension 1: 12 hr (₱960.00) - 01/18/2026 10:10 AM"
  - Fallback to total if no `extension_details` available

- **Function**: `createWithdrawOption()`
  - Builds individual extension option UI
  - Includes radio button, duration, price, timestamp
  - Handles click events for selection

- **Function**: `selectWithdrawOption()`
  - Stores selected extension with index
  - Calculates new checkout time
  - Shows preview of changes
  - Enables confirm button

#### B. Withdrawal Processing (`Booking.html`)
- **Function**: `withdrawExtension(selectedOption)`
  - **Changed from**: Local in-memory update
  - **Changed to**: Server-side API call
  - Sends selected extension details to `withdraw_extend_duration.php`
  - Receives updated booking data from server
  - Updates in-memory booking object
  - Refreshes charges breakdown
  - Shows success message with refund amount

#### C. Charges Breakdown Display (`Booking.html`)
- **Function**: `updateEditChargesBreakdown()`
  - **Active Extensions Section**:
    - Parses `extension_details` from booking
    - Shows each remaining extension individually
    - Format: "Extended (12 Hours): ₱960.00"
  - **Withdrawn Extensions Section**:
    - Shows total withdrawn extensions
    - Format: "Extended (24 Hours) (Withdrawn): -₱1490.00"
    - Styled with strikethrough and gray color
  - **Fallback**: Shows totals if `extension_details` not available

### 4. User Flow

#### Scenario: Withdraw 24hr from 36hr total extension
1. User opens booking with 36 hours extension (12hr + 24hr)
2. User clicks "Withdraw Extension" button
3. Modal shows:
   ```
   Total Extension: 36 hr (₱2450.00)
   
   Select Extension to Withdraw:
   ○ Extension 1: 12 hr (₱960.00) - 01/18/2026 10:10 AM
   ○ Extension 2: 24 hr (₱1490.00) - 01/18/2026 11:15 AM
   ```
4. User selects "Extension 2: 24 hr"
5. Preview shows:
   - Duration to withdraw: 24 hr
   - Amount: ₱1490.00
   - Current checkout: 01/20/2026 10:00 AM
   - New checkout: 01/19/2026 10:00 AM
   - Refund: ₱1490.00 (if paid)
6. User clicks "Withdraw Extension"
7. Server processes withdrawal:
   - Removes 24hr extension from `extension_details`
   - Updates `extend_hours` from 36 to 12
   - Updates `extend_price` from 2450 to 960
   - Adds 24 to `withdrawn_extend_hours`
   - Adds 1490 to `withdrawn_extend_price`
   - Adjusts checkout time back 24 hours
8. Charges breakdown updates:
   ```
   Extended (12 Hours): ₱960.00
   Extended (24 Hours) (Withdrawn): -₱1490.00
   ```
9. Refund of ₱1490.00 applied (if booking was paid)

### 5. Testing

#### Test File: `test_individual_withdrawal.php`
- Creates test booking
- Adds two extensions (12hr + 24hr)
- Withdraws second extension (24hr)
- Verifies:
  - ✓ Active extension: 12 hours, ₱960
  - ✓ Withdrawn extension: 24 hours, ₱1490
  - ✓ Extension details contains only 1 extension
  - ✓ Checkout time adjusted correctly
  - ✓ Total amount recalculated correctly

**Test Result**: ✓ ALL TESTS PASSED

### 6. Backward Compatibility
- Bookings without `extension_details` still work
- Falls back to showing total extension
- Full withdrawal (no index) still supported
- Existing withdrawal logic preserved

### 7. Files Modified
1. `add_extension_details_column.php` - Database migration
2. `save_extend_duration.php` - Store individual extensions
3. `withdraw_extend_duration.php` - Process individual withdrawals
4. `get_bookings.php` - Include extension details in queries
5. `Booking.html` - UI and JavaScript for individual selection

### 8. Database Columns Used
- `extend_hours` - Total active extension hours
- `extend_minutes` - Total active extension minutes
- `extend_price` - Total active extension price
- `withdrawn_extend_hours` - Total withdrawn hours
- `withdrawn_extend_minutes` - Total withdrawn minutes
- `withdrawn_extend_price` - Total withdrawn price
- `extension_details` - JSON array of individual extensions
- `extension_withdraw` - Flag indicating withdrawal occurred
- `refund_amount_extension` - Refund amount for withdrawn extensions

### 9. Key Features
✓ Individual extension selection
✓ Visual timeline with timestamps
✓ Preview before withdrawal
✓ Automatic refund calculation
✓ Checkout time adjustment
✓ Charges breakdown shows both active and withdrawn
✓ Extension history preserved
✓ Server-side processing (no local state issues)
✓ Backward compatible

### 10. Future Enhancements
- Allow re-adding withdrawn extensions
- Show extension history in booking details
- Export extension details in reports
- Add extension notes/reasons
- Bulk extension operations

## Conclusion
The individual extension withdrawal feature is now fully implemented and tested. Users can withdraw specific extensions while keeping others active, with proper refund calculation and checkout time adjustment.
