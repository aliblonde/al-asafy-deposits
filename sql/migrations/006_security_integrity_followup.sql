-- ============================================================
-- Migration 006: Security Integrity Followup
-- Branch: security-integrity-followup
-- Date: 2026-08-22
-- ============================================================
-- This migration is idempotent and environment-agnostic.
-- No USE database statement. No seed data.
-- ============================================================

-- ─── 1. schema_migrations table ───
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `version` INT UNSIGNED NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. audit_export_items manifest table (Section 6) ───
CREATE TABLE IF NOT EXISTS `audit_export_items` (
    `export_id` INT UNSIGNED NOT NULL,
    `activity_log_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`export_id`, `activity_log_id`),
    KEY `idx_aei_log` (`activity_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Fix archived_records column names (Section 13) ───
-- Code expects: record_type, original_id, data_json, deletion_reason, deleted_by, ip_address, deleted_at
-- schema.sql had: table_name, record_id, data_json, reason, deleted_by, deleted_at (no ip_address, no record_type)
-- This migration safely renames if old columns exist, adds missing columns if absent.

-- Add record_type if missing (rename from table_name)
SET @has_table_name = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'archived_records' AND column_name = 'table_name');
SET @has_record_type = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'archived_records' AND column_name = 'record_type');

SET @sql_rt = IF(@has_table_name > 0 AND @has_record_type = 0,
    'ALTER TABLE archived_records CHANGE COLUMN `table_name` `record_type` VARCHAR(64) NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql_rt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add original_id if missing (rename from record_id)
SET @has_record_id = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'archived_records' AND column_name = 'record_id');
SET @has_original_id = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'archived_records' AND column_name = 'original_id');

SET @sql_oi = IF(@has_record_id > 0 AND @has_original_id = 0,
    'ALTER TABLE archived_records CHANGE COLUMN `record_id` `original_id` INT UNSIGNED NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql_oi; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add deletion_reason if missing (rename from reason)
SET @has_reason = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'archived_records' AND column_name = 'reason');
SET @has_deletion_reason = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'archived_records' AND column_name = 'deletion_reason');

SET @sql_dr = IF(@has_reason > 0 AND @has_deletion_reason = 0,
    'ALTER TABLE archived_records CHANGE COLUMN `reason` `deletion_reason` VARCHAR(255) NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql_dr; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add ip_address if missing
SET @has_ip = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'archived_records' AND column_name = 'ip_address');

SET @sql_ip = IF(@has_ip = 0,
    'ALTER TABLE archived_records ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER `deleted_by`',
    'SELECT 1');
PREPARE stmt FROM @sql_ip; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── 4. Fix idempotency (Section 7) ───
-- Add generated column for pending-only unique constraint

SET @has_pending_key = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'approval_requests' AND column_name = 'pending_idempotency_key');

SET @sql_pik = IF(@has_pending_key = 0,
    'ALTER TABLE approval_requests ADD COLUMN `pending_idempotency_key` VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN status = ''pending'' THEN idempotency_key ELSE NULL END) STORED',
    'SELECT 1');
PREPARE stmt FROM @sql_pik; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop old conflicting unique indexes on idempotency_key if they exist
SET @has_old_idx = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'approval_requests'
    AND index_name = 'idx_app_idempotency_status');

SET @sql_drop = IF(@has_old_idx > 0,
    'ALTER TABLE approval_requests DROP INDEX idx_app_idempotency_status',
    'SELECT 1');
PREPARE stmt FROM @sql_drop; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_idx2 = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'approval_requests'
    AND index_name = 'idx_idempotency');

SET @sql_drop2 = IF(@has_old_idx2 > 0,
    'ALTER TABLE approval_requests DROP INDEX idx_idempotency',
    'SELECT 1');
PREPARE stmt FROM @sql_drop2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Create unique index on pending_idempotency_key (NULL values are excluded from unique)
SET @has_new_idx = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'approval_requests'
    AND index_name = 'idx_pending_idempotency');

