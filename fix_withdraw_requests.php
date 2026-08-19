<?php
require 'C:\xampp\htdocs\al-asafy-deposits\config\db.php';
$pdo = getPDO();

try {
    $pdo->exec("ALTER TABLE withdraw_requests ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'IQD' AFTER amount;");
    echo "Column 'currency' added successfully.\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Duplicate column
        echo "Column 'currency' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
