<?php
require_once 'C:\xampp\htdocs\al-asafy-deposits\config\db.php';
require_once 'C:\xampp\htdocs\al-asafy-deposits\config\helpers.php';

$pdo = getPDO();
$month = '2026-02';

$deps = $pdo->prepare("SELECT * FROM deposits WHERE id = 2");
$deps->execute();
$dep = $deps->fetch(PDO::FETCH_ASSOC);

echo "Checking Deposit 2 for Month: $month\n";
echo "Status: " . $dep['status'] . "\n";
echo "Last Profit Date: " . ($dep['last_profit_date'] ?? 'NULL') . "\n";

$nextProfitDate = calcNextProfitDate($dep);
echo "Next Profit Date (calcNextProfitDate): " . ($nextProfitDate ? $nextProfitDate->format('Y-m-d') : 'NULL') . "\n";

$dueYearMonth = $nextProfitDate ? $nextProfitDate->format('Y-m') : 'NULL';
echo "Due Year-Month: $dueYearMonth\n";

if ($dueYearMonth !== $month) {
    echo "SKIPPED: Year-Month mismatch.\n";
} else {
    echo "MATCH: Year-Month matches!\n";
}

$depEndStr = $dep['end_date'] ? date('Y-m', strtotime($dep['end_date'])) : null;
if ($depEndStr && $month > $depEndStr) {
    echo "SKIPPED: Expired ($month > $depEndStr).\n";
}

$cycleDate = date('Y-m-t', strtotime($month . '-01'));
echo "Cycle Date: $cycleDate\n";

// Check idempotency
$check = $pdo->prepare("SELECT * FROM profit_cycles WHERE deposit_id = ? AND cycle_date = ?");
$check->execute([$dep['id'], $cycleDate]);
if ($check->rowCount() > 0) {
    echo "SKIPPED: Found in profit_cycles!\n";
} else {
    echo "CLEARED: Not in profit_cycles.\n";
}
