<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();
$cols = $pdo->query("DESCRIBE deposits")->fetchAll(PDO::FETCH_COLUMN);
echo "Deposits columns: " . implode(', ', $cols) . "\n";
