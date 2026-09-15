-- Storage (ファイル共有) protected download / view audit
ALTER TABLE `groupware_storage`
  ADD COLUMN `is_protected` TINYINT(1) NOT NULL DEFAULT 0 AFTER `storage_size`,
  ADD KEY `idx_storage_is_protected` (`is_protected`);

CREATE TABLE IF NOT EXISTS `groupware_storage_view_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `storage_id` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `userid` varchar(64) NOT NULL,
  `realname` varchar(255) DEFAULT NULL,
  `action` varchar(32) NOT NULL DEFAULT 'view',
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_storage_id` (`storage_id`),
  KEY `idx_userid` (`userid`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
