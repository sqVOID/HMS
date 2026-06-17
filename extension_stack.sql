-- Extension Stack SQL Schema
-- This file contains the SQL commands to add extension_stack functionality to the HMS database

-- Add extension_stack column to bookings table
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS extension_stack TEXT NULL DEFAULT NULL 
COMMENT 'JSON array storing individual extension segments for tracking and withdrawal';

-- Add extension_stack column to reports table  
ALTER TABLE reports 
ADD COLUMN IF NOT EXISTS extension_stack TEXT NULL DEFAULT NULL 
COMMENT 'JSON array storing individual extension segments for tracking and withdrawal';

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_bookings_extension_stack ON bookings(extension_stack(255));
CREATE INDEX IF NOT EXISTS idx_reports_extension_stack ON reports(extension_stack(255));

-- Sample extension_stack JSON structure:
-- [
--   {
--     "h": 12,           -- hours extended
--     "m": 0,            -- minutes extended  
--     "price": 960.00,   -- total price for this extension segment
--     "reg": 800.00,     -- regular rate portion
--     "bun": 160.00,     -- bundle rate portion
--     "bf": "2 Rice (Promo)" -- breakfast items (null if none)
--   },
--   {
--     "h": 24,
--     "m": 0, 
--     "price": 1920.00,
--     "reg": 1600.00,
--     "bun": 320.00,
--     "bf": null
--   }
-- ]

-- Query examples:

-- 1. Get all bookings with extensions
SELECT booking_id, guest_name, room_id, extension_stack 
FROM bookings 
WHERE extension_stack IS NOT NULL AND extension_stack != '';

-- 2. Get bookings with multiple extension segments
SELECT booking_id, guest_name, room_id, 
       JSON_LENGTH(extension_stack) as extension_count,
       extension_stack
FROM bookings 
WHERE JSON_LENGTH(extension_stack) > 1;

-- 3. Calculate total extension hours from stack
SELECT booking_id, guest_name, room_id,
       (SELECT SUM(JSON_EXTRACT(value, '$.h')) 
        FROM JSON_TABLE(extension_stack, '$[*]' COLUMNS (value JSON PATH '$')) AS jt) as total_extension_hours,
       (SELECT SUM(JSON_EXTRACT(value, '$.price')) 
        FROM JSON_TABLE(extension_stack, '$[*]' COLUMNS (value JSON PATH '$')) AS jt) as total_extension_price
FROM bookings 
WHERE extension_stack IS NOT NULL AND extension_stack != '';

-- 4. Get extension history with timestamps (if extension_time_at is available)
SELECT booking_id, guest_name, room_id, extension_stack, extension_time_at
FROM bookings 
WHERE extension_stack IS NOT NULL 
  AND extension_time_at IS NOT NULL
ORDER BY extension_time_at DESC;

-- 5. Find bookings with breakfast extensions
SELECT booking_id, guest_name, room_id, extension_stack
FROM bookings 
WHERE JSON_SEARCH(extension_stack, 'one', '%Promo%', NULL, '$[*].bf') IS NOT NULL;

-- 6. Get extension summary report
SELECT 
    DATE(check_in) as booking_date,
    COUNT(*) as total_bookings_with_extensions,
    SUM((SELECT SUM(JSON_EXTRACT(value, '$.h')) 
         FROM JSON_TABLE(extension_stack, '$[*]' COLUMNS (value JSON PATH '$')) AS jt)) as total_hours_extended,
    SUM((SELECT SUM(JSON_EXTRACT(value, '$.price')) 
         FROM JSON_TABLE(extension_stack, '$[*]' COLUMNS (value JSON PATH '$')) AS jt)) as total_extension_revenue
FROM bookings 
WHERE extension_stack IS NOT NULL AND extension_stack != ''
GROUP BY DATE(check_in)
ORDER BY booking_date DESC;