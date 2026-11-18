-- Usage log of the AI assistant: who, how many tokens, which tools. No conversation content.
-- Also the source for the per-user rate limits. AI::ensureUsageTable() creates it at runtime if missing.
CREATE TABLE IF NOT EXISTS groupware_ai_usage (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    userid VARCHAR(100) NOT NULL,
    input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    cache_read_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    cache_write_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    tools VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_ai_usage_user_created (userid, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
