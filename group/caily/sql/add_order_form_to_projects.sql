-- 決済情報 注文書: one check per 種別 (申請費 / 設計費). NULL = not checked; the value is when it was
-- checked (editable). A 請求 line of that 種別 can be set to 発行済 only while it is checked.
-- Project::ensureOrderFormColumns() runs the same statements when the columns are missing.
ALTER TABLE `groupware_projects`
  ADD COLUMN `order_form_application_at` DATETIME NULL DEFAULT NULL COMMENT '注文書 申請費 (チェック日時)',
  ADD COLUMN `order_form_design_at` DATETIME NULL DEFAULT NULL COMMENT '注文書 設計費 (チェック日時)';
