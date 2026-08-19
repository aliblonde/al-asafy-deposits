<?php
require_once 'c:/xampp/htdocs/al-asafy-deposits/config/db.php';

try {
    $pdo = getPDO();
    $tables = ['investors', 'users', 'deposit_types', 'deposits', 'transactions', 'monthly_rates', 'profit_cycles'];
    $output = "";

    foreach ($tables as $t) {
        $output .= "=== TABLE: $t ===\n";
        try {
            $stmt = $pdo->query("DESCRIBE `$t`");
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                $output .= "- {$c['Field']} ({$c['Type']})\n";
            }
        } catch (Exception $e) {
            $output .= "Error describing table: " . $e->getMessage() . "\n";
        }
        $output .= "\n";
    }

    file_put_contents('schema_dump.txt', $output);

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
