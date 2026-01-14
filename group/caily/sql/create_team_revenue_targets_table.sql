-- Create team revenue targets table to store yearly revenue targets for teams
CREATE TABLE IF NOT EXISTS `groupware_team_revenue_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL COMMENT 'チームID',
  `year` int(4) NOT NULL COMMENT '年度',
  `yearly_target` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '年間目標売上高',
  `monthly_target` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '月間目標売上高（自動計算：年間/12）',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_team_year` (`team_id`, `year`),
  KEY `idx_team_id` (`team_id`),
  KEY `idx_year` (`year`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='チーム売上目標テーブル';

