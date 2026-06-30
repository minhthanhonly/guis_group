-- Optimistic locking for concurrent project edits
ALTER TABLE projects
    ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER updated_at;
