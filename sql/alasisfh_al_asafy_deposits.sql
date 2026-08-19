-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 19, 2026 at 07:24 AM
-- Server version: 11.4.12-MariaDB-cll-lve
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `alasisfh_al_asafy_deposits`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity` varchar(50) NOT NULL DEFAULT '',
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `old_data`, `new_data`, `ip_address`, `created_at`) VALUES
(1, 1, 'CREATE_INVESTOR', 'investors', 4, NULL, '{\"full_name\":\"Ali Mohamad Ali\",\"national_id\":\"920i\"}', '::1', '2026-03-01 22:53:02'),
(2, 1, 'CREATE_INVESTOR', 'investors', 5, NULL, '{\"full_name\":\"mohanned ismael azeez\",\"national_id\":\"88899\"}', '::1', '2026-03-01 22:53:23'),
(3, 1, 'CREATE_DEPOSIT', 'deposits', 6, NULL, '{\"investor_id\":4,\"type\":\"3_years\",\"amount\":10000,\"start_date\":\"2026-03-01\",\"end_date\":\"2029-02-13\",\"payout_frequency\":2,\"receipt_no\":\"AG-202603-000001\"}', '::1', '2026-03-01 22:57:27'),
(4, 1, 'CREATE_DEPOSIT', 'deposits', 7, NULL, '{\"investor_id\":4,\"type\":\"3_years\",\"amount\":10000,\"start_date\":\"2026-01-01\",\"end_date\":\"2028-12-16\",\"payout_frequency\":1,\"receipt_no\":\"AG-202603-000002\"}', '::1', '2026-03-01 23:00:31'),
(5, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-03-01\",\"processed\":0,\"skipped\":0,\"closed\":0,\"total_disbursed\":0}', '::1', '2026-03-01 23:00:57'),
(6, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":6,\"accumulated_added\":17575}', '::1', '2026-03-01 23:04:11'),
(7, 1, 'DISBURSE_PROFIT', 'deposits', 7, NULL, '{\"date\":\"2026-03-01\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":600}', '::1', '2026-03-01 23:05:38'),
(8, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-03-01\",\"processed\":3,\"skipped\":2,\"closed\":0,\"total_disbursed\":13600}', '::1', '2026-03-01 23:05:57'),
(9, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-03-01\",\"processed\":0,\"skipped\":2,\"closed\":0,\"total_disbursed\":0}', '::1', '2026-03-01 23:06:09'),
(10, 1, 'UPDATE_DEPOSIT', 'deposits', 6, '{\"id\":6,\"investor_id\":4,\"deposit_type_id\":4,\"amount\":\"10000.00\",\"currency\":\"USD\",\"start_date\":\"2026-03-01\",\"end_date\":\"2029-02-13\",\"profit_payout_frequency\":2,\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":\"2026-03-31\",\"status\":\"active\",\"created_at\":\"2026-03-01 22:57:27\",\"updated_at\":\"2026-03-01 23:04:11\",\"accumulated_profit\":\"600.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":4,\"type_id\":4,\"amount\":10000,\"start_date\":\"2026-03-01\",\"end_date\":\"2029-02-13\",\"payout_frequency\":1}', '::1', '2026-03-01 23:15:21'),
(11, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:38:16'),
(12, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:38:29'),
(13, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-04\",\"deposits_updated\":1,\"accumulated_added\":600}', '::1', '2026-03-01 23:38:32'),
(14, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:39:09'),
(15, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:40:22'),
(16, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:40:25'),
(17, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:40:32'),
(18, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:40:35'),
(19, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-12\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:40:46'),
(20, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:40:50'),
(21, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:48:32'),
(22, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:49:06'),
(23, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-01 23:59:25'),
(24, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":2,\"accumulated_added\":1500}', '::1', '2026-03-01 23:59:57'),
(25, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":3,\"accumulated_added\":12375}', '::1', '2026-03-02 00:01:55'),
(26, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:01:59'),
(27, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:02:11'),
(28, 1, 'DISBURSE_PROFIT', 'deposits', 7, NULL, '{\"date\":\"2026-03-01\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":600}', '::1', '2026-03-02 00:03:50'),
(29, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-03-01\",\"processed\":0,\"skipped\":4,\"closed\":0,\"total_disbursed\":0}', '::1', '2026-03-02 00:04:09'),
(30, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:04:49'),
(31, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-12\",\"deposits_updated\":1,\"accumulated_added\":3700}', '::1', '2026-03-02 00:06:00'),
(32, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:06:06'),
(33, 1, 'DISBURSE_PROFIT', 'deposits', 2, NULL, '{\"date\":\"2026-03-01\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":3700}', '::1', '2026-03-02 00:07:10'),
(34, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:08:18'),
(35, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:08:22'),
(36, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:13:13'),
(37, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:13:16'),
(38, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-12\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:13:42'),
(39, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-12\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:13:46'),
(40, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:15:06'),
(41, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:20:58'),
(42, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:21:04'),
(43, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:21:19'),
(44, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:21:25'),
(45, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:22:36'),
(46, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:22:57'),
(47, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:23:02'),
(48, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:29:23'),
(49, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:29:27'),
(50, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-01\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:29:33'),
(51, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":1,\"accumulated_added\":3700}', '::1', '2026-03-02 00:30:08'),
(52, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-02 00:30:12'),
(53, 1, 'DISBURSE_PROFIT', 'deposits', 2, NULL, '{\"date\":\"2026-03-01\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":3700}', '::1', '2026-03-02 00:30:40'),
(54, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-03-01\",\"processed\":0,\"skipped\":4,\"closed\":0,\"total_disbursed\":0}', '::1', '2026-03-02 00:32:44'),
(55, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '::1', '2026-03-02 00:33:33'),
(56, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '::1', '2026-03-05 12:35:14'),
(57, 1, 'CREATE_DEPOSIT', 'deposits', 8, NULL, '{\"investor_id\":4,\"type\":\"3_years\",\"amount\":10000,\"start_date\":\"2026-03-05\",\"end_date\":\"2029-02-17\",\"payout_frequency\":1,\"receipt_no\":\"AG-202603-000010\"}', '::1', '2026-03-05 12:36:05'),
(58, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":5}', '::1', '2026-03-05 12:36:49'),
(59, 1, 'CREATE_DEPOSIT', 'deposits', 9, NULL, '{\"investor_id\":4,\"type\":\"6_months\",\"amount\":1000,\"start_date\":\"2026-03-05\",\"end_date\":\"2026-09-01\",\"payout_frequency\":1,\"receipt_no\":\"AG-202603-000011\"}', '::1', '2026-03-05 15:32:13'),
(60, 1, 'DISBURSE_PROFIT', 'deposits', 6, NULL, '{\"date\":\"2026-05-05\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":600}', '::1', '2026-05-05 15:34:44'),
(61, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-05-05\",\"processed\":2,\"skipped\":0,\"closed\":0,\"total_disbursed\":14550}', '::1', '2026-05-05 15:34:55'),
(62, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-05-05\",\"processed\":0,\"skipped\":0,\"closed\":0,\"total_disbursed\":0}', '::1', '2026-05-05 15:35:01'),
(63, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '::1', '2026-03-11 12:11:22'),
(64, 1, 'CREATE_INVESTOR', 'investors', 6, NULL, '{\"full_name\":\"mohammed\",\"national_id\":\"09887\"}', '::1', '2026-03-11 12:15:14'),
(65, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '::1', '2026-03-11 12:20:43'),
(66, 1, 'CREATE_DEPOSIT', 'deposits', 10, NULL, '{\"investor_id\":4,\"type\":\"3_years\",\"amount\":10000,\"start_date\":\"2026-03-11\",\"end_date\":\"2029-02-23\",\"payout_frequency\":2,\"receipt_no\":\"AG-202603-000012\"}', '::1', '2026-03-11 12:23:15'),
(67, 1, 'CREATE_DEPOSIT', 'deposits', 11, NULL, '{\"investor_id\":1,\"type\":\"3_years\",\"amount\":5000,\"start_date\":\"2026-02-01\",\"end_date\":\"2029-01-16\",\"payout_frequency\":1,\"receipt_no\":\"AG-202603-000013\"}', '::1', '2026-03-11 12:25:53'),
(68, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-03\",\"deposits_updated\":2,\"accumulated_added\":4000}', '::1', '2026-03-11 12:29:06'),
(69, 1, 'DISBURSE_PROFIT', 'deposits', 11, NULL, '{\"date\":\"2026-03-11\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":300}', '::1', '2026-03-11 12:29:31'),
(70, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-03-11\",\"processed\":0,\"skipped\":1,\"closed\":0,\"total_disbursed\":0}', '::1', '2026-03-11 12:29:39'),
(71, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"deposits\",\"rows\":11}', '::1', '2026-03-11 12:30:29'),
(72, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '::1', '2026-03-11 12:31:11'),
(73, 3, 'LOGIN', 'users', 3, NULL, '{\"role\":\"investor\"}', '::1', '2026-03-11 12:32:47'),
(74, 3, 'LOGOUT', 'users', 3, NULL, '{\"username\":\"investor1\"}', '::1', '2026-03-11 12:34:40'),
(75, 2, 'LOGIN', 'users', 2, NULL, '{\"role\":\"staff\"}', '::1', '2026-03-11 12:34:53'),
(76, 2, 'LOGOUT', 'users', 2, NULL, '{\"username\":\"staff\"}', '::1', '2026-03-11 12:37:21'),
(77, 3, 'LOGIN', 'users', 3, NULL, '{\"role\":\"investor\"}', '::1', '2026-03-11 12:40:59'),
(78, 3, 'REQUEST_WITHDRAW', 'withdraw_requests', NULL, NULL, '{\"investor_id\":1,\"amount\":1200}', '::1', '2026-03-11 12:46:55'),
(79, 3, 'LOGOUT', 'users', 3, NULL, '{\"username\":\"investor1\"}', '::1', '2026-03-11 12:49:01'),
(80, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '::1', '2026-03-11 12:55:54'),
(81, 1, 'CREATE_DEPOSIT', 'deposits', 12, NULL, '{\"investor_id\":3,\"type\":\"1_year\",\"amount\":1200000,\"start_date\":\"2026-03-11\",\"end_date\":\"2027-03-06\",\"payout_frequency\":12,\"receipt_no\":\"AG-202603-000015\"}', '::1', '2026-03-11 12:58:35'),
(82, 1, 'UPDATE_DEPOSIT', 'deposits', 12, '{\"id\":12,\"investor_id\":3,\"deposit_type_id\":2,\"amount\":\"1200000.00\",\"currency\":\"IQD\",\"start_date\":\"2026-03-11\",\"end_date\":\"2027-03-06\",\"profit_payout_frequency\":12,\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":null,\"status\":\"active\",\"created_at\":\"2026-03-11 12:58:35\",\"updated_at\":\"2026-03-11 12:58:35\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":3,\"type_id\":2,\"amount\":1200000,\"start_date\":\"2025-02-11\",\"end_date\":\"2026-02-06\",\"payout_frequency\":12}', '::1', '2026-03-11 12:58:54'),
(83, 1, 'UPDATE_DEPOSIT', 'deposits', 12, '{\"id\":12,\"investor_id\":3,\"deposit_type_id\":2,\"amount\":\"1200000.00\",\"currency\":\"IQD\",\"start_date\":\"2025-02-11\",\"end_date\":\"2026-02-06\",\"profit_payout_frequency\":12,\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":null,\"status\":\"active\",\"created_at\":\"2026-03-11 12:58:35\",\"updated_at\":\"2026-03-11 12:58:54\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":3,\"type_id\":2,\"amount\":1200000,\"start_date\":\"2025-02-11\",\"end_date\":\"2026-02-06\",\"payout_frequency\":6}', '::1', '2026-03-11 12:59:50'),
(84, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:00:01'),
(85, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-02\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:00:04'),
(86, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-03\",\"deposits_updated\":1,\"accumulated_added\":44400}', '::1', '2026-03-11 13:00:06'),
(87, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-03\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:00:09'),
(88, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-04\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:00:41'),
(89, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-04\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:00:44'),
(90, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-04\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:00:59'),
(91, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-04\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:01'),
(92, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-05\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:04'),
(93, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-05\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:06'),
(94, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-06\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:08'),
(95, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-06\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:10'),
(96, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-07\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:14'),
(97, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-07\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:16'),
(98, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-08\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:18'),
(99, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2025-08\",\"deposits_updated\":0,\"accumulated_added\":0}', '::1', '2026-03-11 13:01:20'),
(100, 1, 'DISBURSE_PROFIT', 'deposits', 12, NULL, '{\"date\":\"2026-03-11\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":44400}', '::1', '2026-03-11 13:25:01'),
(101, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-03-11\",\"processed\":0,\"skipped\":2,\"closed\":0,\"total_disbursed\":0}', '::1', '2026-03-11 13:25:45'),
(102, 1, 'COMPLETE_DEPOSIT', 'deposits', 12, NULL, '{\"receipt_no\":\"AG-202603-000017\",\"refunded_amount\":\"1200000.00\",\"currency\":\"IQD\"}', '::1', '2026-03-11 13:33:03'),
(103, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '::1', '2026-03-29 17:36:46'),
(104, 1, 'DISBURSE_PROFIT', 'deposits', 2, NULL, '{\"date\":\"2026-04-01\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":3700}', '::1', '2026-04-01 15:36:58'),
(105, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-04-01\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":900}', '::1', '2026-04-01 15:37:06'),
(106, 1, 'COMPLETE_DEPOSIT', 'deposits', 4, NULL, '{\"receipt_no\":\"AG-202604-000003\",\"refunded_amount\":\"30000.00\",\"currency\":\"IQD\"}', '::1', '2026-04-01 15:37:32'),
(107, 1, 'COMPLETE_DEPOSIT', 'deposits', 1, NULL, '{\"receipt_no\":\"AG-202604-000004\",\"refunded_amount\":\"50000.00\",\"currency\":\"IQD\"}', '::1', '2026-04-01 15:37:40'),
(108, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 01:15:49'),
(109, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 07:02:10'),
(110, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 07:31:43'),
(111, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 07:41:03'),
(112, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 07:55:43'),
(113, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 07:57:47'),
(114, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":5}', '185.24.60.38', '2026-04-04 07:58:44'),
(115, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 08:06:50'),
(116, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 08:09:17'),
(117, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":5}', '185.24.60.38', '2026-04-04 08:15:03'),
(118, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":5}', '185.24.60.38', '2026-04-04 08:15:12'),
(119, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":5}', '185.24.60.38', '2026-04-04 08:19:20'),
(120, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":5}', '185.24.60.38', '2026-04-04 08:19:20'),
(121, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":5}', '185.24.60.38', '2026-04-04 08:19:42'),
(122, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":5}', '185.24.60.38', '2026-04-04 08:19:42'),
(123, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:19:59'),
(124, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:19:59'),
(125, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:20:19'),
(126, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:20:19'),
(127, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"deposits\",\"rows\":12}', '185.24.60.38', '2026-04-04 08:20:42'),
(128, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"deposits\",\"rows\":12}', '185.24.60.38', '2026-04-04 08:20:42'),
(129, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:20:55'),
(130, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:20:55'),
(131, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:21:28'),
(132, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:21:28'),
(133, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:21:38'),
(134, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:21:38'),
(135, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":4}', '185.24.60.38', '2026-04-04 08:27:32'),
(136, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":2}', '185.24.60.38', '2026-04-04 08:27:51'),
(137, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:28:27'),
(138, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":8}', '185.24.60.38', '2026-04-04 08:28:43'),
(139, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":3}', '185.24.60.38', '2026-04-04 08:28:57'),
(140, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":3}', '185.24.60.38', '2026-04-04 08:29:03'),
(141, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:29:22'),
(142, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":9}', '185.24.60.38', '2026-04-04 08:29:36'),
(143, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:29:43'),
(144, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":15}', '185.24.60.38', '2026-04-04 08:29:57'),
(145, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":15}', '185.24.60.38', '2026-04-04 08:30:20'),
(146, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":6}', '185.24.60.38', '2026-04-04 08:33:53'),
(147, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:34:04'),
(148, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":9}', '185.24.60.38', '2026-04-04 08:34:13'),
(149, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 08:34:20'),
(150, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":9}', '185.24.60.38', '2026-04-04 08:35:13'),
(151, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":1}', '185.24.60.38', '2026-04-04 08:35:34'),
(152, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":3}', '185.24.60.38', '2026-04-04 08:35:50'),
(153, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":1}', '185.24.60.38', '2026-04-04 08:36:50'),
(154, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":9}', '185.24.60.38', '2026-04-04 08:37:55'),
(155, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"profits\",\"rows\":4}', '185.24.60.38', '2026-04-04 08:38:19'),
(156, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":15}', '185.24.60.38', '2026-04-04 08:40:50'),
(157, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":0}', '185.24.60.38', '2026-04-04 08:41:05'),
(158, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":3}', '185.24.60.38', '2026-04-04 08:41:19'),
(159, 1, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":8}', '185.24.60.38', '2026-04-04 08:41:59'),
(160, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":8}', '185.24.60.38', '2026-04-04 08:42:10'),
(161, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 17:49:59'),
(162, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-04 18:00:55'),
(163, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 18:01:04'),
(164, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '104.28.131.55', '2026-04-04 18:02:42'),
(165, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":26}', '185.24.60.38', '2026-04-04 18:02:46'),
(166, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":9}', '185.24.60.38', '2026-04-04 18:05:34'),
(167, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '104.28.131.58', '2026-04-04 18:05:43'),
(168, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":2}', '185.24.60.38', '2026-04-04 18:06:58'),
(169, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '169.224.65.62', '2026-04-04 18:07:30'),
(170, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '104.28.131.58', '2026-04-04 18:07:48'),
(171, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-04-04\",\"processed\":0,\"skipped\":0,\"closed\":0,\"total_disbursed\":0}', '185.24.60.38', '2026-04-04 18:11:37'),
(172, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '169.224.65.62', '2026-04-04 18:13:50'),
(173, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-05 05:33:09'),
(174, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '185.24.60.38', '2026-04-05 05:33:11'),
(175, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-05 05:38:49'),
(176, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '185.24.60.38', '2026-04-05 05:39:10'),
(177, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '82.199.214.127', '2026-04-05 16:41:53'),
(178, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '82.199.214.127', '2026-04-05 16:47:23'),
(179, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '212.95.140.17', '2026-04-05 20:20:44'),
(180, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '212.95.140.17', '2026-04-05 20:21:11'),
(181, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.38', '2026-04-06 16:55:17'),
(182, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '95.170.196.22', '2026-04-11 08:43:49'),
(183, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '195.181.165.183', '2026-04-12 10:12:09'),
(184, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '195.181.165.183', '2026-04-12 10:12:34'),
(185, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '188.72.10.31', '2026-04-16 10:36:33'),
(186, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-04\",\"deposits_updated\":0,\"accumulated_added\":0}', '188.72.10.31', '2026-04-16 10:51:49'),
(187, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '195.181.165.183', '2026-04-28 05:28:52'),
(188, 1, 'CREATE_USER', 'users', 4, NULL, '{\"username\":\"mohammed\",\"role\":\"admin\"}', '195.181.165.183', '2026-04-28 05:29:17'),
(189, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '195.181.165.183', '2026-04-28 05:29:26'),
(190, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '195.181.165.183', '2026-04-28 05:29:34'),
(191, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '91.106.61.197', '2026-04-28 05:31:32'),
(192, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '91.106.61.197', '2026-04-28 06:15:14'),
(193, 4, 'APPROVE_WITHDRAW', 'withdraw_requests', 1, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"note\":\"\"}', '91.106.61.197', '2026-04-28 06:16:08'),
(194, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '82.199.214.17', '2026-04-28 13:49:17'),
(195, 4, 'LOGOUT', 'users', 4, NULL, '{\"username\":\"mohammed\"}', '82.199.214.17', '2026-04-28 13:50:26'),
(196, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '82.199.214.17', '2026-04-28 13:50:54'),
(197, 4, 'CREATE_INVESTOR', 'investors', 7, NULL, '{\"full_name\":\"مراد علي سليم\",\"national_id\":\"٢٢٨٣٨٨٩٩٢٣\"}', '82.199.214.17', '2026-04-28 13:52:51'),
(198, 4, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"muradali\",\"role\":\"investor\",\"investor_id\":7}', '82.199.214.17', '2026-04-28 13:52:51'),
(199, 4, 'CREATE_DEPOSIT', 'deposits', 13, NULL, '{\"investor_id\":7,\"type\":\"3_years\",\"amount\":10000,\"start_date\":\"2026-04-01\",\"end_date\":\"2029-03-16\",\"payout_frequency\":1,\"receipt_no\":\"AG-202604-000005\"}', '82.199.214.17', '2026-04-28 13:53:14'),
(200, 5, 'LOGIN', 'users', 5, NULL, '{\"role\":\"investor\"}', '172.225.189.223', '2026-04-28 13:54:07'),
(201, 4, 'PAY_WITHDRAW', 'withdraw_requests', 1, '{\"status\":\"approved\"}', '{\"status\":\"paid\",\"receipt_no\":\"AG-202604-000006\"}', '82.199.214.17', '2026-04-28 13:55:56'),
(202, 5, 'LOGOUT', 'users', 5, NULL, '{\"username\":\"muradali\"}', '172.225.189.223', '2026-04-28 13:56:54'),
(203, 5, 'LOGIN', 'users', 5, NULL, '{\"role\":\"investor\"}', '172.225.189.223', '2026-04-28 13:57:08'),
(204, 5, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":1}', '172.225.189.223', '2026-04-28 13:57:21'),
(205, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '130.193.248.17', '2026-04-29 07:24:32'),
(206, 4, 'LOGOUT', 'users', 4, NULL, '{\"username\":\"mohammed\"}', '130.193.248.17', '2026-04-29 07:24:34'),
(207, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '130.193.248.17', '2026-04-29 07:28:51'),
(208, 4, 'LOGOUT', 'users', 4, NULL, '{\"username\":\"mohammed\"}', '130.193.248.17', '2026-04-29 07:31:51'),
(209, 5, 'LOGIN', 'users', 5, NULL, '{\"role\":\"investor\"}', '130.193.248.17', '2026-04-29 07:32:03'),
(210, 5, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":1}', '130.193.248.17', '2026-04-29 07:35:37'),
(211, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '130.193.248.17', '2026-04-30 08:40:18'),
(212, 4, 'LOGOUT', 'users', 4, NULL, '{\"username\":\"mohammed\"}', '130.193.248.17', '2026-04-30 08:40:26'),
(213, 5, 'LOGIN', 'users', 5, NULL, '{\"role\":\"investor\"}', '130.193.248.17', '2026-04-30 08:40:54'),
(214, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '91.106.47.118', '2026-05-12 09:02:48'),
(215, 4, 'CREATE_USER', 'users', 6, NULL, '{\"username\":\"baghdad\",\"role\":\"staff\"}', '91.106.47.118', '2026-05-12 09:03:49'),
(216, 4, 'LOGOUT', 'users', 4, NULL, '{\"username\":\"mohammed\"}', '91.106.47.118', '2026-05-12 09:04:03'),
(217, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.118', '2026-05-12 09:04:18'),
(218, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '91.106.47.118', '2026-05-12 09:04:24'),
(219, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '91.106.47.118', '2026-05-12 09:04:32'),
(220, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.118', '2026-05-12 09:07:14'),
(221, 6, 'EXPORT_EXCEL', 'reports', NULL, NULL, '{\"report\":\"deposits\",\"rows\":5}', '91.106.47.118', '2026-05-12 09:11:36'),
(222, 4, 'CREATE_USER', 'users', 7, NULL, '{\"username\":\"erbil\",\"role\":\"staff\"}', '91.106.47.118', '2026-05-12 09:23:59'),
(223, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '212.237.114.3', '2026-05-17 02:31:17'),
(224, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '212.237.114.3', '2026-05-17 02:31:41'),
(225, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.230.250', '2026-05-17 02:33:37'),
(226, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.118', '2026-05-17 02:59:57'),
(227, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.230.250', '2026-05-17 04:58:30'),
(228, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '169.224.64.42', '2026-05-22 10:22:44'),
(229, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.143', '2026-05-22 21:45:23'),
(230, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.143', '2026-05-22 21:45:47'),
(231, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '185.24.60.143', '2026-05-22 21:46:48'),
(232, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.39.150', '2026-05-31 06:24:38'),
(233, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '91.106.39.150', '2026-05-31 06:25:36'),
(234, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.39.150', '2026-05-31 06:25:49'),
(235, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '91.106.39.150', '2026-06-03 06:08:19'),
(236, 4, 'LOGOUT', 'users', 4, NULL, '{\"username\":\"mohammed\"}', '91.106.39.150', '2026-06-03 06:19:15'),
(237, 4, 'LOGIN', 'users', 4, NULL, '{\"role\":\"admin\"}', '91.106.39.150', '2026-06-03 06:19:40'),
(238, 4, 'UPDATE_USER_PASSWORD', 'users', 5, NULL, '{\"username\":\"muradali\"}', '91.106.39.150', '2026-06-03 06:20:08'),
(239, 4, 'UPDATE_INVESTOR', 'investors', 7, '{\"id\":\"7\",\"full_name\":\"مراد علي سليم\",\"phone\":\"٠٧٧٣١١١١٨٩٨\",\"city\":\"كركوك\",\"address\":\"محافظة\",\"notes\":\"\",\"national_id\":\"٢٢٨٣٨٨٩٩٢٣\",\"contract_path\":\"\",\"id_card_path\":\"\",\"created_at\":\"2026-04-28 13:52:51\",\"updated_at\":\"2026-04-28 13:52:51\"}', '{\"full_name\":\"مراد علي سليم\",\"national_id\":\"٢٢٨٣٨٨٩٩٢٣\",\"phone\":\"٠٧٧٣١١١١٨٩٨\",\"city\":\"كركوك\"}', '91.106.39.150', '2026-06-03 06:20:08'),
(240, 4, 'LOGOUT', 'users', 4, NULL, '{\"username\":\"mohammed\"}', '91.106.39.150', '2026-06-03 06:20:11'),
(241, 5, 'LOGIN', 'users', 5, NULL, '{\"role\":\"investor\"}', '91.106.39.150', '2026-06-03 06:20:28'),
(242, 5, 'LOGOUT', 'users', 5, NULL, '{\"username\":\"muradali\"}', '91.106.39.150', '2026-06-03 06:22:40'),
(243, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '195.181.165.183', '2026-06-03 07:08:21'),
(244, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-06\",\"deposits_updated\":0,\"accumulated_added\":0}', '195.181.165.183', '2026-06-03 07:09:21'),
(245, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-05\",\"deposits_updated\":2,\"accumulated_added\":1120}', '195.181.165.183', '2026-06-03 07:09:38'),
(246, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-05\",\"deposits_updated\":0,\"accumulated_added\":0}', '195.181.165.183', '2026-06-03 07:09:42'),
(247, 1, 'DISBURSE_PROFIT', 'deposits', 13, NULL, '{\"date\":\"2026-06-03\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":560}', '195.181.165.183', '2026-06-03 07:10:28'),
(248, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.230.250', '2026-06-17 08:08:18'),
(249, 1, 'CREATE_DEPOSIT', 'deposits', 14, NULL, '{\"investor_id\":4,\"type\":\"3_years\",\"amount\":100000,\"start_date\":\"2026-06-17\",\"end_date\":\"2029-06-01\",\"payout_frequency\":2,\"receipt_no\":\"AG-202606-000002\"}', '130.193.230.250', '2026-06-17 08:09:20'),
(250, 1, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-06\",\"deposits_updated\":2,\"accumulated_added\":1200}', '130.193.230.250', '2026-06-17 08:10:18'),
(251, 1, 'DISBURSE_PROFIT', 'deposits', 13, NULL, '{\"date\":\"2026-06-17\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":600}', '130.193.230.250', '2026-06-17 08:10:45'),
(252, 1, 'COMPLETE_DEPOSIT', 'deposits', 5, NULL, '{\"receipt_no\":\"AG-202606-000004\",\"refunded_amount\":\"75000.00\",\"currency\":\"IQD\"}', '130.193.230.250', '2026-06-17 08:11:42'),
(253, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.230.250', '2026-06-18 06:46:10'),
(254, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.230.250', '2026-06-20 08:06:32'),
(255, 1, 'UPDATE_USER', 'users', 7, '{\"role\":\"staff\"}', '{\"role\":\"staff\",\"password_changed\":true}', '130.193.230.250', '2026-06-20 08:07:13'),
(256, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.230.250', '2026-06-20 08:07:50'),
(257, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '130.193.230.250', '2026-06-20 08:20:16'),
(258, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.230.250', '2026-06-20 08:20:42'),
(259, 7, 'CREATE_INVESTOR', 'investors', 8, NULL, '{\"full_name\":\"اناغيم فهيم طراف\",\"national_id\":\"5576\"}', '130.193.230.250', '2026-06-20 08:33:26'),
(260, 7, 'CREATE_DEPOSIT', 'deposits', 15, NULL, '{\"investor_id\":8,\"type\":\"3_years\",\"amount\":10000,\"start_date\":\"2026-06-20\",\"end_date\":\"2029-06-04\",\"payout_frequency\":1,\"receipt_no\":\"AG-202606-000005\"}', '130.193.230.250', '2026-06-20 08:34:51'),
(261, 7, 'UPDATE_DEPOSIT', 'deposits', 15, '{\"id\":\"15\",\"investor_id\":\"8\",\"deposit_type_id\":\"4\",\"amount\":\"10000.00\",\"currency\":\"USD\",\"start_date\":\"2026-06-20\",\"end_date\":\"2029-06-04\",\"profit_payout_frequency\":\"1\",\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":null,\"status\":\"active\",\"created_at\":\"2026-06-20 08:34:51\",\"updated_at\":\"2026-06-20 08:34:51\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":8,\"type_id\":4,\"amount\":10000,\"start_date\":\"2026-05-01\",\"end_date\":\"2029-04-15\",\"payout_frequency\":1}', '130.193.230.250', '2026-06-20 08:35:55'),
(262, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.230.250', '2026-06-21 02:24:19'),
(263, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.236.172', '2026-06-22 05:37:41'),
(264, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.236.172', '2026-06-23 06:03:53'),
(265, 7, 'CREATE_INVESTOR', 'investors', 9, NULL, '{\"full_name\":\"AL-ASAFY GROUP\",\"national_id\":\"55557676\"}', '130.193.236.172', '2026-06-23 06:16:50'),
(266, 7, 'CREATE_DEPOSIT', 'deposits', 16, NULL, '{\"investor_id\":9,\"type\":\"1_year\",\"amount\":1500,\"start_date\":\"2026-06-23\",\"end_date\":\"2027-06-18\",\"payout_frequency\":1,\"receipt_no\":\"AG-202606-000006\"}', '130.193.236.172', '2026-06-23 06:18:23'),
(267, 7, 'UPDATE_DEPOSIT', 'deposits', 16, '{\"id\":\"16\",\"investor_id\":\"9\",\"deposit_type_id\":\"2\",\"amount\":\"1500.00\",\"currency\":\"USD\",\"start_date\":\"2026-06-23\",\"end_date\":\"2027-06-18\",\"profit_payout_frequency\":\"1\",\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":null,\"status\":\"active\",\"created_at\":\"2026-06-23 06:18:23\",\"updated_at\":\"2026-06-23 06:18:23\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":9,\"type_id\":2,\"amount\":1500,\"start_date\":\"2026-07-23\",\"end_date\":\"2027-07-18\",\"payout_frequency\":1}', '130.193.236.172', '2026-06-23 06:19:24'),
(268, 7, 'UPDATE_DEPOSIT', 'deposits', 16, '{\"id\":\"16\",\"investor_id\":\"9\",\"deposit_type_id\":\"2\",\"amount\":\"1500.00\",\"currency\":\"USD\",\"start_date\":\"2026-07-23\",\"end_date\":\"2027-07-18\",\"profit_payout_frequency\":\"1\",\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":null,\"status\":\"active\",\"created_at\":\"2026-06-23 06:18:23\",\"updated_at\":\"2026-06-23 06:19:24\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":9,\"type_id\":2,\"amount\":1500,\"start_date\":\"2026-05-23\",\"end_date\":\"2027-05-18\",\"payout_frequency\":1}', '130.193.236.172', '2026-06-23 06:19:36'),
(269, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.236.172', '2026-06-25 07:42:48'),
(270, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.236.172', '2026-06-27 07:24:29'),
(271, 1, 'UPDATE_USER', 'users', 6, '{\"role\":\"staff\"}', '{\"role\":\"staff\",\"password_changed\":true}', '130.193.236.172', '2026-06-27 07:24:51'),
(272, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.191', '2026-06-27 07:26:24'),
(273, 6, 'CREATE_INVESTOR', 'investors', 10, NULL, '{\"full_name\":\"mohamad\",\"national_id\":\"0986\"}', '91.106.46.191', '2026-06-27 07:31:51'),
(274, 6, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"mohammad ali\",\"role\":\"investor\",\"investor_id\":10}', '91.106.46.191', '2026-06-27 07:31:51'),
(275, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.191', '2026-06-27 07:37:03'),
(276, 6, 'CREATE_DEPOSIT', 'deposits', 17, NULL, '{\"investor_id\":10,\"type\":\"1_year\",\"amount\":5000000,\"start_date\":\"2026-06-27\",\"end_date\":\"2027-06-22\",\"payout_frequency\":1,\"receipt_no\":\"AG-202606-000007\"}', '91.106.46.191', '2026-06-27 07:43:10'),
(277, 6, 'UPDATE_DEPOSIT', 'deposits', 17, '{\"id\":\"17\",\"investor_id\":\"10\",\"deposit_type_id\":\"2\",\"amount\":\"5000000.00\",\"currency\":\"IQD\",\"start_date\":\"2026-06-27\",\"end_date\":\"2027-06-22\",\"profit_payout_frequency\":\"1\",\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":null,\"status\":\"active\",\"created_at\":\"2026-06-27 07:43:10\",\"updated_at\":\"2026-06-27 07:43:10\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":10,\"type_id\":2,\"amount\":5000000,\"start_date\":\"2026-05-01\",\"end_date\":\"2027-04-26\",\"payout_frequency\":1}', '91.106.46.191', '2026-06-27 07:46:06'),
(278, 6, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-06\",\"deposits_updated\":3,\"accumulated_added\":185655.5}', '91.106.46.191', '2026-06-27 07:47:09'),
(279, 6, 'DISBURSE_PROFIT', 'deposits', 17, NULL, '{\"date\":\"2026-06-27\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":185000}', '91.106.46.191', '2026-06-27 07:48:09'),
(280, 6, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-06-27\",\"processed\":3,\"skipped\":0,\"closed\":0,\"total_disbursed\":1815.5}', '91.106.46.191', '2026-06-27 07:51:24'),
(281, 6, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-04\",\"deposits_updated\":7,\"accumulated_added\":14830}', '91.106.46.191', '2026-06-27 07:54:05'),
(282, 6, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-04\",\"deposits_updated\":0,\"accumulated_added\":0}', '91.106.46.191', '2026-06-27 07:55:16'),
(283, 6, 'DISBURSE_PROFIT', 'deposits', 11, NULL, '{\"date\":\"2026-06-27\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":300}', '91.106.46.191', '2026-06-27 07:55:59'),
(284, 6, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-05\",\"deposits_updated\":7,\"accumulated_added\":14830}', '91.106.46.191', '2026-06-27 07:56:53'),
(285, 6, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-05\",\"deposits_updated\":0,\"accumulated_added\":0}', '91.106.46.191', '2026-06-27 07:57:00'),
(286, 6, 'DISBURSE_PROFIT', 'deposits', 11, NULL, '{\"date\":\"2026-06-27\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":300}', '91.106.46.191', '2026-06-27 07:57:26'),
(287, 6, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-06\",\"deposits_updated\":7,\"accumulated_added\":14830}', '91.106.46.191', '2026-06-27 07:58:00'),
(288, 6, 'DISBURSE_PROFIT', 'deposits', 11, NULL, '{\"date\":\"2026-06-27\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":300}', '91.106.46.191', '2026-06-27 07:58:11'),
(289, 6, 'DISBURSE_PROFIT', 'deposits', 10, NULL, '{\"date\":\"2026-06-27\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":1800}', '91.106.46.191', '2026-06-27 07:58:54'),
(290, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.191', '2026-06-27 09:37:24'),
(291, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.236.172', '2026-06-27 09:58:56'),
(292, 1, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"ali\",\"role\":\"investor\",\"investor_id\":4,\"note\":\"Account created during investor edit\"}', '130.193.236.172', '2026-06-27 10:00:07'),
(293, 1, 'UPDATE_INVESTOR', 'investors', 4, '{\"id\":\"4\",\"full_name\":\"Ali Mohamad Ali\",\"phone\":\"888\",\"city\":\"أربيل\",\"address\":\"jjhjhj\",\"notes\":\"\",\"national_id\":\"920i\",\"contract_path\":\"\",\"id_card_path\":\"\",\"created_at\":\"2026-03-01 22:53:02\",\"updated_at\":\"2026-03-01 22:53:02\"}', '{\"full_name\":\"Ali Mohamad Ali\",\"national_id\":\"920i\",\"phone\":\"888\",\"city\":\"أربيل\"}', '130.193.236.172', '2026-06-27 10:00:07'),
(294, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '130.193.236.172', '2026-06-27 10:00:12'),
(295, 9, 'LOGIN', 'users', 9, NULL, '{\"role\":\"investor\"}', '130.193.236.172', '2026-06-27 10:00:24'),
(296, 9, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":11}', '130.193.236.172', '2026-06-27 10:01:09'),
(297, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.191', '2026-06-28 04:18:56'),
(298, 6, 'CREATE_INVESTOR', 'investors', 11, NULL, '{\"full_name\":\"zahraa\",\"national_id\":\"097643344\"}', '91.106.46.191', '2026-06-28 05:21:20'),
(299, 6, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"zahraa\",\"role\":\"investor\",\"investor_id\":11}', '91.106.46.191', '2026-06-28 05:21:20'),
(300, 6, 'UPDATE_INVESTOR', 'investors', 11, '{\"id\":\"11\",\"full_name\":\"zahraa\",\"phone\":\"078654567\",\"city\":\"بغداد\",\"address\":\"العامرية\",\"notes\":\"\",\"national_id\":\"097643344\",\"contract_path\":\"uploads\\/investors\\/inv_6a40e79070e0e9.61467867.pdf\",\"id_card_path\":\"\",\"created_at\":\"2026-06-28 05:21:20\",\"updated_at\":\"2026-06-28 05:21:20\"}', '{\"full_name\":\"zahraa\",\"national_id\":\"097643344\",\"phone\":\"078654567\",\"city\":\"بغداد\"}', '91.106.46.191', '2026-06-28 05:28:49'),
(301, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.191', '2026-06-28 06:00:11'),
(302, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '188.72.11.68', '2026-06-30 03:59:13'),
(303, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.198.250', '2026-07-15 10:00:22'),
(304, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '130.193.198.250', '2026-07-15 10:00:39'),
(305, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.254.98', '2026-07-15 12:29:20'),
(306, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.250.144', '2026-07-22 08:53:23'),
(307, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '188.72.11.7', '2026-07-25 06:21:43'),
(308, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.82', '2026-07-26 16:20:51'),
(309, 1, 'RESET_INVESTOR_PASSWORD', 'users', 10, NULL, '{\"reset_by\":1,\"investor_id\":11,\"username\":\"zahraa\"}', '185.24.60.82', '2026-07-26 16:22:35'),
(310, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '185.24.60.82', '2026-07-26 16:22:49'),
(311, 10, 'LOGIN', 'users', 10, NULL, '{\"role\":\"investor\"}', '185.24.60.82', '2026-07-26 16:22:59'),
(312, 10, 'CHANGE_OWN_PASSWORD', 'users', 10, NULL, '{\"description\":\"User changed their own password\"}', '185.24.60.82', '2026-07-26 16:23:25'),
(313, 10, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":0}', '185.24.60.82', '2026-07-26 16:23:34'),
(314, 10, 'LOGOUT', 'users', 10, NULL, '{\"username\":\"zahraa\"}', '185.24.60.82', '2026-07-26 16:23:50'),
(315, 10, 'LOGIN', 'users', 10, NULL, '{\"role\":\"investor\"}', '185.24.60.82', '2026-07-26 16:24:18'),
(316, 10, 'LOGOUT', 'users', 10, NULL, '{\"username\":\"zahraa\"}', '185.24.60.82', '2026-07-26 16:24:40'),
(317, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.60.82', '2026-07-26 16:26:22'),
(318, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '127.0.0.1', '2026-07-26 22:47:12'),
(319, 1, 'DISBURSE_PROFIT', 'deposits', 17, NULL, '{\"date\":\"2026-07-27\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":12000}', '127.0.0.1', '2026-07-26 22:47:58'),
(320, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-07-27\",\"processed\":5,\"skipped\":0,\"closed\":0,\"total_disbursed\":41790}', '127.0.0.1', '2026-07-26 22:48:06'),
(321, 1, 'ADD_MANUAL_PROFIT', 'deposits', 17, NULL, '{\"amount\":5000,\"currency\":\"IQD\",\"month\":\"2026-07\",\"anniversary_date\":\"2026-07-01\",\"note\":\"إضافة ربح تراكمي يدوي لشهر 2026-07\"}', '127.0.0.1', '2026-07-26 22:48:29'),
(322, 1, 'ADD_MANUAL_PROFIT', 'deposits', 17, NULL, '{\"amount\":5000,\"currency\":\"IQD\",\"month\":\"2026-08\",\"anniversary_date\":\"2026-08-01\",\"note\":\"إضافة ربح تراكمي يدوي لشهر 2026-08\"}', '127.0.0.1', '2026-07-26 22:48:39'),
(323, 1, 'ADD_MANUAL_PROFIT', 'deposits', 14, NULL, '{\"amount\":50000,\"currency\":\"USD\",\"month\":\"2026-07\",\"anniversary_date\":\"2026-07-17\",\"note\":\"إضافة ربح تراكمي يدوي لشهر 2026-07\"}', '127.0.0.1', '2026-07-26 22:55:01'),
(324, 1, 'ADD_MANUAL_PROFIT', 'deposits', 14, NULL, '{\"amount\":5000,\"currency\":\"USD\",\"month\":\"2026-08\",\"anniversary_date\":\"2026-08-17\",\"note\":\"إضافة ربح تراكمي يدوي لشهر 2026-08\"}', '127.0.0.1', '2026-07-26 22:55:17'),
(325, 1, 'DISBURSE_PROFIT', 'deposits', 15, NULL, '{\"date\":\"2026-07-27\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":10}', '127.0.0.1', '2026-07-26 22:55:37'),
(326, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-07-27\",\"processed\":0,\"skipped\":2,\"closed\":0,\"total_disbursed\":0}', '127.0.0.1', '2026-07-26 22:55:54');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `old_data`, `new_data`, `ip_address`, `created_at`) VALUES
(327, 1, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-07-27\",\"processed\":0,\"skipped\":2,\"closed\":0,\"total_disbursed\":0}', '127.0.0.1', '2026-07-26 22:56:02'),
(328, 1, 'UPDATE_DEPOSIT', 'deposits', 16, '{\"id\":16,\"investor_id\":9,\"deposit_type_id\":2,\"amount\":\"1500.00\",\"currency\":\"USD\",\"start_date\":\"2026-05-23\",\"end_date\":\"2027-05-18\",\"profit_payout_frequency\":1,\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":\"2026-06-23\",\"status\":\"active\",\"created_at\":\"2026-06-23 06:18:23\",\"updated_at\":\"2026-06-27 07:51:24\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":\"2026-06-23\"}', '{\"investor_id\":9,\"type_id\":2,\"amount\":1500,\"start_date\":\"2026-05-23\",\"end_date\":\"2027-05-18\",\"payout_frequency\":12}', '127.0.0.1', '2026-07-26 22:56:51'),
(329, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '212.95.140.58', '2026-07-30 17:21:55'),
(330, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '169.224.65.220', '2026-07-30 17:37:26'),
(331, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '178.52.149.197', '2026-08-01 11:52:34'),
(332, 1, 'CREATE_DEPOSIT', 'deposits', 18, NULL, '{\"investor_id\":4,\"type\":\"3_years\",\"amount\":10000000,\"start_date\":\"2026-08-01\",\"end_date\":\"2029-07-16\",\"payout_frequency\":12,\"receipt_no\":\"AG-202608-000001\"}', '178.52.149.197', '2026-08-01 11:59:57'),
(333, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-02 09:39:13'),
(334, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.198.250', '2026-08-06 06:59:55'),
(335, 1, 'CREATE_INVESTOR', 'investors', 12, NULL, '{\"full_name\":\"علي محمد علي\",\"national_id\":\"12345\"}', '130.193.198.250', '2026-08-06 07:04:30'),
(336, 1, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"alimali\",\"role\":\"investor\",\"investor_id\":12}', '130.193.198.250', '2026-08-06 07:04:31'),
(337, 1, 'RESET_INVESTOR_PASSWORD', 'users', 11, NULL, '{\"reset_by\":1,\"investor_id\":12,\"username\":\"alimali\"}', '130.193.198.250', '2026-08-06 07:06:38'),
(338, 1, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":0}', '130.193.198.250', '2026-08-06 07:06:57'),
(339, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '130.193.198.250', '2026-08-06 07:07:08'),
(340, 11, 'LOGIN', 'users', 11, NULL, '{\"role\":\"investor\"}', '130.193.198.250', '2026-08-06 07:07:32'),
(341, 11, 'LOGOUT', 'users', 11, NULL, '{\"username\":\"alimali\"}', '130.193.198.250', '2026-08-06 07:09:25'),
(342, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.198.250', '2026-08-06 07:09:36'),
(343, 1, 'CREATE_DEPOSIT', 'deposits', 19, NULL, '{\"investor_id\":12,\"type\":\"3_years\",\"amount\":10000,\"start_date\":\"2026-08-06\",\"end_date\":\"2029-07-21\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000002\"}', '130.193.198.250', '2026-08-06 07:10:13'),
(344, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '130.193.198.250', '2026-08-06 07:11:08'),
(345, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-06 07:11:28'),
(346, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '130.193.198.250', '2026-08-06 07:12:39'),
(347, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-06 07:12:59'),
(348, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.198.250', '2026-08-06 07:13:06'),
(349, 6, 'DECLARE_RATES', 'monthly_rates', NULL, NULL, '{\"month\":\"2026-08\",\"deposits_updated\":0,\"accumulated_added\":0}', '130.193.198.250', '2026-08-06 07:13:19'),
(350, 6, 'UPDATE_DEPOSIT', 'deposits', 19, '{\"id\":19,\"investor_id\":12,\"deposit_type_id\":4,\"amount\":\"10000.00\",\"currency\":\"USD\",\"start_date\":\"2026-08-06\",\"end_date\":\"2029-07-21\",\"profit_payout_frequency\":1,\"profit_rate_monthly\":\"0.00000\",\"last_profit_date\":null,\"status\":\"active\",\"created_at\":\"2026-08-06 07:10:13\",\"updated_at\":\"2026-08-06 07:10:13\",\"accumulated_profit\":\"0.00\",\"last_withdrawal_date\":null}', '{\"investor_id\":12,\"type_id\":4,\"amount\":10000,\"start_date\":\"2026-07-06\",\"end_date\":\"2029-06-20\",\"payout_frequency\":1}', '130.193.198.250', '2026-08-06 07:16:35'),
(351, 6, 'DISBURSE_PROFIT', 'deposits', 19, NULL, '{\"date\":\"2026-08-06\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":250}', '130.193.198.250', '2026-08-06 07:21:23'),
(352, 6, 'DISBURSE_PROFIT', 'deposits', NULL, NULL, '{\"date\":\"2026-08-06\",\"processed\":1,\"skipped\":1,\"closed\":0,\"total_disbursed\":10000}', '130.193.198.250', '2026-08-06 07:22:04'),
(353, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '130.193.198.250', '2026-08-06 07:22:49'),
(354, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.198.250', '2026-08-06 07:23:01'),
(355, 1, 'DISBURSE_PROFIT', 'deposits', 11, NULL, '{\"date\":\"2026-08-06\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":6000}', '130.193.198.250', '2026-08-06 07:23:48'),
(356, 1, 'DISBURSE_PROFIT', 'deposits', 15, NULL, '{\"date\":\"2026-08-06\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":500}', '130.193.198.250', '2026-08-06 07:24:24'),
(357, 1, 'DISBURSE_PROFIT', 'deposits', 13, NULL, '{\"date\":\"2026-08-06\",\"processed\":1,\"skipped\":0,\"closed\":0,\"total_disbursed\":123}', '130.193.198.250', '2026-08-06 07:24:38'),
(358, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '130.193.198.250', '2026-08-06 07:24:46'),
(359, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.198.250', '2026-08-06 07:24:55'),
(360, 1, 'LOGOUT', 'users', 1, NULL, '{\"username\":\"admin\"}', '130.193.198.250', '2026-08-06 07:25:15'),
(361, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-06 07:25:21'),
(362, 6, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":2}', '130.193.198.250', '2026-08-06 07:27:10'),
(363, 6, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"deposits\",\"rows\":19}', '130.193.198.250', '2026-08-06 07:28:47'),
(364, 6, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"transactions\",\"rows\":5}', '130.193.198.250', '2026-08-06 07:29:37'),
(365, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '130.193.198.250', '2026-08-06 07:30:03'),
(366, 11, 'LOGIN', 'users', 11, NULL, '{\"role\":\"investor\"}', '130.193.198.250', '2026-08-06 07:30:15'),
(367, 11, 'REQUEST_WITHDRAW', 'withdraw_requests', NULL, NULL, '{\"investor_id\":12,\"amount\":300}', '130.193.198.250', '2026-08-06 07:30:27'),
(368, 11, 'LOGOUT', 'users', 11, NULL, '{\"username\":\"alimali\"}', '130.193.198.250', '2026-08-06 07:30:48'),
(369, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-06 07:30:56'),
(370, 6, 'APPROVE_WITHDRAW', 'withdraw_requests', 2, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"note\":\"\"}', '130.193.198.250', '2026-08-06 07:31:12'),
(371, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '130.193.198.250', '2026-08-06 07:32:41'),
(372, 11, 'LOGIN', 'users', 11, NULL, '{\"role\":\"investor\"}', '130.193.198.250', '2026-08-06 07:32:51'),
(373, 11, 'REQUEST_WITHDRAW', 'withdraw_requests', NULL, NULL, '{\"investor_id\":12,\"amount\":250}', '130.193.198.250', '2026-08-06 07:33:08'),
(374, 11, 'LOGOUT', 'users', 11, NULL, '{\"username\":\"alimali\"}', '130.193.198.250', '2026-08-06 07:33:12'),
(375, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-06 07:33:22'),
(376, 6, 'APPROVE_WITHDRAW', 'withdraw_requests', 3, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"note\":\"\"}', '130.193.198.250', '2026-08-06 07:33:30'),
(377, 6, 'PAY_WITHDRAW', 'withdraw_requests', 3, '{\"status\":\"approved\"}', '{\"status\":\"paid\",\"receipt_no\":\"AG-202608-000008\"}', '130.193.198.250', '2026-08-06 07:33:39'),
(378, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '130.193.198.250', '2026-08-06 07:33:43'),
(379, 11, 'LOGIN', 'users', 11, NULL, '{\"role\":\"investor\"}', '130.193.198.250', '2026-08-06 07:33:58'),
(380, 11, 'LOGOUT', 'users', 11, NULL, '{\"username\":\"alimali\"}', '130.193.198.250', '2026-08-06 07:35:15'),
(381, 1, 'CREATE_USER', 'users', 12, NULL, '{\"username\":\"kirkuk\",\"role\":\"staff\"}', '130.193.198.250', '2026-08-06 07:40:59'),
(382, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-06 07:41:20'),
(383, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.200', '2026-08-06 07:55:56'),
(384, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.50', '2026-08-06 07:57:19'),
(385, 12, 'CREATE_INVESTOR', 'investors', 13, NULL, '{\"full_name\":\"جان فالجان\",\"national_id\":\"122588\"}', '188.72.13.50', '2026-08-06 08:00:43'),
(386, 12, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"canmcan\",\"role\":\"investor\",\"investor_id\":13}', '188.72.13.50', '2026-08-06 08:00:43'),
(387, 12, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":3}', '130.193.198.250', '2026-08-06 08:01:52'),
(388, 12, 'CREATE_DEPOSIT', 'deposits', 20, NULL, '{\"investor_id\":13,\"type\":\"3_years\",\"amount\":20000,\"start_date\":\"2026-08-06\",\"end_date\":\"2029-07-21\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000009\"}', '188.72.13.50', '2026-08-06 08:01:59'),
(389, 12, 'ADD_MANUAL_PROFIT', 'deposits', 20, NULL, '{\"amount\":250,\"currency\":\"USD\",\"month\":\"2026-09\",\"anniversary_date\":\"2026-09-06\",\"note\":\"سحب\"}', '188.72.13.50', '2026-08-06 08:06:20'),
(390, 12, 'LOGOUT', 'users', 12, NULL, '{\"username\":\"kirkuk\"}', '188.72.13.50', '2026-08-06 08:09:57'),
(391, 13, 'LOGIN', 'users', 13, NULL, '{\"role\":\"investor\"}', '188.72.13.50', '2026-08-06 08:10:17'),
(392, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '91.106.47.200', '2026-08-06 08:22:42'),
(393, 12, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"deposits\",\"rows\":20}', '130.193.198.250', '2026-08-06 08:23:08'),
(394, 13, 'LOGOUT', 'users', 13, NULL, '{\"username\":\"canmcan\"}', '188.72.13.50', '2026-08-06 08:29:24'),
(395, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.50', '2026-08-06 08:29:36'),
(396, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.200', '2026-08-06 08:31:44'),
(397, 6, 'CREATE_INVESTOR', 'investors', 14, NULL, '{\"full_name\":\"زهراء\",\"national_id\":\"0986543\"}', '91.106.47.200', '2026-08-06 08:34:06'),
(398, 6, 'LOGOUT', 'users', 6, NULL, '{\"username\":\"baghdad\"}', '91.106.47.200', '2026-08-06 08:43:51'),
(399, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.200', '2026-08-06 08:43:58'),
(400, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '185.240.17.83', '2026-08-08 04:02:53'),
(401, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.200', '2026-08-08 04:04:17'),
(402, 7, 'CREATE_INVESTOR', 'investors', 15, NULL, '{\"full_name\":\"ر\",\"national_id\":\"233\"}', '185.240.17.83', '2026-08-08 04:09:51'),
(403, 6, 'CREATE_INVESTOR', 'investors', 16, NULL, '{\"full_name\":\"زهراء عدنان\",\"national_id\":\"096543323\"}', '91.106.47.200', '2026-08-08 04:10:48'),
(404, 6, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"zahraa H\",\"role\":\"investor\",\"investor_id\":16,\"note\":\"Account created during investor edit\"}', '91.106.47.200', '2026-08-08 04:14:12'),
(405, 6, 'UPDATE_INVESTOR', 'investors', 16, '{\"id\":16,\"full_name\":\"زهراء عدنان\",\"phone\":\"07886661998\",\"city\":\"بغداد\",\"address\":\"العامرية\",\"notes\":\"الاستلام كل 6 اشهر\",\"national_id\":\"096543323\",\"contract_path\":\"\",\"id_card_path\":\"\",\"created_at\":\"2026-08-08 04:10:48\",\"updated_at\":\"2026-08-08 04:10:48\"}', '{\"full_name\":\"زهراء عدنان\",\"national_id\":\"096543323\",\"phone\":\"07886661998\",\"city\":\"بغداد\"}', '91.106.47.200', '2026-08-08 04:14:12'),
(406, 6, 'CREATE_DEPOSIT', 'deposits', 21, NULL, '{\"investor_id\":16,\"type\":\"3_years\",\"amount\":5000000,\"start_date\":\"2026-08-08\",\"end_date\":\"2029-07-23\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000010\"}', '91.106.47.200', '2026-08-08 04:18:34'),
(407, 7, 'CREATE_DEPOSIT', 'deposits', 22, NULL, '{\"investor_id\":14,\"type\":\"3_years\",\"amount\":5000,\"start_date\":\"2026-08-08\",\"end_date\":\"2029-07-23\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000011\"}', '185.240.17.83', '2026-08-08 04:18:48'),
(408, 7, 'PAY_WITHDRAW', 'withdraw_requests', 2, '{\"status\":\"approved\"}', '{\"status\":\"paid\",\"receipt_no\":\"AG-202608-000012\"}', '185.240.17.83', '2026-08-08 04:33:44'),
(409, 14, 'LOGIN', 'users', 14, NULL, '{\"role\":\"investor\"}', '91.106.47.200', '2026-08-08 07:46:40'),
(410, 14, 'EXPORT_PDF', 'reports', NULL, NULL, '{\"report\":\"investor_statement\",\"rows\":1}', '91.106.47.200', '2026-08-08 07:47:27'),
(411, 14, 'LOGOUT', 'users', 14, NULL, '{\"username\":\"zahraa H\"}', '91.106.47.200', '2026-08-08 07:54:46'),
(412, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.47.200', '2026-08-08 07:55:03'),
(413, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.60.197', '2026-08-10 04:53:54'),
(414, 6, 'CREATE_DEPOSIT', 'deposits', 23, NULL, '{\"investor_id\":16,\"type\":\"3_years\",\"amount\":4000000,\"start_date\":\"2026-08-10\",\"end_date\":\"2029-07-25\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000013\"}', '91.106.60.197', '2026-08-10 04:54:57'),
(415, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.50', '2026-08-11 02:29:28'),
(416, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-11 03:20:06'),
(417, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.50', '2026-08-11 03:54:32'),
(418, 12, 'CREATE_INVESTOR', 'investors', 17, NULL, '{\"full_name\":\"مصطفى حسن\",\"national_id\":\"199454415485\"}', '188.72.13.50', '2026-08-11 03:59:10'),
(419, 12, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"eng.mustafa\",\"role\":\"investor\",\"investor_id\":17}', '188.72.13.50', '2026-08-11 03:59:10'),
(420, 12, 'CREATE_DEPOSIT', 'deposits', 24, NULL, '{\"investor_id\":17,\"type\":\"3_years\",\"amount\":5000000,\"start_date\":\"2026-08-11\",\"end_date\":\"2029-07-26\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000014\"}', '188.72.13.50', '2026-08-11 04:00:32'),
(421, 12, 'ADD_MANUAL_PROFIT', 'deposits', 24, NULL, '{\"amount\":250000,\"currency\":\"IQD\",\"month\":\"2026-09\",\"anniversary_date\":\"2026-09-11\",\"note\":\"إضافة ربح تراكمي يدوي لشهر 2026-09\"}', '188.72.13.50', '2026-08-11 04:00:59'),
(422, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.60.197', '2026-08-11 05:46:31'),
(423, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '130.193.198.250', '2026-08-11 06:52:18'),
(424, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.61.81', '2026-08-11 16:48:42'),
(425, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-12 04:25:58'),
(426, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.61.81', '2026-08-12 08:57:52'),
(427, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.218', '2026-08-15 07:06:33'),
(428, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.61.81', '2026-08-15 17:17:41'),
(429, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.218', '2026-08-17 11:09:27'),
(430, 12, 'CREATE_INVESTOR', 'investors', 18, NULL, '{\"full_name\":\"محمد سالم عبد القادر\",\"national_id\":\"198398350830\"}', '188.72.13.218', '2026-08-17 11:19:12'),
(431, 12, 'CREATE_USER', 'users', NULL, NULL, '{\"username\":\"Mohammed 1983\",\"role\":\"investor\",\"investor_id\":18}', '188.72.13.218', '2026-08-17 11:19:12'),
(432, 12, 'CREATE_DEPOSIT', 'deposits', 25, NULL, '{\"investor_id\":18,\"type\":\"3_years\",\"amount\":10000000,\"start_date\":\"2026-08-17\",\"end_date\":\"2029-08-01\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000015\"}', '188.72.13.218', '2026-08-17 11:20:19'),
(433, 12, 'LOGOUT', 'users', 12, NULL, '{\"username\":\"kirkuk\"}', '188.72.13.218', '2026-08-17 11:21:41'),
(434, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.218', '2026-08-17 11:25:48'),
(435, 16, 'LOGIN', 'users', 16, NULL, '{\"role\":\"investor\"}', '82.199.215.55', '2026-08-17 11:28:44'),
(436, 12, 'LOGOUT', 'users', 12, NULL, '{\"username\":\"kirkuk\"}', '188.72.13.218', '2026-08-17 11:30:56'),
(437, 12, 'LOGIN', 'users', 12, NULL, '{\"role\":\"staff\"}', '188.72.13.218', '2026-08-17 11:31:39'),
(438, 12, 'LOGOUT', 'users', 12, NULL, '{\"username\":\"kirkuk\"}', '188.72.13.218', '2026-08-17 11:32:09'),
(439, 16, 'LOGIN', 'users', 16, NULL, '{\"role\":\"investor\"}', '188.72.13.218', '2026-08-17 11:32:26'),
(440, 16, 'LOGIN', 'users', 16, NULL, '{\"role\":\"investor\"}', '82.199.215.55', '2026-08-17 12:00:19'),
(441, 16, 'CHANGE_OWN_PASSWORD', 'users', 16, NULL, '{\"description\":\"User changed their own password\"}', '82.199.215.55', '2026-08-17 12:03:49'),
(442, 16, 'LOGOUT', 'users', 16, NULL, '{\"username\":\"Mohammed 1983\"}', '82.199.215.55', '2026-08-17 12:04:12'),
(443, 16, 'LOGIN', 'users', 16, NULL, '{\"role\":\"investor\"}', '82.199.215.55', '2026-08-17 12:05:15'),
(444, 16, 'LOGOUT', 'users', 16, NULL, '{\"username\":\"Mohammed 1983\"}', '82.199.215.55', '2026-08-17 12:05:37'),
(445, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.228', '2026-08-18 02:34:24'),
(446, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.228', '2026-08-18 05:31:22'),
(447, 6, 'CREATE_DEPOSIT', 'deposits', 26, NULL, '{\"investor_id\":18,\"type\":\"3_years\",\"amount\":2000000,\"start_date\":\"2026-08-18\",\"end_date\":\"2029-08-02\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000016\"}', '91.106.46.228', '2026-08-18 05:34:29'),
(448, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-18 06:39:51'),
(449, 7, 'CREATE_INVESTOR', 'investors', 19, NULL, '{\"full_name\":\"محمد\",\"national_id\":\"55555\"}', '130.193.198.250', '2026-08-18 06:42:35'),
(450, 7, 'CREATE_DEPOSIT', 'deposits', 27, NULL, '{\"investor_id\":19,\"type\":\"3_years\",\"amount\":7777,\"start_date\":\"2026-08-18\",\"end_date\":\"2029-08-02\",\"payout_frequency\":1,\"receipt_no\":\"AG-202608-000017\"}', '130.193.198.250', '2026-08-18 06:46:00'),
(451, 6, 'LOGIN', 'users', 6, NULL, '{\"role\":\"staff\"}', '91.106.46.228', '2026-08-18 08:37:15'),
(452, 1, 'LOGIN', 'users', 1, NULL, '{\"role\":\"admin\"}', '185.24.61.81', '2026-08-18 20:37:01'),
(453, 7, 'LOGIN', 'users', 7, NULL, '{\"role\":\"staff\"}', '130.193.198.250', '2026-08-19 04:37:07');

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(10) UNSIGNED NOT NULL,
  `investor_id` int(10) UNSIGNED NOT NULL,
  `deposit_type_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` enum('IQD','USD') NOT NULL DEFAULT 'IQD',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `profit_payout_frequency` int(11) NOT NULL DEFAULT 1,
  `profit_rate_monthly` decimal(8,5) NOT NULL,
  `last_profit_date` date DEFAULT NULL,
  `status` enum('active','completed','cancelled','defaulted') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accumulated_profit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `last_withdrawal_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `investor_id`, `deposit_type_id`, `amount`, `currency`, `start_date`, `end_date`, `profit_payout_frequency`, `profit_rate_monthly`, `last_profit_date`, `status`, `created_at`, `updated_at`, `accumulated_profit`, `last_withdrawal_date`) VALUES
