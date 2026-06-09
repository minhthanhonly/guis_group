-- Task kind (新規作成 / 修正(エラー) / 修正(変更)) and drawing count per task
-- Run once. If columns already exist, skip or comment out the matching line(s).
ALTER TABLE `groupware_tasks`
ADD COLUMN `task_kind` varchar(32) NOT NULL DEFAULT '新規作成' AFTER `priority`;

ALTER TABLE `groupware_tasks`
ADD COLUMN `drawing_count` int(11) NOT NULL DEFAULT 0 AFTER `task_kind`;

ALTER TABLE `groupware_tasks`
ADD COLUMN `note` text DEFAULT NULL AFTER `estimated_hours`;

ALTER TABLE `groupware_project_drawings`
ADD COLUMN `task_id` int(11) DEFAULT NULL AFTER `project_id`,
ADD COLUMN `drawing_slot` int(11) DEFAULT NULL AFTER `task_id`,
ADD KEY `idx_task_id` (`task_id`);

ALTER TABLE `groupware_project_drawings`
ADD COLUMN `drawing_count` int(11) NOT NULL DEFAULT 1 AFTER `drawing_slot`;


UPDATE `groupware_project_drawings` SET `status` = 'todo' WHERE `status` = 'draft';
UPDATE `groupware_project_drawings` SET `status` = 'confirming' WHERE `status` = 'review';
UPDATE `groupware_project_drawings` SET `status` = 'in-progress' WHERE `status` IN ('revision', 'revised');
UPDATE `groupware_project_drawings` SET `status` = 'completed' WHERE `status` = 'approved';
UPDATE `groupware_project_drawings` SET `status` = 'cancelled' WHERE `status` = 'rejected';


UPDATE `groupware_project_drawings` d
LEFT JOIN `groupware_tasks` t ON t.id = d.task_id
SET d.task_id = NULL
WHERE d.task_id IS NOT NULL AND t.id IS NULL;

ALTER TABLE `groupware_project_drawings`
ADD CONSTRAINT `fk_project_drawings_task_id`
FOREIGN KEY (`task_id`) REFERENCES `groupware_tasks` (`id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `groupware_time_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `hours` decimal(10,2) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`task_id`) REFERENCES `groupware_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;