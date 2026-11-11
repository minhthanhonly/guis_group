-- Parent project (建物) : multiple customers with exactly one primary (主顧客).
-- groupware_parent_projects.customer_id / company_name / branch_name / contact_name
-- stay as a mirror of the primary customer, so existing lists/filters keep working.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS `groupware_parent_project_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_project_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = 主顧客 (連絡先)',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parent_project_customer` (`parent_project_id`, `customer_id`),
  KEY `idx_ppc_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing data: the current customer of each parent project becomes its primary customer.
INSERT INTO `groupware_parent_project_customers`
  (`parent_project_id`, `customer_id`, `is_primary`, `sort_order`, `created_at`)
SELECT p.id, CAST(p.customer_id AS UNSIGNED), 1, 0, NOW()
FROM `groupware_parent_projects` p
INNER JOIN `groupware_customer` c ON c.id = CAST(p.customer_id AS UNSIGNED)
WHERE p.customer_id REGEXP '^[0-9]+$'
  AND NOT EXISTS (
    SELECT 1 FROM `groupware_parent_project_customers` l WHERE l.parent_project_id = p.id
  );