(1, 1, 1, 50000.00, 'IQD', '2025-12-21', '2026-01-20', 1, 0.03000, NULL, 'completed', '2026-03-01 22:35:49', '2026-04-01 15:37:40', 0.00, '2026-04-01'),
(2, 1, 2, 100000.00, 'IQD', '2025-11-26', '2026-08-23', 1, 0.03800, '2026-06-26', 'active', '2026-03-01 22:35:49', '2026-07-26 22:48:06', 0.00, '2026-04-26'),
(3, 2, 3, 200000.00, 'IQD', '2026-01-25', '2027-01-20', 1, 0.04500, '2026-06-25', 'active', '2026-03-01 22:35:49', '2026-07-26 22:48:06', 0.00, '2026-04-25'),
(4, 2, 1, 30000.00, 'IQD', '2026-02-01', '2026-03-03', 1, 0.03000, '2026-04-01', 'completed', '2026-03-01 22:35:49', '2026-04-01 15:37:06', 0.00, '2026-04-01'),
(5, 3, 2, 75000.00, 'IQD', '2026-02-26', '2026-05-27', 1, 0.03800, '2026-03-26', 'completed', '2026-03-01 22:35:49', '2026-06-17 08:11:42', 0.00, '2026-06-17'),
(6, 4, 4, 10000.00, 'USD', '2026-03-01', '2029-02-13', 1, 0.00000, '2026-06-01', 'active', '2026-03-01 22:57:27', '2026-06-27 07:51:24', 0.00, '2026-05-01'),
(7, 4, 4, 10000.00, 'USD', '2026-01-01', '2028-12-16', 1, 0.00000, '2026-06-01', 'active', '2026-03-01 23:00:31', '2026-07-26 22:48:06', 0.00, '2026-04-01'),
(8, 4, 4, 10000.00, 'USD', '2026-03-05', '2029-02-17', 1, 0.00000, '2026-06-05', 'active', '2026-03-05 12:36:05', '2026-07-26 22:48:06', 0.00, '2026-04-05'),
(9, 4, 1, 1000.00, 'USD', '2026-03-05', '2026-09-01', 1, 0.00000, '2026-06-05', 'active', '2026-03-05 15:32:13', '2026-07-26 22:48:06', 0.00, '2026-04-05'),
(10, 4, 4, 10000.00, 'USD', '2026-03-11', '2029-02-23', 2, 0.00000, '2026-06-11', 'active', '2026-03-11 12:23:15', '2026-06-27 07:58:54', 0.00, '2026-05-11'),
(11, 1, 4, 5000.00, 'USD', '2026-02-01', '2029-01-16', 1, 0.00000, '2026-06-01', 'active', '2026-03-11 12:25:53', '2026-08-06 07:23:48', 0.00, '2026-07-01'),
(12, 3, 2, 1200000.00, 'IQD', '2025-02-11', '2026-02-06', 6, 0.00000, '2025-03-11', 'completed', '2026-03-11 12:58:35', '2026-03-11 13:33:03', 0.00, '2026-03-11'),
(13, 7, 4, 10000.00, 'USD', '2026-04-01', '2029-03-16', 1, 0.00000, '2026-06-01', 'active', '2026-04-28 13:53:14', '2026-08-06 07:24:38', 0.00, '2026-07-01'),
(14, 4, 4, 100000.00, 'USD', '2026-06-17', '2029-06-01', 2, 0.00000, '2026-08-17', 'active', '2026-06-17 08:09:20', '2026-07-26 22:55:17', 55000.00, NULL),
(15, 8, 4, 10000.00, 'USD', '2026-05-01', '2029-04-15', 1, 0.00000, '2026-06-01', 'active', '2026-06-20 08:34:51', '2026-08-06 07:24:24', 0.00, '2026-08-01'),
(16, 9, 2, 1500.00, 'IQD', '2026-05-23', '2027-05-18', 12, 0.00000, '2026-06-23', 'active', '2026-06-23 06:18:23', '2026-07-26 22:56:51', 0.00, '2026-06-23'),
(17, 10, 2, 5000000.00, 'IQD', '2026-05-01', '2027-04-26', 1, 0.00000, '2026-08-01', 'active', '2026-06-27 07:43:10', '2026-08-06 07:22:04', 0.00, '2026-08-01'),
(18, 4, 4, 10000000.00, 'USD', '2026-08-01', '2029-07-16', 12, 0.00000, NULL, 'active', '2026-08-01 11:59:57', '2026-08-01 11:59:57', 0.00, NULL),
(19, 12, 4, 10000.00, 'USD', '2026-07-06', '2029-06-20', 1, 0.00000, NULL, 'active', '2026-08-06 07:10:13', '2026-08-06 07:21:23', 0.00, '2026-08-06'),
(20, 13, 4, 20000.00, 'USD', '2026-08-06', '2029-07-21', 1, 0.00000, '2026-09-06', 'active', '2026-08-06 08:01:59', '2026-08-06 08:06:20', 250.00, NULL),
(21, 16, 4, 5000000.00, 'IQD', '2026-08-08', '2029-07-23', 1, 0.00000, NULL, 'active', '2026-08-08 04:18:34', '2026-08-08 04:18:34', 0.00, NULL),
(22, 14, 4, 5000.00, 'IQD', '2026-08-08', '2029-07-23', 1, 0.00000, NULL, 'active', '2026-08-08 04:18:48', '2026-08-08 04:18:48', 0.00, NULL),
(23, 16, 4, 4000000.00, 'IQD', '2026-08-10', '2029-07-25', 1, 0.00000, NULL, 'active', '2026-08-10 04:54:57', '2026-08-10 04:54:57', 0.00, NULL),
(24, 17, 4, 5000000.00, 'IQD', '2026-08-11', '2029-07-26', 1, 0.00000, '2026-09-11', 'active', '2026-08-11 04:00:32', '2026-08-11 04:00:59', 250000.00, NULL),
(25, 18, 4, 10000000.00, 'IQD', '2026-08-17', '2029-08-01', 1, 0.00000, NULL, 'active', '2026-08-17 11:20:19', '2026-08-17 11:20:19', 0.00, NULL),
(26, 18, 4, 2000000.00, 'IQD', '2026-08-18', '2029-08-02', 1, 0.00000, NULL, 'active', '2026-08-18 05:34:29', '2026-08-18 05:34:29', 0.00, NULL),
(27, 19, 4, 7777.00, 'USD', '2026-08-18', '2029-08-02', 1, 0.00000, NULL, 'active', '2026-08-18 06:46:00', '2026-08-18 06:46:00', 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `deposit_types`
--

CREATE TABLE `deposit_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `profit_rate_monthly` decimal(8,5) NOT NULL DEFAULT 0.03000,
  `min_days` int(11) NOT NULL DEFAULT 30,
  `max_days` int(11) NOT NULL DEFAULT 30,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `min_rate` decimal(8,5) NOT NULL DEFAULT 0.00000,
  `max_rate` decimal(8,5) NOT NULL DEFAULT 0.00000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposit_types`
--

INSERT INTO `deposit_types` (`id`, `name_ar`, `code`, `profit_rate_monthly`, `min_days`, `max_days`, `created_at`, `updated_at`, `min_rate`, `max_rate`) VALUES
(1, 'وديعة 6 أشهر', '6_months', 0.02800, 180, 180, '2026-03-01 22:35:49', '2026-03-01 22:47:33', 0.02800, 0.03300),
(2, 'وديعة سنة', '1_year', 0.03500, 360, 360, '2026-03-01 22:35:49', '2026-03-01 22:47:33', 0.03500, 0.03900),
(3, 'وديعة سنتين', '2_years', 0.04200, 720, 720, '2026-03-01 22:35:49', '2026-03-01 22:47:33', 0.04200, 0.04900),
(4, 'وديعة 3 سنين', '3_years', 0.05400, 1080, 1080, '2026-03-01 22:35:49', '2026-03-01 22:47:33', 0.05400, 0.06300);

-- --------------------------------------------------------

--
-- Table structure for table `investors`
--

CREATE TABLE `investors` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `city` varchar(100) NOT NULL DEFAULT '',
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `national_id` varchar(30) NOT NULL DEFAULT '',
  `contract_path` varchar(255) DEFAULT NULL,
  `id_card_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investors`
--

INSERT INTO `investors` (`id`, `full_name`, `phone`, `city`, `address`, `notes`, `national_id`, `contract_path`, `id_card_path`, `created_at`, `updated_at`) VALUES
(1, 'أحمد محمد العسافي', '0501234561', 'الرياض', NULL, NULL, '1012345671', NULL, NULL, '2026-03-01 22:35:49', '2026-03-01 22:35:49'),
(2, 'فاطمة علي الزهراني', '0501234562', 'جدة', NULL, NULL, '1012345672', NULL, NULL, '2026-03-01 22:35:49', '2026-03-01 22:35:49'),
(3, 'خالد عمر القحطاني', '0501234563', 'الدمام', NULL, NULL, '1012345673', NULL, NULL, '2026-03-01 22:35:49', '2026-03-01 22:35:49'),
(4, 'Ali Mohamad Ali', '888', 'أربيل', 'jjhjhj', '', '920i', '', '', '2026-03-01 22:53:02', '2026-03-01 22:53:02'),
(5, 'mohanned ismael azeez', '07516977970', 'أربيل', 'Erbi, Gulan', '', '88899', '', '', '2026-03-01 22:53:23', '2026-03-01 22:53:23'),
(6, 'mohammed', '8776', 'أربيل', '', '', '09887', 'uploads/investors/inv_69b132a2b7e6f1.79242076.pdf', 'uploads/investors/inv_69b132a2b83555.62854913.pdf', '2026-03-11 12:15:14', '2026-03-11 12:15:14'),
(7, 'مراد علي سليم', '٠٧٧٣١١١١٨٩٨', 'كركوك', 'محافظة', '', '٢٢٨٣٨٨٩٩٢٣', '', '', '2026-04-28 13:52:51', '2026-04-28 13:52:51'),
(8, 'اناغيم فهيم طراف', '076656', 'أربيل', 'كواترو', 'عميلة نمبر 1', '5576', 'uploads/investors/inv_6a3688969bf8f1.53008295.pdf', 'uploads/investors/inv_6a3688969c3424.91699347.pdf', '2026-06-20 08:33:26', '2026-06-20 08:33:26'),
(9, 'AL-ASAFY GROUP', '', 'أربيل', '', '', '55557676', '', '', '2026-06-23 06:16:50', '2026-06-23 06:16:50'),
(10, 'mohamad', '', 'أربيل', '', '', '0986', 'uploads/investors/inv_6a3fb4a79beeb4.98759932.pdf', 'uploads/investors/inv_6a3fb4a79d6241.33076220.pdf', '2026-06-27 07:31:51', '2026-06-27 07:31:51'),
(11, 'zahraa', '078654567', 'بغداد', 'العامرية', '', '097643344', 'uploads/investors/inv_6a40e79070e0e9.61467867.pdf', '', '2026-06-28 05:21:20', '2026-06-28 05:21:20'),
(12, 'علي محمد علي', '123456', 'كركوك', 'كركوك شارع المحافظة', 'مستثمر VIP', '12345', 'uploads/investors/inv_6a746a3ecab561.03223294.pdf', 'uploads/investors/inv_6a746a3ecaf501.69005184.pdf', '2026-08-06 07:04:30', '2026-08-06 07:04:30'),
(13, 'جان فالجان', '07733888994', 'كركوك', 'تسعين', 'مستثمر مهم', '122588', 'uploads/investors/inv_6a74776b435930.72105880.pdf', 'uploads/investors/inv_6a74776b43ac15.22489418.pdf', '2026-08-06 08:00:43', '2026-08-06 08:00:43'),
(14, 'زهراء', '07886661997', 'بغداد', 'بغداد', '', '0986543', '', '', '2026-08-06 08:34:06', '2026-08-06 08:34:06'),
(15, 'ر', '556551', 'أربيل', 'كواترو', '', '233', '', '', '2026-08-08 04:09:51', '2026-08-08 04:09:51'),
(16, 'زهراء عدنان', '07886661998', 'بغداد', 'العامرية', 'الاستلام كل 6 اشهر', '096543323', '', '', '2026-08-08 04:10:48', '2026-08-08 04:10:48'),
(17, 'مصطفى حسن', '07700000000', 'كركوك', 'طريق بغداد', '', '199454415485', 'uploads/investors/inv_6a7ad64e27c3e6.21621468.pdf', 'uploads/investors/inv_6a7ad64e285181.89559104.png', '2026-08-11 03:59:10', '2026-08-11 03:59:10'),
(18, 'محمد سالم عبد القادر', '07702325299', 'كركوك', 'حي عدن', '', '198398350830', 'uploads/investors/inv_6a83267045fa94.41844967.pdf', 'uploads/investors/inv_6a8326704702b0.97702189.pdf', '2026-08-17 11:19:12', '2026-08-17 11:19:12'),
(19, 'محمد', '55555', 'أربيل', 'ااا', '', '55555', 'uploads/investors/inv_6a84371b93db89.35031032.pdf', 'uploads/investors/inv_6a84371b943648.46085526.pdf', '2026-08-18 06:42:35', '2026-08-18 06:42:35');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_rates`
--

CREATE TABLE `monthly_rates` (
  `id` int(10) UNSIGNED NOT NULL,
  `month` varchar(7) NOT NULL,
  `deposit_type_id` int(10) UNSIGNED NOT NULL,
  `rate_percent` decimal(8,5) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `monthly_rates`
--

INSERT INTO `monthly_rates` (`id`, `month`, `deposit_type_id`, `rate_percent`, `created_at`) VALUES
(53, '2026-03', 1, 0.03000, '2026-03-01 23:59:57'),
(54, '2026-03', 2, 0.03700, '2026-03-01 23:59:57'),
(55, '2026-03', 3, 0.04500, '2026-03-01 23:59:57'),
(56, '2026-03', 4, 0.06000, '2026-03-01 23:59:57'),
(57, '2026-02', 1, 0.03000, '2026-03-02 00:01:55'),
(58, '2026-02', 2, 0.03700, '2026-03-02 00:01:55'),
(59, '2026-02', 3, 0.04500, '2026-03-02 00:01:55'),
(60, '2026-02', 4, 0.06000, '2026-03-02 00:01:55'),
(73, '2025-12', 1, 0.03000, '2026-03-02 00:05:59'),
(74, '2025-12', 2, 0.03700, '2026-03-02 00:05:59'),
(75, '2025-12', 3, 0.04500, '2026-03-02 00:06:00'),
(76, '2025-12', 4, 0.06000, '2026-03-02 00:06:00'),
(89, '2026-01', 1, 0.03000, '2026-03-02 00:13:13'),
(90, '2026-01', 2, 0.03700, '2026-03-02 00:13:13'),
(91, '2026-01', 3, 0.04500, '2026-03-02 00:13:13'),
(92, '2026-01', 4, 0.06000, '2026-03-02 00:13:13'),
(161, '2025-02', 1, 0.03000, '2026-03-11 13:00:01'),
(162, '2025-02', 2, 0.03700, '2026-03-11 13:00:01'),
(163, '2025-02', 3, 0.04500, '2026-03-11 13:00:01'),
(164, '2025-02', 4, 0.06000, '2026-03-11 13:00:01'),
(169, '2025-03', 1, 0.03000, '2026-03-11 13:00:06'),
(170, '2025-03', 2, 0.03700, '2026-03-11 13:00:06'),
(171, '2025-03', 3, 0.04500, '2026-03-11 13:00:06'),
(172, '2025-03', 4, 0.06000, '2026-03-11 13:00:06'),
(177, '2025-04', 1, 0.03000, '2026-03-11 13:00:41'),
(178, '2025-04', 2, 0.03700, '2026-03-11 13:00:41'),
(179, '2025-04', 3, 0.04500, '2026-03-11 13:00:41'),
(180, '2025-04', 4, 0.06000, '2026-03-11 13:00:41'),
(193, '2025-05', 1, 0.03000, '2026-03-11 13:01:04'),
(194, '2025-05', 2, 0.03700, '2026-03-11 13:01:04'),
(195, '2025-05', 3, 0.04500, '2026-03-11 13:01:04'),
(196, '2025-05', 4, 0.06000, '2026-03-11 13:01:04'),
(201, '2025-06', 1, 0.03000, '2026-03-11 13:01:08'),
(202, '2025-06', 2, 0.03700, '2026-03-11 13:01:08'),
(203, '2025-06', 3, 0.04500, '2026-03-11 13:01:08'),
(204, '2025-06', 4, 0.06000, '2026-03-11 13:01:08'),
(209, '2025-07', 1, 0.03000, '2026-03-11 13:01:14'),
(210, '2025-07', 2, 0.03700, '2026-03-11 13:01:14'),
(211, '2025-07', 3, 0.04500, '2026-03-11 13:01:14'),
(212, '2025-07', 4, 0.06000, '2026-03-11 13:01:14'),
(217, '2025-08', 1, 0.03000, '2026-03-11 13:01:18'),
(218, '2025-08', 2, 0.03700, '2026-03-11 13:01:18'),
(219, '2025-08', 3, 0.04500, '2026-03-11 13:01:18'),
(220, '2025-08', 4, 0.06000, '2026-03-11 13:01:18'),
(225, '2026-06', 4, 0.06000, '2026-06-03 07:09:21'),
(226, '2026-05', 4, 0.06000, '2026-06-03 07:09:38'),
(228, '2026-06', 1, 0.03000, '2026-06-17 08:10:18'),
(229, '2026-06', 2, 0.03700, '2026-06-17 08:10:18'),
(230, '2026-06', 3, 0.04500, '2026-06-17 08:10:18'),
(236, '2026-04', 1, 0.03000, '2026-06-27 07:54:05'),
(237, '2026-04', 2, 0.03700, '2026-06-27 07:54:05'),
(238, '2026-04', 3, 0.04500, '2026-06-27 07:54:05'),
(239, '2026-04', 4, 0.06000, '2026-06-27 07:54:05'),
(244, '2026-05', 1, 0.03000, '2026-06-27 07:56:53'),
(245, '2026-05', 2, 0.03700, '2026-06-27 07:56:53'),
(246, '2026-05', 3, 0.04500, '2026-06-27 07:56:53');

-- --------------------------------------------------------

--
-- Table structure for table `profit_cycles`
--

CREATE TABLE `profit_cycles` (
  `id` int(10) UNSIGNED NOT NULL,
  `deposit_id` int(10) UNSIGNED NOT NULL,
  `cycle_date` date NOT NULL,
  `processed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profit_cycles`
--

INSERT INTO `profit_cycles` (`id`, `deposit_id`, `cycle_date`, `processed_at`) VALUES
(31, 4, '2025-12-31', '2026-03-02 00:05:59'),
(32, 2, '2025-12-31', '2026-03-02 00:06:00'),
(33, 5, '2025-12-31', '2026-03-02 00:06:00'),
(34, 3, '2025-12-31', '2026-03-02 00:06:00'),
(35, 6, '2025-12-31', '2026-03-02 00:06:00'),
(36, 7, '2025-12-31', '2026-03-02 00:06:00'),
(55, 4, '2026-01-31', '2026-03-02 00:13:13'),
(56, 2, '2026-01-31', '2026-03-02 00:13:13'),
(57, 5, '2026-01-31', '2026-03-02 00:13:13'),
(58, 3, '2026-01-31', '2026-03-02 00:13:13'),
(59, 6, '2026-01-31', '2026-03-02 00:13:13'),
(60, 7, '2026-01-31', '2026-03-02 00:13:13'),
(127, 2, '2026-02-28', '2026-03-02 00:30:08'),
(128, 2, '2026-03-31', '2026-03-11 12:29:06'),
(129, 11, '2026-03-31', '2026-03-11 12:29:06'),
(130, 12, '2025-03-31', '2026-03-11 13:00:06'),
(131, 6, '2026-05-31', '2026-06-03 07:09:38'),
(132, 13, '2026-05-31', '2026-06-03 07:09:38'),
(133, 6, '2026-06-30', '2026-06-17 08:10:18'),
(134, 13, '2026-06-30', '2026-06-17 08:10:18'),
(135, 16, '2026-06-30', '2026-06-27 07:47:09'),
(136, 17, '2026-06-30', '2026-06-27 07:47:09'),
(137, 15, '2026-06-30', '2026-06-27 07:47:09'),
(138, 9, '2026-04-30', '2026-06-27 07:54:05'),
(139, 2, '2026-04-30', '2026-06-27 07:54:05'),
(140, 3, '2026-04-30', '2026-06-27 07:54:05'),
(141, 7, '2026-04-30', '2026-06-27 07:54:05'),
(142, 8, '2026-04-30', '2026-06-27 07:54:05'),
(143, 10, '2026-04-30', '2026-06-27 07:54:05'),
(144, 11, '2026-04-30', '2026-06-27 07:54:05'),
(145, 9, '2026-05-31', '2026-06-27 07:56:53'),
(146, 2, '2026-05-31', '2026-06-27 07:56:53'),
(147, 3, '2026-05-31', '2026-06-27 07:56:53'),
(148, 7, '2026-05-31', '2026-06-27 07:56:53'),
(149, 8, '2026-05-31', '2026-06-27 07:56:53'),
(150, 10, '2026-05-31', '2026-06-27 07:56:53'),
(151, 11, '2026-05-31', '2026-06-27 07:56:53'),
(152, 9, '2026-06-30', '2026-06-27 07:58:00'),
(153, 2, '2026-06-30', '2026-06-27 07:58:00'),
(154, 3, '2026-06-30', '2026-06-27 07:58:00'),
(155, 7, '2026-06-30', '2026-06-27 07:58:00'),
(156, 8, '2026-06-30', '2026-06-27 07:58:00'),
(157, 10, '2026-06-30', '2026-06-27 07:58:00'),
(158, 11, '2026-06-30', '2026-06-27 07:58:00'),
(159, 17, '2026-07-31', '2026-07-26 22:48:29'),
(160, 17, '2026-08-31', '2026-07-26 22:48:39'),
(161, 14, '2026-07-31', '2026-07-26 22:55:01'),
(162, 14, '2026-08-31', '2026-07-26 22:55:17'),
(163, 20, '2026-09-30', '2026-08-06 08:06:20'),
(164, 24, '2026-09-30', '2026-08-11 04:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `receipt_no` varchar(30) NOT NULL,
  `investor_id` int(10) UNSIGNED NOT NULL,
  `deposit_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('deposit','profit','withdraw') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` enum('IQD','USD') NOT NULL DEFAULT 'IQD',
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `receipt_no`, `investor_id`, `deposit_id`, `type`, `amount`, `currency`, `date`, `note`, `created_at`) VALUES
(1, 'AG-202512-000001', 1, 1, 'deposit', 50000.00, 'IQD', '2025-12-21 22:35:49', 'وديعة قصيرة - إيداع أولي', '2026-03-01 22:35:49'),
(2, 'AG-202601-000001', 1, 1, 'profit', 1500.00, 'IQD', '2026-01-20 22:35:49', 'ربح شهري - وديعة 1', '2026-03-01 22:35:49'),
(3, 'AG-202603-000001', 4, 6, 'deposit', 10000.00, 'USD', '2026-03-01 22:57:27', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-03-01 22:57:27'),
(4, 'AG-202603-000002', 4, 7, 'deposit', 10000.00, 'USD', '2026-03-01 23:00:31', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-03-01 23:00:31'),
(5, 'AG-202603-000003', 4, 7, 'profit', 600.00, 'USD', '2026-03-01 23:05:38', 'صرف أرباح تراكمية مستحقة', '2026-03-01 23:05:38'),
(6, 'AG-202603-000004', 1, 2, 'profit', 3700.00, 'IQD', '2026-03-01 23:05:57', 'صرف أرباح تراكمية مستحقة', '2026-03-01 23:05:57'),
(7, 'AG-202603-000005', 2, 3, 'profit', 9000.00, 'IQD', '2026-03-01 23:05:57', 'صرف أرباح تراكمية مستحقة', '2026-03-01 23:05:57'),
(8, 'AG-202603-000006', 2, 4, 'profit', 900.00, 'IQD', '2026-03-01 23:05:57', 'صرف أرباح تراكمية مستحقة', '2026-03-01 23:05:57'),
(9, 'AG-202603-000007', 4, 7, 'profit', 600.00, 'USD', '2026-03-02 00:03:50', 'صرف أرباح تراكمية مستحقة', '2026-03-02 00:03:50'),
(10, 'AG-202603-000008', 1, 2, 'profit', 3700.00, 'IQD', '2026-03-02 00:07:10', 'صرف أرباح تراكمية مستحقة', '2026-03-02 00:07:10'),
(11, 'AG-202603-000009', 1, 2, 'profit', 3700.00, 'IQD', '2026-03-02 00:30:40', 'صرف أرباح تراكمية مستحقة', '2026-03-02 00:30:40'),
(12, 'AG-202603-000010', 4, 8, 'deposit', 10000.00, 'USD', '2026-03-05 12:36:05', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-03-05 12:36:05'),
(13, 'AG-202603-000011', 4, 9, 'deposit', 1000.00, 'USD', '2026-03-05 15:32:13', 'إيداع جديد — وديعة وديعة 6 أشهر', '2026-03-05 15:32:13'),
(14, 'AG-202605-000001', 4, 6, 'profit', 600.00, 'USD', '2026-05-05 15:34:44', 'صرف أرباح تراكمية مستحقة', '2026-05-05 15:34:44'),
(15, 'AG-202605-000002', 2, 3, 'profit', 9000.00, 'IQD', '2026-05-05 15:34:55', 'صرف أرباح تراكمية مستحقة', '2026-05-05 15:34:55'),
(16, 'AG-202605-000003', 3, 5, 'profit', 5550.00, 'IQD', '2026-05-05 15:34:55', 'صرف أرباح تراكمية مستحقة', '2026-05-05 15:34:55'),
(17, 'AG-202603-000012', 4, 10, 'deposit', 10000.00, 'USD', '2026-03-11 12:23:15', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-03-11 12:23:15'),
(18, 'AG-202603-000013', 1, 11, 'deposit', 5000.00, 'USD', '2026-03-11 12:25:53', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-03-11 12:25:53'),
(19, 'AG-202603-000014', 1, 11, 'profit', 300.00, 'USD', '2026-03-11 12:29:31', 'صرف أرباح تراكمية مستحقة', '2026-03-11 12:29:31'),
(20, 'AG-202603-000015', 3, 12, 'deposit', 1200000.00, 'IQD', '2026-03-11 12:58:35', 'إيداع جديد — وديعة وديعة سنة', '2026-03-11 12:58:35'),
(21, 'AG-202603-000016', 3, 12, 'profit', 44400.00, 'IQD', '2026-03-11 13:25:01', 'صرف أرباح تراكمية مستحقة', '2026-03-11 13:25:01'),
(22, 'AG-202603-000017', 3, 12, 'withdraw', 1200000.00, 'IQD', '2026-03-11 13:33:03', 'إرجاع رأس المال وإنهاء الوديعة', '2026-03-11 13:33:03'),
(23, 'AG-202604-000001', 1, 2, 'profit', 3700.00, 'IQD', '2026-04-01 15:36:58', 'صرف أرباح تراكمية مستحقة', '2026-04-01 15:36:58'),
(24, 'AG-202604-000002', 2, 4, 'profit', 900.00, 'IQD', '2026-04-01 15:37:06', 'صرف أرباح تراكمية مستحقة', '2026-04-01 15:37:06'),
(25, 'AG-202604-000003', 2, 4, 'withdraw', 30000.00, 'IQD', '2026-04-01 15:37:32', 'إرجاع رأس المال وإنهاء الوديعة', '2026-04-01 15:37:32'),
(26, 'AG-202604-000004', 1, 1, 'withdraw', 50000.00, 'IQD', '2026-04-01 15:37:40', 'إرجاع رأس المال وإنهاء الوديعة', '2026-04-01 15:37:40'),
(27, 'AG-202604-000005', 7, 13, 'deposit', 10000.00, 'USD', '2026-04-28 13:53:14', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-04-28 13:53:14'),
(28, 'AG-202604-000006', 1, NULL, 'withdraw', 1200.00, 'IQD', '2026-04-28 13:55:56', 'صرف طلب سحب #1', '2026-04-28 13:55:56'),
(29, 'AG-202606-000001', 7, 13, 'profit', 560.00, 'USD', '2026-06-03 07:10:28', 'صرف أرباح تراكمية مستحقة', '2026-06-03 07:10:28'),
(30, 'AG-202606-000002', 4, 14, 'deposit', 100000.00, 'USD', '2026-06-17 08:09:20', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-06-17 08:09:20'),
(31, 'AG-202606-000003', 7, 13, 'profit', 600.00, 'USD', '2026-06-17 08:10:45', 'صرف أرباح تراكمية مستحقة', '2026-06-17 08:10:45'),
(32, 'AG-202606-000004', 3, 5, 'withdraw', 75000.00, 'IQD', '2026-06-17 08:11:42', 'إرجاع رأس المال وإنهاء الوديعة', '2026-06-17 08:11:42'),
(33, 'AG-202606-000005', 8, 15, 'deposit', 10000.00, 'USD', '2026-06-20 08:34:51', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-06-20 08:34:51'),
(34, 'AG-202606-000006', 9, 16, 'deposit', 1500.00, 'USD', '2026-06-23 06:18:23', 'إيداع جديد — وديعة وديعة سنة', '2026-06-23 06:18:23'),
(35, 'AG-202606-000007', 10, 17, 'deposit', 5000000.00, 'IQD', '2026-06-27 07:43:10', 'إيداع جديد — وديعة وديعة سنة', '2026-06-27 07:43:10'),
(36, 'AG-202606-000008', 10, 17, 'profit', 185000.00, 'IQD', '2026-06-27 07:48:09', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:48:09'),
(37, 'AG-202606-000009', 4, 6, 'profit', 1160.00, 'USD', '2026-06-27 07:51:24', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:51:24'),
(38, 'AG-202606-000010', 8, 15, 'profit', 600.00, 'USD', '2026-06-27 07:51:24', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:51:24'),
(39, 'AG-202606-000011', 9, 16, 'profit', 55.50, 'USD', '2026-06-27 07:51:24', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:51:24'),
(40, 'AG-202606-000012', 1, 11, 'profit', 300.00, 'USD', '2026-06-27 07:55:59', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:55:59'),
(41, 'AG-202606-000013', 1, 11, 'profit', 300.00, 'USD', '2026-06-27 07:57:26', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:57:26'),
(42, 'AG-202606-000014', 1, 11, 'profit', 300.00, 'USD', '2026-06-27 07:58:11', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:58:11'),
(43, 'AG-202606-000015', 4, 10, 'profit', 1800.00, 'USD', '2026-06-27 07:58:54', 'صرف أرباح تراكمية مستحقة', '2026-06-27 07:58:54'),
(44, 'AG-202607-000001', 10, 17, 'profit', 12000.00, 'IQD', '2026-07-26 22:47:58', 'صرف أرباح تراكمية مستحقة', '2026-07-26 22:47:58'),
(45, 'AG-202607-000002', 1, 2, 'profit', 11100.00, 'IQD', '2026-07-26 22:48:06', 'صرف أرباح تراكمية مستحقة', '2026-07-26 22:48:06'),
(46, 'AG-202607-000003', 2, 3, 'profit', 27000.00, 'IQD', '2026-07-26 22:48:06', 'صرف أرباح تراكمية مستحقة', '2026-07-26 22:48:06'),
(47, 'AG-202607-000004', 4, 7, 'profit', 1800.00, 'USD', '2026-07-26 22:48:06', 'صرف أرباح تراكمية مستحقة', '2026-07-26 22:48:06'),
(48, 'AG-202607-000005', 4, 8, 'profit', 1800.00, 'USD', '2026-07-26 22:48:06', 'صرف أرباح تراكمية مستحقة', '2026-07-26 22:48:06'),
(49, 'AG-202607-000006', 4, 9, 'profit', 90.00, 'USD', '2026-07-26 22:48:06', 'صرف أرباح تراكمية مستحقة', '2026-07-26 22:48:06'),
(50, 'AG-202607-000007', 8, 15, 'profit', 10.00, 'USD', '2026-07-26 22:55:37', 'صرف أرباح تراكمية مستحقة', '2026-07-26 22:55:37'),
(51, 'AG-202608-000001', 4, 18, 'deposit', 10000000.00, 'USD', '2026-08-01 11:59:57', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-01 11:59:57'),
(52, 'AG-202608-000002', 12, 19, 'deposit', 10000.00, 'USD', '2026-08-06 07:10:13', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-06 07:10:13'),
(53, 'AG-202608-000003', 12, 19, 'profit', 250.00, 'USD', '2026-08-06 07:21:23', 'صرف أرباح', '2026-08-06 07:21:23'),
(54, 'AG-202608-000004', 10, 17, 'profit', 10000.00, 'IQD', '2026-08-06 07:22:04', 'صرف أرباح تراكمية مستحقة', '2026-08-06 07:22:04'),
(55, 'AG-202608-000005', 1, 11, 'profit', 6000.00, 'USD', '2026-08-06 07:23:48', 'صرف أرباح تراكمية مستحقة', '2026-08-06 07:23:48'),
(56, 'AG-202608-000006', 8, 15, 'profit', 500.00, 'USD', '2026-08-06 07:24:24', 'صرف أرباح تراكمية مستحقة', '2026-08-06 07:24:24'),
(57, 'AG-202608-000007', 7, 13, 'profit', 123.00, 'USD', '2026-08-06 07:24:38', 'صرف أرباح تراكمية مستحقة', '2026-08-06 07:24:38'),
(58, 'AG-202608-000008', 12, NULL, 'withdraw', 250.00, 'USD', '2026-08-06 07:33:39', 'صرف طلب سحب #3', '2026-08-06 07:33:39'),
(59, 'AG-202608-000009', 13, 20, 'deposit', 20000.00, 'USD', '2026-08-06 08:01:59', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-06 08:01:59'),
(60, 'AG-202608-000010', 16, 21, 'deposit', 5000000.00, 'IQD', '2026-08-08 04:18:34', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-08 04:18:34'),
(61, 'AG-202608-000011', 14, 22, 'deposit', 5000.00, 'IQD', '2026-08-08 04:18:48', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-08 04:18:48'),
(62, 'AG-202608-000012', 12, NULL, 'withdraw', 300.00, 'USD', '2026-08-08 04:33:44', 'صرف طلب سحب #2', '2026-08-08 04:33:44'),
(63, 'AG-202608-000013', 16, 23, 'deposit', 4000000.00, 'IQD', '2026-08-10 04:54:57', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-10 04:54:57'),
(64, 'AG-202608-000014', 17, 24, 'deposit', 5000000.00, 'IQD', '2026-08-11 04:00:32', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-11 04:00:32'),
(65, 'AG-202608-000015', 18, 25, 'deposit', 10000000.00, 'IQD', '2026-08-17 11:20:19', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-17 11:20:19'),
(66, 'AG-202608-000016', 18, 26, 'deposit', 2000000.00, 'IQD', '2026-08-18 05:34:29', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-18 05:34:29'),
(67, 'AG-202608-000017', 19, 27, 'deposit', 7777.00, 'USD', '2026-08-18 06:46:00', 'إيداع جديد — وديعة وديعة 3 سنين', '2026-08-18 06:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `investor_id` int(10) UNSIGNED DEFAULT NULL,
  `role` enum('admin','staff','investor') NOT NULL DEFAULT 'investor',
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `investor_id`, `role`, `username`, `password_hash`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, NULL, 'admin', 'admin', '$2y$10$shmpLnc0mUFdaCF.B6nGMOrnPx16FtSodhQsz.VhPBv4YtacqzslK', '2026-08-18 20:37:01', '2026-03-01 22:35:49', '2026-08-18 20:37:01'),
(2, NULL, 'staff', 'staff', '$2y$10$NGwuKzHRU9eEDf4VYQODy.6YRvCGhHIv/F/0tAAN3m8dm26tplc.e', '2026-03-11 12:34:53', '2026-03-01 22:35:49', '2026-03-11 12:34:53'),
(3, 1, 'investor', 'investor1', '$2y$10$zQiafXXAEDDUeEEE6vX28u8u4ddEb3aRMIyTBWxcXvy5tXbR97WPq', '2026-03-11 12:40:59', '2026-03-01 22:35:49', '2026-03-11 12:40:59'),
(4, NULL, 'admin', 'mohammed', '$2y$10$rKktd0reG8qsV3KAoKWmc.rEnystiRQGIaoBdQVD5h4apvHxYTnNa', '2026-06-03 06:19:40', '2026-04-28 05:29:17', '2026-06-03 06:19:40'),
(5, 7, 'investor', 'muradali', '$2y$12$AxS5ra56bu/bzlw3BLl4fu8g/BpsJqf2y9BA.HykRMFDdSCorb5hi', '2026-06-03 06:20:28', '2026-04-28 13:52:51', '2026-06-03 06:20:28'),
(6, NULL, 'staff', 'baghdad', '$2y$10$2YI976DeisRU5ay/7JDSmuVuvUT8LS6n4IC7GCBcaOimZ3thFkbsC', '2026-08-18 08:37:15', '2026-05-12 09:03:49', '2026-08-18 08:37:15'),
(7, NULL, 'staff', 'erbil', '$2y$10$HkwS/2ysz59og95yINJQf.ylSBBugjVEf/SbGxHb9F6CNczmTqE2S', '2026-08-19 04:37:07', '2026-05-12 09:23:59', '2026-08-19 04:37:07'),
(8, 10, 'investor', 'mohammad ali', '$2y$12$OlTS87tDMnphpjAzEOvyGupNronf76irGpD64z7yh/.mEG5TDZmfS', NULL, '2026-06-27 07:31:51', '2026-06-27 07:31:51'),
(9, 4, 'investor', 'ali', '$2y$12$HvVY7mPmAczkdeKUbcIwD.410JBa4CS80WbsNalbpryfge/nC9EEi', '2026-06-27 10:00:24', '2026-06-27 10:00:07', '2026-06-27 10:00:24'),
(10, 11, 'investor', 'zahraa', '$2y$10$z38wI3ZkOCHKLtmuleZz2u.0o8x86D2NS2PRWdAcNdhtrGR.EoEum', '2026-07-26 16:24:18', '2026-06-28 05:21:20', '2026-07-26 16:24:18'),
(11, 12, 'investor', 'alimali', '$2y$10$E5q7/0sxpA6KIiktCiHHBOl1UKfIbU5AcCnyVb.1yImAaqWE38ysu', '2026-08-06 07:33:58', '2026-08-06 07:04:31', '2026-08-06 07:33:58'),
(12, NULL, 'staff', 'kirkuk', '$2y$10$TEXWL5Rl1HBb8RXxCN/zp.LuMTrT9zkhjhNTuX7yvTfyF5.4jkhGi', '2026-08-17 11:31:39', '2026-08-06 07:40:59', '2026-08-17 11:31:39'),
(13, 13, 'investor', 'canmcan', '$2y$12$hivwaCfRkjrkeXYZRqdU7eX1r7F77h/8qRe5ecIT6mvt58BCgTR6O', '2026-08-06 08:10:17', '2026-08-06 08:00:43', '2026-08-06 08:10:17'),
(14, 16, 'investor', 'zahraa H', '$2y$12$jNAmaTBFssnpdujTDmcv5Olt4f3lJHi5QsJXEQ.oMyX1YUQNtSDL6', '2026-08-08 07:46:40', '2026-08-08 04:14:12', '2026-08-08 07:46:40'),
(15, 17, 'investor', 'eng.mustafa', '$2y$12$nPmZWSsOogvuYqzKCttOsuc1wtP5BOgQdtTrYOBXvfXy42MPMFVfq', NULL, '2026-08-11 03:59:10', '2026-08-11 03:59:10'),
(16, 18, 'investor', 'Mohammed 1983', '$2y$10$O60Kk2uTbTmY8uFi7tmk.OoVcrH9fMV3YIZJQRuJ1QXrY323.bj.C', '2026-08-17 12:05:15', '2026-08-17 11:19:12', '2026-08-17 12:05:15');

-- --------------------------------------------------------

--
-- Table structure for table `withdraw_requests`
--

CREATE TABLE `withdraw_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `investor_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'IQD',
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `staff_user_id` int(10) UNSIGNED DEFAULT NULL,
  `decision_date` datetime DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdraw_requests`
--

INSERT INTO `withdraw_requests` (`id`, `investor_id`, `amount`, `currency`, `request_date`, `status`, `staff_user_id`, `decision_date`, `note`) VALUES
(1, 1, 1200.00, 'IQD', '2026-03-11 12:46:55', 'paid', 4, '2026-04-28 13:55:56', ''),
(2, 12, 300.00, 'USD', '2026-08-06 07:30:27', 'paid', 7, '2026-08-08 04:33:44', ''),
(3, 12, 250.00, 'USD', '2026-08-06 07:33:08', 'paid', 6, '2026-08-06 07:33:39', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_al_user` (`user_id`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_deposits_investor` (`investor_id`),
  ADD KEY `fk_deposits_deposit_type` (`deposit_type_id`);

--
-- Indexes for table `deposit_types`
--
ALTER TABLE `deposit_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_deposit_type_code` (`code`);

--
-- Indexes for table `investors`
--
ALTER TABLE `investors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `monthly_rates`
--
ALTER TABLE `monthly_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_monthly_rate` (`month`,`deposit_type_id`),
  ADD KEY `fk_mr_deposit_type` (`deposit_type_id`);

--
-- Indexes for table `profit_cycles`
--
ALTER TABLE `profit_cycles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_profit_cycle` (`deposit_id`,`cycle_date`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_receipt_no` (`receipt_no`),
  ADD KEY `fk_tx_investor` (`investor_id`),
  ADD KEY `fk_tx_deposit` (`deposit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_username` (`username`),
  ADD KEY `fk_users_investor` (`investor_id`);

--
-- Indexes for table `withdraw_requests`
--
ALTER TABLE `withdraw_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_wr_investor` (`investor_id`),
  ADD KEY `fk_wr_staff` (`staff_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=454;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `deposit_types`
--
ALTER TABLE `deposit_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `investors`
--
ALTER TABLE `investors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `monthly_rates`
--
ALTER TABLE `monthly_rates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=256;

--
-- AUTO_INCREMENT for table `profit_cycles`
--
ALTER TABLE `profit_cycles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `withdraw_requests`
--
ALTER TABLE `withdraw_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `fk_deposits_deposit_type` FOREIGN KEY (`deposit_type_id`) REFERENCES `deposit_types` (`id`),
  ADD CONSTRAINT `fk_deposits_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`);

--
-- Constraints for table `monthly_rates`
--
ALTER TABLE `monthly_rates`
  ADD CONSTRAINT `fk_mr_deposit_type` FOREIGN KEY (`deposit_type_id`) REFERENCES `deposit_types` (`id`);

--
-- Constraints for table `profit_cycles`
--
ALTER TABLE `profit_cycles`
  ADD CONSTRAINT `fk_pc_deposit` FOREIGN KEY (`deposit_id`) REFERENCES `deposits` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_tx_deposit` FOREIGN KEY (`deposit_id`) REFERENCES `deposits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tx_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `withdraw_requests`
--
ALTER TABLE `withdraw_requests`
  ADD CONSTRAINT `fk_wr_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  ADD CONSTRAINT `fk_wr_staff` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
