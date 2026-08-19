<?php
require_once 'c:/xampp/htdocs/al-asafy-deposits/config/db.php';

try {
    $pdo = getPDO();

    // Add contract_path column
    try {
        $pdo->exec("ALTER TABLE `investors` ADD COLUMN `contract_path` VARCHAR(255) NULL AFTER `national_id`");
        echo "Added contract_path column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column contract_path already exists.\n";
        } else {
            echo "Notice on contract_path: " . $e->getMessage() . "\n";
        }
    }

    // Add id_card_path column
    try {
        $pdo->exec("ALTER TABLE `investors` ADD COLUMN `id_card_path` VARCHAR(255) NULL AFTER `contract_path`");
        echo "Added id_card_path column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column id_card_path already exists.\n";
        } else {
            echo "Notice on id_card_path: " . $e->getMessage() . "\n";
        }
    }

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
