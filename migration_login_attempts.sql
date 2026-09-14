-- Migration: add login_attempts table for login rate-limiting.
-- Safe to run on your existing database — it won't touch users
-- or contact_messages, and IF NOT EXISTS means it's safe to
-- run more than once.
--
-- Usage:
--   mysql -u root -p accounting_firm < migration_login_attempts.sql

USE `accounting_firm`;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `username`      VARCHAR(50) NOT NULL PRIMARY KEY,
    `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_until`  TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;