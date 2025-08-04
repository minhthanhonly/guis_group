-- Add is_kadai field to projects table
ALTER TABLE `groupware_projects` 
ADD COLUMN `is_kadai` tinyint(1) DEFAULT 0 AFTER `tags`,
ADD KEY `idx_is_kadai` (`is_kadai`);

-- Update existing child projects to have is_kadai = 1
UPDATE `groupware_projects` 
SET `is_kadai` = 1 
WHERE `parent_project_id` IS NOT NULL; 