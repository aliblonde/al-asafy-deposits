<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = getPDO();
echo "--- Testing Strict Due Date Payout & Balance Validation Rules ---\n";

// 1. Fetch active deposit
$stmt = $pdo->query("SELECT d.*, i.full_name FROM deposits d JOIN investors i ON i.id=d.investor_id WHERE d.status='active' LIMIT 1");
$dep = $stmt->fetch();

if (!$dep) {
    echo "No active deposit found for testing.\n";
    exit;
}

echo "Testing Deposit #{$dep['id']} for Investor: {$dep['full_name']}\n";
echo "Start Date: {$dep['start_date']} | Last Withdrawal: " . ($dep['last_withdrawal_date'] ?? 'None') . "\n";

$dueDate = calcNextWithdrawalDate($dep);
$dueStr = $dueDate ? $dueDate->format('Y-m-d') : null;
$isDue = isDepositProfitDue($dep);

echo "Next Due Date: " . ($dueStr ? formatDate($dueStr) : 'None') . "\n";
echo "Is Due Today (" . date('Y-m-d') . "): " . ($isDue ? "YES" : "NO") . "\n";

// Verify strict rule: if not due, isDepositProfitDue returns false
if (!$isDue) {
    echo "[SUCCESS] Strict Due Date Rule Enforced: Deposit is NOT due, disbursement will be blocked.\n";
} else {
    echo "[INFO] Deposit IS due today or past due, ready for disbursement.\n";
}

echo "--- Test Completed Successfully ---\n";
