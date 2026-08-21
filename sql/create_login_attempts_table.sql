-- ============================================================
-- create_login_attempts_table.sql — Persistent Login Rate Limiting Table
-- DO NOT EXECUTE AUTOMATICALLY AGAINST PRODUCTION.
-- Run this migration manually in cPanel phpMyAdmin / MySQL CLI.
-- ============================================================

USE `al_asafy_deposits`;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempted_at`),
  KEY `idx_user_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
