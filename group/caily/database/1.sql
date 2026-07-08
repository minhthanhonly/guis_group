-- Split project_director into stat / view / edit permissions
ALTER TABLE `groupware_user_department`
  ADD COLUMN `project_director_stat` tinyint(1) NOT NULL DEFAULT 0 AFTER `project_director`,
  ADD COLUMN `project_director_view` tinyint(1) NOT NULL DEFAULT 0 AFTER `project_director_stat`,
  ADD COLUMN `project_director_edit` tinyint(1) NOT NULL DEFAULT 0 AFTER `project_director_view`;

UPDATE `groupware_user_department`
SET
  `project_director_stat` = `project_director`,
  `project_director_view` = `project_director`,
  `project_director_edit` = `project_director`
WHERE `project_director` = 1;


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



ALTER TABLE groupware_projects
    ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER updated_at;

ALTER TABLE groupware_projects
    ADD COLUMN payment_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER version;


ALTER TABLE `groupware_projects`
  MODIFY COLUMN `estimate_status`
    ENUM('未発行','見積作成中','発行済','発行済み','承認済み','却下','調整')
    DEFAULT '未発行',
  MODIFY COLUMN `invoice_status`
    ENUM('未発行','請求準備','発行済','発行済み','承認済み','却下','調整')
    DEFAULT '未発行';