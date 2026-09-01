<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();
try {
    $pdo->exec("ALTER TABLE `deposit_types` MODIFY `code` VARCHAR(50) NOT NULL");
    $pdo->exec("ALTER TABLE `deposit_types` ADD COLUMN IF NOT EXISTS `is_locked` TINYINT(1) NOT NULL DEFAULT 0");

    $packages = [
        ['name_ar' => 'ASAIFY START - سنة', 'code' => 'L1Y', 'min_days' => 365, 'min_rate' => 6.3, 'max_rate' => 7.3, 'is_locked' => 1],
        ['name_ar' => 'ASAIFY ADVANCE - سنتان', 'code' => 'L2Y', 'min_days' => 730, 'min_rate' => 7.3, 'max_rate' => 8.4, 'is_locked' => 1],
        ['name_ar' => 'ASAIFY PRESTIGE - 3 سنوات', 'code' => 'L3Y', 'min_days' => 1095, 'min_rate' => 8.4, 'max_rate' => 9.7, 'is_locked' => 1],
        ['name_ar' => 'ASAIFY SIGNATURE - 5 سنوات', 'code' => 'L5Y', 'min_days' => 1825, 'min_rate' => 9.7, 'max_rate' => 10.8, 'is_locked' => 1],
    ];

    foreach ($packages as $p) {
        $stmt = $pdo->prepare("SELECT id FROM deposit_types WHERE code = ?");
        $stmt->execute([$p['code']]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO deposit_types (name_ar, code, min_days, max_days, min_rate, max_rate, is_locked) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$p['name_ar'], $p['code'], $p['min_days'], $p['min_days'], $p['min_rate'], $p['max_rate'], $p['is_locked']]);
        }
    }
    echo "<h1>Migration completed successfully! You can delete this file.</h1>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}