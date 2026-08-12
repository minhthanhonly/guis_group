-- 予定工程 (structured month range) on child projects
ALTER TABLE groupware_projects
  ADD COLUMN yotei TEXT NULL COMMENT '予定工程 JSON: from_month, from_part, to_month, to_part, sort_start, sort_end, display';
