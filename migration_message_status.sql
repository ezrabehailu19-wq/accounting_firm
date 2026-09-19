-- Migration: adds a status column to contact_messages for the
-- admin inbox (new / read / replied). Safe to run once; re-running
-- is safe too (checks before altering).
--
-- Usage:
--   mysql -u root -p accounting_firm < migration_message_status.sql

USE `accounting_firm`;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contact_messages'
      AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    "ALTER TABLE contact_messages ADD COLUMN status ENUM('new','read','replied') NOT NULL DEFAULT 'new' AFTER message",
    'SELECT "status column already exists, skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contact_messages'
      AND INDEX_NAME = 'idx_contact_messages_status'
);

SET @sql2 = IF(@idx_exists = 0,
    'ALTER TABLE contact_messages ADD KEY idx_contact_messages_status (status)',
    'SELECT "index already exists, skipping"'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- All existing rows default to 'new' automatically via the column default.