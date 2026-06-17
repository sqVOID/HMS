-- ============================================================================
-- Add New Payment Methods (Instapay, Online Banking, Airbnb)
-- This script adds columns for the new payment methods to bookings and reports tables
-- ============================================================================

-- ============================================================================
-- BOOKINGS TABLE
-- ============================================================================

-- Add payment status columns for new payment methods
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `payment_status_instapay` TEXT NULL DEFAULT NULL AFTER `payment_status_maya`,
ADD COLUMN IF NOT EXISTS `payment_status_online_banking` TEXT NULL DEFAULT NULL AFTER `payment_status_instapay`,
ADD COLUMN IF NOT EXISTS `payment_status_airbnb` TEXT NULL DEFAULT NULL AFTER `payment_status_online_banking`;

-- Add deposit breakdown columns for new payment methods
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `deposit_instapay` DECIMAL(10,2) DEFAULT 0 AFTER `deposit_maya`,
ADD COLUMN IF NOT EXISTS `deposit_online_banking` DECIMAL(10,2) DEFAULT 0 AFTER `deposit_instapay`,
ADD COLUMN IF NOT EXISTS `deposit_airbnb` DECIMAL(10,2) DEFAULT 0 AFTER `deposit_online_banking`;

-- Add reference number columns for new payment methods
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `deposit_instapay_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `deposit_maya_ref`,
ADD COLUMN IF NOT EXISTS `deposit_online_banking_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `deposit_instapay_ref`,
ADD COLUMN IF NOT EXISTS `deposit_airbnb_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `deposit_online_banking_ref`;

-- Add payment history columns for new payment methods
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `payment_amount_instapay_history` TEXT NULL DEFAULT NULL AFTER `payment_amount_maya_history`,
ADD COLUMN IF NOT EXISTS `payment_amount_online_banking_history` TEXT NULL DEFAULT NULL AFTER `payment_amount_instapay_history`,
ADD COLUMN IF NOT EXISTS `payment_amount_airbnb_history` TEXT NULL DEFAULT NULL AFTER `payment_amount_online_banking_history`;

-- Add reference number columns for payment tracking
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `reference_no_instapay` VARCHAR(255) NULL DEFAULT NULL AFTER `reference_no_maya`,
ADD COLUMN IF NOT EXISTS `reference_no_online_banking` VARCHAR(255) NULL DEFAULT NULL AFTER `reference_no_instapay`,
ADD COLUMN IF NOT EXISTS `reference_no_airbnb` VARCHAR(255) NULL DEFAULT NULL AFTER `reference_no_online_banking`;

-- ============================================================================
-- REPORTS TABLE
-- ============================================================================

-- Add payment status columns for new payment methods
ALTER TABLE `reports` 
ADD COLUMN IF NOT EXISTS `payment_status_instapay` TEXT NULL DEFAULT NULL AFTER `payment_status_maya`,
ADD COLUMN IF NOT EXISTS `payment_status_online_banking` TEXT NULL DEFAULT NULL AFTER `payment_status_instapay`,
ADD COLUMN IF NOT EXISTS `payment_status_airbnb` TEXT NULL DEFAULT NULL AFTER `payment_status_online_banking`;

-- Add deposit breakdown columns for new payment methods
ALTER TABLE `reports` 
ADD COLUMN IF NOT EXISTS `deposit_instapay` DECIMAL(10,2) DEFAULT 0 AFTER `deposit_maya`,
ADD COLUMN IF NOT EXISTS `deposit_online_banking` DECIMAL(10,2) DEFAULT 0 AFTER `deposit_instapay`,
ADD COLUMN IF NOT EXISTS `deposit_airbnb` DECIMAL(10,2) DEFAULT 0 AFTER `deposit_online_banking`;

-- Add reference number columns for new payment methods
ALTER TABLE `reports` 
ADD COLUMN IF NOT EXISTS `deposit_instapay_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `deposit_maya_ref`,
ADD COLUMN IF NOT EXISTS `deposit_online_banking_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `deposit_instapay_ref`,
ADD COLUMN IF NOT EXISTS `deposit_airbnb_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `deposit_online_banking_ref`;

-- Add payment history columns for new payment methods
ALTER TABLE `reports` 
ADD COLUMN IF NOT EXISTS `payment_amount_instapay_history` TEXT NULL DEFAULT NULL AFTER `payment_amount_maya_history`,
ADD COLUMN IF NOT EXISTS `payment_amount_online_banking_history` TEXT NULL DEFAULT NULL AFTER `payment_amount_instapay_history`,
ADD COLUMN IF NOT EXISTS `payment_amount_airbnb_history` TEXT NULL DEFAULT NULL AFTER `payment_amount_online_banking_history`;

-- Add reference number columns for payment tracking
ALTER TABLE `reports` 
ADD COLUMN IF NOT EXISTS `reference_no_instapay` VARCHAR(255) NULL DEFAULT NULL AFTER `reference_no_maya`,
ADD COLUMN IF NOT EXISTS `reference_no_online_banking` VARCHAR(255) NULL DEFAULT NULL AFTER `reference_no_instapay`,
ADD COLUMN IF NOT EXISTS `reference_no_airbnb` VARCHAR(255) NULL DEFAULT NULL AFTER `reference_no_online_banking`;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Verify bookings table columns
SELECT 
    'BOOKINGS TABLE - New Payment Method Columns' AS verification,
    COUNT(*) AS total_columns
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'bookings' 
    AND COLUMN_NAME IN (
        'payment_status_instapay',
        'payment_status_online_banking',
        'payment_status_airbnb',
        'deposit_instapay',
        'deposit_online_banking',
        'deposit_airbnb',
        'deposit_instapay_ref',
        'deposit_online_banking_ref',
        'deposit_airbnb_ref',
        'payment_amount_instapay_history',
        'payment_amount_online_banking_history',
        'payment_amount_airbnb_history',
        'reference_no_instapay',
        'reference_no_online_banking',
        'reference_no_airbnb'
    );

-- Verify reports table columns
SELECT 
    'REPORTS TABLE - New Payment Method Columns' AS verification,
    COUNT(*) AS total_columns
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reports' 
    AND COLUMN_NAME IN (
        'payment_status_instapay',
        'payment_status_online_banking',
        'payment_status_airbnb',
        'deposit_instapay',
        'deposit_online_banking',
        'deposit_airbnb',
        'deposit_instapay_ref',
        'deposit_online_banking_ref',
        'deposit_airbnb_ref',
        'payment_amount_instapay_history',
        'payment_amount_online_banking_history',
        'payment_amount_airbnb_history',
        'reference_no_instapay',
        'reference_no_online_banking',
        'reference_no_airbnb'
    );

-- ============================================================================
-- NOTES
-- ============================================================================
-- 
-- This script adds support for three new payment methods:
-- 1. Instapay
-- 2. Online Banking
-- 3. Airbnb
--
-- Each payment method includes:
-- - payment_status_* : Stores payment details (TEXT)
-- - deposit_* : Stores deposit amount breakdown (DECIMAL)
-- - deposit_*_ref : Stores reference number for the payment (VARCHAR)
-- - payment_amount_*_history : Stores payment history in pipe-separated format (TEXT)
-- - reference_no_* : Stores transaction reference numbers (VARCHAR)
--
-- The columns are added to both bookings and reports tables to maintain
-- consistency and support full payment tracking throughout the booking lifecycle.
--
-- Expected result: 15 columns added to each table (30 total)
-- ============================================================================
