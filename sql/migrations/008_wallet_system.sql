-- 008_wallet_system.sql
-- Al-Asafy Group: Digital Wallet System & Admin Direct Credit

CREATE TABLE IF NOT EXISTS `wallet_balances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id` INT UNSIGNED NOT NULL,
  `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wb_investor_currency` (`investor_id`, `currency`),
  CONSTRAINT `fk_wb_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `idx_wtr_status` (`status`),
  CONSTRAINT `fk_wtr_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id` INT UNSIGNED NOT NULL,
  `type` ENUM('topup_credit','admin_credit','profit_transfer','withdrawal','investment_out') NOT NULL,
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
  KEY `idx_wt_type` (`type`),
  CONSTRAINT `fk_wt_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_payment_methods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('bank','wallet','exchange','cash') NOT NULL DEFAULT 'bank',
  `account_title` VARCHAR(150) NOT NULL,
  `account_number` VARCHAR(150) NOT NULL,
  `currency` VARCHAR(20) NOT NULL DEFAULT 'ALL',
  `instructions` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_no` VARCHAR(40) NOT NULL,
  `sender_investor_id` INT UNSIGNED NOT NULL,
  `receiver_investor_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
  `note` VARCHAR(255) NULL,
  `status` ENUM('completed','failed','reversed') NOT NULL DEFAULT 'completed',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfer_no` (`transfer_no`),
  KEY `idx_wt_sender` (`sender_investor_id`),
  KEY `idx_wt_receiver` (`receiver_investor_id`),
  CONSTRAINT `fk_wtrf_sender` FOREIGN KEY (`sender_investor_id`) REFERENCES `investors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wtrf_receiver` FOREIGN KEY (`receiver_investor_id`) REFERENCES `investors` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
