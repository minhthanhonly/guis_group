-- Reuse type3 for 非住宅の主要用途 (shown when type2 includes 非住宅)
ALTER TABLE `groupware_parent_projects`
  MODIFY COLUMN `type3` varchar(255) DEFAULT NULL COMMENT '非住宅の主要用途 (用途番号)';
