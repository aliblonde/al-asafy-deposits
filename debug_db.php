<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();
$types = $pdo->query("SELECT * FROM deposit_types")->fetchAll();
echo "Current Deposit Types:\n";
foreach ($types as $t) {
    echo "- ID: {$t['id']}, Code: {$t['code']}, Name: {$t['name_ar']}, Rate: {$t['profit_rate_monthly']}\n";
}
