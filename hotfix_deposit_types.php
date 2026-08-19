<?php
require_once 'c:/xampp/htdocs/al-asafy-deposits/config/db.php';

try {
    $pdo = getPDO();

    // 1. Add min_rate to deposit_types if it's not there
    try {
        $pdo->exec("ALTER TABLE `deposit_types` ADD COLUMN `min_rate` DECIMAL(8,5) NOT NULL DEFAULT 0.00");
        echo "Added min_rate column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column min_rate already exists.\n";
        } else {
            echo "Notice on min_rate: " . $e->getMessage() . "\n";
        }
    }

    // 2. Add max_rate to deposit_types if it's not there
    try {
        $pdo->exec("ALTER TABLE `deposit_types` ADD COLUMN `max_rate` DECIMAL(8,5) NOT NULL DEFAULT 0.00");
        echo "Added max_rate column.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "Column max_rate already exists.\n";
        } else {
            echo "Notice on max_rate: " . $e->getMessage() . "\n";
        }
    }

    // 3. Update the types with the correct bounds
    // 6 اشهر النسبة تكون بين 2.8 الى 3.3
    $pdo->exec("UPDATE deposit_types SET code = '6_months', min_rate = 0.02800, max_rate = 0.03300 WHERE id = 1 OR code = 'short'");

    // سنة النسبة تكون بين 3.5 الى 3.9
    $pdo->exec("UPDATE deposit_types SET code = '1_year', min_rate = 0.03500, max_rate = 0.03900 WHERE id = 2 OR code = 'medium'");

    // سنتين النسبة تكون بين 4.2 الى 4.9
    $pdo->exec("UPDATE deposit_types SET code = '2_years', min_rate = 0.04200, max_rate = 0.04900 WHERE id = 3 OR code = 'long'");

    // 3 سنوات النسبة تكون بين 5.4 الى 6.3
    // Might not exist initially, let's insert it if id=4 doesn't exist
    $stmt = $pdo->query("SELECT id FROM deposit_types WHERE id = 4");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO deposit_types (id, name_ar, code, min_rate, max_rate, min_days, max_days) VALUES (4, '3 سنوات', '3_years', 0.05400, 0.06300, 1080, 1080)");
    } else {
        $pdo->exec("UPDATE deposit_types SET code = '3_years', min_rate = 0.05400, max_rate = 0.06300 WHERE id = 4");
    }

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
