<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();

try {
    // Reset the 600 profit on the specific deposit that was created in March
    $pdo->exec("UPDATE deposits SET accumulated_profit = 0, last_profit_date = NULL WHERE accumulated_profit = 600.00 AND deposit_type_id IN (SELECT id FROM deposit_types WHERE code = '3_years')");

    // Clear out the idempotency table so it can be recalculated properly in the future
    $pdo->exec("TRUNCATE TABLE profit_cycles");
    echo "Successfully reset profits and cleared profit cycles!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
