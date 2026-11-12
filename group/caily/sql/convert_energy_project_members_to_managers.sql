-- 省エネ departments: メンバー is no longer used; the people in charge are 管理 (shown as 担当者).
-- This converts the existing メンバー (role = 'member') of every project in a 省エネ department
-- into 管理 (role = 'manager'), so the 担当者 field shows them.
--
-- Run manually, once. Read before running:
--   * 管理 may edit the project (Project::canUserEditProject), so the converted users gain that right.
--   * Scope: projects whose department name contains 省エネ, any status.
--   * A user who is already 管理 of the same project is skipped (no duplicate manager row);
--     their leftover member row stays as it is (see the optional step 4).
--   * created_at has ON UPDATE CURRENT_TIMESTAMP: it is re-assigned to itself so the dates are kept.
--   * Re-running is harmless: once converted there is nothing left to update.

-- 1) Preview: rows that will be converted, and rows skipped because the user is already 管理
SELECT
    SUM(mg.id IS NULL)     AS rows_to_convert,
    SUM(mg.id IS NOT NULL) AS rows_skipped_already_manager,
    COUNT(DISTINCT CASE WHEN mg.id IS NULL THEN m.project_id END) AS projects_affected
FROM `groupware_project_members` m
JOIN `groupware_projects` p ON p.id = m.project_id
JOIN `groupware_departments` d ON d.id = p.department_id
LEFT JOIN `groupware_project_members` mg
       ON mg.project_id = m.project_id AND mg.user_id = m.user_id AND mg.role = 'manager'
WHERE d.name LIKE '%省エネ%'
  AND m.role = 'member';

-- 2) Backup of the rows about to change (needed for the rollback at the bottom).
--    CREATE TABLE commits implicitly, so run it before the UPDATE, not inside a transaction with it.
CREATE TABLE IF NOT EXISTS `groupware_project_members_energy_backup_20261112` AS
SELECT m.*
FROM `groupware_project_members` m
JOIN `groupware_projects` p ON p.id = m.project_id
JOIN `groupware_departments` d ON d.id = p.department_id
LEFT JOIN `groupware_project_members` mg
       ON mg.project_id = m.project_id AND mg.user_id = m.user_id AND mg.role = 'manager'
WHERE d.name LIKE '%省エネ%'
  AND m.role = 'member'
  AND mg.id IS NULL;

-- 3) Convert メンバー -> 管理
UPDATE `groupware_project_members` m
JOIN `groupware_projects` p ON p.id = m.project_id
JOIN `groupware_departments` d ON d.id = p.department_id
LEFT JOIN `groupware_project_members` mg
       ON mg.project_id = m.project_id AND mg.user_id = m.user_id AND mg.role = 'manager'
SET m.role = 'manager',
    m.created_at = m.created_at
WHERE d.name LIKE '%省エネ%'
  AND m.role = 'member'
  AND mg.id IS NULL;

-- 4) Optional: remove member rows of users who were already 管理 of the same project
--    (they are hidden in the forms either way). Check the count from step 1 first.
-- DELETE m
-- FROM `groupware_project_members` m
-- JOIN `groupware_projects` p ON p.id = m.project_id
-- JOIN `groupware_departments` d ON d.id = p.department_id
-- JOIN `groupware_project_members` mg
--        ON mg.project_id = m.project_id AND mg.user_id = m.user_id AND mg.role = 'manager'
-- WHERE d.name LIKE '%省エネ%'
--   AND m.role = 'member';

-- Rollback (only rows this script converted, using the backup from step 2):
-- UPDATE `groupware_project_members` m
-- JOIN `groupware_project_members_energy_backup_20261112` b ON b.id = m.id
-- SET m.role = 'member',
--     m.created_at = b.created_at
-- WHERE m.role = 'manager';
-- DROP TABLE `groupware_project_members_energy_backup_20261112`;
