-- Multi-currency migration: add `currency` column to deposits, transactions, withdraw_requests
-- Run this in phpMyAdmin on database `al_asafy_deposits`

ALTER TABLE `deposits`
  ADD COLUMN `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'IQD' AFTER `amount`;

ALTER TABLE `transactions`
  ADD COLUMN `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'IQD' AFTER `amount`;

ALTER TABLE `withdraw_requests`
  ADD COLUMN `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'IQD' AFTER `amount`;
