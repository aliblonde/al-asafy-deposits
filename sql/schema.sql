-- ============================================================
-- schema.sql — AL-ASAFY Investment Deposit Management System
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `al_asafy_deposits`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `al_asafy_deposits`;

-- -------------------------------------------------------
-- 1. investors
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `investors` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`   VARCHAR(150) NOT NULL,
  `phone`       VARCHAR(30)  NOT NULL,
  `city`        VARCHAR(100) NOT NULL DEFAULT '',
  `address`     VARCHAR(255) NULL,
  `notes`       TEXT NULL,
  `national_id` VARCHAR(30)  NOT NULL DEFAULT '',
  `contract_path` VARCHAR(255) NULL,
  `id_card_path`  VARCHAR(255) NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 2. users
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id`   INT UNSIGNED NULL,
  `role`          ENUM('admin','staff','investor') NOT NULL DEFAULT 'investor',
  `username`      VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `last_login_at` DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  CONSTRAINT `fk_users_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. deposit_types
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deposit_types` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_ar`             VARCHAR(100) NOT NULL,
  `code`                ENUM('6_months','1_year','2_years','3_years') NOT NULL,
  `min_rate`            DECIMAL(8,5) NOT NULL DEFAULT 0.02800,
  `max_rate`            DECIMAL(8,5) NOT NULL DEFAULT 0.03300,
  `min_days`            INT NOT NULL DEFAULT 180,
  `max_days`            INT NOT NULL DEFAULT 180,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_deposit_type_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 4. deposits
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deposits` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id`             INT UNSIGNED NOT NULL,
  `deposit_type_id`         INT UNSIGNED NOT NULL,
  `amount`                  DECIMAL(12,2) NOT NULL,
  `currency`                ENUM('IQD','USD') NOT NULL DEFAULT 'IQD',
  `start_date`              DATE NOT NULL,
  `end_date`                DATE NOT NULL,
  `profit_payout_frequency` INT NOT NULL DEFAULT 1,
  `accumulated_profit`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `last_profit_date`        DATE NULL,
  `last_withdrawal_date`    DATE NULL,
  `status`                  ENUM('active','completed','cancelled','defaulted') NOT NULL DEFAULT 'active',
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_deposits_investor`     FOREIGN KEY (`investor_id`)     REFERENCES `investors`     (`id`),
  CONSTRAINT `fk_deposits_deposit_type` FOREIGN KEY (`deposit_type_id`) REFERENCES `deposit_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adding diverse deposits with varying profit frequencies.
INSERT INTO `deposits` (`id`, `investor_id`, `deposit_type_id`, `amount`, `currency`, `start_date`, `end_date`, `profit_payout_frequency`, `accumulated_profit`, `last_profit_date`, `last_withdrawal_date`, `status`) VALUES
-- Active deposits
(1, 1, 1, 15000000.00, 'IQD', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_ADD(CURDATE(), INTERVAL 5 MONTH), 1, 0.00, NULL, NULL, 'active'),
(2, 2, 2, 20000.00, 'USD', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 8 MONTH), 3, 0.00, NULL, NULL, 'active'),
(3, 3, 3, 50000000.00, 'IQD', DATE_SUB(CURDATE(), INTERVAL 14 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 6, 0.00, NULL, NULL, 'active'),
(4, 3, 4, 15000.00, 'USD', DATE_SUB(CURDATE(), INTERVAL 20 DAYS), DATE_ADD(CURDATE(), INTERVAL 1060 DAYS), 12, 0.00, NULL, NULL, 'active'),

-- Completed deposit
(5, 1, 1, 5000000.00, 'IQD', DATE_SUB(CURDATE(), INTERVAL 7 MONTH), DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 1, 0.00, NULL, NULL, 'completed'),

-- Cancelled deposit
(6, 2, 2, 10000.00, 'USD', DATE_SUB(CURDATE(), INTERVAL 12 MONTH), DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 3, 0.00, NULL, NULL, 'cancelled');

-- -------------------------------------------------------
-- 5. transactions
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_no`  VARCHAR(30) NOT NULL,
  `investor_id` INT UNSIGNED NOT NULL,
  `deposit_id`  INT UNSIGNED NULL,
  `type`        ENUM('deposit','profit','withdraw') NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL,
  `currency`    ENUM('IQD','USD') NOT NULL DEFAULT 'IQD',
  `date`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note`        VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_no` (`receipt_no`),
  CONSTRAINT `fk_tx_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  CONSTRAINT `fk_tx_deposit`  FOREIGN KEY (`deposit_id`)  REFERENCES `deposits`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 6. monthly_rates (declared profit rates per month)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monthly_rates` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month`           VARCHAR(7) NOT NULL, -- Format: YYYY-MM
  `deposit_type_id` INT UNSIGNED NOT NULL,
  `rate_percent`    DECIMAL(8,5) NOT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_rate` (`month`, `deposit_type_id`),
  CONSTRAINT `fk_mr_deposit_type` FOREIGN KEY (`deposit_type_id`) REFERENCES `deposit_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 7. withdraw_requests
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `withdraw_requests` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id`   INT UNSIGNED NOT NULL,
  `amount`        DECIMAL(12,2) NOT NULL,
  `currency`      ENUM('IQD','USD') NOT NULL DEFAULT 'IQD',
  `request_date`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`        ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `staff_user_id` INT UNSIGNED NULL,
  `decision_date` DATETIME NULL,
  `note`          VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_wr_investor` FOREIGN KEY (`investor_id`)   REFERENCES `investors` (`id`),
  CONSTRAINT `fk_wr_staff`    FOREIGN KEY (`staff_user_id`) REFERENCES `users`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 8. activity_logs
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL,
  `action`     VARCHAR(50)  NOT NULL,
  `entity`     VARCHAR(50)  NOT NULL DEFAULT '',
  `entity_id`  INT UNSIGNED NULL,
  `old_data`   JSON NULL,
  `new_data`   JSON NULL,
  `ip_address` VARCHAR(45)  NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
