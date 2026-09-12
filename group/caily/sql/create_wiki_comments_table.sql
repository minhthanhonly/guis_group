-- Wiki comments table (threaded via parent_id)
CREATE TABLE IF NOT EXISTS `groupware_wiki_comments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `page_id` int(11) NOT NULL,
    `user_id` varchar(50) NOT NULL,
    `parent_id` int(11) DEFAULT NULL,
    `content` text NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_id` (`page_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_parent_id` (`parent_id`),
    FOREIGN KEY (`page_id`) REFERENCES `groupware_wiki_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
