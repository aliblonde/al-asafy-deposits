<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();
try {
    $pdo->exec("ALTER TABLE deposit_types MODIFY code VARCHAR(50) NOT NULL;");

    // Update existing types to match new user requirements
    $pdo->exec("UPDATE deposit_types SET name_ar='وديعة 6 أشهر', code='6months', profit_rate_monthly=0.02800, min_days=180, max_days=180 WHERE id=1 OR code='short'");
    $pdo->exec("UPDATE deposit_types SET name_ar='وديعة سنة', code='1year', profit_rate_monthly=0.03500, min_days=360, max_days=360 WHERE id=2 OR code='medium'");
    $pdo->exec("UPDATE deposit_types SET name_ar='وديعة سنتين', code='2years', profit_rate_monthly=0.04200, min_days=720, max_days=720 WHERE id=3 OR code='long'");

    // Insert the 4th type if it doesn't exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM deposit_types WHERE code='3years'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO deposit_types (name_ar, code, profit_rate_monthly, min_days, max_days) VALUES ('وديعة 3 سنين', '3years', 0.05400, 1080, 1080)");
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
