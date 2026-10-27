-- Per-department default tasks created when a child project is created.
-- tasks JSON: [{title, price_pct, task_kind, assign_guis_receiver, status, progress, drawing_count}, ...]
CREATE TABLE IF NOT EXISTS `department_default_tasks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `department_id` INT NOT NULL,
  `tasks` TEXT NOT NULL COMMENT 'JSON array of default task definitions',
  `updated_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_department_default_tasks_department` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
