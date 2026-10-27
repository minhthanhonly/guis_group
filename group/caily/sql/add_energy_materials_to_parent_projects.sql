-- Parent project: 資料メモ / 省エネ資料
ALTER TABLE `groupware_parent_projects`
  ADD COLUMN `materials_memo` VARCHAR(500) NULL DEFAULT NULL COMMENT '資料・その他メモ' AFTER `materials`,
  ADD COLUMN `energy_materials` TEXT NULL DEFAULT NULL COMMENT '省エネ資料 (comma-separated)' AFTER `bels_zeh_grade`,
  ADD COLUMN `energy_materials_memo` VARCHAR(500) NULL DEFAULT NULL COMMENT '省エネ資料・その他メモ' AFTER `energy_materials`;
