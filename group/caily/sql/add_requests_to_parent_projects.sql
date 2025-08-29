-- Add requests column to parent_projects table
ALTER TABLE `parent_projects` ADD COLUMN `requests` TEXT NULL AFTER `desired_delivery_date`;

-- Add comment to the column
ALTER TABLE `parent_projects` MODIFY COLUMN `requests` TEXT NULL COMMENT 'Request types: 意匠,設備,省エネ,その他 (comma-separated)';
