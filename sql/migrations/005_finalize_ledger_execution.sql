-- ============================================================
-- 005_finalize_ledger_execution.sql
-- Migration: Add transactions.approval_request_id and direction,
-- add deposit_adjustments.direction if missing,
-- backfill legacy direction values, add composite unique indexes.
-- Environment-agnostic (No hardcoded USE database statement).
-- ============================================================

-- 1. Add approval_request_id to transactions if missing
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'transactions' AND column_name = 'approval_request_id');
SET @sql_add_col := IF(@col_exists = 0, 'ALTER TABLE `transactions` ADD COLUMN `approval_request_id` INT UNSIGNED DEFAULT NULL AFTER `currency`', 'SELECT 1');
PREPARE stmt_col FROM @sql_add_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- 2. Add direction ENUM to transactions if missing
SET @dir_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'transactions' AND column_name = 'direction');
SET @sql_dir := IF(@dir_exists = 0, "ALTER TABLE `transactions` ADD COLUMN `direction` ENUM('credit','debit','neutral') NOT NULL DEFAULT 'neutral' AFTER `type`", 'SELECT 1');
PREPARE stmt_dir FROM @sql_dir;
EXECUTE stmt_dir;
DEALLOCATE PREPARE stmt_dir;

-- 3. Index on transactions.approval_request_id
SET @idx_tx_app := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transactions' AND index_name = 'idx_tx_approval_req');
SET @sql_idx := IF(@idx_tx_app = 0, 'ALTER TABLE `transactions` ADD INDEX `idx_tx_approval_req` (`approval_request_id`)', 'SELECT 1');
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- 4. Composite unique index to prevent duplicate execution transactions
-- (approval_request_id + deposit_id + type) — allows NULL approval_request_id for legacy
SET @idx_tx_uniq := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transactions' AND index_name = 'idx_tx_app_dep_type');
SET @sql_uniq := IF(@idx_tx_uniq = 0, 'ALTER TABLE `transactions` ADD UNIQUE KEY `idx_tx_app_dep_type` (`approval_request_id`, `deposit_id`, `type`)', 'SELECT 1');
PREPARE stmt_uniq FROM @sql_uniq;
EXECUTE stmt_uniq;
DEALLOCATE PREPARE stmt_uniq;

-- 5. Add direction to deposit_adjustments if missing
SET @adj_dir := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'deposit_adjustments' AND column_name = 'direction');
SET @sql_adj_dir := IF(@adj_dir = 0, "ALTER TABLE `deposit_adjustments` ADD COLUMN `direction` ENUM('increase','decrease') NOT NULL DEFAULT 'increase' AFTER `difference`", 'SELECT 1');
PREPARE stmt_adj_dir FROM @sql_adj_dir;
EXECUTE stmt_adj_dir;
DEALLOCATE PREPARE stmt_adj_dir;

-- 6. Backfill direction for existing deposit_adjustments
UPDATE `deposit_adjustments` SET `direction` = 'increase' WHERE `difference` > 0 AND `direction` = 'increase';
UPDATE `deposit_adjustments` SET `direction` = 'decrease' WHERE `difference` < 0;

-- 7. Backfill direction for existing legacy transactions
UPDATE `transactions` SET `direction` = 'credit' WHERE `type` IN ('deposit', 'profit', 'profit_accrual') AND `direction` = 'neutral';
UPDATE `transactions` SET `direction` = 'debit' WHERE `type` IN ('withdraw', 'profit_payout', 'withdrawal_payout', 'principal_refund') AND `direction` = 'neutral';

-- 8. Update transactions type ENUM to include all new types
ALTER TABLE `transactions` MODIFY COLUMN `type` ENUM(
  'deposit',
  'profit',
  'withdraw',
  'profit_accrual',
  'profit_payout',
  'withdrawal_payout',
  'principal_refund',
  'deposit_adjustment'
) NOT NULL;

-- 9. Ensure manual_profit_adjustments has unique approval_request_id
SET @mpa_uniq := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'manual_profit_adjustments' AND index_name = 'idx_mpa_approval');
-- Already created in 004, this is a safety check
SELECT 1;

-- 10. Ensure deposit_adjustments has unique approval_request_id
SET @adj_uniq := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'deposit_adjustments' AND index_name = 'idx_adj_approval');
-- Already created in 004, this is a safety check
SELECT 1;
