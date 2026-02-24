-- Add CAILY納期状況 and GUIS納期状況 to projects table
ALTER TABLE `groupware_projects`
ADD COLUMN `caily_nouki_status` varchar(50) DEFAULT NULL COMMENT 'CAILY納期状況' AFTER `caily_nouki`,
ADD COLUMN `guis_nouki_status` varchar(50) DEFAULT NULL COMMENT 'GUIS納期状況' AFTER `guis_nouki`;

