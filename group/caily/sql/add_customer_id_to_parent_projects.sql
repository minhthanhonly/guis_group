-- Add customer_id column to parent_projects table
-- This moves customer relationship from child projects to parent projects

ALTER TABLE `groupware_parent_projects` 
ADD COLUMN `customer_id` VARCHAR(255) DEFAULT NULL COMMENT 'Customer ID from customer table' 
AFTER `contact_name`;

-- Add index for better performance
ALTER TABLE `groupware_parent_projects` 
ADD KEY `idx_customer_id` (`customer_id`);

-- Remove customer_id column from projects table if it exists (optional migration)
-- Uncomment the following lines if you want to remove customer_id from projects table
-- ALTER TABLE `groupware_projects` DROP COLUMN IF EXISTS `customer_id`;