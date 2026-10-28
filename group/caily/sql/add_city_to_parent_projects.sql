-- Parent project: 市区町村 / thành phố
ALTER TABLE `groupware_parent_projects`
  ADD COLUMN `city` VARCHAR(255) NULL DEFAULT NULL COMMENT '市区町村' AFTER `scale`;
