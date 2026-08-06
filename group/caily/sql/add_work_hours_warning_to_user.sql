-- GUIS Plus: per-user work-hours overtime toast (default OFF)
ALTER TABLE `groupware_user`
  ADD COLUMN `work_hours_warning` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'GUIS Plus overtime toast: 1=enabled 0=disabled'
  AFTER `can_approve_request`;
