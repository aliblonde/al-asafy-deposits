<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
$pdo = getPDO();

$stmt = $pdo->query("SELECT * FROM deposits WHERE status = 'active'");
$deposits = $stmt->fetchAll();

$fixedCount = 0;
foreach ($deposits as $d) {
    // Check if there are manual profit adjustments that have a month greater than the current last_profit_date
    $adjStmt = $pdo->prepare("SELECT MAX(month) FROM manual_profit_adjustments WHERE deposit_id = ?");
    $adjStmt->execute([$d['id']]);
    $maxMonth = $adjStmt->fetchColumn();
    
    if ($maxMonth) {
        $expectedDate = $maxMonth . '-01';
        $currentDate = $d['last_profit_date'] ?: $d['start_date'];
        
        if (substr($currentDate, 0, 7) < $maxMonth) {
            // Need to advance last_profit_date
            // We'll calculate what calcNextProfitDate would have been
            $dt = DateTimeImmutable::createFromFormat('Y-m', $maxMonth);
            if ($dt) {
                // If they paid for '2026-09', then the last profit date should reflect the profit date of that month
                // Let's just set it to the day of the start_date in that month
                $day = substr($d['start_date'], 8, 2);
                $newDate = $maxMonth . '-' . $day;
                
                $upd = $pdo->prepare("UPDATE deposits SET last_profit_date = ? WHERE id = ?");
                $upd->execute([$newDate, $d['id']]);
                $fixedCount++;
            }
        }
    }
}
echo "تم إصلاح تواريخ الأرباح لـ $fixedCount وديعة بنجاح.";
