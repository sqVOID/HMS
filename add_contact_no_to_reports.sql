-- Add contact_no column to reports table

-- Check if column exists and add it
ALTER TABLE reports 
ADD COLUMN IF NOT EXISTS contact_no VARCHAR(20) NULL DEFAULT NULL 
AFTER contact_person_name;
