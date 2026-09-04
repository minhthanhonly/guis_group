-- Fix: allow 4-byte UTF-8 (rare CJK / emoji) in parent_projects
-- Error example: Incorrect string value: '\xF0\xA0\xAE\xB7...' for column 'project_name'
-- Cause: table/column charset utf8 (max 3 bytes) instead of utf8mb4

ALTER TABLE `groupware_parent_projects`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Also fix related text tables used with parent projects (safe if already utf8mb4)
ALTER TABLE `groupware_parent_project_notes`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `groupware_parent_projects_logs`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
