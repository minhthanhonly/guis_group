-- Optimistic locking for payment / business document edits (決済情報)
ALTER TABLE projects
    ADD COLUMN payment_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER version;
