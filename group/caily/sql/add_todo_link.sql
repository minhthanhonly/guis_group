-- Add todo_link (URL or path) to todo table
ALTER TABLE `groupware_todo`
ADD COLUMN `todo_link` VARCHAR(2000) DEFAULT NULL COMMENT 'Link (e.g. project detail URL)' AFTER `todo_title`;
