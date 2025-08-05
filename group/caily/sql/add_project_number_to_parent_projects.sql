-- Add project_number column to existing parent_projects table
ALTER TABLE `groupware_parent_projects` 
ADD COLUMN `project_number` varchar(255) DEFAULT NULL COMMENT 'プロジェクト番号' AFTER `construction_number`,
ADD KEY `idx_project_number` (`project_number`); 