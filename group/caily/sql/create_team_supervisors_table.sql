-- Team supervisors (managers who are NOT team members)
CREATE TABLE IF NOT EXISTS `groupware_team_supervisors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `team_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL COMMENT 'user.id (numeric)',
    `created_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `team_supervisor_user` (`team_id`,`user_id`),
    KEY `idx_team_supervisors_team` (`team_id`),
    KEY `idx_team_supervisors_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
