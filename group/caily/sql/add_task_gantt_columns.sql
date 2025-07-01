-- Add missing columns for Gantt chart functionality

-- Add missing columns to tasks table
ALTER TABLE `groupware_tasks` 
ADD COLUMN IF NOT EXISTS `start_date` date DEFAULT NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `actual_end_date` date DEFAULT NULL AFTER `due_date`,
ADD COLUMN IF NOT EXISTS `category_id` int(11) DEFAULT NULL AFTER `progress`,
ADD COLUMN IF NOT EXISTS `position` int(11) DEFAULT 0 AFTER `actual_hours`,
ADD INDEX IF NOT EXISTS `idx_start_date` (`start_date`),
ADD INDEX IF NOT EXISTS `idx_due_date` (`due_date`);

-- Update status enum to include new values
ALTER TABLE `groupware_tasks` 
MODIFY COLUMN `status` enum('todo','in-progress','confirming','paused','completed','cancelled') DEFAULT 'todo';

 