-- Add column "updated_by" (userid of last editor) to project_drawings
ALTER TABLE `groupware_project_drawings`
ADD COLUMN `updated_by` VARCHAR(64) DEFAULT NULL AFTER `updated_at`;
