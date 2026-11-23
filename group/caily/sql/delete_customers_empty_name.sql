-- =====================================================================
-- Xóa khách hàng (groupware_customer) có 担当者名 (name) rỗng
--
-- "Rỗng" = name IS NULL, hoặc chỉ có khoảng trắng (kể cả khoảng trắng toàn góc 　).
--
-- Giống nút xóa trong app (Customer::delete_customer), khách hàng còn được dùng
-- sẽ được GIỮ LẠI:
--   - là khách hàng chính của tòa nhà chưa xóa (parent_projects.customer_id)
--   - nằm trong danh sách khách hàng của tòa nhà chưa xóa (parent_project_customers)
--   - là 担当者 của dự án chưa xóa (projects.customer_id)
-- Dòng liên kết parent_project_customers trỏ tới khách hàng bị xóa
-- (chỉ còn ở tòa nhà đã xóa) cũng được xóa theo.
--
-- Backup trước khi chạy. Đổi COMMIT thành ROLLBACK để chạy thử.
-- =====================================================================

SET NAMES utf8mb4;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_empty_customers;
CREATE TEMPORARY TABLE tmp_empty_customers (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO tmp_empty_customers (id)
SELECT c.id
FROM groupware_customer c
WHERE c.name IS NULL
   OR TRIM(REPLACE(c.name, '　', '')) = '';

-- Khách hàng còn được dùng → không xóa
DROP TEMPORARY TABLE IF EXISTS tmp_used_customers;
CREATE TEMPORARY TABLE tmp_used_customers (id INT PRIMARY KEY) ENGINE=InnoDB;
INSERT IGNORE INTO tmp_used_customers (id)
SELECT CAST(pp.customer_id AS UNSIGNED)
FROM groupware_parent_projects pp
WHERE pp.customer_id REGEXP '^[0-9]+$'
  AND (pp.status IS NULL OR pp.status <> 'deleted');
INSERT IGNORE INTO tmp_used_customers (id)
SELECT l.customer_id
FROM groupware_parent_project_customers l
JOIN groupware_parent_projects pp ON pp.id = l.parent_project_id
WHERE (pp.status IS NULL OR pp.status <> 'deleted');
INSERT IGNORE INTO tmp_used_customers (id)
SELECT CAST(p.customer_id AS UNSIGNED)
FROM groupware_projects p
WHERE p.customer_id REGEXP '^[0-9]+$'
  AND (p.status IS NULL OR p.status <> 'deleted');

-- Xem trước
SELECT COUNT(*) AS empty_name_total FROM tmp_empty_customers;
SELECT COUNT(*) AS kept_in_use FROM tmp_empty_customers WHERE id IN (SELECT id FROM tmp_used_customers);

DELETE FROM tmp_empty_customers WHERE id IN (SELECT id FROM tmp_used_customers);

SELECT COUNT(*) AS customers_to_delete FROM tmp_empty_customers;

-- Xóa
DELETE FROM groupware_parent_project_customers WHERE customer_id IN (SELECT id FROM tmp_empty_customers);
DELETE FROM groupware_customer WHERE id IN (SELECT id FROM tmp_empty_customers);

-- Kiểm tra: số khách hàng 担当者名 rỗng còn lại (= số đang được dùng)
SELECT COUNT(*) AS remaining_empty_name
FROM groupware_customer c
WHERE c.name IS NULL OR TRIM(REPLACE(c.name, '　', '')) = '';

COMMIT;
-- ROLLBACK;

DROP TEMPORARY TABLE IF EXISTS tmp_empty_customers, tmp_used_customers;
