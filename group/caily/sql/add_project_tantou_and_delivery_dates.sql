-- Add 担当 (tantou), CAILY納期, GUIS納期 to projects table
ALTER TABLE `groupware_projects`
ADD COLUMN `tantou` varchar(20) DEFAULT NULL COMMENT '担当: CAILY or GUIS' AFTER `actual_end_date`,
ADD COLUMN `caily_nouki` datetime DEFAULT NULL COMMENT 'CAILY納期' AFTER `tantou`,
ADD COLUMN `guis_nouki` datetime DEFAULT NULL COMMENT 'GUIS納期' AFTER `caily_nouki`;
