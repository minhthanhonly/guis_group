-- Project Favorites table
CREATE TABLE IF NOT EXISTS `groupware_project_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL COMMENT 'プロジェクトID',
  `user_id` int(11) NOT NULL COMMENT 'ユーザーID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_project` (`project_id`, `user_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_favorites_project` 
    FOREIGN KEY (`project_id`) REFERENCES `groupware_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorites_user_project` 
    FOREIGN KEY (`user_id`) REFERENCES `groupware_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='プロジェクトお気に入り';

