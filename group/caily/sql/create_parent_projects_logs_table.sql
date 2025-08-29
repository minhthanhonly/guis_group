-- Parent Projects Logs table
CREATE TABLE IF NOT EXISTS `groupware_parent_projects_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_project_id` int(11) NOT NULL COMMENT '親プロジェクトID',
  `user_id` varchar(50) NOT NULL COMMENT 'ユーザーID',
  `username` varchar(255) NOT NULL COMMENT 'ユーザー名',
  `action` varchar(100) NOT NULL COMMENT 'アクション',
  `note` text DEFAULT NULL COMMENT 'メモ',
  `value1` varchar(255) DEFAULT NULL COMMENT '変更前の値',
  `value2` varchar(255) DEFAULT NULL COMMENT '変更後の値',
  `time` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'ログ時間',
  PRIMARY KEY (`id`),
  KEY `idx_parent_project_id` (`parent_project_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_time` (`time`),
  FOREIGN KEY (`parent_project_id`) REFERENCES `groupware_parent_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
