-- Create team table
CREATE TABLE IF NOT EXISTS `groupware_team` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `department_id` int(11) NOT NULL,
    `description` text,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create team members table
CREATE TABLE IF NOT EXISTS `groupware_team_members` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `team_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `project_edit` tinyint(1) NOT NULL DEFAULT '0',
    `project_delete` tinyint(1) NOT NULL DEFAULT '0',
    `project_comment` tinyint(1) NOT NULL DEFAULT '0',
    `task_view` tinyint(1) NOT NULL DEFAULT '1',
    `task_add` tinyint(1) NOT NULL DEFAULT '1',
    `task_edit` tinyint(1) NOT NULL DEFAULT '1',
    `task_delete` tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `team_user` (`team_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 