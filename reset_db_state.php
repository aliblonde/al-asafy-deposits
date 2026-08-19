<?php
require_once 'C:\xampp\htdocs\al-asafy-deposits\config\db.php';
$pdo = getPDO();

try {
    // Reset Deposit 7 (which should be purely waiting for Feb/March profit)
    $pdo->exec("UPDATE deposits SET last_profit_date = '2026-02-01', accumulated_profit = 0 WHERE id = 7");

    // Reset Deposit 6
    $pdo->exec("UPDATE deposits SET last_profit_date = '2026-03-01', accumulated_profit = 0 WHERE id = 6");

    // Reset Deposit 2, 3, 4, 5 (for safety against previous script corruption)
    // Deposit 2: start 2025-11-26, last withdrawal 2025-12-26 -> last profit should be 2025-12-26
    $pdo->exec("UPDATE deposits SET last_profit_date = '2025-12-26' WHERE id = 2");

    // Deposit 4: start 2026-02-01, last withdrawal 2026-03-01 -> last profit should be 2026-03-01
    $pdo->exec("UPDATE deposits SET last_profit_date = '2026-03-01' WHERE id = 4");

    // Deposit 3: start 2026-01-25, last withdrawal 2026-02-25 -> last profit should be 2026-02-25
    $pdo->exec("UPDATE deposits SET last_profit_date = '2026-02-25' WHERE id = 3");

    // Deposit 5: start 2026-02-26, no withdrawal -> last profit should be null to use start date
    $pdo->exec("UPDATE deposits SET last_profit_date = NULL WHERE id = 5");

    $pdo->exec("TRUNCATE TABLE profit_cycles");
    $pdo->exec("DELETE FROM monthly_rates");

    echo "Successfully reset database state to baseline!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
