-- Parent project: 特徴 / 申請提出先の審査機関 / BELSのZEH等級
ALTER TABLE `groupware_parent_projects`
  ADD COLUMN `features` TEXT NULL DEFAULT NULL COMMENT '特徴 (comma-separated)' AFTER `materials`,
  ADD COLUMN `application_review_agency` VARCHAR(255) NULL DEFAULT NULL COMMENT '申請提出先の審査機関' AFTER `features`,
  ADD COLUMN `bels_zeh_grade` VARCHAR(10) NULL DEFAULT '0' COMMENT 'BELSのZEH等級 (0-6)' AFTER `application_review_agency`;
