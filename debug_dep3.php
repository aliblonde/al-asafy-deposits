<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = 3");
$stmt->execute();
$d = $stmt->fetch();
echo "Deposit #3: accumulated_profit = {$d['accumulated_profit']} | last_withdrawal_date = {$d['last_withdrawal_date']}\n";
