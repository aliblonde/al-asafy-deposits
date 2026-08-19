<?php
// TEMPORARY DEBUG — delete after fixing
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$pdo = getPDO();
$today = date('Y-m-d');

$deposits = $pdo->query(
    "SELECT d.*, i.full_name, dt.name_ar, dt.code
     FROM deposits d
     JOIN investors i ON i.id = d.investor_id
     JOIN deposit_types dt ON dt.id = d.deposit_type_id
     WHERE d.status = 'active'
     ORDER BY d.id"
)->fetchAll();

echo "<pre style='font-family:monospace;font-size:13px;background:#111;color:#eee;padding:20px'>";
echo "Today: $today\n\n";

foreach ($deposits as $dep) {
    $rawLast = $dep['last_profit_date'];
    $dep2 = $dep;
    if (empty($dep2['last_profit_date']))
        $dep2['last_profit_date'] = null;

    $next = calcNextProfitDate($dep2);
    $nextStr = $next ? $next->format('Y-m-d') : 'NULL';
    $isDue = $next && $nextStr <= $today;

    // Check profit_cycles
    $existing = $pdo->prepare("SELECT cycle_date FROM profit_cycles WHERE deposit_id=? ORDER BY cycle_date");
    $existing->execute([$dep['id']]);
    $cycles = array_column($existing->fetchAll(), 'cycle_date');

    echo "Deposit #{$dep['id']} — {$dep['full_name']}\n";
    echo "  start_date      : {$dep['start_date']}\n";
    echo "  end_date        : {$dep['end_date']}\n";
    echo "  last_profit_date: " . var_export($rawLast, true) . "\n";
    echo "  next_profit_date: $nextStr\n";
    echo "  is_due          : " . ($isDue ? 'YES ← should process' : 'no') . "\n";
    echo "  profit_cycles   : " . (empty($cycles) ? '(none)' : implode(', ', $cycles)) . "\n";
    echo "\n";
}

// ── Test: check if currency column exists ──
echo "─────────────────────────────────────────\n";
echo "DB COLUMN CHECK:\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM transactions")->fetchAll(PDO::FETCH_COLUMN);
    echo "  transactions columns: " . implode(', ', $cols) . "\n\n";

    // Check for bad receipts
    $bad = $pdo->query("SELECT id, receipt_no, type, date FROM transactions WHERE receipt_no LIKE '%E+%'")->fetchAll();
    if ($bad) {
        echo "  [!!!] CORRUPTED RECEIPTS FOUND:\n";
        foreach ($bad as $b) {
            echo "        ID: {$b['id']} | No: {$b['receipt_no']} | Type: {$b['type']} | Date: {$b['date']}\n";
        }
    } else {
        echo "  (No corrupted receipts found with 'E+')\n";
    }

    echo "\n";
    $cols2 = $pdo->query("SHOW COLUMNS FROM deposits")->fetchAll(PDO::FETCH_COLUMN);
    echo "  deposits columns    : " . implode(', ', $cols2) . "\n\n";

    $cols3 = $pdo->query("SHOW COLUMNS FROM withdraw_requests")->fetchAll(PDO::FETCH_COLUMN);
    echo "  withdraw_requests   : " . implode(', ', $cols3) . "\n";
} catch (PDOException $e) {
    echo "  ERROR checking columns: " . $e->getMessage() . "\n";
}

echo "</pre>";

