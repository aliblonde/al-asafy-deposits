-- ============================================================
-- schema.sql — AL-ASAFY Investment Deposit Management System
-- Complete Production Schema Definition
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `al_asafy_deposits`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `al_asafy_deposits`;

-- 1. investors
CREATE TABLE IF NOT EXISTS `investors` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(150) NOT NULL,
  `phone`         VARCHAR(30)  NOT NULL,
  `city`          VARCHAR(100) NOT NULL DEFAULT '',
  `address`       VARCHAR(255) NULL,
  `notes`         TEXT NULL,
  `national_id`   VARCHAR(30)  NOT NULL DEFAULT '',
  `contract_path` VARCHAR(255) NULL,
  `id_card_path`  VARCHAR(255) NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. users
CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id`     INT UNSIGNED NULL,
  `role`            ENUM('admin','staff','investor') NOT NULL DEFAULT 'investor',
  `username`        VARCHAR(100) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `session_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_by`      INT UNSIGNED NULL,
  `last_login_at`   DATETIME NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  CONSTRAINT `fk_users_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. roles & permissions (RBAC)
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50) NOT NULL,
  `label_ar`    VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `label_ar`    VARCHAR(150) NOT NULL,
  `category`    VARCHAR(50) NOT NULL DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perm_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id`         INT UNSIGNED NOT NULL,
  `permission_id`   INT UNSIGNED NOT NULL,
  `permission_type` ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  PRIMARY KEY (`user_id`, `permission_id`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. deposit_types
CREATE TABLE IF NOT EXISTS `deposit_types` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_ar`    VARCHAR(100) NOT NULL,
  `code`       ENUM('6_months','1_year','2_years','3_years') NOT NULL,
  `min_rate`   DECIMAL(8,5) NOT NULL DEFAULT 0.02800,
  `max_rate`   DECIMAL(8,5) NOT NULL DEFAULT 0.03300,
  `min_days`   INT NOT NULL DEFAULT 180,
  `max_days`   INT NOT NULL DEFAULT 180,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_deposit_type_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. deposits
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
  `paid_profit`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `principal_refunded`      TINYINT(1) NOT NULL DEFAULT 0,
  `last_profit_date`        DATE NULL,
  `last_withdrawal_date`    DATE NULL,
  `status`                  ENUM('active','completed','cancelled','defaulted') NOT NULL DEFAULT 'active',
  `created_by`              INT UNSIGNED NULL,
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_deposits_investor`     FOREIGN KEY (`investor_id`)     REFERENCES `investors`     (`id`),
  CONSTRAINT `fk_deposits_deposit_type` FOREIGN KEY (`deposit_type_id`) REFERENCES `deposit_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. approval_requests
CREATE TABLE IF NOT EXISTS `approval_requests` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_type`      VARCHAR(50) NOT NULL,
  `entity_type`         VARCHAR(50) NOT NULL,
  `entity_id`           INT UNSIGNED NULL,
  `requested_by`        INT UNSIGNED NOT NULL,
  `approved_by`         INT UNSIGNED NULL,
  `rejected_by`         INT UNSIGNED NULL,
  `payload_json`        JSON NOT NULL,
  `old_data_json`       JSON NULL,
  `status`              ENUM('pending','executed','rejected','failed') NOT NULL DEFAULT 'pending',
  `idempotency_key`     VARCHAR(64) NOT NULL,
  `rejection_reason`    TEXT NULL,
  `execution_reference` VARCHAR(100) NULL,
  `approved_at`         DATETIME NULL,
  `rejected_at`         DATETIME NULL,
  `executed_at`         DATETIME NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pending_idempotency_key` VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN idempotency_key ELSE NULL END) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_pending_idempotency` (`pending_idempotency_key`),
  CONSTRAINT `fk_app_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. withdraw_requests
CREATE TABLE IF NOT EXISTS `withdraw_requests` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `investor_id`         INT UNSIGNED NOT NULL,
  `deposit_id`          INT UNSIGNED NULL,
  `amount`              DECIMAL(12,2) NOT NULL,
  `currency`            ENUM('IQD','USD') NOT NULL DEFAULT 'IQD',
  `request_date`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`              ENUM('pending','approved','paid','rejected','needs_review') NOT NULL DEFAULT 'pending',
  `staff_user_id`       INT UNSIGNED NULL,
  `approval_request_id` INT UNSIGNED NULL,
  `transaction_id`      INT UNSIGNED NULL,
  `decision_date`       DATETIME NULL,
  `note`                VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wr_deposit` (`deposit_id`),
  KEY `idx_wr_dep_status` (`deposit_id`, `status`),
  CONSTRAINT `fk_wr_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  CONSTRAINT `fk_wr_deposit`  FOREIGN KEY (`deposit_id`)  REFERENCES `deposits`  (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. manual_profit_adjustments
CREATE TABLE IF NOT EXISTS `manual_profit_adjustments` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_id`          INT UNSIGNED NOT NULL,
  `amount`              DECIMAL(12,2) NOT NULL,
  `currency`            VARCHAR(3) NOT NULL DEFAULT 'IQD',
  `month`               VARCHAR(7) NOT NULL,
  `reason`              VARCHAR(255) NOT NULL,
  `approval_request_id` INT UNSIGNED NOT NULL,
  `requested_by`        INT UNSIGNED NOT NULL,
  `approved_by`         INT UNSIGNED NOT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_mpa_approval` (`approval_request_id`),
  KEY `idx_mpa_deposit` (`deposit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. deposit_adjustments
