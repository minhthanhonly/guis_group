-- Wiki reactions table (useful, outdated, bookmark)
CREATE TABLE IF NOT EXISTS `groupware_wiki_reactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `page_id` int(11) NOT NULL,
    `user_id` varchar(50) NOT NULL,
    `type` enum('useful','outdated','bookmark') NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_page_user_type` (`page_id`, `user_id`, `type`),
    KEY `idx_page_id` (`page_id`),
    KEY `idx_user_id` (`user_id`),
    FOREIGN KEY (`page_id`) REFERENCES `groupware_wiki_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
