-- Link child project to a parent 依頼 type (e.g. 意匠, 設備, 3D設備)
ALTER TABLE `groupware_projects`
  ADD COLUMN `parent_request_type` VARCHAR(64) NULL DEFAULT NULL
  COMMENT 'Loại 依頼 của parent mà child đáp ứng'
  AFTER `parent_project_id`;
