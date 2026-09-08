-- Confirm drawing share with 省エネ when 意匠/設備 complete or mark 納品済み
ALTER TABLE `groupware_projects`
  ADD COLUMN `energy_drawing_share_status` VARCHAR(20) DEFAULT NULL COMMENT 'shared|not_shared|NULL=unanswered' AFTER `guis_nouki_status`,
  ADD COLUMN `energy_drawing_share_reason` VARCHAR(50) DEFAULT NULL COMMENT 'waiting_assignee|additional_revision|other' AFTER `energy_drawing_share_status`,
  ADD COLUMN `energy_drawing_share_note` TEXT DEFAULT NULL AFTER `energy_drawing_share_reason`,
  ADD COLUMN `energy_drawing_share_at` DATETIME DEFAULT NULL AFTER `energy_drawing_share_note`,
  ADD COLUMN `energy_drawing_share_by` VARCHAR(50) DEFAULT NULL AFTER `energy_drawing_share_at`;
