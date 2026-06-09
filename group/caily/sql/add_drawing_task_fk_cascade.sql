-- Cascade delete: when a task is removed, linked drawings are removed automatically.
-- Run after add_drawing_task_link.sql. Clear invalid task_id before adding FK if needed.

UPDATE `groupware_project_drawings` d
LEFT JOIN `groupware_tasks` t ON t.id = d.task_id
SET d.task_id = NULL
WHERE d.task_id IS NOT NULL AND t.id IS NULL;

ALTER TABLE `groupware_project_drawings`
ADD CONSTRAINT `fk_project_drawings_task_id`
FOREIGN KEY (`task_id`) REFERENCES `groupware_tasks` (`id`) ON DELETE CASCADE;
