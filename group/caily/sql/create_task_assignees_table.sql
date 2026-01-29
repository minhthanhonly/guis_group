-- Task Assignees table for tracking task assignment acknowledgements
CREATE TABLE IF NOT EXISTS `groupware_task_assignees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Internal user ID',
  `acknowledged` tinyint(1) DEFAULT 0 COMMENT '0 = chưa nhận, 1 = đã nhận',
  `acknowledged_at` datetime DEFAULT NULL COMMENT 'Thời điểm đánh dấu đã nhận',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_user` (`task_id`, `user_id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_acknowledged` (`acknowledged`),
  FOREIGN KEY (`task_id`) REFERENCES `groupware_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bảng phân công task và trạng thái xác nhận đã nhận việc';
