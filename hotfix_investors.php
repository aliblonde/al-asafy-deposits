<?php
require_once 'c:/xampp/htdocs/al-asafy-deposits/config/db.php';

try {
    $pdo = getPDO();

    // Add address column
    try {
        $pdo->exec("ALTER TABLE `investors` ADD COLUMN `address` VARCHAR(255) NULL AFTER `city`");
        echo "Added address column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column address already exists.\n";
        } else {
            echo "Notice on address: " . $e->getMessage() . "\n";
        }
    }

    // Add notes column
    try {
        $pdo->exec("ALTER TABLE `investors` ADD COLUMN `notes` TEXT NULL AFTER `address`");
        echo "Added notes column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column notes already exists.\n";
        } else {
            echo "Notice on notes: " . $e->getMessage() . "\n";
        }
    }

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
