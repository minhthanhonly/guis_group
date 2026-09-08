-- Branch / locality technical specs (multi-department) + parent construction_city

CREATE TABLE IF NOT EXISTS `groupware_branch_spec_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_key` varchar(32) NOT NULL COMMENT 'isho|setsubi|...',
  `company_key` varchar(255) DEFAULT '*' COMMENT '* = any company',
  `priority` int(11) DEFAULT 0,
  `match_branch` varchar(500) DEFAULT NULL COMMENT 'CSV normalized branch names',
  `match_prefecture` varchar(500) DEFAULT NULL COMMENT 'CSV prefectures',
  `match_city` varchar(500) DEFAULT NULL COMMENT 'CSV cities',
  `match_structure` varchar(20) DEFAULT 'any' COMMENT 'wood|steel_rc|both|any',
  `match_type1` varchar(255) DEFAULT NULL,
  `match_type2` varchar(255) DEFAULT NULL,
  `match_scale_pattern` varchar(255) DEFAULT NULL COMMENT 'regex-ish OR patterns separated by |',
  `match_note` varchar(500) DEFAULT NULL,
  `title_ja` varchar(500) DEFAULT NULL,
  `title_vi` varchar(500) DEFAULT NULL,
  `content_ja` text,
  `content_vi` text,
  `apply_electrical` tinyint(1) DEFAULT 0,
  `apply_sanitary` tinyint(1) DEFAULT 0,
  `apply_architectural` tinyint(1) DEFAULT 0,
  `apply_wood` tinyint(1) DEFAULT 1,
  `apply_steel_rc` tinyint(1) DEFAULT 1,
  `requires_manual_confirm` tinyint(1) DEFAULT 0,
  `source_sheet` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dept_active` (`department_key`, `is_active`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parent: city/ward for locality matching (高槻市, 神戸市, ...)
ALTER TABLE `groupware_parent_projects`
  ADD COLUMN `construction_city` varchar(255) DEFAULT NULL COMMENT '建築地（市区町村）' AFTER `construction_branch`;
