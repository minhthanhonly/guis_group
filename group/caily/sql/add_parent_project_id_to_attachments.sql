-- Add parent_project_id field to existing project attachment tables

-- Add parent_project_id to project_folders table
ALTER TABLE `caily_project_folders` 
ADD COLUMN `parent_project_id` int(11) DEFAULT NULL AFTER `project_id`,
ADD KEY `idx_parent_project_id` (`parent_project_id`),
ADD FOREIGN KEY (`parent_project_id`) REFERENCES `caily_parent_projects` (`id`) ON DELETE CASCADE;

-- Add parent_project_id to project_attachments table  
ALTER TABLE `caily_project_attachments` 
ADD COLUMN `parent_project_id` int(11) DEFAULT NULL AFTER `project_id`,
ADD KEY `idx_parent_project_id` (`parent_project_id`),
ADD FOREIGN KEY (`parent_project_id`) REFERENCES `caily_parent_projects` (`id`) ON DELETE CASCADE;
