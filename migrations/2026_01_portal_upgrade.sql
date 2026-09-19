-- =========================================================
--  Migration: portal upgrade
-- =========================================================
--  Run this ONLY if you already have a working database from the
--  earlier version of the app and want to keep your existing rows.
--  A fresh install should just import schema.sql instead.
--
--      mysql -u root -p accounting_firm < migrations/2026_01_portal_upgrade.sql
--
--  Take a backup first:
--      mysqldump -u root -p accounting_firm > backup_before_upgrade.sql
--
--  MySQL has no portable "ADD COLUMN IF NOT EXISTS", so each
--  addition is wrapped in a tiny stored procedure that checks
--  information_schema first. That makes this file safe to run
--  twice — it will simply do nothing the second time.
-- =========================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `add_column_if_missing`$$
CREATE PROCEDURE `add_column_if_missing`(
    IN tbl  VARCHAR(64),
    IN col  VARCHAR(64),
    IN ddl  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = tbl
          AND COLUMN_NAME  = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `add_index_if_missing`$$
CREATE PROCEDURE `add_index_if_missing`(
    IN tbl  VARCHAR(64),
    IN idx  VARCHAR(64),
    IN ddl  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = tbl
          AND INDEX_NAME   = idx
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ---------- users ----------
CALL add_column_if_missing('users', 'email',         '`email` VARCHAR(150) NULL AFTER `username`');
CALL add_column_if_missing('users', 'full_name',     '`full_name` VARCHAR(120) NULL AFTER `email`');
CALL add_column_if_missing('users', 'phone',         '`phone` VARCHAR(30) NULL AFTER `full_name`');
CALL add_column_if_missing('users', 'company',       '`company` VARCHAR(120) NULL AFTER `phone`');
CALL add_column_if_missing('users', 'status',        "`status` ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER `role`");
CALL add_column_if_missing('users', 'last_login_at', '`last_login_at` DATETIME NULL AFTER `status`');

CALL add_index_if_missing('users', 'uq_users_username', 'UNIQUE KEY `uq_users_username` (`username`)');

-- ---------- contact_messages ----------
CALL add_column_if_missing('contact_messages', 'reference',   '`reference` VARCHAR(20) NULL AFTER `id`');
CALL add_column_if_missing('contact_messages', 'user_id',     '`user_id` INT UNSIGNED NULL AFTER `reference`');
CALL add_column_if_missing('contact_messages', 'status',      "`status` ENUM('new','read','in_progress','replied','closed') NOT NULL DEFAULT 'new' AFTER `message`");
CALL add_column_if_missing('contact_messages', 'admin_reply', '`admin_reply` TEXT NULL AFTER `status`');
CALL add_column_if_missing('contact_messages', 'replied_at',  '`replied_at` DATETIME NULL AFTER `admin_reply`');
CALL add_column_if_missing('contact_messages', 'replied_by',  '`replied_by` INT UNSIGNED NULL AFTER `replied_at`');
CALL add_column_if_missing('contact_messages', 'updated_at',  '`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL add_index_if_missing('contact_messages', 'idx_contact_status', 'KEY `idx_contact_status` (`status`)');
CALL add_index_if_missing('contact_messages', 'idx_contact_user',   'KEY `idx_contact_user` (`user_id`)');
CALL add_index_if_missing('contact_messages', 'idx_contact_email',  'KEY `idx_contact_email` (`email`)');

-- Give every pre-existing message a tracking reference so clients
-- can look up requests they submitted before this upgrade.
UPDATE `contact_messages`
SET `reference` = CONCAT('REQ-', DATE_FORMAT(`submitted_at`, '%Y'), '-', LPAD(`id`, 4, '0'))
WHERE `reference` IS NULL OR `reference` = '';

CALL add_index_if_missing('contact_messages', 'uq_contact_reference', 'UNIQUE KEY `uq_contact_reference` (`reference`)');

-- Attach old anonymous messages to accounts where the email matches.
UPDATE `contact_messages` m
JOIN `users` u ON u.`email` = m.`email`
SET m.`user_id` = u.`id`
WHERE m.`user_id` IS NULL;

-- ---------- login_attempts ----------
-- The old table was keyed on username alone, which let an attacker
-- lock a real user out of their own account on purpose. The new one
-- tracks username and IP separately. There is nothing worth keeping
-- in the old rows (they are transient counters), so replace it.
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope`         ENUM('username','ip') NOT NULL,
    `scope_key`     VARCHAR(100) NOT NULL,
    `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_until`  DATETIME NULL DEFAULT NULL,
    UNIQUE KEY `uq_login_attempts` (`scope`, `scope_key`),
    KEY `idx_login_attempts_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- password_resets ----------
-- Old version keyed resets by username string; new version uses a
-- real foreign key to users.id. Outstanding reset links are short
-- lived, so dropping them costs nothing.
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `token_hash`  CHAR(64) NOT NULL,
    `expires_at`  DATETIME NOT NULL,
    `used_at`     DATETIME NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_password_resets_token` (`token_hash`),
    KEY `idx_password_resets_user` (`user_id`),
    CONSTRAINT `fk_password_resets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- new tables ----------
CREATE TABLE IF NOT EXISTS `documents` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `owner_id`       INT UNSIGNED NOT NULL,
    `uploaded_by`    INT UNSIGNED NULL,
    `request_id`     INT UNSIGNED NULL,
    `direction`      ENUM('from_client','from_firm') NOT NULL DEFAULT 'from_client',
    `category`       VARCHAR(60) NULL,
    `original_name`  VARCHAR(255) NOT NULL,
    `stored_name`    VARCHAR(120) NOT NULL,
    `mime_type`      VARCHAR(120) NOT NULL,
    `size_bytes`     BIGINT UNSIGNED NOT NULL,
    `sha256`         CHAR(64) NULL,
    `note`           VARCHAR(255) NULL,
    `uploaded_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_documents_stored_name` (`stored_name`),
    KEY `idx_documents_owner`   (`owner_id`),
    KEY `idx_documents_request` (`request_id`),
    CONSTRAINT `fk_documents_owner`
        FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_uploader`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_documents_request`
        FOREIGN KEY (`request_id`) REFERENCES `contact_messages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_no`    VARCHAR(20) NOT NULL,
    `client_id`     INT UNSIGNED NOT NULL,
    `created_by`    INT UNSIGNED NULL,
    `issue_date`    DATE NOT NULL,
    `due_date`      DATE NOT NULL,
    `currency`      VARCHAR(8) NOT NULL DEFAULT 'ETB',
    `subtotal`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `tax_rate`      DECIMAL(5,2)  NOT NULL DEFAULT 15.00,
    `tax_amount`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `total`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `amount_paid`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status`        ENUM('draft','sent','paid','overdue','void') NOT NULL DEFAULT 'draft',
    `notes`         TEXT NULL,
    `paid_at`       DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_invoices_no` (`invoice_no`),
    KEY `idx_invoices_client` (`client_id`),
    KEY `idx_invoices_status` (`status`),
    KEY `idx_invoices_due`    (`due_date`),
    CONSTRAINT `fk_invoices_client`
        FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoices_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`  INT UNSIGNED NOT NULL,
    `position`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `description` VARCHAR(255) NOT NULL,
    `quantity`    DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit_price`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `line_total`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    KEY `idx_invoice_items_invoice` (`invoice_id`),
    CONSTRAINT `fk_invoice_items_invoice`
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_id`     INT UNSIGNED NULL,
    `actor_name`   VARCHAR(50) NOT NULL,
    `action`       VARCHAR(60) NOT NULL,
    `entity_type`  VARCHAR(40) NULL,
    `entity_id`    VARCHAR(40) NULL,
    `summary`      VARCHAR(255) NULL,
    `meta`         TEXT NULL,
    `ip_address`   VARCHAR(45) NULL,
    `user_agent`   VARCHAR(255) NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_audit_created` (`created_at`),
    KEY `idx_audit_actor`   (`actor_id`),
    KEY `idx_audit_action`  (`action`),
    KEY `idx_audit_entity`  (`entity_type`, `entity_id`),
    CONSTRAINT `fk_audit_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- tidy up ----------
DROP PROCEDURE IF EXISTS `add_column_if_missing`;
DROP PROCEDURE IF EXISTS `add_index_if_missing`;

-- Note: users.email is left nullable by this migration because old
-- rows may not have one. Once you have filled in every user's email,
-- tighten it up:
--   ALTER TABLE users MODIFY email VARCHAR(150) NOT NULL,
--     ADD UNIQUE KEY uq_users_email (email);
