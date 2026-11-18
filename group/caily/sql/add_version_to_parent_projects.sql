-- Parent project (建物): optimistic lock. Every write adds 1; the edit form saves only while
-- the row still has the version it loaded, so concurrent edits are not silently overwritten.
-- ParentProject::ensureParentProjectVersionColumn() runs the same statement when the column is missing.
ALTER TABLE `groupware_parent_projects`
  ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '楽観ロック用バージョン (更新ごとに+1)';
