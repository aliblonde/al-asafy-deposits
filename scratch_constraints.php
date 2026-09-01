<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo = getPDO();
    $pdo->exec("ALTER TABLE deposits ADD CONSTRAINT chk_accumulated_profit CHECK (accumulated_profit >= 0)");
    $pdo->exec("ALTER TABLE deposits ADD CONSTRAINT chk_paid_profit CHECK (paid_profit >= 0)");
    echo "Constraints applied successfully.\n";
} catch (Exception $e) {
    echo "Constraints failed/already exist: " . $e->getMessage() . "\n";
}
