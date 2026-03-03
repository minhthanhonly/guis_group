-- Add add_to_calendar and schedule_id to requests (for 休暇届, 外出申請書, 出張申請書, 休日勤務申請書)
ALTER TABLE groupware_requests
  ADD COLUMN add_to_calendar TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN schedule_id INT NULL;
