-- Set drawing status to completed when parent project is completed.
-- Run once (safe to re-run: only updates drawings not already completed/cancelled).

UPDATE `groupware_project_drawings` d
INNER JOIN `groupware_projects` p ON p.id = d.project_id
SET d.status = 'completed',
    d.completed_at = COALESCE(d.completed_at, d.updated_at, d.check_date, d.created_at, NOW())
WHERE p.status = 'completed'
  AND d.status NOT IN ('completed', 'cancelled');

-- Legacy status alias (if any rows still use approved)
UPDATE `groupware_project_drawings` d
INNER JOIN `groupware_projects` p ON p.id = d.project_id
SET d.status = 'completed',
    d.completed_at = COALESCE(d.completed_at, d.updated_at, d.check_date, d.created_at, NOW())
WHERE p.status = 'completed'
  AND d.status = 'approved';
