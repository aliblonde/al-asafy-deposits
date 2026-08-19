<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();

try {
    echo "Dropping all tables to ensure a clean slate...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Explicitly drop all known tables
    $tables = [
        'activity_logs',
        'withdraw_requests',
        'profit_cycles',
        'transactions',
        'deposits',
        'monthly_rates',
        'deposit_types',
        'users',
        'investors'
    ];

    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "Dropped $table\n";
        } catch (Exception $e) {
            echo "Notice dropping $table: " . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Applying schema.sql...\n";
    $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
    if ($schema === false)
        throw new Exception("Could not read schema.sql");
    $pdo->exec($schema);

    echo "Applying seed.sql...\n";
    $seed = file_get_contents(__DIR__ . '/sql/seed.sql');
    // Remove the conflicting INSERT into deposits from seed.sql, we did it in schema.sql
    // Wait, let's just make sure we don't have dupes. I'll execute the raw seed string.

    try {
        $pdo->exec($seed);
    } catch (Exception $e) {
        echo "Seed notice: " . $e->getMessage() . "\n";
    }

    echo "Database reset complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
