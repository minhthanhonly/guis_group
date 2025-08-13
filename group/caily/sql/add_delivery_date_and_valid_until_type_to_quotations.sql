-- Add delivery_date and valid_until_type columns to quotations table
ALTER TABLE `quotations` 
ADD COLUMN `delivery_date` date DEFAULT NULL AFTER `total_with_tax`,
ADD COLUMN `valid_until_type` varchar(20) DEFAULT '1_month' AFTER `payment_method`;

-- Update existing records to have default valid_until_type
UPDATE `quotations` SET `valid_until_type` = '1_month' WHERE `valid_until_type` IS NULL;
