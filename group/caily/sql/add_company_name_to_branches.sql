-- Add company_name field to branches table
ALTER TABLE `groupware_branches` 
ADD COLUMN `company_name` VARCHAR(255) DEFAULT NULL COMMENT '会社名' AFTER `registration_number`;

-- Add index for better performance
CREATE INDEX `idx_company_name` ON `groupware_branches` (`company_name`);
