<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
$pdo = getPDO();

// Verify calcNextProfitDate logic
$stmt = $pdo->query("SELECT * FROM deposits WHERE status = 'active'");
while ($dep = $stmt->fetch()) {
    $next = calcNextProfitDate($dep);
    echo "Deposit #{$dep['id']} (Freq: {$dep['profit_payout_frequency']} month/s, Rate: {$dep['profit_rate_monthly']})\n";
    echo "  Start: {$dep['start_date']} | Last Profit: " . ($dep['last_profit_date'] ?? 'None') . "\n";
    echo "  Next Profit Due: " . ($next ? $next->format('Y-m-d') : 'None') . "\n";

    // Simulate what profit_run.php does
    $freq = (int) ($dep['profit_payout_frequency'] ?? 1);
    $profit = round((float) $dep['amount'] * (float) $dep['profit_rate_monthly'] * $freq, 2);
    echo "  Calculated Profit per payout window: {$profit}\n\n";
}
