# Design: Report Booking Type Counts

## Overview

This feature adds Walk-in and Reservation booking count statistics to the Report.php dashboard. The implementation follows the existing pattern used for other dashboard statistics: backend PHP calculates counts via SQL queries, frontend JavaScript displays them in styled cards.

## Architecture

### Component Diagram

```
┌─────────────────┐
│   Report.php    │ (Frontend)
│  - HTML Cards   │
│  - JavaScript   │
└────────┬────────┘
         │ AJAX Request
         ↓
┌─────────────────────┐
│ get_report_stats.php│ (Backend API)
│  - SQL Queries      │
│  - Date Filtering   │
└────────┬────────────┘
         │ SQL Queries
         ↓
┌─────────────────────┐
│   MySQL Database    │
│  - bookings table   │
│  - reports table    │
└─────────────────────┘
```

## Database Schema

### Migration Script

**File**: `add_booking_type_column.php`

The migration script adds the `booking_type` column to both `bookings` and `reports` tables:

```sql
ALTER TABLE bookings ADD COLUMN booking_type VARCHAR(20) DEFAULT 'Walk-in' AFTER status;
ALTER TABLE reports ADD COLUMN booking_type VARCHAR(20) DEFAULT 'Walk-in' AFTER status;
```

**Column Specifications**:
- Type: VARCHAR(20)
- Default: 'Walk-in'
- Position: After `status` column
- Allowed Values: 'Walk-in', 'Reservation'

## Backend Implementation

### API Endpoint: get_report_stats.php

**Location**: Lines 255-313

**Functionality**: Calculates Walk-in and Reservation counts from both `bookings` and `reports` tables, filtered by date range.

#### Walk-in Count Calculation

```php
// Count from bookings table
SELECT COUNT(*) as count 
FROM bookings 
WHERE booking_type = 'Walk-in'
  AND DATE(check_in) BETWEEN :start AND :end

// Count from reports table
SELECT COUNT(*) as count 
FROM reports 
WHERE booking_type = 'Walk-in'
  AND DATE(check_in) BETWEEN :start AND :end

// Sum both counts
$walkinCount = bookings_count + reports_count
```

#### Reservation Count Calculation

```php
// Count from bookings table
SELECT COUNT(*) as count 
FROM bookings 
WHERE booking_type = 'Reservation'
  AND DATE(check_in) BETWEEN :start AND :end

// Count from reports table
SELECT COUNT(*) as count 
FROM reports 
WHERE booking_type = 'Reservation'
  AND DATE(check_in) BETWEEN :start AND :end

// Sum both counts
$reservationCount = bookings_count + reports_count
```

#### Response Format

```json
{
  "success": true,
  "stats": {
    "walkin": 15,
    "reservation": 8,
    ...
  }
}
```

### Date Range Filtering

The implementation reuses the existing `buildDateRange()` function from `report_helpers.php`:

- **Today**: Current date only
- **Last 7 Days**: Last 7 days including today
- **Last 30 Days**: Last 30 days including today
- **Custom**: User-specified start and end dates

All queries use the same `$filterStart` and `$filterEnd` variables for consistency.

## Frontend Implementation

### HTML Display: Report.php

**Location**: Lines 285-292

Two statistic cards are added to the dashboard grid:

#### Walk-in Card

```html
<div style="background: linear-gradient(135deg, #5cb85c 100%); padding: 24px; border-radius: 8px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Walk-in</div>
    <div style="font-size: 36px; font-weight: 700;" id="walkinCount">0</div>
</div>
```

