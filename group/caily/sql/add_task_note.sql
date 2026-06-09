-- Task note (HTML from Quill editor)
ALTER TABLE `groupware_tasks`
ADD COLUMN `note` text DEFAULT NULL AFTER `estimated_hours`;
