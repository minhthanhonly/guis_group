-- Business document fields for 業務書類 box on project detail
ALTER TABLE `groupware_projects`
  MODIFY COLUMN `estimate_status` enum('未発行','発行済','発行済み','承認済み','却下','調整') DEFAULT '未発行',
  MODIFY COLUMN `invoice_status` enum('未発行','発行済','発行済み','承認済み','却下','調整') DEFAULT '未発行',
  ADD COLUMN `estimate_date` datetime DEFAULT NULL AFTER `estimate_status`,
  ADD COLUMN `estimate_number` varchar(100) DEFAULT NULL AFTER `estimate_date`,
  ADD COLUMN `invoice_date` datetime DEFAULT NULL AFTER `invoice_status`,
  ADD COLUMN `invoice_amount` decimal(15,2) DEFAULT 0 AFTER `invoice_date`,
  ADD COLUMN `invoice_number` varchar(100) DEFAULT NULL AFTER `invoice_amount`,
  ADD COLUMN `payment_status` enum('未入金','入金済','入金拒否') DEFAULT '未入金' AFTER `invoice_number`,
  ADD COLUMN `payment_date` datetime DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `payment_amount` decimal(15,2) DEFAULT 0 AFTER `payment_date`,
  ADD COLUMN `receipt_number` varchar(100) DEFAULT NULL AFTER `payment_amount`,
  ADD COLUMN `payment_note` text DEFAULT NULL AFTER `receipt_number`;