**Styling**:
- Background: Green gradient (#5cb85c)
- Text: White, 36px, bold
- Label: 14px, semi-transparent
- Padding: 24px
- Border-radius: 8px
- Box-shadow: 0 4px 6px rgba(0,0,0,0.1)

#### Reservation Card

```html
<div style="background: linear-gradient(135deg, #5bc0de 100%); padding: 24px; border-radius: 8px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;">Reservation</div>
    <div style="font-size: 36px; font-weight: 700;" id="reservationCount">0</div>
</div>
```

**Styling**:
- Background: Blue gradient (#5bc0de)
- Text: White, 36px, bold
- Label: 14px, semi-transparent
- Padding: 24px
- Border-radius: 8px
- Box-shadow: 0 4px 6px rgba(0,0,0,0.1)

### JavaScript Update: Report.php

**Location**: Lines 606-607

The `loadReportStats()` function updates the counts when the API response is received:

```javascript
document.getElementById('walkinCount').textContent = stats.walkin || 0;
document.getElementById('reservationCount').textContent = stats.reservation || 0;
```

**Fallback Behavior**: If `stats.walkin` or `stats.reservation` is missing or null, defaults to 0.

## Data Flow

### Booking Creation Flow

1. User creates a booking in `Booking.html`
2. Frontend sends `booking_type` field (either 'Walk-in' or 'Reservation') to `confirm_booking.php`
3. Backend validates `booking_type` (defaults to 'Walk-in' if invalid)
4. Backend inserts booking into `bookings` table with `booking_type` field
5. Dashboard displays updated count when date range includes the booking

### Checkout Flow

1. User checks out a booking in `Booking.html`
2. Backend copies booking data from `bookings` table to `reports` table
3. Backend includes `booking_type` field in the copy operation (line 320 in `checkout_booking.php`)
4. Backend deletes booking from `bookings` table
5. Dashboard continues to display the count (now from `reports` table)

### Dashboard Display Flow

1. User opens `Report.php` or changes date range filter
2. Frontend calls `get_report_stats.php` with date range parameters
3. Backend queries both `bookings` and `reports` tables
4. Backend sums counts from both tables
5. Backend returns JSON response with `walkin` and `reservation` counts
6. Frontend updates DOM elements `#walkinCount` and `#reservationCount`

## Error Handling

### Missing Column Handling

If the `booking_type` column doesn't exist:
- SQL queries will fail with "Unknown column" error
- User must run the migration script: `add_booking_type_column.php`
- Migration script checks if column exists before adding it (idempotent)

### NULL Value Handling

If `booking_type` is NULL or empty:
- Database default value 'Walk-in' is used
- Backend validation in `confirm_booking.php` ensures only 'Walk-in' or 'Reservation' values are stored

### Missing Data Handling

If API response doesn't include `walkin` or `reservation` fields:
- JavaScript uses fallback value of 0
- No error is displayed to user

## Testing Strategy

### Manual Testing Checklist

1. **Database Migration**:
   - Run `add_booking_type_column.php`
   - Verify column exists in both `bookings` and `reports` tables
   - Verify default value is 'Walk-in'

2. **Walk-in Booking**:
   - Create a Walk-in booking
   - Verify dashboard shows incremented Walk-in count
   - Verify count updates with date range filters

3. **Reservation Booking**:
   - Create a Reservation booking
   - Verify dashboard shows incremented Reservation count
   - Verify count updates with date range filters

4. **Checkout Flow**:
   - Check out a Walk-in booking
   - Verify Walk-in count remains the same (moved to reports table)
   - Check out a Reservation booking
   - Verify Reservation count remains the same

5. **Date Range Filtering**:
   - Test "Today" filter
   - Test "Last 7 Days" filter
   - Test "Last 30 Days" filter
   - Test "Custom" filter with various date ranges

## Deployment Instructions

1. **Backup Database**: Create a backup before running migration
2. **Run Migration**: Navigate to `http://your-domain/add_booking_type_column.php`
3. **Verify Migration**: Check that both tables have the `booking_type` column
4. **Test Dashboard**: Open Report.php and verify counts display correctly
5. **Test Booking Creation**: Create Walk-in and Reservation bookings to verify tracking

## Performance Considerations

- **Query Optimization**: Uses indexed `check_in` column for date filtering
- **Dual Table Queries**: Separate queries for `bookings` and `reports` tables (cannot be combined due to different data states)
- **Count Aggregation**: Uses `COUNT(*)` which is optimized by MySQL
- **No N+1 Queries**: Single query per table, not per booking

## Future Enhancements

1. **Booking Type Breakdown**: Show Walk-in vs Reservation breakdown for other statistics (revenue, check-ins, etc.)
2. **Trend Analysis**: Display booking type trends over time (line chart)
3. **Conversion Tracking**: Track how many Reservations convert to actual check-ins
4. **Export Support**: Include booking type in exported reports
