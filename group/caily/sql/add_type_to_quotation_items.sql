-- Add type column to quotation_items table
-- This column stores the product type for each quotation item

ALTER TABLE `quotation_items` 
ADD COLUMN `type` VARCHAR(100) NULL COMMENT 'Product type for this quotation item' 
AFTER `product_name`;

-- Add index for better query performance
ALTER TABLE `quotation_items` 
ADD INDEX `idx_type` (`type`);
