-- =============================================================================
-- AL-ASAFY GROUP - Digital Wallet System & Permissions Schema
-- =============================================================================

-- 1. Wallet Balances Table
CREATE TABLE IF NOT EXISTS `wallet_balances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id` INT UNSIGNED NOT NULL,
  `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wb_investor_currency` (`investor_id`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Wallet Top-up Requests Table
CREATE TABLE IF NOT EXISTS `wallet_topup_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
  `payment_method` VARCHAR(50) NOT NULL,
  `transfer_reference` VARCHAR(100) NULL,
  `receipt_image` VARCHAR(255) NULL,
  `note` TEXT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_id` INT UNSIGNED NULL,
  `admin_note` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wtr_investor` (`investor_id`),
  KEY `idx_wtr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. P2P Transfers Table (Between Investors)
CREATE TABLE IF NOT EXISTS `wallet_transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_no` VARCHAR(60) NOT NULL,
  `sender_investor_id` INT UNSIGNED NOT NULL,
  `receiver_investor_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
  `note` VARCHAR(255) NULL,
  `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wt_transfer_no` (`transfer_no`),
  KEY `idx_wt_sender` (`sender_investor_id`),
  KEY `idx_wt_receiver` (`receiver_investor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Central Wallet Transactions Ledger (Audit Trail)
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `direction` ENUM('credit','debit') NOT NULL DEFAULT 'credit',
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
  `balance_after` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `reference_id` INT UNSIGNED NULL,
  `note` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wt_investor` (`investor_id`),
  KEY `idx_wt_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Company Payment Channels Table
CREATE TABLE IF NOT EXISTS `company_payment_methods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('bank','wallet','exchange','cash','other') NOT NULL DEFAULT 'bank',
  `account_title` VARCHAR(150) NOT NULL,
  `account_number` VARCHAR(100) NOT NULL,
  `currency` ENUM('IQD','USD','ALL') NOT NULL DEFAULT 'ALL',
  `instructions` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Register Wallet Permissions into `permissions` table
INSERT INTO `permissions` (`name`, `label_ar`, `category`) VALUES
('wallets.view', 'عرض المحافظ الرقمية وسجل العمليات والتقارير', 'المحافظ الرقمية'),
('wallets.topup', 'إدارة واعتماد طلبات شحن المحافظ', 'المحافظ الرقمية'),
('wallets.adjust', 'السحب والتصفير والتعديل الإداري لأرصدة المحافظ', 'المحافظ الرقمية'),
('wallets.settings', 'إدارة قنوات وطرق دفع وحسابات الشركة', 'المحافظ الرقمية')
ON DUPLICATE KEY UPDATE `label_ar` = VALUES(`label_ar`), `category` = VALUES(`category`);
