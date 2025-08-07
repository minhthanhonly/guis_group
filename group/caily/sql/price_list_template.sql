-- Price List Data Template
-- Column Headers: code, name, department_id, type, unit, price, cost, notes, created_at
-- Type: 新規 (New)
-- Product codes starting from 100

-- First, ensure the table structure exists
-- Run this if you haven't already:
-- SOURCE sql/add_cost_and_type_to_price_list.sql;

-- Sample data template
-- Replace the department_id values with actual department IDs from your database
-- You can check existing departments with: SELECT id, name FROM groupware_departments;

INSERT INTO `price_list_products` (`code`, `name`, `department_id`, `type`, `unit`, `price`, `cost`, `notes`, `created_at`) VALUES
-- Format: ('商品コード', '商品名', 部署ID, '新規', '単位', 単価, 売上原価, '備考', NOW())
('100', '商品A', 1, '新規', '個', 1000.00, 600.00, '基本商品A', NOW()),
('101', '商品B', 1, '新規', '個', 1500.00, 900.00, '基本商品B', NOW()),
('102', '商品C', 2, '新規', '個', 2000.00, 1200.00, '基本商品C', NOW()),
('103', '商品D', 2, '新規', '個', 2500.00, 1500.00, '基本商品D', NOW()),
('104', '商品E', 3, '新規', '個', 3000.00, 1800.00, '基本商品E', NOW()),
('105', '商品F', 3, '新規', '個', 3500.00, 2100.00, '基本商品F', NOW()),
('106', '商品G', 1, '新規', '個', 4000.00, 2400.00, '基本商品G', NOW()),
('107', '商品H', 2, '新規', '個', 4500.00, 2700.00, '基本商品H', NOW()),
('108', '商品I', 3, '新規', '個', 5000.00, 3000.00, '基本商品I', NOW()),
('109', '商品J', 1, '新規', '個', 5500.00, 3300.00, '基本商品J', NOW()),
('110', '商品K', 2, '新規', '個', 6000.00, 3600.00, '基本商品K', NOW()),
('111', '商品L', 3, '新規', '個', 6500.00, 3900.00, '基本商品L', NOW()),
('112', '商品M', 1, '新規', '個', 7000.00, 4200.00, '基本商品M', NOW()),
('113', '商品N', 2, '新規', '個', 7500.00, 4500.00, '基本商品N', NOW()),
('114', '商品O', 3, '新規', '個', 8000.00, 4800.00, '基本商品O', NOW()),
('115', '商品P', 1, '新規', '個', 8500.00, 5100.00, '基本商品P', NOW()),
('116', '商品Q', 2, '新規', '個', 9000.00, 5400.00, '基本商品Q', NOW()),
('117', '商品R', 3, '新規', '個', 9500.00, 5700.00, '基本商品R', NOW()),
('118', '商品S', 1, '新規', '個', 10000.00, 6000.00, '基本商品S', NOW()),
('119', '商品T', 2, '新規', '個', 10500.00, 6300.00, '基本商品T', NOW()),
('120', '商品U', 3, '新規', '個', 11000.00, 6600.00, '基本商品U', NOW());

-- To check the inserted data:
-- SELECT code, name, department_id, type, unit, price, cost, notes FROM price_list_products WHERE type = '新規' ORDER BY code;

-- To get the next available product code:
-- SELECT COALESCE(MAX(CAST(code AS UNSIGNED)), 99) + 1 as next_code FROM price_list_products;
