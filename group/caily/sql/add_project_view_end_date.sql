-- Allow viewing 期限日 (end_date) via department project permission
ALTER TABLE `groupware_user_department`
  ADD COLUMN `project_view_end_date` tinyint(1) NOT NULL DEFAULT 0
  COMMENT 'プロジェクト権限: 期限日閲覧'
  AFTER `project_comment`;
