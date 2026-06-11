-- Add GUIS receiver to child projects (optional override of parent building receiver)
ALTER TABLE `groupware_projects`
ADD COLUMN `guis_receiver` varchar(255) DEFAULT NULL COMMENT 'GUIS　受付者 (userid)' AFTER `customer_id`;
