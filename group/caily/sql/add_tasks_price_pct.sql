-- タスク画面: per-project 比率(%) of a default task. NULL = department 既定タスク config.
-- Also added at runtime by Task::ensurePricePctColumn().
ALTER TABLE `groupware_tasks`
  ADD COLUMN `price_pct` DECIMAL(5,2) NULL DEFAULT NULL
  COMMENT 'Default task 比率(%) override for this project; NULL = department config'
  AFTER `drawing_count`;
