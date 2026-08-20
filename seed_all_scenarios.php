<?php
// seed_all_scenarios.php — Reset & Populate Database with All Test Cases
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();

echo "--- Seeding Database with Comprehensive Test Cases ---\n";

// Disable FK checks to truncate tables cleanly
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$pdo->exec("TRUNCATE TABLE transactions");
$pdo->exec("TRUNCATE TABLE withdraw_requests");
$pdo->exec("TRUNCATE TABLE profit_cycles");
$pdo->exec("TRUNCATE TABLE deposits");
$pdo->exec("TRUNCATE TABLE investors");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "Tables cleared successfully.\n";

// 1. Insert Test Investors
$investorsData = [
    [1, 'علي محمد العسافي', '07700000001', 'بغداد', 'بغداد - المنصور', 'مستثمر رئيسي - ودائع بالدولار والدينار'],
    [2, 'مراد علي سليم', '07700000002', 'أربيل', 'أربيل - العرصات', 'مستثمر ودائع مستحقة للصرف'],
    [3, 'أناغيم فهيم طراف', '07700000003', 'البصرة', 'البصرة - الجزاير', 'مستثمر ودائع غير مستحقة بعد'],
    [4, 'خالد عمر القحطاني', '07700000004', 'كركوك', 'كركوك - طريق بغداد', 'مستثمر ودائع منتهية الأجل']
];

$insInv = $pdo->prepare("INSERT INTO investors (id, full_name, phone, city, address, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
foreach ($investorsData as $inv) {
    $insInv->execute($inv);
}
echo "Investors seeded.\n";

// 2. Fetch Deposit Types
$types = $pdo->query("SELECT id, code FROM deposit_types")->fetchAll(PDO::FETCH_KEY_PAIR);

$type6m = $types['6_months'] ?? 1;
$type1y = $types['1_year'] ?? 2;
$type2y = $types['2_years'] ?? 3;
$type3y = $types['3_years'] ?? 4;

$today = date('Y-m-d');
$pastMonth = date('Y-m-d', strtotime('-1 month'));
$past2Months = date('Y-m-d', strtotime('-2 months'));
$past6Months = date('Y-m-d', strtotime('-6 months'));
$future3Months = date('Y-m-d', strtotime('+3 months'));
$future1Year = date('Y-m-d', strtotime('+1 year'));

// 3. Insert Deposits covering ALL POSSIBLE SCENARIOS
$depositsData = [
    // [ID, InvestorID, TypeID, Amount, Currency, StartDate, EndDate, PayoutFreq, MonthlyRate, LastProfitDate, Status, AccProfit, LastWithdrawalDate]
    
    // حالة 1: وديعة نشطة مستحقة للصرف اليوم بالدولار (دورية شهرية - استحقاق حان وبها أرباح معلنة)
    [1, 1, $type1y, 10000.00, 'USD', $past2Months, $future1Year, 1, 3.50, $pastMonth, 'active', 350.00, $past2Months],

    // حالة 2: وديعة نشطة غير مستحقة للصرف بعد (دورية 3 أشهر - بدأت مؤخراً وتستحق في المستقبل)
    [2, 3, $type3y, 15000.00, 'USD', date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+3 years')), 3, 3.50, null, 'active', 0.00, null],

    // حالة 3: وديعة نشطة بالدينار العراقي مستحقة للصرف اليوم (دورية شهرية)
    [3, 1, $type6m, 5000000.00, 'IQD', $past2Months, $future3Months, 1, 3.50, $pastMonth, 'active', 175000.00, $past2Months],

    // حالة 4: وديعة نشطة دورية 6 أشهر حلت ذكراها الشهرية لإضافة ربح تراكمي يدوي
    [4, 2, $type2y, 20000.00, 'USD', $pastMonth, date('Y-m-d', strtotime('+2 years')), 6, 3.50, null, 'active', 0.00, null],

    // حالة 5: وديعة مكتملة الأجل وتنتظر صرف أرباحها التراكمية الأخيرة قبل الإغلاق
    [5, 4, $type1y, 1200000.00, 'IQD', date('Y-m-d', strtotime('-13 months')), $pastMonth, 1, 3.50, $pastMonth, 'active', 42000.00, $past2Months],

    // حالة 6: وديعة مكتملة الأجل ومصروفة ومغلقة نهائياً (Completed & Closed)
    [6, 4, $type6m, 2000000.00, 'IQD', date('Y-m-d', strtotime('-8 months')), $past2Months, 1, 3.50, $past2Months, 'completed', 0.00, $past2Months]
];

$insDep = $pdo->prepare("
    INSERT INTO deposits 
    (id, investor_id, deposit_type_id, amount, currency, start_date, end_date, profit_payout_frequency, profit_rate_monthly, last_profit_date, status, accumulated_profit, last_withdrawal_date, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

foreach ($depositsData as $dep) {
    $insDep->execute($dep);
}
echo "Deposits seeded.\n";

// 4. Insert Transactions & Withdrawal Requests for testing Investor Portal
// Add a disbursed profit transaction for Ali Mohamad Ali
$pdo->prepare("
    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
    VALUES ('AG-202607-000001', 1, 1, 'profit', 350.00, 'USD', NOW(), 'صرف أرباح تراكمية سابقة')
")->execute();

// Add a pending withdrawal request for Ali Mohamad Ali
$pdo->prepare("
    INSERT INTO withdraw_requests (investor_id, amount, currency, request_date, status, note)
    VALUES (1, 100.00, 'USD', NOW(), 'pending', 'طلب سحب جزء من الأرباح المتاحة')
")->execute();

echo "Transactions & Withdraw Requests seeded.\n";
echo "--- Seeding Completed Successfully! ---\n";
