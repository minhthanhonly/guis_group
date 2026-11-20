-- =====================================================================
-- Xóa (hard delete) dự án của các phòng ban 省エネ và tòa nhà chỉ có dự án 省エネ
--
-- - Dự án: groupware_projects thuộc phòng ban có tên chứa '省エネ'
--   (省エネ計算 / 省エネ計算1 / 省エネ計算2・3・CAILY ...)
-- - Tòa nhà: groupware_parent_projects chỉ bị xóa khi có ít nhất 1 dự án 省エネ
--   và KHÔNG có dự án nào của phòng ban khác (kể cả dự án đã hủy / is_deleted,
--   dự án không có department_id). Tòa nhà có dự án lẫn phòng ban khác được giữ lại.
-- - File vật lý (đính kèm, bản vẽ) trên ổ đĩa KHÔNG bị xóa bởi script này.
--
-- Hãy backup DB trước khi chạy. Script chạy trong 1 transaction:
-- xem các SELECT đếm số lượng, rồi COMMIT (mặc định) hoặc đổi thành ROLLBACK.
-- =====================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ---------- 1. Danh sách mục tiêu ----------
DROP TEMPORARY TABLE IF EXISTS tmp_del_projects;
CREATE TEMPORARY TABLE tmp_del_projects (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_del_projects (id)
SELECT p.id
FROM groupware_projects p
JOIN groupware_departments d ON d.id = p.department_id
WHERE d.name LIKE '%省エネ%';

DROP TEMPORARY TABLE IF EXISTS tmp_del_buildings;
CREATE TEMPORARY TABLE tmp_del_buildings (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_del_buildings (id)
SELECT pp.id
FROM groupware_parent_projects pp
WHERE EXISTS (
        SELECT 1 FROM groupware_projects p
        JOIN groupware_departments d ON d.id = p.department_id
        WHERE p.parent_project_id = pp.id AND d.name LIKE '%省エネ%'
    )
  AND NOT EXISTS (
        SELECT 1 FROM groupware_projects p
        LEFT JOIN groupware_departments d ON d.id = p.department_id
        WHERE p.parent_project_id = pp.id
          AND (d.id IS NULL OR d.name NOT LIKE '%省エネ%')
    );

DROP TEMPORARY TABLE IF EXISTS tmp_del_tasks;
CREATE TEMPORARY TABLE tmp_del_tasks (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_del_tasks (id)
SELECT t.id FROM groupware_tasks t
WHERE t.project_id IN (SELECT id FROM tmp_del_projects);

DROP TEMPORARY TABLE IF EXISTS tmp_del_threads;
CREATE TEMPORARY TABLE tmp_del_threads (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_del_threads (id)
SELECT th.id FROM groupware_comment_threads th
WHERE th.project_id IN (SELECT id FROM tmp_del_projects)
   OR th.task_id IN (SELECT id FROM tmp_del_tasks);

DROP TEMPORARY TABLE IF EXISTS tmp_del_comments;
CREATE TEMPORARY TABLE tmp_del_comments (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_del_comments (id)
SELECT c.id FROM groupware_comments c
WHERE (c.project_id REGEXP '^[0-9]+$' AND CAST(c.project_id AS UNSIGNED) IN (SELECT id FROM tmp_del_projects))
   OR c.task_id IN (SELECT id FROM tmp_del_tasks)
   OR c.thread_id IN (SELECT id FROM tmp_del_threads);

DROP TEMPORARY TABLE IF EXISTS tmp_del_notes;
CREATE TEMPORARY TABLE tmp_del_notes (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_del_notes (id)
SELECT n.id FROM groupware_project_notes n
WHERE n.project_id IN (SELECT id FROM tmp_del_projects);

DROP TEMPORARY TABLE IF EXISTS tmp_del_quotations;
CREATE TEMPORARY TABLE tmp_del_quotations (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_del_quotations (id)
SELECT q.id FROM groupware_quotations q
WHERE q.parent_project_id IN (SELECT id FROM tmp_del_buildings);

-- ---------- 2. Xem trước số lượng ----------
SELECT
    (SELECT COUNT(*) FROM tmp_del_projects)   AS projects,
    (SELECT COUNT(*) FROM tmp_del_buildings)  AS buildings,
    (SELECT COUNT(*) FROM tmp_del_tasks)      AS tasks,
    (SELECT COUNT(*) FROM tmp_del_threads)    AS comment_threads,
    (SELECT COUNT(*) FROM tmp_del_comments)   AS comments,
    (SELECT COUNT(*) FROM tmp_del_notes)      AS notes,
    (SELECT COUNT(*) FROM tmp_del_quotations) AS quotations;

-- ---------- 3. Dữ liệu phụ thuộc task ----------
DELETE FROM groupware_task_assignees WHERE task_id IN (SELECT id FROM tmp_del_tasks);
DELETE FROM groupware_task_logs      WHERE task_id IN (SELECT id FROM tmp_del_tasks);
DELETE FROM groupware_task_reactions WHERE task_id IN (SELECT id FROM tmp_del_tasks);
DELETE FROM groupware_time_entries   WHERE task_id IN (SELECT id FROM tmp_del_tasks);

-- ---------- 4. Comment ----------
DELETE FROM groupware_comment_likes        WHERE comment_id IN (SELECT id FROM tmp_del_comments);
DELETE FROM groupware_comment_reads        WHERE project_id IN (SELECT id FROM tmp_del_projects)
                                              OR task_id IN (SELECT id FROM tmp_del_tasks);
DELETE FROM groupware_comment_thread_reads WHERE thread_id IN (SELECT id FROM tmp_del_threads);
DELETE FROM groupware_comments             WHERE id IN (SELECT id FROM tmp_del_comments);
DELETE FROM groupware_comment_threads      WHERE id IN (SELECT id FROM tmp_del_threads);

-- ---------- 5. Thông báo ----------
DELETE FROM notification WHERE project_id IN (SELECT id FROM tmp_del_projects)
                            OR task_id IN (SELECT id FROM tmp_del_tasks);

-- ---------- 6. Ghi chú dự án ----------
DELETE FROM groupware_project_note_user_pins  WHERE note_id IN (SELECT id FROM tmp_del_notes);
DELETE FROM groupware_project_note_user_board WHERE note_id IN (SELECT id FROM tmp_del_notes)
                                                 OR project_id IN (SELECT id FROM tmp_del_projects);
DELETE FROM groupware_project_notes           WHERE id IN (SELECT id FROM tmp_del_notes);

-- ---------- 7. Dữ liệu khác của dự án ----------
DELETE FROM groupware_project_drawings      WHERE project_id IN (SELECT id FROM tmp_del_projects)
                                               OR task_id IN (SELECT id FROM tmp_del_tasks);
DELETE FROM groupware_project_attachments   WHERE project_id IN (SELECT id FROM tmp_del_projects)
                                               OR parent_project_id IN (SELECT id FROM tmp_del_buildings);
DELETE FROM groupware_project_folders       WHERE project_id IN (SELECT id FROM tmp_del_projects)
                                               OR parent_project_id IN (SELECT id FROM tmp_del_buildings);
DELETE FROM groupware_project_favorites     WHERE project_id IN (SELECT id FROM tmp_del_projects);
DELETE FROM groupware_project_logs          WHERE project_id IN (SELECT id FROM tmp_del_projects);
DELETE FROM groupware_project_members       WHERE project_id IN (SELECT id FROM tmp_del_projects);
DELETE FROM groupware_project_payment_items WHERE project_id IN (SELECT id FROM tmp_del_projects);
DELETE FROM groupware_task_links            WHERE project_id IN (SELECT id FROM tmp_del_projects);

-- ---------- 8. Báo giá ----------
DELETE FROM groupware_quotation_history WHERE quotation_id IN (SELECT id FROM tmp_del_quotations);
DELETE FROM groupware_quotation_items   WHERE quotation_id IN (SELECT id FROM tmp_del_quotations)
                                           OR project_id IN (SELECT id FROM tmp_del_projects);
DELETE FROM groupware_quotations        WHERE id IN (SELECT id FROM tmp_del_quotations);

-- ---------- 9. Task, dự án ----------
DELETE FROM groupware_tasks    WHERE id IN (SELECT id FROM tmp_del_tasks);
DELETE FROM groupware_projects WHERE id IN (SELECT id FROM tmp_del_projects);

-- ---------- 10. Tòa nhà ----------
DELETE FROM groupware_parent_project_customers WHERE parent_project_id IN (SELECT id FROM tmp_del_buildings);
DELETE FROM groupware_parent_project_favorites WHERE parent_project_id IN (SELECT id FROM tmp_del_buildings);
DELETE FROM groupware_parent_project_notes     WHERE parent_project_id IN (SELECT id FROM tmp_del_buildings);
DELETE FROM groupware_parent_projects_logs     WHERE parent_project_id IN (SELECT id FROM tmp_del_buildings);
DELETE FROM groupware_parent_projects          WHERE id IN (SELECT id FROM tmp_del_buildings);

-- ---------- 11. Kiểm tra sau khi xóa (đều phải = 0) ----------
SELECT
    (SELECT COUNT(*) FROM groupware_projects p JOIN groupware_departments d ON d.id = p.department_id
      WHERE d.name LIKE '%省エネ%') AS remaining_shoene_projects,
    (SELECT COUNT(*) FROM groupware_parent_projects WHERE id IN (SELECT id FROM tmp_del_buildings)) AS remaining_target_buildings;

COMMIT;
-- ROLLBACK;

DROP TEMPORARY TABLE IF EXISTS tmp_del_projects, tmp_del_buildings, tmp_del_tasks,
    tmp_del_threads, tmp_del_comments, tmp_del_notes, tmp_del_quotations;
