-- Create employee statistics table to store calculated statistics
CREATE TABLE IF NOT EXISTS `groupware_employee_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL COMMENT 'ユーザーID',
  `team_id` int(11) DEFAULT NULL COMMENT 'チームID',
  `period_type` enum('week','month','quarter','year') NOT NULL COMMENT '期間タイプ',
  `period_start` date NOT NULL COMMENT '期間開始日',
  `period_end` date NOT NULL COMMENT '期間終了日',
  `revenue` decimal(15,2) DEFAULT 0.00 COMMENT '売上高',
  `task_likes` int(11) DEFAULT 0 COMMENT 'タスクいいね数',
  `task_dislikes` int(11) DEFAULT 0 COMMENT 'タスクいいね数',
  `total_drawings_revenue` decimal(15,2) DEFAULT 0.00 COMMENT '図面売上合計',
  `drawing_count` int(11) DEFAULT 0 COMMENT '図面数',
  `task_count` int(11) DEFAULT 0 COMMENT 'タスク数',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_period` (`user_id`, `period_type`, `period_start`, `period_end`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_team_id` (`team_id`),
  KEY `idx_period_type` (`period_type`),
  KEY `idx_period_start` (`period_start`),
  KEY `idx_period_end` (`period_end`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='従業員統計テーブル';

