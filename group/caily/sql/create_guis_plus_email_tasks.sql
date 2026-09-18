-- GUIS Plus email → Kanri tasks (TaskAPI.php)
CREATE TABLE IF NOT EXISTS `groupware_guis_plus_tasks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `owner` VARCHAR(64) NOT NULL,
  `title` VARCHAR(1000) NOT NULL,
  `description` TEXT NULL,
  `customer` VARCHAR(255) DEFAULT NULL,
  `priority` VARCHAR(32) NOT NULL DEFAULT 'normal',
  `due_date` DATE DEFAULT NULL,
  `category` VARCHAR(128) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  `source` VARCHAR(32) NOT NULL DEFAULT 'email',
  `account_id` VARCHAR(128) DEFAULT NULL,
  `mailbox` VARCHAR(255) DEFAULT 'INBOX',
  `email_uid` VARCHAR(64) DEFAULT NULL,
  `attachment_paths` MEDIUMTEXT NULL,
  `todo_id` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_owner_status` (`owner`, `status`),
  KEY `idx_owner_customer` (`owner`, `customer`),
  KEY `idx_find_by_email` (`owner`, `account_id`, `email_uid`, `mailbox`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
