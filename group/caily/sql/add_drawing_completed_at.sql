-- Drawing completion timestamp for revenue statistics (by completed_at, not created_at).
-- Run once.

ALTER TABLE `groupware_project_drawings`
ADD COLUMN `completed_at` timestamp NULL DEFAULT NULL AFTER `updated_at`;

-- Backfill existing completed drawings (prefer updated_at, then check_date, then created_at).
UPDATE `groupware_project_drawings`
SET `completed_at` = COALESCE(`updated_at`, `check_date`, `created_at`)
WHERE `status` IN ('completed', 'approved')
  AND `completed_at` IS NULL;
