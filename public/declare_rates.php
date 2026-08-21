<?php
// public/declare_rates.php — Declare Monthly Profit Rates & Accumulate
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/approval.php';

requirePermission('rates.request_declaration');
$pdo = getPDO();

$errs = [];
$successes = [];

// Fetch deposit types
$types = $pdo->query("SELECT * FROM deposit_types ORDER BY id ASC")->fetchAll();

$month = $_POST['month'] ?? $_GET['month'] ?? date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    // We expect an array of rates for each type ID
    $rates = $_POST['rates'] ?? [];
    
    if (empty($month)) {
        $errs[] = "يرجى تحديد الشهر.";
    }

    if (empty($errs)) {
        $pdo->beginTransaction();
        try {
            $processedCount = 0;
            $accumulatedTotal = 0.0;
            
            foreach ($types as $type) {
                $tid = $type['id'];
                $rStr = trim($rates[$tid] ?? '');
                
                if ($rStr === '') continue; // Skip if no rate provided for this type
                
                $r = (float) $rStr;
                $min = (float) $type['min_rate'] * 100;
                $max = (float) $type['max_rate'] * 100;
                
                if ($r < $min || $r > $max) {
                    throw new Exception("النسبة المدخلة لنوع ({$type['name_ar']}) غير مسموحة. النطاق المسموح هو {$min}% - {$max}%");
                }
                
                // 1. Save or Update Monthly Rate
                $stmtMr = $pdo->prepare("
                    INSERT INTO monthly_rates (month, deposit_type_id, rate_percent)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE rate_percent = VALUES(rate_percent)
                ");
                $stmtMr->execute([$month, $tid, ($r / 100)]);
                
                // 2. Accumulate profit for all active deposits of this type 
                //    that haven't already calculated profit for this precise month (idempotency guard via cycle_date concept)
                
                // Fetch active deposits of this type (and legacy completed ones that haven't been fully refunded yet)
                $deps = $pdo->prepare("SELECT d.* FROM deposits d WHERE d.deposit_type_id = ? AND d.status IN ('active', 'completed') AND (SELECT COUNT(*) FROM transactions t WHERE t.deposit_id = d.id AND t.type = 'withdraw') = 0");
                $deps->execute([$tid]);
                $activeDeposits = $deps->fetchAll();
                
                // We'll use the last day of the 'month' as the cycle date
                // Month format: YYYY-MM
                $cycleDate = date('Y-m-t', strtotime($month . '-01')); 
                
                foreach ($activeDeposits as $dep) {
                     // Check idempotency for this specific month + deposit
                     $pcCheck = $pdo->prepare("SELECT id FROM profit_cycles WHERE deposit_id = ? AND cycle_date = ?");
                     $pcCheck->execute([$dep['id'], $cycleDate]);
                     
                     if ($pcCheck->rowCount() > 0) {
                         // Already run for this deposit this month
                         continue;
                     }
                     // ALGORITHM: Strict 1-Month Anniversary Profit
                     // A deposit ONLY earns profit when its exact 1-Month anniversary falls within the declared calendar month.
                     
                     // Helper: calculate exactly when the NEXT 1-month chunk of profit is due based on its cycle.
                     $nextProfitDate = calcNextProfitDate($dep);
                     
                     if (!$nextProfitDate) {
                         continue; // Something wrong with deposit dates
                     }
                     
                     // Format this due date's year-month to compare against the declared `$month` (e.g., '2026-04')
                     $dueYearMonth = $nextProfitDate->format('Y-m');
                     
                     // If the deposit's next anniversary is NOT in the month being declared, skip it.
                     // Examples:
                     // 1. Created Mar 15. Declaring Mar rates. Next profit due is Apr 15. ('2026-04' !== '2026-03'). Skipped.
                     // 2. Created Mar 15. Declaring Apr rates. Next profit due is Apr 15. ('2026-04' === '2026-04'). Gets profit!
                     if ($dueYearMonth !== $month) {
                         continue;
                     }
                     
                     // Also ensure the deposit hasn't expired BEFORE this due date
                     $depEndStr = $dep['end_date'] ? date('Y-m', strtotime($dep['end_date'])) : null;
                     if ($depEndStr && $month > $depEndStr) {
                         continue; // The deposit already expired before this month
                     }
                     
                     // Calculate EXACLTY ONE FULL MONTH of profit
                     $profitThisMonth = round((float) $dep['amount'] * ($r / 100), 2);
                     
                     if ($profitThisMonth <= 0) {
                         continue;
                     }
                     
                     // The new "last_profit_date" becomes the exact anniversary date that just matured
                     $newLastProfitDate = $nextProfitDate->format('Y-m-d');
                     
                     // Finalize idempotency guard
                     $pcIns = $pdo->prepare("INSERT INTO profit_cycles (deposit_id, cycle_date) VALUES (?, ?)");
                     $pcIns->execute([$dep['id'], $cycleDate]);
                     
                     // Add to deposit's accumulated_profit
                     $updDeposit = $pdo->prepare("
                        UPDATE deposits 
                        SET accumulated_profit = accumulated_profit + ?,
                            last_profit_date = ?
                        WHERE id = ?
                     ");
                     $updDeposit->execute([$profitThisMonth, $newLastProfitDate, $dep['id']]);
                     
                     $processedCount++;
                     $accumulatedTotal += $profitThisMonth;
                }
            }
            
            $pdo->commit();
            logActivity($pdo, 'DECLARE_RATES', 'monthly_rates', null, null, [
                'month' => $month,
                'deposits_updated' => $processedCount,
                'accumulated_added' => $accumulatedTotal
            ]);
            
            if ($processedCount > 0) {
                setFlash('success', "تم إعلان النسب بنجاح وإضافة أرباح لـ {$processedCount} وديعة بقيمة إجمالية (تراكمية): " . formatMoney($accumulatedTotal));
            } else {
                setFlash('info', "تم حفظ النسب ولكن لم يتم العثور على أي ودائع جديدة لتطبيق هذه الأرباح لشهر {$month} (إما محسوبة مسبقاً أو غير موجودة).");
            }
            
            header("Location: declare_rates.php?month=" . urlencode($month));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errs[] = $e->getMessage();
        }
    }
}

// Check existing rates for the form
$existingRates = [];
if ($month) {
    $stmt = $pdo->prepare("SELECT deposit_type_id, rate_percent FROM monthly_rates WHERE month = ?");
    $stmt->execute([$month]);
    foreach ($stmt->fetchAll() as $row) {
         $existingRates[$row['deposit_type_id']] = (float) $row['rate_percent'] * 100;
    }
}

$pageTitle = 'إعلان نسب الأرباح الشهرية';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-graph-up-arrow me-2"></i>إعلان نسب الأرباح</h1>
                <p class="page-subtitle">أدخل نسبة الربح المئوية لكل نوع من الودائع للشهر المحدد ليتم حسابها كأرباح تراكمية للمستثمرين.</p>
            </div>

            <div class="row g-4 flex-lg-row-reverse">
                <!-- Info Section (Right side visually on RTL) -->
                <div class="col-lg-4">
                     <div class="form-card h-100">
                         <h5 class="text-gold mb-4"><i class="bi bi-info-circle me-2"></i>كيف تعمل هذه الصفحة؟</h5>
                         <ul class="text-muted small" style="line-height:2; padding-right:1.2rem;">
                             <li class="mb-2"><strong>إعلان شهري:</strong> أدخل نسبة الربح الفعلية التي تحققت خلال الشهر المحدد.</li>
                             <li class="mb-2"><strong>تراكم الأرباح:</strong> تحفظ كـ "أرباح تراكمية" في محفظة كل وديعة بناءً على دورتها.</li>
                             <li class="mb-2"><strong>منع التكرار الآمن:</strong> النظام يحتسب الربح مرة واحدة فقط شهرياً؛ تحديث النسبة السابقة لن يكرر الإضافة.</li>
                             <li><strong>صرف الأرباح:</strong> يتم الصرف الفعلي عندما يحين موعد السحب المتفق عليه.</li>
                         </ul>
                     </div>
                </div>

                <!-- Form Section -->
                <div class="col-lg-8">
                    <div class="form-card">
                        <?php if (!empty($errs)): ?>
                            <div class="alert flash-danger border border-danger mb-4" style="border-radius:8px">
                                <ul class="mb-0 pe-3">
                                    <?php foreach ($errs as $e) echo "<li>" . htmlspecialchars($e) . "</li>" ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <?= csrfField() ?>

                            <div class="row mb-4 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label text-gold fw-bold mb-2"><i class="bi bi-calendar-event me-2"></i>تحديد الشهر</label>
                                    <input type="month" name="month" class="form-control form-control-lg" value="<?= htmlspecialchars($month) ?>" required onchange="this.form.submit()">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-text text-muted mb-2"><i class="bi bi-arrow-repeat me-1"></i>سيتم جلب النسب المدخلة مسبقاً (إن وُجدت).</div>
                                </div>
                            </div>

                            <div class="table-wrapper mb-4">
                                <div class="table-responsive">
                                    <table class="table table-dark-custom align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%">نوع الوديعة</th>
                                                <th>النطاق المسموح</th>
                                                <th style="width: 35%">نسبة الربح (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($types as $t): 
                                                $min = number_format($t['min_rate'] * 100, 2);
                                                $max = number_format($t['max_rate'] * 100, 2);
                                                $val = isset($existingRates[$t['id']]) ? number_format($existingRates[$t['id']], 3, '.', '') : '';
                                            ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($t['name_ar']) ?></td>
                                                <td class="text-muted" style="font-size: 0.9rem">بين <?= $min ?>% و <?= $max ?>%</td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" name="rates[<?= $t['id'] ?>]" class="form-control text-center"
                                                               min="<?= $min ?>" max="<?= $max ?>" step="0.001" 
                                                               placeholder="مثال: 3.10" value="<?= htmlspecialchars($val) ?>">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top border-secondary">
                                <button type="submit" class="btn btn-gold px-5 btn-lg" onclick="return confirm('تأكيد إعلان هذه النسب وتطبيق الأرباح التراكمية على جميع الودائع النشطة لهذا الشهر؟ لا يمكن التراجع عن هذه العملية.');">
                                    <i class="bi bi-check2-all me-2"></i> حفظ وتطبيق الأرباح
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
