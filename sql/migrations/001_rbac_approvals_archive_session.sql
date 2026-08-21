-- ============================================================
-- 001_rbac_approvals_archive_session.sql
-- Migration: Add RBAC, Approval Workflows, Rate Declarations,
-- Archive System, Audit Export History, and Session Versioning
-- ============================================================

USE `alasisfh_al_asafy_deposits`;

-- 1. Roles Table
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `label_ar` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Roles
INSERT IGNORE INTO `roles` (`id`, `name`, `label_ar`, `description`) VALUES
(1, 'admin', 'مدير النظام', 'صلاحيات كاملة على النظام والموافقات والمستخدمين'),
(2, 'staff', 'موظف', 'إدارة العمليات اليومية وإنشاء طلبات الموافقات'),
(3, 'investor', 'مستثمر', 'الاطلاع على الحساب والودائع الشخصية فقط');

-- 2. Permissions Table
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `label_ar` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_permissions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Permissions
INSERT IGNORE INTO `permissions` (`name`, `label_ar`, `category`) VALUES
('investors.view', 'مشاهدة المستثمرين', 'investors'),
('investors.create', 'إضافة مستثمر جديد', 'investors'),
('investors.edit', 'تعديل بيانات المستثمر', 'investors'),
('investor_accounts.create', 'إنشاء حساب دخول للمستثمر', 'investors'),
('investor_accounts.reset_password', 'إعادة تعيين كلمة مرور المستثمر', 'investors'),

('deposits.view', 'مشاهدة الودائع', 'deposits'),
('deposits.create', 'إضافة وديعة جديدة', 'deposits'),
('deposits.edit_own', 'تعديل الوديعة الخاصة بالمنشئ', 'deposits'),
('deposits.edit_any', 'تعديل أي وديعة', 'deposits'),
('deposits.request_financial_change', 'طلب تعديل مبلغ أو عملة وديعة', 'deposits'),
('deposits.approve_financial_change', 'الموافقة على تعديل مالي لوديعة', 'deposits'),
('deposits.request_close', 'طلب إنهاء وديعة وإرجاع رأس المال', 'deposits'),
('deposits.approve_close', 'الموافقة على إنهاء وديعة وإرجاع رأس المال', 'deposits'),
('deposits.archive', 'أرشفة وحذف وديعة', 'deposits'),

('profits.view', 'مشاهدة الأرباح', 'profits'),
('profits.request_manual', 'طلب إضافة ربح يدوي', 'profits'),
('profits.approve_manual', 'الموافقة على إضافة ربح يدوي', 'profits'),
('profits.request_payout', 'طلب صرف أرباح وديعة', 'profits'),
('profits.approve_payout', 'الموافقة على صرف أرباح وديعة', 'profits'),

('rates.request_declaration', 'إدخال إعلان نسب الأرباح الشهرية', 'rates'),
('rates.approve_declaration', 'الموافقة على إعلان نسب الأرباح الشهرية', 'rates'),

('withdrawals.view', 'مشاهدة طلبات السحب للمستثمرين', 'withdrawals'),
('withdrawals.request', 'تقديم طلب سحب أرباح', 'withdrawals'),
('withdrawals.approve', 'الموافقة على طلب سحب مستثمر', 'withdrawals'),

('reports.view', 'مشاهدة التقارير', 'reports'),
('reports.export', 'تصدير التقارير (Excel/PDF)', 'reports'),

('users.manage', 'إدارة الموظفين والمستخدمين', 'users'),
('roles.manage', 'إدارة الأدوار والصلاحيات', 'users'),
('permissions.manage', 'إدارة الصلاحيات الفردية', 'users'),

('audit.view', 'مشاهدة سجل العمليات', 'audit'),
('audit.export', 'تصدير سجل العمليات', 'audit'),
('audit.delete_exported', 'حذف سجلات التدقيق المصدّرة', 'audit'),

('archive.view', 'مشاهدة أرشيف المحذوفات', 'archive'),
('archive.restore', 'استعادة سجل من الأرشيف', 'archive'),
('archive.permanent_delete', 'الحذف النهائي من الأرشيف', 'archive');

-- 3. Role Permissions Table
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Map Admin to all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Map Staff to default staff permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `name` IN (
  'investors.view', 'investors.create', 'investors.edit', 'investor_accounts.create',
  'deposits.view', 'deposits.create', 'deposits.edit_own',
  'deposits.request_financial_change', 'deposits.request_close',
  'profits.view', 'profits.request_manual', 'profits.request_payout',
  'rates.request_declaration', 'withdrawals.view',
  'reports.view', 'reports.export'
);

-- Map Investor to default investor permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `name` IN (
  'deposits.view', 'profits.view', 'withdrawals.request', 'withdrawals.view',
  'reports.view', 'reports.export'
);

-- 4. User Permissions Table (Allow / Deny Overrides)
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `permission_type` ENUM('allow', 'deny') NOT NULL DEFAULT 'allow',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_perm` (`user_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Approval Requests Central Table
CREATE TABLE IF NOT EXISTS `approval_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_type` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `old_data_json` LONGTEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'executed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `executed_at` DATETIME DEFAULT NULL,
  `execution_reference` VARCHAR(100) DEFAULT NULL,
  `rejection_reason` VARCHAR(255) DEFAULT NULL,
  `idempotency_key` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_approval_idempotency` (`idempotency_key`),
  KEY `idx_approval_status` (`status`),
  KEY `idx_approval_operation` (`operation_type`),
  KEY `idx_approval_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Rate Declarations Table
CREATE TABLE IF NOT EXISTS `rate_declarations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month` VARCHAR(7) NOT NULL,
  `deposit_type_id` INT UNSIGNED NOT NULL,
  `declared_rate_monthly` DECIMAL(8,4) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'executed') NOT NULL DEFAULT 'pending',
  `created_by` INT UNSIGNED NOT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `executed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_month_type` (`month`, `deposit_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Archived Records Table (Soft Deletes)
CREATE TABLE IF NOT EXISTS `archived_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_type` VARCHAR(50) NOT NULL,
  `original_id` INT UNSIGNED NOT NULL,
  `data_json` LONGTEXT NOT NULL,
  `deletion_reason` VARCHAR(255) DEFAULT NULL,
  `deleted_by` INT UNSIGNED NOT NULL,
  `deleted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_archive_type` (`record_type`, `original_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Audit Export History Table
CREATE TABLE IF NOT EXISTS `audit_export_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exported_by` INT UNSIGNED NOT NULL,
  `export_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_start` DATETIME DEFAULT NULL,
  `period_end` DATETIME DEFAULT NULL,
  `record_count` INT UNSIGNED NOT NULL,
  `file_hash` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Add Columns to Existing Tables (Safe ALTERs)
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `session_version` INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `created_by` INT UNSIGNED DEFAULT NULL;

ALTER TABLE `deposits` ADD COLUMN IF NOT EXISTS `created_by` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `deposits` ADD COLUMN IF NOT EXISTS `principal_refunded` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `withdraw_requests` ADD COLUMN IF NOT EXISTS `approval_request_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `withdraw_requests` ADD COLUMN IF NOT EXISTS `transaction_id` INT UNSIGNED DEFAULT NULL;
