-- Add registration_number field to branches table
ALTER TABLE `groupware_branches` 
ADD COLUMN `registration_number` VARCHAR(100) DEFAULT NULL COMMENT '登録番号' AFTER `name_kana`;

-- Add index for better performance
CREATE INDEX `idx_registration_number` ON `groupware_branches` (`registration_number`);
