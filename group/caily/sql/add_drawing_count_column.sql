-- Number of drawings represented by one list row (task-synced rows use task.drawing_count)
ALTER TABLE `groupware_project_drawings`
ADD COLUMN `drawing_count` int(11) NOT NULL DEFAULT 1 AFTER `drawing_slot`;
