-- Wiki page tags junction table (reuses existing groupware_tags table)
CREATE TABLE IF NOT EXISTS `groupware_wiki_page_tags` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `page_id` int(11) NOT NULL,
    `tag_id` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_page_tag` (`page_id`, `tag_id`),
    KEY `idx_page_id` (`page_id`),
    KEY `idx_tag_id` (`tag_id`),
    FOREIGN KEY (`page_id`) REFERENCES `groupware_wiki_pages` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`) REFERENCES `groupware_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