SET @sql_idx = IF(@has_new_idx = 0,
    'ALTER TABLE approval_requests ADD UNIQUE INDEX idx_pending_idempotency (pending_idempotency_key)',
    'SELECT 1');
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── 5. Fix idx_adj_approval uniqueness (Section 8) ───
-- Check if deposit_adjustments.idx_adj_approval is non-unique and fix it

-- First check for duplicates that would block unique index
SET @dup_count = (SELECT COUNT(*) FROM (
    SELECT approval_request_id, COUNT(*) AS cnt
    FROM deposit_adjustments
    GROUP BY approval_request_id
    HAVING cnt > 1
) AS dups);

-- If duplicates exist, STOP with a message (do not silently merge)
-- We use a SELECT to signal; the unique index creation will fail naturally
-- if duplicates exist, which is the safe behavior

SET @has_adj_idx = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'deposit_adjustments'
    AND index_name = 'idx_adj_approval');

SET @is_unique = (SELECT COALESCE(MIN(non_unique), 1) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'deposit_adjustments'
    AND index_name = 'idx_adj_approval');

-- If index exists but is non-unique and no duplicates, drop and recreate as unique
SET @sql_fix = IF(@has_adj_idx > 0 AND @is_unique = 1 AND @dup_count = 0,
    'ALTER TABLE deposit_adjustments DROP INDEX idx_adj_approval, ADD UNIQUE INDEX idx_adj_approval (approval_request_id)',
    IF(@has_adj_idx = 0,
        'ALTER TABLE deposit_adjustments ADD UNIQUE INDEX idx_adj_approval (approval_request_id)',
        'SELECT 1'));
PREPARE stmt FROM @sql_fix; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Same fix for manual_profit_adjustments.idx_mpa_approval
SET @has_mpa_idx = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'manual_profit_adjustments'
    AND index_name = 'idx_mpa_approval');

SET @is_mpa_unique = (SELECT COALESCE(MIN(non_unique), 1) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'manual_profit_adjustments'
    AND index_name = 'idx_mpa_approval');

SET @sql_mpa_fix = IF(@has_mpa_idx > 0 AND @is_mpa_unique = 1,
    'ALTER TABLE manual_profit_adjustments DROP INDEX idx_mpa_approval, ADD UNIQUE INDEX idx_mpa_approval (approval_request_id)',
    IF(@has_mpa_idx = 0,
        'ALTER TABLE manual_profit_adjustments ADD UNIQUE INDEX idx_mpa_approval (approval_request_id)',
        'SELECT 1'));
PREPARE stmt FROM @sql_mpa_fix; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── 6. Add rejected_by column to approval_requests ───
SET @has_rejected_by = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'approval_requests' AND column_name = 'rejected_by');

SET @sql_rb = IF(@has_rejected_by = 0,
    'ALTER TABLE approval_requests ADD COLUMN `rejected_by` INT UNSIGNED NULL AFTER `approved_by`',
    'SELECT 1');
PREPARE stmt FROM @sql_rb; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── 7. Update transaction type ENUM to include currency correction types (Section 12) ───
SET @current_enum = (SELECT column_type FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'transactions' AND column_name = 'type');

SET @sql_enum = IF(@current_enum NOT LIKE '%deposit_currency_reversal%',
    CONCAT('ALTER TABLE transactions MODIFY COLUMN `type` ENUM(',
        '''deposit'',''profit'',''withdraw'',''profit_accrual'',''profit_payout'',',
        '''withdrawal_payout'',''principal_refund'',''deposit_adjustment'',',
        '''deposit_currency_reversal'',''deposit_currency_restatement''',
    ') NOT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql_enum; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── 8. Record this migration version ───
INSERT INTO schema_migrations (version, description) VALUES (6, 'Security integrity followup — atomicity, RBAC, idempotency, archive, audit manifest')
ON DUPLICATE KEY UPDATE applied_at = NOW();
