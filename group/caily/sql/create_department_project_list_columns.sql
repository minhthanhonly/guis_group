-- Per-department default column layout for project list (visibility / order / widths).
CREATE TABLE IF NOT EXISTS `department_project_list_columns` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `department_id` INT NOT NULL,
  `config` MEDIUMTEXT NOT NULL COMMENT 'JSON: {visibility, order, widths}',
  `updated_by` VARCHAR(50) NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_project_list_columns` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
