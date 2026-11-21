<?php
// public/api/dashboard.php — Mobile API Investor Dashboard Overview
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = requireApiAuth();
$investorId = (int)$user['investor_id'];
$pdo = getPDO();

// 1. Fetch Summary Totals by Currency
$stmtSum = $pdo->prepare("
    SELECT 
        currency,
        COALESCE(SUM(amount), 0) AS total_principal,
        COALESCE(SUM(accumulated_profit), 0) AS total_accumulated_profit,
        COALESCE(SUM(paid_profit), 0) AS total_paid_profit,
        COUNT(*) AS deposit_count
    FROM deposits 
    WHERE investor_id = ? AND status = 'active'
    GROUP BY currency
");
$stmtSum->execute([$investorId]);
$summaryRows = $stmtSum->fetchAll();

// Structure summaries
$summary = [
    'IQD' => ['principal' => 0.0, 'accumulated_profit' => 0.0, 'paid_profit' => 0.0, 'deposits_count' => 0],
    'USD' => ['principal' => 0.0, 'accumulated_profit' => 0.0, 'paid_profit' => 0.0, 'deposits_count' => 0],
];

foreach ($summaryRows as $row) {
    $cur = $row['currency'];
    if (isset($summary[$cur])) {
        $summary[$cur]['principal'] = (float)$row['total_principal'];
        $summary[$cur]['accumulated_profit'] = (float)$row['total_accumulated_profit'];
        $summary[$cur]['paid_profit'] = (float)$row['total_paid_profit'];
        $summary[$cur]['deposits_count'] = (int)$row['deposit_count'];
    }
}

// 2. Fetch Active Deposits with computed next payout dates
$depStmt = $pdo->prepare("
    SELECT d.id, d.amount, d.currency, d.start_date, d.end_date, d.status,
           d.accumulated_profit, d.paid_profit, d.profit_payout_frequency,
           d.last_profit_date, d.last_withdrawal_date,
           dt.name_ar AS type_name, dt.code AS type_code, dt.is_locked
    FROM deposits d
    JOIN deposit_types dt ON dt.id = d.deposit_type_id
    WHERE d.investor_id = ? AND d.status = 'active'
    ORDER BY d.start_date DESC
");
$depStmt->execute([$investorId]);
$activeDeposits = $depStmt->fetchAll();

$formattedDeposits = [];
$earliestNextPayout = null;

foreach ($activeDeposits as $d) {
    $nextProfit = calcNextProfitDate($d);
    $nextWithdrawal = calcNextWithdrawalDate($d);
    $isDue = isDepositProfitDue($d);

    $nextWithdrawalStr = $nextWithdrawal ? $nextWithdrawal->format('Y-m-d') : null;
    if ($nextWithdrawalStr) {
        if ($earliestNextPayout === null || $nextWithdrawalStr < $earliestNextPayout) {
            $earliestNextPayout = $nextWithdrawalStr;
        }
    }

    $formattedDeposits[] = [
        'id' => (int)$d['id'],
        'amount' => (float)$d['amount'],
        'currency' => $d['currency'],
        'type_name' => $d['type_name'],
        'type_code' => $d['type_code'],
        'is_locked' => (bool)$d['is_locked'],
        'start_date' => $d['start_date'],
        'end_date' => $d['end_date'],
        'accumulated_profit' => (float)$d['accumulated_profit'],
        'paid_profit' => (float)$d['paid_profit'],
        'frequency_months' => (int)$d['profit_payout_frequency'],
        'next_profit_date' => $nextProfit ? $nextProfit->format('Y-m-d') : null,
        'next_payout_date' => $nextWithdrawalStr,
        'is_payout_due' => $isDue,
    ];
}

// 3. Fetch Pending Withdrawal Requests Total
$pStmt = $pdo->prepare("
    SELECT currency, COALESCE(SUM(amount), 0) AS pending_amount
    FROM withdraw_requests 
    WHERE investor_id = ? AND status = 'pending'
    GROUP BY currency
");
$pStmt->execute([$investorId]);
$pendingRows = $pStmt->fetchAll();
$pendingWithdrawals = ['IQD' => 0.0, 'USD' => 0.0];
foreach ($pendingRows as $pr) {
    if (isset($pendingWithdrawals[$pr['currency']])) {
        $pendingWithdrawals[$pr['currency']] = (float)$pr['pending_amount'];
    }
}

// 4. Latest 5 Transactions
$txStmt = $pdo->prepare("
    SELECT id, receipt_no, type, direction, amount, currency, date, note
    FROM transactions
    WHERE investor_id = ?
    ORDER BY date DESC, id DESC
    LIMIT 5
");
$txStmt->execute([$investorId]);
$recentTransactions = $txStmt->fetchAll();

apiResponse(true, [
    'investor' => [
        'id' => $investorId,
        'full_name' => $user['full_name'],
        'phone' => $user['phone'],
        'city' => $user['city']
    ],
    'summary' => $summary,
    'pending_withdrawals' => $pendingWithdrawals,
    'earliest_next_payout' => $earliestNextPayout,
    'deposits' => $formattedDeposits,
    'recent_transactions' => $recentTransactions
], 'تم جلب بيانات لوحة التحكم بنجاح.');
