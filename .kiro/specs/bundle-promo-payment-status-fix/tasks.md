# Implementation Plan: Bundle/Promo Payment Status Fix

## Overview

This implementation plan fixes the bug where Bundle/Promo bookings with included breakfast incorrectly show as "Unpaid" even when fully paid. The fix modifies the `parseBreakfastSelection` function in `report_helpers.php` to detect promo breakfast items (marked with "(Promo)" suffix) and return a price of 0.0 for them, ensuring only the promo price is counted in the total amount calculation.

## Tasks

- [x] 1. Modify parseBreakfastSelection to detect promo breakfast items
  - Update the function to check for "(Promo)" suffix (case-insensitive)
  - Return price = 0.0 for breakfast items with "(Promo)" suffix
  - Preserve existing behavior for regular breakfast items with prices
  - Handle multi-item breakfast strings (items separated by "|")
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3_

- [x] 1.1 Write property test for promo breakfast detection
  - **Property 1: Promo Breakfast Returns Zero Price**
  - **Validates: Requirements 1.1, 2.1, 4.1**
  - Generate random breakfast strings with "(Promo)" suffix
  - Verify parseBreakfastSelection returns price = 0.0
  - Test with various cases: "(promo)", "(PROMO)", "(Promo)"
  - Test with quantity prefixes: "2 TAPA (Promo)", "1 LONGGANISA (Promo)"

- [x] 1.2 Write property test for regular breakfast price extraction
  - **Property 2: Regular Breakfast Price Extraction**
  - **Validates: Requirements 1.2, 2.2, 4.2**
  - Generate random breakfast strings with prices
  - Verify parseBreakfastSelection extracts correct price
  - Test various price formats: "₱120.00", "₱1,200.00"

- [x] 1.3 Write property test for mixed breakfast items
  - **Property 3: Mixed Breakfast Items Pricing**
  - **Validates: Requirements 1.3, 2.3**
  - Generate random multi-item strings with mix of promo and regular items
  - Verify total price equals sum of only non-promo prices
  - Test with "|" separator: "2 HOTDOG - ₱240.00 | 1 TAPA (Promo)"

- [ ] 1.4 Write unit tests for edge cases
  - Test empty breakfast strings (null, "", "None", "Select Breakfast")
  - Test the reported bug scenario: "2 TAPA (Promo)" should return price = 0.0
  - Test whitespace variations around "(Promo)"
  - _Requirements: 2.4_

- [ ] 2. Verify computeBookingTotalAmount uses the fixed logic
  - Confirm that computeBookingTotalAmount correctly uses the price returned by parseBreakfastSelection
  - No code changes needed (function already works correctly)
  - _Requirements: 1.4_

- [ ] 2.1 Write property test for promo booking total calculation
  - **Property 4: Promo Booking Total Excludes Breakfast**
  - **Validates: Requirements 1.4**
  - Generate random booking data with promo and promo breakfast
  - Verify computeBookingTotalAmount returns total = promo price (not promo + breakfast)
  - Test with various room types and durations

- [ ] 2.2 Write unit tests for total amount calculation
  - Test specific example: Promo booking with "2 TAPA (Promo)" should not add breakfast price
  - Test regular booking with "2 HOTDOG - ₱240.00" should add breakfast price
  - Test mixed scenario with both promo and regular items
  - _Requirements: 1.1, 1.2, 1.3_

- [ ] 3. Checkpoint - Ensure all tests pass
  - Run all unit tests and property tests
  - Verify the bug is fixed: Bundle/Promo bookings with promo breakfast show correct total
  - Ask the user if questions arise

- [ ] 4. Verify fix works across all booking pages
  - Test confirm_booking.php uses the corrected calculation
  - Test update_booking.php uses the corrected calculation
  - Test get_bookings.php uses the corrected calculation
  - Verify payment status displays correctly as "Paid" for fully paid promo bookings
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ] 4.1 Write integration tests for payment status accuracy
  - Test Bundle/Promo booking fully paid → status = "Paid"
  - Test regular booking fully paid → status = "Paid"
  - Test booking with payment < total → status = "Unpaid"
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 5. Final checkpoint - Ensure all tests pass
  - Run complete test suite
  - Verify backward compatibility with existing bookings
  - Confirm no data migration needed
  - Ask the user if questions arise

## Notes

- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (minimum 100 iterations each)
- Unit tests validate specific examples and edge cases
- The fix is backward compatible and requires no database changes