CREATE TABLE IF NOT EXISTS `deposit_adjustments` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_id`          INT UNSIGNED NOT NULL,
  `old_amount`          DECIMAL(12,2) NOT NULL,
  `new_amount`          DECIMAL(12,2) NOT NULL,
  `difference`          DECIMAL(12,2) NOT NULL,
  `direction`           ENUM('increase','decrease') NOT NULL DEFAULT 'increase',
  `currency`            VARCHAR(3) NOT NULL DEFAULT 'IQD',
  `approval_request_id` INT UNSIGNED NOT NULL,
  `requested_by`        INT UNSIGNED NOT NULL,
  `approved_by`         INT UNSIGNED NOT NULL,
  `reason`              VARCHAR(255) DEFAULT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_adj_approval` (`approval_request_id`),
  KEY `idx_adj_deposit` (`deposit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. transactions (Immutable Financial Ledger)
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_no`          VARCHAR(30) NOT NULL,
  `investor_id`         INT UNSIGNED NOT NULL,
  `deposit_id`          INT UNSIGNED NULL,
  `type`                ENUM('deposit','profit','withdraw','profit_accrual','profit_payout','withdrawal_payout','principal_refund','deposit_adjustment') NOT NULL,
  `direction`           ENUM('credit','debit','neutral') NOT NULL DEFAULT 'neutral',
  `amount`              DECIMAL(12,2) NOT NULL,
  `currency`            ENUM('IQD','USD') NOT NULL DEFAULT 'IQD',
  `approval_request_id` INT UNSIGNED NULL,
  `date`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note`                VARCHAR(255) NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_no` (`receipt_no`),
  UNIQUE KEY `idx_tx_app_dep_type` (`approval_request_id`, `deposit_id`, `type`),
  KEY `idx_tx_approval_req` (`approval_request_id`),
  CONSTRAINT `fk_tx_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  CONSTRAINT `fk_tx_deposit`  FOREIGN KEY (`deposit_id`)  REFERENCES `deposits`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. monthly_rates & rate_declarations
CREATE TABLE IF NOT EXISTS `monthly_rates` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month`           VARCHAR(7) NOT NULL,
  `deposit_type_id` INT UNSIGNED NOT NULL,
  `rate_percent`    DECIMAL(8,5) NOT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_rate` (`month`, `deposit_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_declarations` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month`                  VARCHAR(7) NOT NULL,
  `deposit_type_id`        INT UNSIGNED NOT NULL,
  `declared_rate_monthly`  DECIMAL(8,5) NOT NULL,
  `status`                 ENUM('pending','executed','rejected') NOT NULL DEFAULT 'pending',
  `created_by`             INT UNSIGNED NOT NULL,
  `approved_by`            INT UNSIGNED NULL,
  `executed_at`            DATETIME NULL,
  `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_month_type` (`month`, `deposit_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. profit_cycles
CREATE TABLE IF NOT EXISTS `profit_cycles` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_id`    INT UNSIGNED NOT NULL,
  `cycle_date`    DATE NOT NULL,
  `profit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`        VARCHAR(20) NOT NULL DEFAULT 'calculated',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_deposit_cycle_unique` (`deposit_id`, `cycle_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. archived_records & activity_logs
CREATE TABLE IF NOT EXISTS `archived_records` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_type`     VARCHAR(64) NOT NULL,
  `original_id`     INT UNSIGNED NOT NULL,
  `data_json`       JSON NOT NULL,
  `deletion_reason` VARCHAR(255) NOT NULL,
  `deleted_by`      INT UNSIGNED NOT NULL,
  `ip_address`      VARCHAR(45) NULL,
  `deleted_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. schema_migrations
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version`     INT UNSIGNED NOT NULL,
  `applied_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. audit_export_items
CREATE TABLE IF NOT EXISTS `audit_export_items` (
  `export_id`       INT UNSIGNED NOT NULL,
  `activity_log_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`export_id`, `activity_log_id`),
  KEY `idx_aei_log` (`activity_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
