-- Project Folders Table
CREATE TABLE IF NOT EXISTS `caily_project_folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `parent_folder_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_parent_folder_id` (`parent_folder_id`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`project_id`) REFERENCES `caily_projects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_folder_id`) REFERENCES `caily_project_folders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `caily_user` (`userid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project Attachments Table
CREATE TABLE IF NOT EXISTS `caily_project_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_folder_id` (`folder_id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_uploaded_at` (`uploaded_at`),
  FOREIGN KEY (`project_id`) REFERENCES `caily_projects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`folder_id`) REFERENCES `caily_project_folders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `caily_user` (`userid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 