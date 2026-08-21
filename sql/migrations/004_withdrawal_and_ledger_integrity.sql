-- ============================================================
-- 004_withdrawal_and_ledger_integrity.sql
-- Migration: Add deposit_id to withdraw_requests,
-- manual_profit_adjustments table, deposit_adjustments with direction,
-- and updated transaction types (profit_accrual, profit_payout, withdrawal_payout, principal_refund, deposit_adjustment).
-- Environment-agnostic (No hardcoded USE database statement).
-- ============================================================

-- 1. Add deposit_id to withdraw_requests
ALTER TABLE `withdraw_requests` ADD COLUMN IF NOT EXISTS `deposit_id` INT UNSIGNED DEFAULT NULL AFTER `investor_id`;

-- Update withdraw_requests status ENUM to include 'needs_review'
ALTER TABLE `withdraw_requests` MODIFY COLUMN `status` ENUM('pending','approved','paid','rejected','needs_review') NOT NULL DEFAULT 'pending';

-- Add indexes on withdraw_requests(deposit_id) and (deposit_id, status)
SET @exist_wr_dep := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'withdraw_requests' AND index_name = 'idx_wr_deposit');
SET @sqlstmt_wr_dep := IF(@exist_wr_dep = 0, 'ALTER TABLE `withdraw_requests` ADD INDEX `idx_wr_deposit` (`deposit_id`)', 'SELECT 1');
PREPARE stmt_wr_dep FROM @sqlstmt_wr_dep;
EXECUTE stmt_wr_dep;
DEALLOCATE PREPARE stmt_wr_dep;

SET @exist_wr_dep_st := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'withdraw_requests' AND index_name = 'idx_wr_dep_status');
SET @sqlstmt_wr_dep_st := IF(@exist_wr_dep_st = 0, 'ALTER TABLE `withdraw_requests` ADD INDEX `idx_wr_dep_status` (`deposit_id`, `status`)', 'SELECT 1');
PREPARE stmt_wr_dep_st FROM @sqlstmt_wr_dep_st;
EXECUTE stmt_wr_dep_st;
DEALLOCATE PREPARE stmt_wr_dep_st;

-- Mark legacy withdraw_requests lacking deposit_id as 'needs_review'
UPDATE `withdraw_requests` SET `status` = 'needs_review' WHERE `deposit_id` IS NULL AND `status` = 'pending';

-- 2. Create manual_profit_adjustments table
CREATE TABLE IF NOT EXISTS `manual_profit_adjustments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'IQD',
  `month` VARCHAR(7) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `approval_request_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `approved_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_mpa_approval` (`approval_request_id`),
  KEY `idx_mpa_deposit` (`deposit_id`),
  KEY `idx_mpa_dep_month` (`deposit_id`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create or Update deposit_adjustments table with direction ENUM
CREATE TABLE IF NOT EXISTS `deposit_adjustments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_id` INT UNSIGNED NOT NULL,
  `old_amount` DECIMAL(12,2) NOT NULL,
  `new_amount` DECIMAL(12,2) NOT NULL,
  `difference` DECIMAL(12,2) NOT NULL,
  `direction` ENUM('increase','decrease') NOT NULL DEFAULT 'increase',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'IQD',
  `approval_request_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `approved_by` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_adj_approval` (`approval_request_id`),
  KEY `idx_adj_deposit` (`deposit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Update transactions.type ENUM to distinct financial ledger types
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

-- 5. Add Foreign Key for deposit_id on withdraw_requests if supported
SET @exist_fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'withdraw_requests' AND constraint_name = 'fk_wr_deposit');
SET @sqlstmt_fk := IF(@exist_fk = 0, 'ALTER TABLE `withdraw_requests` ADD CONSTRAINT `fk_wr_deposit` FOREIGN KEY (`deposit_id`) REFERENCES `deposits` (`id`) ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt_fk FROM @sqlstmt_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
