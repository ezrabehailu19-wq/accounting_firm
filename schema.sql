-- =========================================================
--  Selamawit H/Mariam Accounting Firm — Database Schema
-- =========================================================
--  Fresh install:
--      mysql -u root -p < schema.sql
--
--  Already running an older version of this app?
--  Run migrations/2026_01_portal_upgrade.sql instead — it adds
--  the new tables and columns without touching your data.
--
--  Conventions used throughout:
--    * InnoDB + utf8mb4 everywhere (real foreign keys, emoji-safe).
--    * All money stored as DECIMAL(14,2). Never FLOAT — floats
--      lose cents, and this is an accounting system.
--    * All timestamps are DB-generated (NOW()) so PHP's timezone
--      and MySQL's timezone can never disagree.
--    * Anything user-deletable that other rows point at uses
--      ON DELETE SET NULL so we keep the audit trail intact.
-- =========================================================

CREATE DATABASE IF NOT EXISTS `accounting_firm`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `accounting_firm`;

-- ---------------------------------------------------------
--  users
-- ---------------------------------------------------------
--  `username` and `email` are UNIQUE at the DB level, so two
--  simultaneous registrations can never both succeed. The PHP
--  layer catches the resulting duplicate-key error (1062) and
--  turns it into a friendly message — application-level checks
--  alone cannot close that race.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`       VARCHAR(50)  NOT NULL,
    `email`          VARCHAR(150) NOT NULL,
    `full_name`      VARCHAR(120) NULL,
    `phone`          VARCHAR(30)  NULL,
    `company`        VARCHAR(120) NULL,
    `password`       VARCHAR(255) NOT NULL,      -- bcrypt via password_hash()
    `role`           ENUM('user','admin') NOT NULL DEFAULT 'user',
    `status`         ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `last_login_at`  DATETIME NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email`    (`email`),
    KEY `idx_users_role`   (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  contact_messages  (a client "request")
-- ---------------------------------------------------------
--  `user_id` links the message to an account when the sender was
--  logged in, which is what makes client-side request tracking
--  possible. Anonymous submissions keep it NULL and are matched
--  back to an account by email if the person registers later.
--
--  `reference` is the human-facing tracking code (REQ-2026-0001).
--  Clients quote it on the phone; nobody wants to read out a
--  surrogate primary key.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference`     VARCHAR(20)  NULL,
    `user_id`       INT UNSIGNED NULL,
    `fullname`      VARCHAR(100) NOT NULL,
    `email`         VARCHAR(150) NOT NULL,
    `phone`         VARCHAR(30)  NULL,
    `service`       VARCHAR(100) NULL,
    `message`       TEXT NOT NULL,
    `status`        ENUM('new','read','in_progress','replied','closed')
                    NOT NULL DEFAULT 'new',
    `admin_reply`   TEXT NULL,
    `replied_at`    DATETIME NULL,
    `replied_by`    INT UNSIGNED NULL,
    `submitted_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_contact_reference` (`reference`),
    KEY `idx_contact_submitted` (`submitted_at`),
    KEY `idx_contact_status`    (`status`),
    KEY `idx_contact_user`      (`user_id`),
    KEY `idx_contact_email`     (`email`),

    CONSTRAINT `fk_contact_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_contact_replied_by`
        FOREIGN KEY (`replied_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  login_attempts
-- ---------------------------------------------------------
--  Rate limiting is keyed on BOTH the username and the client IP.
--  Username-only locking lets an attacker lock a real user out of
--  their own account on purpose (a denial-of-service), so we also
--  track the IP and lock whichever one is misbehaving.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope`         ENUM('username','ip') NOT NULL,
    `scope_key`     VARCHAR(100) NOT NULL,
    `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_until`  DATETIME NULL DEFAULT NULL,

    UNIQUE KEY `uq_login_attempts` (`scope`, `scope_key`),
    KEY `idx_login_attempts_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  password_resets
-- ---------------------------------------------------------
--  We store a SHA-256 hash of the token, never the token itself,
--  for the same reason we never store plaintext passwords: a
--  leaked database should not hand out working reset links.
--  expires_at is written with MySQL's own NOW() so it can't drift
--  from the NOW() used to validate it.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
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

-- ---------------------------------------------------------
--  documents
-- ---------------------------------------------------------
--  Files never live under the web root. `stored_name` is a random
--  name on disk inside /storage/documents; `original_name` is what
--  the client called it. Downloads go through document_download.php,
--  which checks the session before streaming a single byte.
--
--  `direction` records who sent what: a client uploading last
--  year's bank statements, or the firm returning a filed tax return.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `owner_id`       INT UNSIGNED NOT NULL,       -- the client the file belongs to
    `uploaded_by`    INT UNSIGNED NULL,           -- who actually uploaded it
    `request_id`     INT UNSIGNED NULL,           -- optional: attached to a request
    `direction`      ENUM('from_client','from_firm') NOT NULL DEFAULT 'from_client',
    `category`       VARCHAR(60) NULL,
    `original_name`  VARCHAR(255) NOT NULL,
    `stored_name`    VARCHAR(120) NOT NULL,
    `mime_type`      VARCHAR(120) NOT NULL,
    `size_bytes`     BIGINT UNSIGNED NOT NULL,
    `sha256`         CHAR(64) NULL,               -- integrity check / de-dup
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

-- ---------------------------------------------------------
--  invoices  /  invoice_items
-- ---------------------------------------------------------
--  Totals are stored, not computed on the fly, because an invoice
--  is a legal record: if the VAT rate changes next year, last
--  year's invoice must still show last year's numbers.
--  invoice_items keeps a copy of the line description and rate for
--  the same reason.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_no`    VARCHAR(20) NOT NULL,
    `client_id`     INT UNSIGNED NOT NULL,
    `created_by`    INT UNSIGNED NULL,
    `issue_date`    DATE NOT NULL,
    `due_date`      DATE NOT NULL,
    `currency`      VARCHAR(8) NOT NULL DEFAULT 'ETB',
    `subtotal`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `tax_rate`      DECIMAL(5,2)  NOT NULL DEFAULT 15.00,   -- Ethiopian VAT
    `tax_amount`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `total`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `amount_paid`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status`        ENUM('draft','sent','paid','overdue','void')
                    NOT NULL DEFAULT 'draft',
    `notes`         TEXT NULL,
    `paid_at`       DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

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

-- ---------------------------------------------------------
--  audit_log
-- ---------------------------------------------------------
--  Append-only record of every state change an admin makes.
--  actor_name is denormalised on purpose: if the account is later
--  deleted, the log must still say who did it.
--  `meta` holds a small JSON blob of whatever context the action
--  had — stored as TEXT rather than JSON so this runs on MySQL 5.6
--  and MariaDB installs too.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_id`     INT UNSIGNED NULL,
    `actor_name`   VARCHAR(50) NOT NULL,
    `action`       VARCHAR(60) NOT NULL,        -- e.g. 'message.delete'
    `entity_type`  VARCHAR(40) NULL,            -- e.g. 'contact_message'
    `entity_id`    VARCHAR(40) NULL,
    `summary`      VARCHAR(255) NULL,           -- human-readable one-liner
    `meta`         TEXT NULL,
    `ip_address`   VARCHAR(45) NULL,            -- 45 chars fits IPv6
    `user_agent`   VARCHAR(255) NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY `idx_audit_created` (`created_at`),
    KEY `idx_audit_actor`   (`actor_id`),
    KEY `idx_audit_action`  (`action`),
    KEY `idx_audit_entity`  (`entity_type`, `entity_id`),

    CONSTRAINT `fk_audit_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  Creating the first admin account
-- ---------------------------------------------------------
--  There is deliberately no seeded admin row here. A default
--  username and password baked into a schema file is the single
--  most common way small PHP apps get broken into — it ends up in
--  git, in backups, and on every copy of the site that was ever
--  deployed "just for testing".
--
--  Instead, after importing this file, open setup_admin.php in
--  your browser once and choose your own credentials. That script
--  refuses to run a second time and tells you to delete it.
-- ---------------------------------------------------------
