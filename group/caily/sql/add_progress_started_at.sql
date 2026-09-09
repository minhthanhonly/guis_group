-- Timestamp of the first progress update (for behind-schedule judgment)
ALTER TABLE `groupware_projects`
ADD COLUMN `progress_started_at` DATETIME NULL DEFAULT NULL COMMENT '初回進捗更新日時' AFTER `progress`;
