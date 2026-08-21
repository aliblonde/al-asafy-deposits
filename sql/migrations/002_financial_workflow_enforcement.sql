-- ============================================================
-- 002_financial_workflow_enforcement.sql
-- Migration: Add missing permissions, columns, unique keys,
-- and indexes for strict approval workflow enforcement.
-- ============================================================

USE `alasisfh_al_asafy_deposits`;

-- 1. Ensure new permissions exist
INSERT IGNORE INTO `permissions` (`name`, `label_ar`, `category`) VALUES
('approvals.view', 'مشاهدة وإدارة طلبات الموافقة', 'approvals'),
('deposits.supervise_update', 'تعديل بيانات الوديعة لغير المنشئ', 'deposits'),
('deposits.update', 'تعديل بيانات الوديعة غير المالية', 'deposits'),
('investors.update', 'تعديل بيانات المستثمر', 'investors'),
('users.create_investor', 'إنشاء حساب دخول للمستثمر', 'users');

-- Map permissions to default roles
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions` WHERE `name` IN (
  'approvals.view', 'deposits.supervise_update', 'deposits.update', 'investors.update', 'users.create_investor'
);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `name` IN (
  'approvals.view', 'deposits.update', 'investors.update', 'users.create_investor'
);

-- 2. Add rejected_by and rejected_at to approval_requests
ALTER TABLE `approval_requests` ADD COLUMN IF NOT EXISTS `rejected_by` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `approval_requests` ADD COLUMN IF NOT EXISTS `rejected_at` DATETIME DEFAULT NULL;

-- 3. Add created_by and principal_refunded to deposits
ALTER TABLE `deposits` ADD COLUMN IF NOT EXISTS `created_by` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `deposits` ADD COLUMN IF NOT EXISTS `principal_refunded` TINYINT(1) NOT NULL DEFAULT 0;

-- 4. Add approval_request_id and transaction_id to withdraw_requests
ALTER TABLE `withdraw_requests` ADD COLUMN IF NOT EXISTS `approval_request_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `withdraw_requests` ADD COLUMN IF NOT EXISTS `transaction_id` INT UNSIGNED DEFAULT NULL;

-- 5. Ensure UNIQUE Key on profit_cycles (deposit_id, cycle_date)
ALTER TABLE `profit_cycles` ADD COLUMN IF NOT EXISTS `cycle_date` DATE DEFAULT NULL;
ALTER TABLE `profit_cycles` ADD COLUMN IF NOT EXISTS `profit_amount` DECIMAL(12,2) DEFAULT NULL;
ALTER TABLE `profit_cycles` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) DEFAULT 'calculated';

-- Safely add UNIQUE KEY on profit_cycles if missing
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'profit_cycles' AND index_name = 'idx_deposit_cycle_unique');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE `profit_cycles` ADD UNIQUE KEY `idx_deposit_cycle_unique` (`deposit_id`, `cycle_date`)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
