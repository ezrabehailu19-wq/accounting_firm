-- Migration: adds password-reset support to an existing database.
-- Safe to run once. Re-running is safe too (checks before altering).
--
-- Usage:
--   mysql -u root -p accounting_firm < migration_password_reset.sql

USE `accounting_firm`;

-- Add an email column if it doesn't already exist.
-- NULL-able so existing accounts (registered before this change)
-- aren't broken; they just won't be able to use password reset
-- until they set an email (see note at bottom of this file).
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'email'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL UNIQUE AFTER username',
    'SELECT "email column already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`    VARCHAR(50) NOT NULL,
    `token_hash`  CHAR(64) NOT NULL,
    `expires_at`  TIMESTAMP NOT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_password_resets_token_hash` (`token_hash`),
    KEY `idx_password_resets_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: any accounts you created before this migration (e.g. your
-- admin test account) will have email = NULL and won't be able to
-- use "forgot password" until you set one, e.g.:
--   UPDATE users SET email = 'you@example.com' WHERE username = 'admin';