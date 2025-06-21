-- Database tables for Project Management System

-- Projects table
CREATE TABLE IF NOT EXISTS `groupware_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_number` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('draft','open','in_progress','completed','paused','cancelled') DEFAULT 'draft',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `estimated_hours` decimal(10,2) DEFAULT 0.00,
  `actual_hours` decimal(10,2) DEFAULT 0.00,
  `customer_id` int(11) DEFAULT NULL,
  `building_size` varchar(255) DEFAULT NULL,
  `building_type` varchar(255) DEFAULT NULL,
  `building_number` varchar(255) DEFAULT NULL,
  `project_order_type` varchar(50) DEFAULT NULL,
  `project_estimate_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_department_id` (`department_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`department_id`) REFERENCES `groupware_departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks table
CREATE TABLE IF NOT EXISTS `groupware_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `status` enum('new','in_progress','review','completed') DEFAULT 'new',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `assigned_to` varchar(50) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `estimated_hours` decimal(10,2) DEFAULT 0.00,
  `actual_hours` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`project_id`) REFERENCES `groupware_projects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `groupware_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project Members table
CREATE TABLE IF NOT EXISTS `groupware_project_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `role` enum('manager','member','viewer') DEFAULT 'member',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_user` (`project_id`, `user_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`project_id`) REFERENCES `groupware_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project Comments table
CREATE TABLE IF NOT EXISTS `groupware_project_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`project_id`) REFERENCES `groupware_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time Entries table
CREATE TABLE IF NOT EXISTS `groupware_time_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `hours` decimal(10,2) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`task_id`) REFERENCES `groupware_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task Comments table
CREATE TABLE IF NOT EXISTS `groupware_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`task_id`) REFERENCES `groupware_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Departments table
CREATE TABLE IF NOT EXISTS `groupware_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `can_project` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Branches table
CREATE TABLE IF NOT EXISTS `groupware_branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `name_kana` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leave Requests table
CREATE TABLE IF NOT EXISTS `groupware_leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `days` decimal(5,2) NOT NULL,
  `leave_type` enum('paid','unpaid') NOT NULL,
  `paid_type` enum('full','am','pm') DEFAULT NULL,
  `unpaid_type` enum('congratulatory','menstrual','child_nursing') DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample departments
INSERT IGNORE INTO `groupware_departments` (`id`, `name`, `description`, `can_project`, `created_at`, `updated_at`) VALUES
(1, '開発部', '開発プロジェクト担当', 1, NOW(), NOW()),
(2, '営業部', '営業活動担当', 1, NOW(), NOW()),
(3, '人事部', '人事管理担当', 0, NOW(), NOW()),
(4, '総務部', '総務担当', 0, NOW(), NOW());

-- Bảng notification: chỉ lưu nội dung, không lưu user nhận trực tiếp
CREATE TABLE IF NOT EXISTS `notification` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event` VARCHAR(64) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `data` TEXT DEFAULT NULL,
    `project_id` INT DEFAULT NULL,
    `task_id` INT DEFAULT NULL,
    `request_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`event`),
    INDEX (`project_id`),
    INDEX (`task_id`),
    INDEX (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng notification_user: mapping notification <-> user nhận, lưu trạng thái đã đọc
CREATE TABLE IF NOT EXISTS `notification_user` (
    `notification_id` INT UNSIGNED NOT NULL,
    `user_id` VARCHAR(64) NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`notification_id`, `user_id`),
    FOREIGN KEY (`notification_id`) REFERENCES `notification`(`id`) ON DELETE CASCADE,
    INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 