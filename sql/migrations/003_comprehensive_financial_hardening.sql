-- ============================================================
-- 003_comprehensive_financial_hardening.sql
-- Migration: Add deposit_adjustments table, principal_refund transaction type,
-- unique constraints, and indexes for financial hardening.
-- Idempotent & environment-agnostic (No hardcoded database name).
-- ============================================================

-- 1. Create deposit_adjustments table for tracking principal amount changes
CREATE TABLE IF NOT EXISTS `deposit_adjustments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_id` INT UNSIGNED NOT NULL,
  `old_amount` DECIMAL(12,2) NOT NULL,
  `new_amount` DECIMAL(12,2) NOT NULL,
  `difference` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'IQD',
  `approval_request_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `approved_by` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adj_deposit` (`deposit_id`),
  KEY `idx_adj_approval` (`approval_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Modify transactions table type column to include 'principal_refund' and 'deposit_adjustment'
ALTER TABLE `transactions` MODIFY COLUMN `type` ENUM('deposit','profit','withdraw','principal_refund','deposit_adjustment') NOT NULL;

-- 3. Ensure permissions exist
INSERT IGNORE INTO `permissions` (`name`, `label_ar`, `category`) VALUES
('approvals.view', 'مشاهدة وإدارة طلبات الموافقة', 'approvals'),
('deposits.supervise_update', 'تعديل بيانات الوديعة لغير المنشئ', 'deposits'),
('deposits.update', 'تعديل بيانات الوديعة غير المالية', 'deposits'),
('investors.update', 'تعديل بيانات المستثمر', 'investors'),
('users.create_investor', 'إنشاء حساب دخول للمستثمر', 'users');

-- Map permissions to Admin and Staff
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions` WHERE `name` IN (
  'approvals.view', 'deposits.supervise_update', 'deposits.update', 'investors.update', 'users.create_investor'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `name` IN (
  'approvals.view', 'deposits.update', 'investors.update', 'users.create_investor'
);

-- 4. Add rejected_by, rejected_at, and execution_reference to approval_requests
ALTER TABLE `approval_requests` ADD COLUMN IF NOT EXISTS `rejected_by` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `approval_requests` ADD COLUMN IF NOT EXISTS `rejected_at` DATETIME DEFAULT NULL;
ALTER TABLE `approval_requests` ADD COLUMN IF NOT EXISTS `execution_reference` VARCHAR(100) DEFAULT NULL;

-- 5. Add created_by and principal_refunded to deposits
ALTER TABLE `deposits` ADD COLUMN IF NOT EXISTS `created_by` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `deposits` ADD COLUMN IF NOT EXISTS `principal_refunded` TINYINT(1) NOT NULL DEFAULT 0;

-- 6. Add approval_request_id and transaction_id to withdraw_requests
ALTER TABLE `withdraw_requests` ADD COLUMN IF NOT EXISTS `approval_request_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `withdraw_requests` ADD COLUMN IF NOT EXISTS `transaction_id` INT UNSIGNED DEFAULT NULL;

-- 7. Ensure UNIQUE Key on profit_cycles (deposit_id, cycle_date)
ALTER TABLE `profit_cycles` ADD COLUMN IF NOT EXISTS `cycle_date` DATE DEFAULT NULL;
ALTER TABLE `profit_cycles` ADD COLUMN IF NOT EXISTS `profit_amount` DECIMAL(12,2) DEFAULT NULL;
ALTER TABLE `profit_cycles` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) DEFAULT 'calculated';

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'profit_cycles' AND index_name = 'idx_deposit_cycle_unique');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `profit_cycles` ADD UNIQUE KEY `idx_deposit_cycle_unique` (`deposit_id`, `cycle_date`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8. Add Index on approval_requests for idempotency and pending status lookups
SET @exist_app := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'approval_requests' AND index_name = 'idx_app_idempotency_status');
SET @sqlstmt_app := IF(@exist_app = 0, 'ALTER TABLE `approval_requests` ADD INDEX `idx_app_idempotency_status` (`idempotency_key`, `status`)', 'SELECT 1');
PREPARE stmt_app FROM @sqlstmt_app;
EXECUTE stmt_app;
DEALLOCATE PREPARE stmt_app;
