<?php
require_once 'c:/xampp/htdocs/al-asafy-deposits/config/db.php';

try {
    $pdo = getPDO();

    // 1. Add currency to deposits
    try {
        $pdo->exec("ALTER TABLE `deposits` ADD COLUMN `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'IQD' AFTER `amount`");
        echo "Added currency to deposits.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column currency already exists in deposits.\n";
        } else {
            echo "Notice on deposits currency: " . $e->getMessage() . "\n";
        }
    }

    // 2. Add profit_payout_frequency to deposits
    try {
        $pdo->exec("ALTER TABLE `deposits` ADD COLUMN `profit_payout_frequency` INT NOT NULL DEFAULT 1 AFTER `end_date`");
        echo "Added profit_payout_frequency to deposits.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column profit_payout_frequency already exists in deposits.\n";
        } else {
            echo "Notice on profit_payout_frequency: " . $e->getMessage() . "\n";
        }
    }

    // 3. Add currency to transactions
    try {
        $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'IQD' AFTER `amount`");
        echo "Added currency to transactions.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column currency already exists in transactions.\n";
        } else {
            echo "Notice on transactions currency: " . $e->getMessage() . "\n";
        }
    }

    echo "Schema sync complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
