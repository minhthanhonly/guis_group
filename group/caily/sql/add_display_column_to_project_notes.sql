-- Add display_column to project_notes (column key from project list for where to show note)
ALTER TABLE `groupware_project_notes` 
ADD COLUMN `display_column` varchar(100) DEFAULT NULL AFTER `needs_confirmation`;
