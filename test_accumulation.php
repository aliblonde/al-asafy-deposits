<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
$pdo = getPDO();

echo "Running Test Simulation for Profit Accumulation and Disbursement...\n";

// 1. Fetch deposit #2 (USD, freq: 3, start: 4 months ago)
$stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = 2");
$stmt->execute();
$dep = $stmt->fetch();

echo "Deposit #2 Details: Amount: {$dep['amount']} {$dep['currency']} | Freq: {$dep['profit_payout_frequency']} | Start: {$dep['start_date']} | Accumulated: {$dep['accumulated_profit']}\n";

// 2. Simulate declaring a rate for Month 1 (deposit_type_id = 2, let's say 3.7%)
$month1 = date('Y-m', strtotime('-3 months'));
$rate1 = 0.037;
echo "Declaring Rate for $month1: " . ($rate1 * 100) . "%\n";

$cycleDate1 = date('Y-m-t', strtotime($month1 . '-01'));

$pdo->prepare("INSERT IGNORE INTO profit_cycles (deposit_id, cycle_date) VALUES (?, ?)")->execute([2, $cycleDate1]);
$profit1 = round((float) $dep['amount'] * $rate1, 2);
$pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ?, last_profit_date = ? WHERE id = ?")->execute([$profit1, $cycleDate1, 2]);

// 3. Simulate Month 2 (let's say 3.8%)
$month2 = date('Y-m', strtotime('-2 months'));
$rate2 = 0.038;
echo "Declaring Rate for $month2: " . ($rate2 * 100) . "%\n";

$cycleDate2 = date('Y-m-t', strtotime($month2 . '-01'));
$pdo->prepare("INSERT IGNORE INTO profit_cycles (deposit_id, cycle_date) VALUES (?, ?)")->execute([2, $cycleDate2]);
$profit2 = round((float) $dep['amount'] * $rate2, 2);
$pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ?, last_profit_date = ? WHERE id = ?")->execute([$profit2, $cycleDate2, 2]);

$stmt->execute();
$depUpdated = $stmt->fetch();
echo "Accumulated Profit is now: {$depUpdated['accumulated_profit']}\n";
echo "Next Withdrawal Date allowed: " . calcNextWithdrawalDate($depUpdated)->format('Y-m-d') . "\n";
echo "Done.\n";
