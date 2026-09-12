-- Wiki page version history table
CREATE TABLE IF NOT EXISTS `groupware_wiki_page_versions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `page_id` int(11) NOT NULL,
    `content` longtext NOT NULL,
    `content_md` longtext,
    `change_summary` varchar(500),
    `editor_id` varchar(50) NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_id` (`page_id`),
    FOREIGN KEY (`page_id`) REFERENCES `groupware_wiki_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
