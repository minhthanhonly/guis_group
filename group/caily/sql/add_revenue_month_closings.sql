-- 月次締め (月次売上統計): a department's closed months. While a month is closed, the
-- 見積 / 請求 lines dated in it cannot be changed, deleted or added.
-- Project::ensureRevenueClosingsTable() also creates it on first use.
CREATE TABLE IF NOT EXISTS `groupware_revenue_month_closings` (
    `department_id` INT NOT NULL,
    `month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `closed_at` DATETIME NOT NULL,
    `closed_by` VARCHAR(100) NOT NULL DEFAULT '',
    PRIMARY KEY (`department_id`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='月次締め (見積・請求)';
