-- ============================================================
-- seed.sql — Demo Data
-- ============================================================
USE `al_asafy_deposits`;
SET NAMES utf8mb4;

-- -------------------------------------------------------
-- Deposit Types
-- -------------------------------------------------------
INSERT INTO `deposit_types` (`name_ar`, `code`, `min_rate`, `max_rate`, `min_days`, `max_days`) VALUES
('وديعة 6 أشهر', '6_months', 0.02800, 0.03300, 180, 180),
('وديعة سنة',    '1_year',   0.03500, 0.03900, 360, 360),
('وديعة سنتين',  '2_years',  0.04200, 0.04900, 720, 720),
('وديعة 3 سنوات', '3_years',  0.05400, 0.06300, 1080, 1080);

-- -------------------------------------------------------
-- Investors
-- -------------------------------------------------------
INSERT INTO `investors` (`full_name`, `phone`, `city`, `national_id`) VALUES
('أحمد محمد العسافي',   '0501234561', 'الرياض',  '1012345671'),
('فاطمة علي الزهراني', '0501234562', 'جدة',     '1012345672'),
('خالد عمر القحطاني',  '0501234563', 'الدمام',  '1012345673');

-- -------------------------------------------------------
-- Users  (passwords hashed with PHP password_hash DEFAULT)
-- admin    → Admin@123
-- staff    → Staff@123
-- investor1→ Investor@123
-- -------------------------------------------------------
INSERT INTO `users` (`investor_id`, `role`, `username`, `password_hash`) VALUES
(NULL, 'admin',    'admin',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(NULL, 'staff',    'staff',     '$2y$12$c.jTNn85xVS5HQXQ0yXyEuJkX7vlTOb7p6KFPD0LoW.C5AjjSREW'),
(1,    'investor', 'investor1', '$2y$12$u6BGGH.ykexwgENdnRdCsORJg2e4JkXz4lnJjN.eV/gIagcuqlveG');

-- NOTE: If the above hashes don't match your PHP version, run this regeneration script
-- once after import (see setup guide in walkthrough). Alternatively import and visit
-- /al-asafy-deposits/public/setup_passwords.php (temporary helper, delete after use).

-- -------------------------------------------------------
-- Sample Deposits (varied dates for alerts + due profits)
-- -------------------------------------------------------
-- Deposit 1: 6_months, monthly payout, started 70 days ago
INSERT INTO `deposits`
  (`investor_id`,`deposit_type_id`,`amount`,`start_date`,`end_date`,`profit_rate_monthly`,`profit_payout_frequency`,`last_profit_date`,`status`)
VALUES
  (1, 1, 50000.00,
   DATE_SUB(CURDATE(), INTERVAL 70 DAY),
   DATE_ADD(CURDATE(), INTERVAL 110 DAY),
   0.03000, 1, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'active');

-- Deposit 2: 1_year, payout every 3 months, started 95 days ago -> profit due
INSERT INTO `deposits`
  (`investor_id`,`deposit_type_id`,`amount`,`start_date`,`end_date`,`profit_rate_monthly`,`profit_payout_frequency`,`last_profit_date`,`status`)
VALUES
  (1, 2, 100000.00,
   DATE_SUB(CURDATE(), INTERVAL 95 DAY),
   DATE_ADD(CURDATE(), INTERVAL 265 DAY),
   0.03800, 3, NULL, 'active');

-- Deposit 3: 3_years, payout every 6 months, started 35 days ago
INSERT INTO `deposits`
  (`investor_id`,`deposit_type_id`,`amount`,`start_date`,`end_date`,`profit_rate_monthly`,`profit_payout_frequency`,`last_profit_date`,`status`)
VALUES
  (2, 4, 200000.00,
   DATE_SUB(CURDATE(), INTERVAL 35 DAY),
   DATE_ADD(CURDATE(), INTERVAL 1045 DAY),
   0.05500, 6, NULL, 'active');

-- Deposit 4: 6_months, payout every 2 months, started 58 days ago -> profit due in 2 days
INSERT INTO `deposits`
  (`investor_id`,`deposit_type_id`,`amount`,`start_date`,`end_date`,`profit_rate_monthly`,`profit_payout_frequency`,`last_profit_date`,`status`)
VALUES
  (2, 1, 30000.00,
   DATE_SUB(CURDATE(), INTERVAL 58 DAY),
   DATE_ADD(CURDATE(), INTERVAL 122 DAY),
   0.03200, 2, NULL, 'active');

-- Deposit 5: 2_years, monthly payout, started 3 days ago -> profit due in ~27 days
INSERT INTO `deposits`
  (`investor_id`,`deposit_type_id`,`amount`,`start_date`,`end_date`,`profit_rate_monthly`,`profit_payout_frequency`,`last_profit_date`,`status`)
VALUES
  (3, 3, 75000.00,
   DATE_SUB(CURDATE(), INTERVAL 3 DAY),
   DATE_ADD(CURDATE(), INTERVAL 717 DAY),
   0.04500, 1, NULL, 'active');

-- -------------------------------------------------------
-- Sample Transactions (for deposit 1 — the completed one)
-- -------------------------------------------------------
INSERT INTO `transactions` (`receipt_no`,`investor_id`,`deposit_id`,`type`,`amount`,`date`,`note`) VALUES
('AG-202512-000001', 1, 1, 'deposit', 50000.00,
  DATE_SUB(NOW(), INTERVAL 70 DAY), 'وديعة قصيرة - إيداع أولي'),
('AG-202601-000001', 1, 1, 'profit',   1500.00,
  DATE_SUB(NOW(), INTERVAL 40 DAY), 'ربح شهري - وديعة 1');
