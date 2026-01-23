-- Add needs_confirmation column to project_notes table
ALTER TABLE `groupware_project_notes` 
ADD COLUMN `needs_confirmation` tinyint(1) DEFAULT 0 AFTER `is_important`;

-- Add index for better query performance
ALTER TABLE `groupware_project_notes` 
ADD INDEX `idx_needs_confirmation` (`needs_confirmation`);

