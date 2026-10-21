-- Add label to project_notes (memo category from department note_labels)
ALTER TABLE `groupware_project_notes`
ADD COLUMN `label` varchar(100) DEFAULT NULL COMMENT 'メモラベル' AFTER `display_column`;

-- Add note_labels JSON to departments (default memo labels per department)
ALTER TABLE `groupware_departments`
ADD COLUMN `note_labels` TEXT NULL DEFAULT NULL COMMENT 'メモラベル JSON [{name,color}]' AFTER `can_project`;
