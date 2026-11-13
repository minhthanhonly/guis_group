-- メモボード: background colour (palette key) and visibility (公開範囲) per note.
-- ProjectNote::ensureBoardColumns() applies the same change at runtime.
ALTER TABLE `groupware_project_notes`
  ADD COLUMN `bg_color` varchar(20) DEFAULT NULL COMMENT 'メモ背景色' AFTER `sort_order`,
  ADD COLUMN `visibility` varchar(10) NOT NULL DEFAULT 'all' COMMENT '公開範囲 all/members/self' AFTER `bg_color`;
