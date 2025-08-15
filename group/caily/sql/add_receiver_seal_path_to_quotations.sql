-- Add receiver_seal_path field to quotations table to store seal image path
-- This ensures the seal is preserved even if the user becomes inactive
ALTER TABLE quotations ADD COLUMN receiver_seal_path VARCHAR(255) DEFAULT '' AFTER receiver_contact;
