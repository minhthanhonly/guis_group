-- 作業比重: existing projects keep the fixed default-task % (0); every project created afterwards uses 作業比重 (1).
-- Also added at runtime by Project::ensureTaskWeightModeColumn().
ALTER TABLE `groupware_projects`
  ADD COLUMN `task_weight_mode` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = 作業比重 (new projects), 0 = fixed default-task % (projects before 作業比重)';
ALTER TABLE `groupware_projects` ALTER COLUMN `task_weight_mode` SET DEFAULT 1;
