-- Add subject field to quotations table
ALTER TABLE quotations ADD COLUMN subject TEXT DEFAULT '' AFTER valid_until;
