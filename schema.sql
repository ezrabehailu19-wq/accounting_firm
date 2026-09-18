-- =========================================================
-- Accounting Firm — Database Schema
-- =========================================================
-- Usage:
--   mysql -u root -p < schema.sql
-- or import via phpMyAdmin.
--
-- This matches the exact columns referenced in the PHP code:
--   classes/Model.class.php, admin.php, admin_users.php,
--   admin_messages.php, login.php, register.php, contact_submit.php
-- =========================================================

CREATE DATABASE IF NOT EXISTS `accounting_firm`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `accounting_firm`;

-- ---------------------------------------------------------
-- users
-- ---------------------------------------------------------
-- `username` has a UNIQUE constraint at the DB level so two
-- simultaneous registrations can never create duplicate
-- accounts (closes the check-then-insert race condition that
-- existed when uniqueness was only checked in application code).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50)  NOT NULL,
    `email`      VARCHAR(150) NULL,
    `password`   VARCHAR(255) NOT NULL,   -- bcrypt hash via password_hash()
    `role`       ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- contact_messages
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fullname`      VARCHAR(100) NOT NULL,
    `email`         VARCHAR(150) NOT NULL,
    `phone`         VARCHAR(30)  NULL,
    `service`       VARCHAR(100) NULL,
    `message`       TEXT NOT NULL,
    `submitted_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_contact_messages_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- login_attempts
-- ---------------------------------------------------------
-- Tracks failed login attempts per username to support
-- rate-limiting / lockout. A row is deleted on successful
-- login, and naturally stops mattering once locked_until
-- passes (no cron job needed to clean it up, but you may
-- want one eventually for old unused rows).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `username`      VARCHAR(50) NOT NULL PRIMARY KEY,
    `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_until`  TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- password_resets
-- ---------------------------------------------------------
-- Stores a SHA-256 hash of each reset token, never the raw
-- token itself — mirrors how we never store plaintext passwords.
-- The raw token only ever exists in the emailed link.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`    VARCHAR(50) NOT NULL,
    `token_hash`  CHAR(64) NOT NULL,
    `expires_at`  TIMESTAMP NOT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_password_resets_token_hash` (`token_hash`),
    KEY `idx_password_resets_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Optional: seed an initial admin account.
-- Uncomment and replace the password hash before running once.
-- Generate a hash with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
-- ---------------------------------------------------------
-- INSERT INTO `users` (`username`, `password`, `role`)
-- VALUES ('admin', '$2y$10$REPLACE_WITH_REAL_HASH', 'admin');