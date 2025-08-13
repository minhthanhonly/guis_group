-- Add project_id column to quotation_items table
-- This column stores the project ID associated with each quotation item

ALTER TABLE `quotation_items` 
ADD COLUMN `project_id` INT(11) NULL COMMENT 'Associated project ID for this quotation item' 
AFTER `quotation_id`;

-- Add index for better query performance
ALTER TABLE `quotation_items` 
ADD INDEX `idx_project_id` (`project_id`);

-- Add foreign key constraint (optional, depending on your requirements)
-- ALTER TABLE `quotation_items` 
-- ADD CONSTRAINT `fk_quotation_items_project_id` 
-- FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
