-- =====================================================================
-- Xóa các dòng 決済情報 (groupware_project_payment_items) không có giá trị nào
--
-- Dòng "trống" = cùng lúc:
--   - amount IS NULL            (0 là giá trị, được giữ lại)
--   - item_date IS NULL
--   - status là 未発行 hoặc rỗng (giống normalizePaymentItemStatus)
--   (種別 item_type chỉ là nhãn, không tính là giá trị)
--
-- Ngoại lệ an toàn: nếu xóa làm dự án không còn dòng nào, UI sẽ dựng lại
-- 決済情報 từ các cột cũ (buildPaymentItemsFromLegacy). Khi các cột cũ đó còn
-- giá trị, dòng trống của dự án ấy được GIỮ LẠI để giá trị cũ không hiện lại.
--
-- Backup trước khi chạy. Đổi COMMIT thành ROLLBACK để chạy thử.
-- =====================================================================

SET NAMES utf8mb4;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_empty_items;
CREATE TEMPORARY TABLE tmp_empty_items (id INT UNSIGNED PRIMARY KEY, project_id INT NOT NULL, KEY (project_id)) ENGINE=InnoDB;
INSERT INTO tmp_empty_items (id, project_id)
SELECT i.id, i.project_id
FROM groupware_project_payment_items i
WHERE i.amount IS NULL
  AND i.item_date IS NULL
  AND COALESCE(NULLIF(TRIM(i.status), ''), '未発行') = '未発行';

-- Dự án mà toàn bộ dòng đều trống và cột cũ còn giá trị → không xóa
DROP TEMPORARY TABLE IF EXISTS tmp_keep_projects;
CREATE TEMPORARY TABLE tmp_keep_projects (project_id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_keep_projects (project_id)
SELECT p.id
FROM groupware_projects p
WHERE p.id IN (SELECT project_id FROM tmp_empty_items)
  AND NOT EXISTS (
        SELECT 1 FROM groupware_project_payment_items i2
        WHERE i2.project_id = p.id
          AND NOT (i2.amount IS NULL AND i2.item_date IS NULL
                   AND COALESCE(NULLIF(TRIM(i2.status), ''), '未発行') = '未発行')
    )
  AND (
        COALESCE(p.amount, 0) > 0 OR COALESCE(p.invoice_amount, 0) > 0
     OR p.estimate_date IS NOT NULL OR p.invoice_date IS NOT NULL
     OR COALESCE(NULLIF(TRIM(p.estimate_status), ''), '未発行') <> '未発行'
     OR COALESCE(NULLIF(TRIM(p.invoice_status), ''), '未発行') <> '未発行'
     OR p.estimate_application_amount IS NOT NULL OR p.estimate_application_date IS NOT NULL
     OR COALESCE(NULLIF(TRIM(p.estimate_application_status), ''), '未発行') <> '未発行'
     OR p.estimate_design_amount IS NOT NULL OR p.estimate_design_date IS NOT NULL
     OR COALESCE(NULLIF(TRIM(p.estimate_design_status), ''), '未発行') <> '未発行'
     OR p.invoice_application_amount IS NOT NULL OR p.invoice_application_date IS NOT NULL
     OR COALESCE(NULLIF(TRIM(p.invoice_application_status), ''), '未発行') <> '未発行'
     OR p.invoice_design_amount IS NOT NULL OR p.invoice_design_date IS NOT NULL
     OR COALESCE(NULLIF(TRIM(p.invoice_design_status), ''), '未発行') <> '未発行'
    );

DELETE FROM tmp_empty_items WHERE project_id IN (SELECT project_id FROM tmp_keep_projects);

-- Xem trước
SELECT COUNT(*) AS rows_to_delete, COUNT(DISTINCT project_id) AS projects_affected FROM tmp_empty_items;
SELECT COUNT(*) AS projects_kept_legacy_values FROM tmp_keep_projects;

-- Xóa
DELETE FROM groupware_project_payment_items WHERE id IN (SELECT id FROM tmp_empty_items);

-- Tăng payment_version để form 決済情報 đang mở phải tải lại, không lưu đè dòng cũ
UPDATE groupware_projects
SET payment_version = payment_version + 1
WHERE id IN (SELECT DISTINCT project_id FROM tmp_empty_items);

COMMIT;
-- ROLLBACK;

DROP TEMPORARY TABLE IF EXISTS tmp_empty_items, tmp_keep_projects;
