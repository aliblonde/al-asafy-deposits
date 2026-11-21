<?php
// public/api/deposits.php — Mobile API Deposits List
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = requireApiAuth();
$investorId = (int)$user['investor_id'];
$pdo = getPDO();

$stmt = $pdo->prepare("
    SELECT d.id, d.amount, d.currency, d.start_date, d.end_date, d.status,
           d.accumulated_profit, d.paid_profit, d.profit_payout_frequency,
           d.last_profit_date, d.last_withdrawal_date,
           dt.name_ar AS type_name, dt.code AS type_code, dt.is_locked,
           dt.min_rate, dt.max_rate
    FROM deposits d
    JOIN deposit_types dt ON dt.id = d.deposit_type_id
    WHERE d.investor_id = ?
    ORDER BY CASE WHEN d.status = 'active' THEN 0 ELSE 1 END, d.start_date DESC
");
$stmt->execute([$investorId]);
$rows = $stmt->fetchAll();

$deposits = [];
foreach ($rows as $d) {
    $nextProfit = calcNextProfitDate($d);
    $nextWithdrawal = calcNextWithdrawalDate($d);
    $isDue = isDepositProfitDue($d);

    // Calculate pending withdrawal amount for this specific deposit
    $pendingStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdraw_requests WHERE deposit_id = ? AND status = 'pending'");
    $pendingStmt->execute([(int)$d['id']]);
    $pendingForDep = (float)$pendingStmt->fetchColumn();

    $availableToWithdraw = max(0.0, (float)$d['accumulated_profit'] - $pendingForDep);

    $deposits[] = [
        'id' => (int)$d['id'],
        'amount' => (float)$d['amount'],
        'currency' => $d['currency'],
        'type_name' => $d['type_name'],
        'type_code' => $d['type_code'],
        'is_locked' => (bool)$d['is_locked'],
        'status' => $d['status'],
        'status_ar' => arabicStatus($d['status']),
        'start_date' => $d['start_date'],
        'end_date' => $d['end_date'],
        'accumulated_profit' => (float)$d['accumulated_profit'],
        'paid_profit' => (float)$d['paid_profit'],
        'pending_withdrawal' => $pendingForDep,
        'available_to_withdraw' => $availableToWithdraw,
        'profit_frequency_months' => (int)$d['profit_payout_frequency'],
        'next_profit_date' => $nextProfit ? $nextProfit->format('Y-m-d') : null,
        'next_payout_date' => $nextWithdrawal ? $nextWithdrawal->format('Y-m-d') : null,
        'is_payout_due' => $isDue,
        'min_monthly_rate' => ((float)$d['min_rate'] * 100) . '%',
        'max_monthly_rate' => ((float)$d['max_rate'] * 100) . '%'
    ];
}

apiResponse(true, $deposits, 'تم جلب قائمة الودائع بنجاح.');
