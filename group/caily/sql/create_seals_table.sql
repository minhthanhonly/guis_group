-- Create seals table for managing company and employee seals
CREATE TABLE IF NOT EXISTS `groupware_seals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '印鑑名',
  `type` enum('company','employee') NOT NULL DEFAULT 'company' COMMENT 'タイプ',
  `owner_name` varchar(255) DEFAULT NULL COMMENT '所有者名',
  `owner_id` int(11) DEFAULT NULL COMMENT '所有者ID (employeeの場合)',
  `description` text DEFAULT NULL COMMENT '説明',
  `image_path` varchar(500) NOT NULL COMMENT '画像パス',
  `file_name` varchar(255) NOT NULL COMMENT 'ファイル名',
  `file_size` bigint(20) DEFAULT NULL COMMENT 'ファイルサイズ',
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'MIMEタイプ',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '有効/無効',
  `created_by` varchar(50) DEFAULT NULL COMMENT '作成者',
  `updated_by` varchar(50) DEFAULT NULL COMMENT '更新者',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='印鑑管理テーブル';
