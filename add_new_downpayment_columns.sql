-- ============================================
-- Add New Downpayment Columns to Bookings and Reports Tables
-- ============================================
-- This script adds InstaPay, Online Banking, and Airbnb payment columns
-- Run this script to update both bookings and reports tables
-- ============================================

-- Add new downpayment columns to BOOKINGS table
ALTER TABLE bookings 
ADD COLUMN downpayment_instapay DECIMAL(10,2) DEFAULT 0.00 COMMENT 'InstaPay payment amount',
ADD COLUMN downpayment_online_banking DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Online Banking payment amount',
ADD COLUMN downpayment_airbnb DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Airbnb payment amount',
ADD COLUMN downpayment_instapay_ref VARCHAR(255) NULL DEFAULT NULL COMMENT 'InstaPay reference number',
ADD COLUMN downpayment_online_banking_ref VARCHAR(255) NULL DEFAULT NULL COMMENT 'Online Banking reference number',
ADD COLUMN downpayment_airbnb_ref VARCHAR(255) NULL DEFAULT NULL COMMENT 'Airbnb reference number';

-- Add new downpayment columns to REPORTS table
ALTER TABLE reports 
ADD COLUMN downpayment_instapay DECIMAL(10,2) DEFAULT 0.00 COMMENT 'InstaPay payment amount',
ADD COLUMN downpayment_online_banking DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Online Banking payment amount',
ADD COLUMN downpayment_airbnb DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Airbnb payment amount',
ADD COLUMN downpayment_instapay_ref VARCHAR(255) NULL DEFAULT NULL COMMENT 'InstaPay reference number',
ADD COLUMN downpayment_online_banking_ref VARCHAR(255) NULL DEFAULT NULL COMMENT 'Online Banking reference number',
ADD COLUMN downpayment_airbnb_ref VARCHAR(255) NULL DEFAULT NULL COMMENT 'Airbnb reference number';

-- ============================================
-- Verification Queries
-- ============================================

-- Check all downpayment columns in BOOKINGS table
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'bookings' 
AND COLUMN_NAME LIKE 'downpayment%'
ORDER BY ORDINAL_POSITION;

-- Check all downpayment columns in REPORTS table
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'reports' 
AND COLUMN_NAME LIKE 'downpayment%'
ORDER BY ORDINAL_POSITION;

-- ============================================
-- Summary of All Downpayment Columns
-- ============================================
-- After running this script, both tables will have:
-- 
-- AMOUNT COLUMNS:
-- - downpayment_amount (Total)
-- - downpayment_cash
-- - downpayment_gcash
-- - downpayment_maya
-- - downpayment_instapay (NEW)
-- - downpayment_online_banking (NEW)
-- - downpayment_airbnb (NEW)
--
-- REFERENCE NUMBER COLUMNS:
-- - downpayment_gcash_ref
-- - downpayment_maya_ref
-- - downpayment_instapay_ref (NEW)
-- - downpayment_online_banking_ref (NEW)
-- - downpayment_airbnb_ref (NEW)
--
-- STATUS & DATE COLUMNS:
-- - downpayment_status
-- - downpayment_date
-- ============================================
