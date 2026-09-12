-- Wiki subscriptions table (tag/team/page level watch)
CREATE TABLE IF NOT EXISTS `groupware_wiki_subscriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` varchar(50) NOT NULL,
    `target_type` enum('tag','team','page') NOT NULL,
    `target_id` int(11) NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_target` (`user_id`, `target_type`, `target_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
