-- ============================================================================
-- Sync check_out_at from reports table to bookings table
-- Only for bookings with status "Checked Out"
-- NOTE: reports table uses 'checked_out_at', bookings table uses 'check_out_at'
-- ============================================================================

-- Preview: See what will be updated (run this first to verify)
SELECT 
    b.id,
    b.booking_id,
    b.guest_name,
    b.status,
    b.check_out AS planned_check_out,
    b.check_out_at AS current_check_out_at,
    r.checked_out_at AS report_checked_out_at,
    'Will be updated' AS action
FROM bookings b
INNER JOIN reports r ON b.booking_id COLLATE utf8mb4_unicode_ci = r.booking_id COLLATE utf8mb4_unicode_ci
WHERE b.status = 'Checked Out'
AND r.checked_out_at IS NOT NULL
ORDER BY b.id DESC;

-- ============================================================================
-- ACTUAL UPDATE QUERY (uncomment to execute)
-- ============================================================================
/*
UPDATE bookings b
INNER JOIN reports r ON b.booking_id COLLATE utf8mb4_unicode_ci = r.booking_id COLLATE utf8mb4_unicode_ci
SET b.check_out_at = r.checked_out_at
WHERE b.status = 'Checked Out'
AND r.checked_out_at IS NOT NULL;
*/

-- ============================================================================
-- Verify the update (run this after the UPDATE to confirm)
-- ============================================================================
/*
SELECT 
    b.id,
    b.booking_id,
    b.guest_name,
    b.status,
    b.check_out AS planned_check_out,
    b.check_out_at AS updated_check_out_at,
    r.checked_out_at AS report_checked_out_at
FROM bookings b
INNER JOIN reports r ON b.booking_id COLLATE utf8mb4_unicode_ci = r.booking_id COLLATE utf8mb4_unicode_ci
WHERE b.status = 'Checked Out'
AND b.check_out_at IS NOT NULL
ORDER BY b.id DESC
LIMIT 20;
*/
