-- Add start_date and end_date to requests table
-- Table name = DB_PREFIX + 'requests' (e.g. groupware_requests). Adjust table name if your prefix differs.
ALTER TABLE `groupware_requests` ADD COLUMN `start_date` DATE NULL DEFAULT NULL;
ALTER TABLE `groupware_requests` ADD COLUMN `end_date` DATE NULL DEFAULT NULL;
