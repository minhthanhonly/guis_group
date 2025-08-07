-- Add cost column and type field to price_list_products table
ALTER TABLE `price_list_products` 
ADD COLUMN `cost` decimal(15,2) DEFAULT 0.00 COMMENT '売上原価' AFTER `price`,
ADD COLUMN `type` enum('新規','修正','その他') DEFAULT '新規' COMMENT '商品タイプ' AFTER `department_id`;

-- Update existing records to have default type
UPDATE `price_list_products` SET `type` = '新規' WHERE `type` IS NULL;
