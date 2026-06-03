-- 退職日 (resignation date) on groupware_user
ALTER TABLE `groupware_user`
ADD COLUMN `quite_date` DATETIME NULL DEFAULT NULL COMMENT '退職日' AFTER `is_suspend`;
