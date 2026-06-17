# Requirements Document

## Introduction

This specification addresses a critical bug in the payment status calculation for Bundle/Promo bookings. When a booking uses a Bundle/Promo that includes breakfast (indicated by the "(Promo)" suffix), the system incorrectly adds both the promo price and the breakfast price to the total amount. This causes fully paid bookings to incorrectly display as "Unpaid" because the calculated total exceeds the amount the customer actually paid.

## Glossary

- **Bundle/Promo**: A promotional package that includes room accommodation and may include breakfast at a fixed price
- **Promo Breakfast**: A breakfast item included in a Bundle/Promo package, marked with "(Promo)" suffix (e.g., "2 TAPA (Promo)")
- **Regular Breakfast**: A breakfast item purchased separately, with a price (e.g., "2 HOTDOG - ₱240.00")
- **Payment Status**: The status indicating whether a booking is "Paid" or "Unpaid" based on comparing payment received against total amount due
- **Total Amount**: The calculated sum of all charges for a booking (room, breakfast, additional items, etc.)
- **computeBookingTotalAmount**: The function in report_helpers.php that calculates the total booking amount
- **parseBreakfastSelection**: The function that extracts breakfast name and price from the breakfast string

## Requirements

### Requirement 1: Correct Total Amount Calculation for Promo Bookings

**User Story:** As a hotel staff member, I want Bundle/Promo bookings to calculate the correct total amount, so that fully paid bookings show "Paid" status instead of "Unpaid".

#### Acceptance Criteria

1. WHEN a booking has a promo with included breakfast (marked with "(Promo)" suffix), THE System SHALL NOT add the breakfast price to the total amount
2. WHEN a booking has a regular breakfast (with a price), THE System SHALL add the breakfast price to the total amount
3. WHEN a booking has multiple breakfast items where some are promo and some are regular, THE System SHALL only add the prices of regular breakfast items to the total amount
4. WHEN calculating the total amount for a promo booking, THE System SHALL only include the promo price once, not the promo price plus breakfast price

### Requirement 2: Breakfast Item Identification

**User Story:** As the system, I need to correctly identify whether a breakfast item is included in a promo or purchased separately, so that I can calculate the correct total amount.

#### Acceptance Criteria

1. WHEN a breakfast string contains "(Promo)" suffix, THE System SHALL identify it as a promo breakfast
2. WHEN a breakfast string contains a price (₱ symbol followed by numbers), THE System SHALL identify it as a regular breakfast
3. WHEN a breakfast string contains multiple items separated by "|", THE System SHALL identify each item individually as promo or regular
4. WHEN a breakfast string is empty or "None", THE System SHALL treat it as no breakfast

### Requirement 3: Payment Status Accuracy

**User Story:** As a hotel manager, I want the payment status to accurately reflect whether a booking is fully paid, so that I can trust the financial reports.

#### Acceptance Criteria

1. WHEN a Bundle/Promo booking is fully paid (payment equals promo price), THE System SHALL display Payment Status as "Paid"
2. WHEN a regular booking is fully paid (payment equals calculated total), THE System SHALL display Payment Status as "Paid"
3. WHEN any booking has payment less than the calculated total, THE System SHALL display Payment Status as "Unpaid"
4. WHEN the total amount is recalculated after fixing the breakfast logic, THE System SHALL maintain consistency across all booking-related pages (confirm_booking.php, update_booking.php, get_bookings.php)

### Requirement 4: Backward Compatibility

**User Story:** As a system administrator, I want the fix to work with existing bookings, so that historical data remains accurate.

#### Acceptance Criteria

1. WHEN processing existing bookings with promo breakfasts, THE System SHALL correctly identify them using the "(Promo)" suffix
2. WHEN processing existing bookings with regular breakfasts, THE System SHALL continue to calculate their totals correctly
3. WHEN the fix is deployed, THE System SHALL not require data migration or manual updates to existing bookings
4. WHEN displaying historical reports, THE System SHALL use the corrected calculation logic for all bookings
