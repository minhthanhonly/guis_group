-- Parent Projects table
CREATE TABLE IF NOT EXISTS `groupware_parent_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL COMMENT '会社名',
  `branch_name` varchar(255) DEFAULT NULL COMMENT '支店名',
  `contact_name` varchar(255) DEFAULT NULL COMMENT '担当様',
  `guis_receiver` varchar(255) DEFAULT NULL COMMENT 'GUIS　受付者',
  `request_date` date DEFAULT NULL COMMENT '依頼日',
  `construction_number` varchar(255) DEFAULT NULL COMMENT '工事番号',
  `project_name` varchar(255) NOT NULL COMMENT '案件名',
  `scale` varchar(255) DEFAULT NULL COMMENT '規模',
  `type1` varchar(255) DEFAULT NULL COMMENT '種類1',
  `type2` varchar(255) DEFAULT NULL COMMENT '種類2',
  `type3` varchar(255) DEFAULT NULL COMMENT '種類3',
  `request_type` varchar(255) DEFAULT NULL COMMENT '依頼',
  `desired_delivery_date` date DEFAULT NULL COMMENT '希望納期',
  `materials` text DEFAULT NULL COMMENT '資料',
  `structural_office` varchar(255) DEFAULT NULL COMMENT '構造事務所',
  `notes` text DEFAULT NULL COMMENT '備考',
  `status` enum('draft','open','in_progress','completed','cancelled') DEFAULT 'draft',
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company_name` (`company_name`),
  KEY `idx_construction_number` (`construction_number`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_request_date` (`request_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add parent_project_id to existing projects table to link child projects
ALTER TABLE `groupware_projects` 
ADD COLUMN `parent_project_id` int(11) DEFAULT NULL AFTER `id`,
ADD KEY `idx_parent_project_id` (`parent_project_id`),
ADD CONSTRAINT `fk_projects_parent_project` 
FOREIGN KEY (`parent_project_id`) REFERENCES `groupware_parent_projects` (`id`) ON DELETE SET NULL; 