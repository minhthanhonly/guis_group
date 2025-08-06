-- Sửa cột owner_id từ int(11) thành varchar(50) để hỗ trợ userid dạng chuỗi
ALTER TABLE `groupware_seals` 
MODIFY COLUMN `owner_id` varchar(50) DEFAULT NULL COMMENT '所有者ID (employeeの場合)';
