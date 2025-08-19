-- Quotation History table
-- This table stores the activity and edit history of quotations
CREATE TABLE IF NOT EXISTS `quotation_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL COMMENT 'Reference to the quotation',
  `user_id` varchar(50) NOT NULL COMMENT 'User ID who performed the action',
  `username` varchar(255) NOT NULL COMMENT 'Real name of the user',
  `action` varchar(100) NOT NULL COMMENT 'Action performed (created, updated, status_changed, etc.)',
  `note` text COMMENT 'Description of the action',
  `value1` varchar(255) DEFAULT NULL COMMENT 'Previous value (for changes)',
  `value2` varchar(255) DEFAULT NULL COMMENT 'New value (for changes)',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action occurred',
  PRIMARY KEY (`id`),
  KEY `idx_quotation_id` (`quotation_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_time` (`time`),
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
