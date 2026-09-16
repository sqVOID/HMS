# Requirements: Report Booking Type Counts

## 1. Walk-in Booking Count Display

**User Story**: As a hotel manager, I want to see the count of Walk-in bookings on the dashboard so that I can track how many guests book directly at the property.

### Acceptance Criteria

1.1 The system SHALL count all bookings where `booking_type = 'Walk-in'` from both the `bookings` and `reports` tables

1.2 The count SHALL include bookings with status IN ('Confirming', 'Confirmed', 'Occupied', 'Checked Out') from the `bookings` table

1.3 The count SHALL include bookings with status IN ('Confirming', 'Confirmed', 'Occupied', 'Checked Out') from the `reports` table

1.4 The count SHALL be filtered by the selected date range (check_in date)

1.5 The Walk-in count SHALL be displayed on the dashboard in a statistic card with a green gradient background

## 2. Reservation Booking Count Display

**User Story**: As a hotel manager, I want to see the count of Reservation bookings on the dashboard so that I can track how many guests make advance reservations.

### Acceptance Criteria

2.1 The system SHALL count all bookings where `booking_type = 'Reservation'` from both the `bookings` and `reports` tables

2.2 The count SHALL include bookings with status = 'Reserved' OR any other active status from both tables

2.3 The count SHALL be filtered by the selected date range (check_in date)

2.4 The Reservation count SHALL be displayed on the dashboard in a statistic card with a blue gradient background

## 3. Date Range Filtering

**User Story**: As a hotel manager, I want to filter booking type counts by date range so that I can analyze booking patterns over different time periods.

### Acceptance Criteria

3.1 The system SHALL support "Today" date range filter

3.2 The system SHALL support "Last 7 Days" date range filter

3.3 The system SHALL support "Last 30 Days" date range filter

3.4 The system SHALL support "Custom" date range filter with user-specified start and end dates

3.5 All booking type counts SHALL update when the date range is changed

## 4. Visual Display Requirements

**User Story**: As a hotel manager, I want the booking type counts to be visually consistent with other dashboard statistics so that the interface is cohesive and easy to read.

### Acceptance Criteria

4.1 The Walk-in statistic card SHALL use a green gradient background (#5cb85c)

4.2 The Reservation statistic card SHALL use a blue gradient background (#5bc0de)

4.3 Both cards SHALL display the count in large, bold white text (36px, font-weight 700)

4.4 Both cards SHALL match the styling of existing statistic cards (padding, border-radius, box-shadow)

## 5. Data Integrity Requirements

**User Story**: As a system administrator, I want booking type data to be accurately tracked and stored so that reports are reliable.

### Acceptance Criteria

5.1 The `booking_type` column SHALL exist in the `bookings` table

5.2 The `booking_type` column SHALL exist in the `reports` table

5.3 When a booking status changes, the booking type SHALL remain consistent

5.4 The system SHALL handle missing or NULL `booking_type` values by defaulting to 'Walk-in'

## 6. Database Schema Requirements

**User Story**: As a developer, I need the database schema to support booking type tracking so that the feature can be implemented.

### Acceptance Criteria

6.1 The `bookings` table SHALL have a `booking_type` column of type VARCHAR(20)

6.2 The `reports` table SHALL have a `booking_type` column of type VARCHAR(20)

6.3 The `booking_type` column SHALL have a default value of 'Walk-in'

6.4 The `booking_type` column SHALL be positioned after the `status` column

6.5 A migration script SHALL be provided to add the column to existing databases
