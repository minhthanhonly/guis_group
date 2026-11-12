-- Child project (省エネ部署): 省エネ向け資料 / 申請提出先の審査機関 / BELSのZEH等級
-- These fields moved from groupware_parent_projects to groupware_projects.
--
-- 1) Columns. The Project model adds them at runtime (ensureEnergyFieldColumns) on the
--    first request after deploy, so this block is normally NOT needed and is commented out:
--    running it when the columns already exist fails with "Duplicate column name".
--    Uncomment only to add the columns before deploying the code.
-- ALTER TABLE `groupware_projects`
--   ADD COLUMN `energy_materials` TEXT NULL DEFAULT NULL COMMENT '省エネ向け資料 (comma-separated)',
--   ADD COLUMN `energy_materials_memo` VARCHAR(500) NULL DEFAULT NULL COMMENT '省エネ向け資料・その他メモ',
--   ADD COLUMN `application_review_agency` VARCHAR(255) NULL DEFAULT NULL COMMENT '申請提出先の審査機関',
--   ADD COLUMN `bels_zeh_grade` VARCHAR(10) NULL DEFAULT NULL COMMENT 'BELSのZEH等級 (0-9, empty=未選択)';

-- 2) One-time data copy: building values -> every child project in a 省エネ department.
--    Run manually once, right after deploy.
--    Scope: only buildings whose 依頼 contains 省エネ. That is the rule the old UI used to
--    show these fields, so what users saw on the building is what appears on the child project.
--    (groupware_parent_projects.bels_zeh_grade has DEFAULT '0', so buildings that never had a
--    省エネ request still carry an untouched '0'; copying those would show
--    "0 : 該当なし（非ZEH）" on thousands of projects where nothing was displayed before.
--    To copy them anyway, remove the FIND_IN_SET line below.)
--    Re-run: only child projects whose four fields are all empty are touched, so values edited
--    on a child project are never overwritten. A child project the user deliberately left
--    empty would be refilled by a later run, which is why this is meant to run once.
--    Buildings without a 省エネ child project keep their values in the old
--    groupware_parent_projects columns (not dropped, no longer shown in the UI).

-- Preview: rows that will be updated
-- SELECT COUNT(*) FROM groupware_projects p
-- JOIN groupware_parent_projects pp ON pp.id = p.parent_project_id
-- JOIN groupware_departments d ON d.id = p.department_id
-- WHERE d.name LIKE '%省エネ%'
--   AND FIND_IN_SET('省エネ', REPLACE(COALESCE(pp.requests, ''), ' ', '')) > 0
--   AND COALESCE(p.energy_materials, '') = '' AND COALESCE(p.energy_materials_memo, '') = ''
--   AND COALESCE(p.application_review_agency, '') = '' AND COALESCE(p.bels_zeh_grade, '') = ''
--   AND (COALESCE(pp.energy_materials, '') <> '' OR COALESCE(pp.energy_materials_memo, '') <> ''
--     OR COALESCE(pp.application_review_agency, '') <> '' OR COALESCE(pp.bels_zeh_grade, '') <> '');

-- Preview: buildings (依頼 contains 省エネ) with values but no 省エネ child project
-- SELECT COUNT(*) FROM groupware_parent_projects pp
-- WHERE FIND_IN_SET('省エネ', REPLACE(COALESCE(pp.requests, ''), ' ', '')) > 0
--   AND (COALESCE(pp.energy_materials, '') <> '' OR COALESCE(pp.energy_materials_memo, '') <> ''
--     OR COALESCE(pp.application_review_agency, '') <> '' OR COALESCE(pp.bels_zeh_grade, '') <> '')
--   AND NOT EXISTS (
--     SELECT 1 FROM groupware_projects p
--     JOIN groupware_departments d ON d.id = p.department_id
--     WHERE p.parent_project_id = pp.id AND d.name LIKE '%省エネ%');

UPDATE `groupware_projects` p
JOIN `groupware_parent_projects` pp ON pp.id = p.parent_project_id
JOIN `groupware_departments` d ON d.id = p.department_id
SET p.energy_materials = pp.energy_materials,
    p.energy_materials_memo = pp.energy_materials_memo,
    p.application_review_agency = pp.application_review_agency,
    p.bels_zeh_grade = pp.bels_zeh_grade
WHERE d.name LIKE '%省エネ%'
  AND FIND_IN_SET('省エネ', REPLACE(COALESCE(pp.requests, ''), ' ', '')) > 0
  AND COALESCE(p.energy_materials, '') = ''
  AND COALESCE(p.energy_materials_memo, '') = ''
  AND COALESCE(p.application_review_agency, '') = ''
  AND COALESCE(p.bels_zeh_grade, '') = ''
  AND (COALESCE(pp.energy_materials, '') <> ''
    OR COALESCE(pp.energy_materials_memo, '') <> ''
    OR COALESCE(pp.application_review_agency, '') <> ''
    OR COALESCE(pp.bels_zeh_grade, '') <> '');
