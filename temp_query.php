<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();
$r = $pdo->query('SELECT d.id, d.status, d.accumulated_profit,
    (SELECT COUNT(*) FROM transactions t WHERE t.deposit_id = d.id AND t.type = "withdraw") as withdraw_count 
    FROM deposits d WHERE d.status = "completed"')->fetchAll();
print_r($r);
