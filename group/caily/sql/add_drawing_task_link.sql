-- Link project drawings to tasks (auto-sync from task drawing_count)
ALTER TABLE `groupware_project_drawings`
ADD COLUMN `task_id` int(11) DEFAULT NULL AFTER `project_id`,
ADD COLUMN `drawing_slot` int(11) DEFAULT NULL AFTER `task_id`,
ADD KEY `idx_task_id` (`task_id`);
