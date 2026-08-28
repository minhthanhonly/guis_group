-- Custom todo display order (per owner)
ALTER TABLE `groupware_todo`
ADD COLUMN `todo_sort` INT NOT NULL DEFAULT 0 COMMENT 'Display order in custom todo widget' AFTER `todo_complete`;
