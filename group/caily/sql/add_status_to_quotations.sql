-- Add missing columns to quotations table
ALTER TABLE `quotations` ADD COLUMN `status` VARCHAR(20) DEFAULT '下書き' AFTER `parent_project_id`;
ALTER TABLE `quotations` ADD COLUMN `receiver_tel` VARCHAR(50) DEFAULT NULL AFTER `receiver_contact`;
ALTER TABLE `quotations` ADD COLUMN `receiver_fax` VARCHAR(50) DEFAULT NULL AFTER `receiver_tel`;
ALTER TABLE `quotations` ADD COLUMN `receiver_registration_number` VARCHAR(100) DEFAULT NULL AFTER `receiver_fax`;
ALTER TABLE `quotations` ADD COLUMN `delivery_date` VARCHAR(255) DEFAULT NULL AFTER `total_with_tax`;
ALTER TABLE `quotations` ADD COLUMN `valid_until_type` VARCHAR(50) DEFAULT NULL AFTER `delivery_date`;

-- Add indexes for better performance
ALTER TABLE `quotations` ADD INDEX `idx_status` (`status`);
ALTER TABLE `quotations` ADD INDEX `idx_receiver_tel` (`receiver_tel`);
ALTER TABLE `quotations` ADD INDEX `idx_receiver_fax` (`receiver_fax`);

-- Update existing quotations to have default status
UPDATE `quotations` SET `status` = '下書き' WHERE `status` IS NULL;
