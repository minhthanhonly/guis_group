-- Parent Project Notes table (メモ for 親プロジェクト)
CREATE TABLE IF NOT EXISTS `groupware_parent_project_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_project_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_important` tinyint(1) DEFAULT 0,
  `needs_confirmation` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent_project_id` (`parent_project_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_important` (`is_important`),
  KEY `idx_needs_confirmation` (`needs_confirmation`),
  FOREIGN KEY (`parent_project_id`) REFERENCES `groupware_parent_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
