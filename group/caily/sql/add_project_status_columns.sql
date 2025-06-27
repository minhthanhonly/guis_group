-- Add new columns to groupware_projects table for project status tracking
ALTER TABLE `groupware_projects` 
ADD COLUMN `estimate_status` enum('未発行','発行済み','承認済み','却下','調整') DEFAULT '未発行' AFTER `amount`,
ADD COLUMN `invoice_status` enum('未発行','発行済み','承認済み','却下','調整') DEFAULT '未発行' AFTER `estimate_status`,
ADD COLUMN `tags` text DEFAULT NULL AFTER `invoice_status`; 