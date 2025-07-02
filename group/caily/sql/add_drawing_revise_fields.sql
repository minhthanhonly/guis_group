ALTER TABLE `groupware_project_drawings`
ADD COLUMN `revise_by` INT DEFAULT NULL AFTER `checked_by`,
ADD COLUMN `revise_date` DATETIME DEFAULT NULL AFTER `revise_by`; 