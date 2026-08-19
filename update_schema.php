<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();

try {
    $pdo->beginTransaction();

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Update deposit_types table
    $pdo->exec("
        ALTER TABLE `deposit_types`
        MODIFY COLUMN `code` ENUM('6_months','1_year','2_years','3_years') NOT NULL,
        ADD COLUMN `min_rate` DECIMAL(8,5) NOT NULL DEFAULT 0.02800 AFTER `code`,
        ADD COLUMN `max_rate` DECIMAL(8,5) NOT NULL DEFAULT 0.03300 AFTER `min_rate`,
        DROP COLUMN `profit_rate_monthly`;
    ");

    // 2. Update deposits table
    $pdo->exec("
        ALTER TABLE `deposits`
        ADD COLUMN `profit_payout_frequency` INT NOT NULL DEFAULT 1 AFTER `profit_rate_monthly`;
    ");

    // 3. Clear existing deposit types and insert new ones
    $pdo->exec("DELETE FROM `deposit_types`");
    $pdo->exec("
        INSERT INTO `deposit_types` (`id`, `name_ar`, `code`, `min_rate`, `max_rate`, `min_days`, `max_days`) VALUES
        (1, 'وديعة 6 أشهر', '6_months', 0.02800, 0.03300, 180, 180),
        (2, 'وديعة سنة',    '1_year',   0.03500, 0.03900, 360, 360),
        (3, 'وديعة سنتين',  '2_years',  0.04200, 0.04900, 720, 720),
        (4, 'وديعة 3 سنوات', '3_years',  0.05400, 0.06300, 1080, 1080);
    ");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $pdo->commit();
    echo "Schema updated successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
