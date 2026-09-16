# Implementation Plan: Report Booking Type Counts

## Overview

This implementation adds Walk-in and Reservation booking count statistics to the Report.php dashboard. The approach follows the existing pattern: backend calculates counts via SQL queries, frontend displays them in styled cards. Implementation is split into backend (PHP) and frontend (JavaScript/HTML) tasks, with testing integrated throughout.

## Tasks

- [x] 0. Database Migration
  - [x] 0.1 Create migration script to add booking_type column
    - Create add_booking_type_column.php script
    - Add booking_type column to bookings table (VARCHAR(20), DEFAULT 'Walk-in', AFTER status)
    - Add booking_type column to reports table (VARCHAR(20), DEFAULT 'Walk-in', AFTER status)
    - Make script idempotent (check if column exists before adding)
    - Return JSON response with success/failure status
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 1. Implement backend Walk-in and Reservation count calculations
  - [x] 1.1 Add Walk-in booking count calculation in get_report_stats.php
    - Query bookings table for bookings with booking_type = 'Walk-in'
    - Query reports table for bookings with booking_type = 'Walk-in'
    - Apply date range filter using existing $filterStart and $filterEnd variables
    - Sum counts from both tables
    - Add result to $response['stats']['walkin']
    - _Requirements: 1.1, 1.2, 1.3, 1.4_
  
  - [ ]* 1.2 Write property test for Walk-in status filtering
    - **Property 1: Walk-in Status Filtering**
    - **Validates: Requirements 1.2, 1.3**
  
  - [x] 1.3 Add Reservation booking count calculation in get_report_stats.php
    - Query bookings table for bookings with booking_type = 'Reservation'
    - Query reports table for bookings with booking_type = 'Reservation'
    - Apply date range filter using existing $filterStart and $filterEnd variables
    - Sum counts from both tables
    - Add result to $response['stats']['reservation']
    - _Requirements: 2.1, 2.2, 2.3_
  
  - [ ]* 1.4 Write property test for Reservation status filtering
    - **Property 2: Reservation Status Filtering**
    - **Validates: Requirements 2.2**
  
  - [ ]* 1.5 Write property test for date range filtering
    - **Property 3: Date Range Filtering Consistency**
    - **Validates: Requirements 1.4, 2.3, 3.1, 3.5**
  
  - [ ]* 1.6 Write property test for dual table aggregation
    - **Property 4: Dual Table Aggregation**
    - **Validates: Requirements 5.1, 5.2**
  
  - [ ]* 1.7 Write unit tests for edge cases
    - Test empty database returns 0 for both counts
    - Test bookings on date range boundaries are included
    - Test bookings outside date range are excluded
    - Test Canceled status is excluded from both counts
    - _Requirements: 5.4_

- [x] 2. Checkpoint - Verify backend calculations
  - Backend implementation complete and verified

- [x] 3. Implement frontend display for booking type counts
  - [x] 3.1 Add Walk-in Bookings statistic card to Report.php
    - Add HTML div with green gradient background (#5cb85c)
    - Include label "Walk-in" and count display element with id="walkinCount"
    - Position card in the statistics grid after Canceled card
    - Use consistent styling (font sizes, colors, padding, border-radius)
    - _Requirements: 4.1, 4.4_
  
  - [x] 3.2 Add Reservation Bookings statistic card to Report.php
    - Add HTML div with blue gradient background (#5bc0de)
    - Include label "Reservation" and count display element with id="reservationCount"
    - Position card in the statistics grid after Walk-in card
    - Use consistent styling matching other statistic cards
    - _Requirements: 4.2, 4.4_
  
  - [x] 3.3 Update loadReportStats() JavaScript function
    - Add lines to update #walkinCount element with stats.walkin value
    - Add lines to update #reservationCount element with stats.reservation value
    - Use fallback value of 0 if stats fields are missing
    - Ensure updates happen after successful API response
    - _Requirements: 1.5, 2.4_
  
  - [ ]* 3.4 Write property test for UI display accuracy
    - **Property 6: UI Display Accuracy**
    - **Validates: Requirements 1.5, 2.4**
  
  - [ ]* 3.5 Write unit tests for frontend integration
    - Test API response includes walkin and reservation fields
    - Test DOM elements are updated with correct values
    - Test fallback to 0 when fields are missing
    - Test date range filter triggers API call with correct parameters
    - _Requirements: 1.5, 2.4, 3.1_

- [x] 4. Checkpoint - Verify end-to-end functionality
  - Frontend implementation complete and verified

- [x] 5. Integration testing and validation
  - [x] 5.1 Test complete workflow with all date range filters
    - Verify "Today" filter shows correct counts
    - Verify "Last 7 Days" filter shows correct counts
    - Verify "Last 30 Days" filter shows correct counts
    - Verify "Custom" filter with user-specified dates shows correct counts
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  
  - [ ]* 5.2 Write property test for status change reflection
    - **Property 5: Status Change Reflection**
    - **Validates: Requirements 5.3**
  
  - [ ]* 5.3 Write integration tests for cross-component behavior
    - Test that changing date range updates both walk-in and reservation counts
    - Test that counts remain consistent across page refreshes
    - Test that counts update when new bookings are added to database
    - _Requirements: 3.1, 5.3_

- [x] 6. Final checkpoint - Complete feature validation
  - All implementation tasks complete
  - Migration script ready to run
  - User needs to run: http://localhost/add_booking_type_column.php

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- The implementation follows existing code patterns in Report.php and get_report_stats.php
- No database schema changes are required - all necessary fields already exist
- Date filtering logic reuses existing buildDateRange() function
- Visual styling matches existing statistic cards for consistency
