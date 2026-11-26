<?php
// config/wallet_schema.php — Auto-provisioning schema & permissions for Digital Wallet System

function ensureWalletSystemSchema(PDO $pdo): void
{
    static $executed = false;
    if ($executed) return;
    $executed = true;

    try {
        // 1. Wallet Balances Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `wallet_balances` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `investor_id` INT UNSIGNED NOT NULL,
              `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
              `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_wb_investor_currency` (`investor_id`, `currency`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Wallet Top-up Requests Table
        $pdo->exec("
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
        ");

        // 3. P2P Transfers Table
        $pdo->exec("
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
        ");

        // 4. Wallet Transactions Ledger
        $pdo->exec("
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
        ");

        // Ensure type column is VARCHAR to support all types (admin_debit, admin_reset, p2p_transfer, etc.)
        try {
            $pdo->exec("ALTER TABLE `wallet_transactions` MODIFY COLUMN `type` VARCHAR(50) NOT NULL");
        } catch (\Throwable $e) {}

        // 5. Company Payment Methods
        $pdo->exec("
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
        ");

        // 6. Ensure Wallet Permissions exist in `permissions` table
        $permissions = [
            [
                'name' => 'wallets.view',
                'label_ar' => 'عرض المحافظ الرقمية وسجل العمليات والتقارير',
                'category' => 'المحافظ الرقمية'
            ],
            [
                'name' => 'wallets.topup',
                'label_ar' => 'إدارة واعتماد طلبات شحن المحافظ',
                'category' => 'المحافظ الرقمية'
            ],
            [
                'name' => 'wallets.adjust',
                'label_ar' => 'السحب والتصفير والتعديل الإداري لأرصدة المحافظ',
                'category' => 'المحافظ الرقمية'
            ],
            [
                'name' => 'wallets.settings',
                'label_ar' => 'إدارة قنوات وطرق دفع وحسابات الشركة',
                'category' => 'المحافظ الرقمية'
            ],
        ];

        $insPerm = $pdo->prepare("
            INSERT INTO permissions (`name`, `label_ar`, `category`) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `label_ar` = VALUES(`label_ar`), `category` = VALUES(`category`)
        ");

        foreach ($permissions as $p) {
            try {
                $insPerm->execute([$p['name'], $p['label_ar'], $p['category']]);
            } catch (\Throwable $e) {}
        }

    } catch (\Throwable $e) {
        error_log("ensureWalletSystemSchema notice: " . $e->getMessage());
    }
}
