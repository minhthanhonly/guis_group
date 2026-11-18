-- 月次締め (月次売上統計): a department's closed months. While a month is closed, the 発行済
-- 請求 dated in it cannot be changed or deleted, and no other 請求 can be saved with a date in it.
-- Project::ensureRevenueClosingsTable() also creates it on first use.
CREATE TABLE IF NOT EXISTS `groupware_revenue_month_closings` (
    `department_id` INT NOT NULL,
    `month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `closed_at` DATETIME NOT NULL,
    `closed_by` VARCHAR(100) NOT NULL DEFAULT '',
    PRIMARY KEY (`department_id`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='月次締め (請求)';
