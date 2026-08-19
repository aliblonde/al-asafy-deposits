<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();

try {
    // Drop existing database entirely
    $pdo->exec("DROP DATABASE IF EXISTS `al_asafy_deposits`;");
    $pdo->exec("CREATE DATABASE `al_asafy_deposits` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `al_asafy_deposits`;");

    // Read and execute schema
    $schemaSql = file_get_contents(__DIR__ . '/sql/schema.sql');
    $pdo->exec($schemaSql);

    // Read and execute seed
    $seedSql = file_get_contents(__DIR__ . '/sql/seed.sql');
    // Using simple approach to split statements if necessary, but PDO can often run the whole string
    // if emulation is on or using a different driver. Let's try executing it all together first.
    try {
        $pdo->exec($seedSql);
        echo "Database dropped, schema recreated, and seeded successfully.\n";
    } catch (Exception $e) {
        // If single execution fails, try splitting by statement (very simplified)
        echo "Direct seed failed. Trying statement by statement...\n";
        $statements = explode(';', $seedSql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
        echo "Fallback seed completed.\n";
    }

} catch (Exception $e) {
    echo "Error resetting database: " . $e->getMessage() . "\n";
}
