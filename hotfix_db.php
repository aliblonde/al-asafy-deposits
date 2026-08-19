<?php
require_once 'c:/xampp/htdocs/al-asafy-deposits/config/db.php';

try {
    $pdo = getPDO();

    // 1. Create monthly_rates table
    $sql1 = "
    CREATE TABLE IF NOT EXISTS `monthly_rates` (
      `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `month`           VARCHAR(7) NOT NULL,
      `deposit_type_id` INT UNSIGNED NOT NULL,
      `rate_percent`    DECIMAL(8,5) NOT NULL,
      `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_monthly_rate` (`month`, `deposit_type_id`),
      CONSTRAINT `fk_mr_deposit_type` FOREIGN KEY (`deposit_type_id`) REFERENCES `deposit_types` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql1);
    echo "monthly_rates table ensured.\n";

    // 2. Add accumulated_profit to deposits if it's not there
    try {
        $pdo->exec("ALTER TABLE `deposits` ADD COLUMN `accumulated_profit` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        echo "Added accumulated_profit column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column accumulated_profit already exists.\n";
        } else {
            echo "Notice on accumulated_profit: " . $e->getMessage() . "\n";
        }
    }

    // 3. Add last_withdrawal_date to deposits if it's not there
    try {
        $pdo->exec("ALTER TABLE `deposits` ADD COLUMN `last_withdrawal_date` DATE NULL");
        echo "Added last_withdrawal_date column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column last_withdrawal_date already exists.\n";
        } else {
            echo "Notice on last_withdrawal_date: " . $e->getMessage() . "\n";
        }
    }

    // 4. Create profit_cycles table (for idempotency)
    $sql2 = "
    CREATE TABLE IF NOT EXISTS `profit_cycles` (
      `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `deposit_id` INT UNSIGNED NOT NULL,
      `cycle_date` DATE NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_cycle` (`deposit_id`, `cycle_date`),
      CONSTRAINT `fk_pc_deposit` FOREIGN KEY (`deposit_id`) REFERENCES `deposits` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql2);
    echo "profit_cycles table ensured.\n";

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
