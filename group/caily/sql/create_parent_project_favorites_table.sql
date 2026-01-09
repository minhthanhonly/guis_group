-- Parent Project Favorites table
CREATE TABLE IF NOT EXISTS `groupware_parent_project_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_project_id` int(11) NOT NULL COMMENT '親プロジェクトID',
  `user_id` int(11) NOT NULL COMMENT 'ユーザーID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_project` (`parent_project_id`, `user_id`),
  KEY `idx_parent_project_id` (`parent_project_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_favorites_parent_project` 
    FOREIGN KEY (`parent_project_id`) REFERENCES `groupware_parent_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorites_user` 
    FOREIGN KEY (`user_id`) REFERENCES `groupware_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='親プロジェクトお気に入り';

