-- Update status enum in existing parent_projects table
ALTER TABLE `groupware_parent_projects` 
MODIFY COLUMN `status` enum('draft','under_contract','in_progress','completed','cancelled') DEFAULT 'draft'; 