<?php
// public/api/wallet.php — Mobile API Investor Digital Wallet Details
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = requireApiAuth();
$investorId = (int)$user['investor_id'];
$pdo = getPDO();

// Ensure wallet tables exist gracefully (self-migrating if needed)
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
      KEY `idx_wt_type` (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 1. Fetch direct wallet balances
$wbStmt = $pdo->prepare("SELECT currency, balance FROM wallet_balances WHERE investor_id = ?");
$wbStmt->execute([$investorId]);
$wbRows = $wbStmt->fetchAll();

$directWallet = ['IQD' => 0.0, 'USD' => 0.0];
foreach ($wbRows as $r) {
    if (isset($directWallet[$r['currency']])) {
        $directWallet[$r['currency']] = (float)$r['balance'];
    }
}

// 2. Fetch profits from active deposits
$pStmt = $pdo->prepare("
    SELECT 
        currency,
        COALESCE(SUM(amount), 0) AS total_principal,
        COALESCE(SUM(accumulated_profit), 0) AS total_accumulated_profit,
        COALESCE(SUM(paid_profit), 0) AS total_paid_profit
    FROM deposits 
    WHERE investor_id = ? AND status = 'active'
    GROUP BY currency
");
$pStmt->execute([$investorId]);
$profitRows = $pStmt->fetchAll();

$principal = ['IQD' => 0.0, 'USD' => 0.0];
$accumulatedProfits = ['IQD' => 0.0, 'USD' => 0.0];

foreach ($profitRows as $pr) {
    $cur = $pr['currency'];
    if (isset($principal[$cur])) {
        $principal[$cur] = (float)$pr['total_principal'];
        $accumulatedProfits[$cur] = (float)$pr['total_accumulated_profit'];
    }
}

// 3. Fetch pending withdrawals
$pwStmt = $pdo->prepare("
    SELECT currency, COALESCE(SUM(amount), 0) AS pending_amount
    FROM withdraw_requests
    WHERE investor_id = ? AND status = 'pending'
    GROUP BY currency
");
$pwStmt->execute([$investorId]);
$pwRows = $pwStmt->fetchAll();

$pendingWithdrawals = ['IQD' => 0.0, 'USD' => 0.0];
foreach ($pwRows as $pwr) {
    if (isset($pendingWithdrawals[$pwr['currency']])) {
        $pendingWithdrawals[$pwr['currency']] = (float)$pwr['pending_amount'];
    }
}

// 4. Net available profits per currency
$netAvailableProfits = [
    'USD' => max(0.0, $accumulatedProfits['USD'] - $pendingWithdrawals['USD']),
    'IQD' => max(0.0, $accumulatedProfits['IQD'] - $pendingWithdrawals['IQD']),
];

// 5. Total available to withdraw = Net Available Profits + Direct Wallet Balance
$totalAvailable = [
    'USD' => $netAvailableProfits['USD'] + $directWallet['USD'],
    'IQD' => $netAvailableProfits['IQD'] + $directWallet['IQD'],
];

// 6. Fetch pending top-up requests
$topupStmt = $pdo->prepare("
    SELECT id, amount, currency, payment_method, transfer_reference, receipt_image, note, status, created_at
    FROM wallet_topup_requests
    WHERE investor_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 20
");
$topupStmt->execute([$investorId]);
$topupRequests = $topupStmt->fetchAll();

// 7. Fetch wallet transactions history
$txStmt = $pdo->prepare("
    SELECT id, type, direction, amount, currency, balance_after, note, created_at
    FROM wallet_transactions
    WHERE investor_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 30
");
$txStmt->execute([$investorId]);
$walletTransactions = $txStmt->fetchAll();

// 8. Company official payment channels for top-ups
$companyChannels = [
    [
        'id' => 'bank_fib',
        'name' => 'مصرف العراق الأول (FIB)',
        'type' => 'bank',
        'account_title' => 'مجموعة العسافي للاستثمارات المالية',
        'account_number' => 'IQ78FIBK00010000000001234567',
        'instructions' => 'يرجى كتابة اسم المستثمر في حقل الملاحظات عند التحويل وإرفاق وصل التحويل هنا.'
    ],
    [
        'id' => 'zaincash',
        'name' => 'محفظة زين كاش (ZainCash)',
        'type' => 'wallet',
        'account_title' => 'محفظة الأعمال المعتمدة - مجموعة العسافي',
        'account_number' => '07731111861',
        'instructions' => 'التحويل المالي المباشر عبر تطبيق زين كاش لرقم المحفظة وإرفاق لقطة شاشة الإشعار.'
    ],
    [
        'id' => 'qicard',
        'name' => 'محفظة كي كارد (Qi Card / SuperQi)',
        'type' => 'wallet',
        'account_title' => 'حساب الدفع المعتمد - مجموعة العسافي',
        'account_number' => '07731111861',
        'instructions' => 'تحويل من محفظة SuperQi أو بطاقة الماستر كارد التابعة لـ Qi.'
    ],
    [
        'id' => 'exchange_office',
        'name' => 'الحوالات المعتمدة ومكاتب الصيرفة',
        'type' => 'exchange',
        'account_title' => 'شركة الطيف / الروابي للصرافة',
        'account_number' => 'فرع بغداد - باسم مجموعة العسافي للاستثمارات',
        'instructions' => 'تسليم الحوالة بالفرع أو استلام وصل الإيداع وإرفاق صورته في التطبيق.'
    ]
];

apiResponse(true, [
    'wallet_id' => 'W-' . str_pad((string)$investorId, 6, '0', STR_PAD_LEFT),
    'total_available' => $totalAvailable,
    'direct_wallet_balance' => $directWallet,
    'available_profits' => $netAvailableProfits,
    'invested_capital' => $principal, // strictly separated and frozen
    'pending_withdrawals' => $pendingWithdrawals,
    'topup_requests' => $topupRequests,
    'wallet_transactions' => $walletTransactions,
    'company_channels' => $companyChannels,
], 'تم جلب بيانات المحفظة بنجاح.');
