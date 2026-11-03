-- 省エネ: split 決済情報 into 申請費 / 設計費 (見積 + 請求)
ALTER TABLE `groupware_projects`
  ADD COLUMN `estimate_application_amount` DECIMAL(15,2) DEFAULT NULL AFTER `estimate_number`,
  ADD COLUMN `estimate_application_date` DATETIME DEFAULT NULL AFTER `estimate_application_amount`,
  ADD COLUMN `estimate_application_status` VARCHAR(50) DEFAULT NULL AFTER `estimate_application_date`,
  ADD COLUMN `estimate_design_amount` DECIMAL(15,2) DEFAULT NULL AFTER `estimate_application_status`,
  ADD COLUMN `estimate_design_date` DATETIME DEFAULT NULL AFTER `estimate_design_amount`,
  ADD COLUMN `estimate_design_status` VARCHAR(50) DEFAULT NULL AFTER `estimate_design_date`,
  ADD COLUMN `invoice_application_amount` DECIMAL(15,2) DEFAULT NULL AFTER `invoice_number`,
  ADD COLUMN `invoice_application_date` DATETIME DEFAULT NULL AFTER `invoice_application_amount`,
  ADD COLUMN `invoice_application_status` VARCHAR(50) DEFAULT NULL AFTER `invoice_application_date`,
  ADD COLUMN `invoice_design_amount` DECIMAL(15,2) DEFAULT NULL AFTER `invoice_application_status`,
  ADD COLUMN `invoice_design_date` DATETIME DEFAULT NULL AFTER `invoice_design_amount`,
  ADD COLUMN `invoice_design_status` VARCHAR(50) DEFAULT NULL AFTER `invoice_design_date`;

-- One-shot: map legacy payment fields → 申請費 for 省エネ departments
UPDATE `groupware_projects` p
INNER JOIN `groupware_departments` d ON d.id = p.department_id
SET
  p.estimate_application_amount = p.amount,
  p.estimate_application_date = p.estimate_date,
  p.estimate_application_status = NULLIF(TRIM(p.estimate_status), ''),
  p.invoice_application_amount = p.invoice_amount,
  p.invoice_application_date = p.invoice_date,
  p.invoice_application_status = NULLIF(TRIM(p.invoice_status), '')
WHERE d.name LIKE '%省エネ%'
  AND p.estimate_application_status IS NULL
  AND p.estimate_application_date IS NULL
  AND (p.estimate_application_amount IS NULL OR p.estimate_application_amount = 0)
  AND (
    (p.amount IS NOT NULL AND p.amount <> 0)
    OR (p.estimate_date IS NOT NULL)
    OR (NULLIF(TRIM(p.estimate_status), '') IS NOT NULL)
    OR (p.invoice_amount IS NOT NULL AND p.invoice_amount <> 0)
    OR (p.invoice_date IS NOT NULL)
    OR (NULLIF(TRIM(p.invoice_status), '') IS NOT NULL)
  );
