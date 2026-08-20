<?php
// public/profit_run.php — Disburse Accumulated Profits for Due Deposits
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

$results = null;
$depositId = isset($_GET['deposit_id']) ? (int) $_GET['deposit_id'] : null;
$targetDeposit = null;

if ($depositId) {
    $stmt = $pdo->prepare("SELECT d.*, i.full_name, dt.name_ar AS type_name FROM deposits d JOIN investors i ON i.id = d.investor_id JOIN deposit_types dt ON dt.id = d.deposit_type_id WHERE d.id = ?");
    $stmt->execute([$depositId]);
    $targetDeposit = $stmt->fetch();

    if (!$targetDeposit || !in_array($targetDeposit['status'], ['active', 'completed'])) {
        setFlash('danger', 'الوديعة غير موجودة أو ملغاة.');
        header('Location: deposits.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Step 1: Auto-close expired deposits
    $closed = autoCloseExpiredDeposits($pdo);

    // Step 2: Fetch active deposits to process (must have depositId)
    if ($depositId && $targetDeposit) {
        $activeDeposits = [$targetDeposit];
    } else {
        setFlash('danger', 'يرجى اختيار وديعة محددة من الجدول للصرف.');
        header('Location: profit_run.php');
        exit;
    }

    $today = date('Y-m-d');
    $processed = 0;
    $skipped = 0;
    $totalDisbursed = 0.0;
    $detail = [];
    $runErrors = [];

    // Read manual amount or custom note
    $manualAmount = isset($_POST['disburse_amount']) ? (float)$_POST['disburse_amount'] : null;
    $customNote = isset($_POST['note']) ? trim($_POST['note']) : '';
    $manualCurrency = isset($_POST['currency']) && in_array($_POST['currency'], ['IQD', 'USD']) ? $_POST['currency'] : null;

    foreach ($activeDeposits as $dep) {
        $accumulated = (float) $dep['accumulated_profit'];

        $isManual = ($depositId && $manualAmount !== null);
        $amountToDisburse = $isManual ? $manualAmount : $accumulated;
        $payoutCurrency = ($isManual && $manualCurrency) ? $manualCurrency : ($dep['currency'] ?? 'IQD');
        $note = $isManual ? ($customNote ?: 'صرف أرباح يدوية') : 'صرف أرباح تراكمية مستحقة';

        if ($amountToDisburse <= 0) {
            $runErrors[] = "مبلغ الصرف الفعلي يجب أن يكون أكبر من الصفر.";
            $skipped++;
            continue;
        }

        // Check if the withdrawal date has been reached
        $nextWithdrawal = calcNextWithdrawalDate($dep);
        $dueStr = $nextWithdrawal ? $nextWithdrawal->format('Y-m-d') : null;

        // STRICT RULE: No profit disbursement allowed before the due date under any circumstances
        if (!$dueStr || $dueStr > $today) {
            $runErrors[] = "عفواً، لا يجوز صرف الأرباح للوديعة #{$dep['id']} قبل موعد استحقاقها القادم بتاريخ: " . formatDate($dueStr);
            $skipped++;
            continue;
        }

        try {
            $pdo->beginTransaction();

            $receiptNo = generateReceiptNo($pdo);

            // 1. Insert profit transaction
            $pdo->prepare(
                "INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                 VALUES (?, ?, ?, 'profit', ?, ?, NOW(), ?)"
            )->execute([
                $receiptNo,
                $dep['investor_id'],
                $dep['id'],
                $amountToDisburse,
                $payoutCurrency,
                $note
            ]);

            // 2. Update accumulated_profit and last_withdrawal_date to today to advance cycle to future
            $newAccumulated = max(0.00, $accumulated - $amountToDisburse);

            $pdo->prepare("UPDATE deposits SET accumulated_profit = ?, last_withdrawal_date = ? WHERE id = ?")
                ->execute([$newAccumulated, $today, $dep['id']]);

            $pdo->commit();

            $processed++;
            $totalDisbursed += $amountToDisburse;
            $detail[] = [
                'investor' => $dep['full_name'],
                'deposit_id' => $dep['id'],
                'amount' => $dep['amount'],
                'disbursed' => $amountToDisburse,
                'currency' => $payoutCurrency,
                'receipt_no' => $receiptNo,
                'due_date' => $useDueDate,
            ];

        } catch (PDOException $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            $runErrors[] = "خطأ مع الوديعة #{$dep['id']} : " . $e->getMessage();
        }
    }

    logActivity($pdo, 'DISBURSE_PROFIT', 'deposits', ($depositId ?: null), null, [
        'date' => $today,
        'processed' => $processed,
        'skipped' => $skipped,
        'closed' => $closed,
        'total_disbursed' => $totalDisbursed,
    ]);

    $results = compact('processed', 'skipped', 'closed', 'totalDisbursed', 'detail', 'runErrors');
}

// Fetch all due deposits if no specific deposit selected
$dueDepositsList = [];
if (!$depositId) {
    $allActive = $pdo->query(
        "SELECT d.*, i.full_name, dt.name_ar AS type_name, dt.code
         FROM deposits d
         JOIN investors i ON i.id = d.investor_id
         JOIN deposit_types dt ON dt.id = d.deposit_type_id
         WHERE d.status = 'active' OR (d.status = 'completed' AND d.accumulated_profit > 0)
         ORDER BY d.created_at DESC"
    )->fetchAll();

    foreach ($allActive as $dep) {
        if (isDepositProfitDue($dep)) {
            $dueDepositsList[] = $dep;
        }
    }
}

$pageTitle = 'صرف الأرباح المستحقة';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title"><i class="bi bi-wallet2 me-2"></i>صرف الأرباح المستحقة</h1>
                    <p class="text-muted small mb-0">مراجعة وصرف أرباح الودائع التي حان موعد استحقاقها اليوم.</p>
                </div>
                <?php if ($depositId): ?>
                    <a href="profit_run.php" class="btn btn-outline-gold btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> العودة لجدول الودائع المستحقة
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($targetDeposit && $results === null): ?>
                <!-- Single Deposit Payout Options Form -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="run-card text-center">
                            <div class="run-icon mb-3"><i class="bi bi-cash-stack"></i></div>
                            <h3 class="text-gold fw-bold mb-2">صرف أرباح الوديعة #<?= $targetDeposit['id'] ?></h3>
                            <p class="text-muted small mb-4">المستثمر: <strong><?= htmlspecialchars($targetDeposit['full_name']) ?></strong> | اختر طريقة الصرف والعملة أدناه.</p>

                            <form method="post" action="" onsubmit="if(!confirm('هل أنت متأكد من رغبتك في تنفيذ عملية صرف الأرباح الآن؟')) return false; this.querySelector('button[type=submit]').disabled=true;">
                                <?= csrfField() ?>
                                
                                <div class="card bg-base border border-gold text-start mx-auto p-4 mb-4" style="max-width: 620px; border-radius: 12px;">
                                    <h6 class="text-gold mb-3 border-bottom pb-2 fw-bold">
                                        <i class="bi bi-gear-wide-connected me-2"></i>خيارات طريقة صرف الأرباح
                                    </h6>
                                    
                                    <!-- Deposit Info Summary -->
                                    <div class="row g-3 mb-3 bg-dark p-3 rounded border border-secondary">
                                        <div class="col-6">
                                            <span class="text-muted small d-block">المستثمر:</span>
                                            <span class="fw-bold text-white"><?= htmlspecialchars($targetDeposit['full_name']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted small d-block">مبلغ الوديعة الأصلية:</span>
                                            <span class="fw-bold text-gold"><?= formatMoney($targetDeposit['amount'], $targetDeposit['currency']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted small d-block">الأرباح وفق النسب المعلنة:</span>
                                            <span class="fw-bold text-success"><?= formatMoney($targetDeposit['accumulated_profit'], $targetDeposit['currency']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted small d-block">تاريخ الاستحقاق الحالي:</span>
                                            <span class="text-white fw-bold"><?= formatDate(calcNextWithdrawalDate($targetDeposit)?->format('Y-m-d')) ?></span>
                                        </div>
                                    </div>

                                    <!-- Payout Method Selection -->
                                    <div class="mb-4">
                                        <label class="form-label text-gold fw-bold mb-2">حدد طريقة الصرف المطلوبة:</label>
                                        
                                        <div class="form-check mb-2 p-3 bg-dark rounded border border-secondary">
                                            <input class="form-check-input ms-2" type="radio" name="payout_method" id="method_declared" value="declared" <?= (float)$targetDeposit['accumulated_profit'] > 0 ? 'checked' : '' ?> onchange="togglePayoutMethod()">
                                            <label class="form-check-label text-white fw-bold" for="method_declared" style="cursor:pointer">
                                                1. صرف وفق النسب المعلنة والمحسوبة بالنظام (<?= formatMoney($targetDeposit['accumulated_profit'], $targetDeposit['currency']) ?>)
                                            </label>
                                        </div>

                                        <div class="form-check p-3 bg-dark rounded border border-secondary">
                                            <input class="form-check-input ms-2" type="radio" name="payout_method" id="method_custom" value="custom" <?= (float)$targetDeposit['accumulated_profit'] <= 0 ? 'checked' : '' ?> onchange="togglePayoutMethod()">
                                            <label class="form-check-label text-white fw-bold" for="method_custom" style="cursor:pointer">
                                                2. صرف مبلغ ربح يدوي مخصص (إدخال مبلغ يدوياً)
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Amount & Currency Input Group -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-7">
                                            <label class="form-label text-gold fw-bold mb-1">مبلغ الصرف الفعلي:</label>
                                            <input type="number" name="disburse_amount" id="disburse_amount_input" class="form-control text-center fw-bold form-control-lg" step="0.01" min="0.01" 
                                                   value="<?= htmlspecialchars((float)$targetDeposit['accumulated_profit']) ?>" required placeholder="0.00">
                                        </div>

                                        <div class="col-md-5">
                                            <label class="form-label text-gold fw-bold mb-1">عملة التسليم / الصرف:</label>
                                            <select name="currency" class="form-select form-select-lg bg-gold text-black fw-bold">
                                                <option value="USD" <?= ($targetDeposit['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>$ دولار (USD)</option>
                                                <option value="IQD" <?= ($targetDeposit['currency'] ?? 'USD') === 'IQD' ? 'selected' : '' ?>>د.ع دينار (IQD)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label text-gold fw-bold mb-1">الملاحظة وطبيعة العملية:</label>
                                        <input type="text" name="note" id="note_input" class="form-control form-control-sm" value="صرف أرباح الوديعة المستحقة">
                                    </div>
                                </div>

                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="submit" class="btn btn-gold btn-lg px-5">
                                        <i class="bi bi-play-fill me-2"></i> تأكيد وصرف الأرباح الآن
                                    </button>
                                    <a href="profit_run.php" class="btn btn-outline-secondary btn-lg px-4">إلغاء</a>
                                </div>
                            </form>

                            <script>
                            function togglePayoutMethod() {
                                var isCustom = document.getElementById('method_custom').checked;
                                var amountInput = document.getElementById('disburse_amount_input');
                                var noteInput = document.getElementById('note_input');
                                var declaredAmount = <?= (float)$targetDeposit['accumulated_profit'] ?>;
                                
                                if (isCustom) {
                                    amountInput.removeAttribute('readonly');
                                    amountInput.style.backgroundColor = '#1e1e2d';
                                    amountInput.style.color = '#fff';
                                    if (parseFloat(amountInput.value) === declaredAmount) {
                                        amountInput.value = '';
                                    }
                                    amountInput.focus();
                                    noteInput.value = 'صرف مبلغ ربح يدوي مخصص';
                                } else {
                                    amountInput.value = declaredAmount.toFixed(2);
                                    amountInput.setAttribute('readonly', 'readonly');
                                    amountInput.style.backgroundColor = '#151521';
                                    amountInput.style.color = '#2ecc71';
                                    noteInput.value = 'صرف أرباح وفق النسب المعلنة';
                                }
                            }
                            document.addEventListener('DOMContentLoaded', togglePayoutMethod);
                            </script>
                        </div>
                    </div>
                </div>

            <?php elseif ($results !== null): ?>
                <!-- Execution Results Card -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="result-card p-4 bg-base border border-success rounded text-center">
                            <h4 class="text-success fw-bold mb-3"><i class="bi bi-check-circle-fill me-2"></i>تمت عملية صرف الأرباح بنجاح</h4>
                            
                            <?php if (!empty($results['runErrors'])): ?>
                                <div class="alert alert-danger mb-4 text-start">
                                    <h6 class="fw-bold mb-2">ملاحظات / أخطاء:</h6>
                                    <ul class="mb-0 small">
                                        <?php foreach ($results['runErrors'] as $err): ?>
                                            <li><?= htmlspecialchars($err) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($results['detail'])): ?>
                                <div class="table-responsive mb-4 text-start">
                                    <table class="table table-dark-custom mb-0">
                                        <thead>
                                            <tr>
                                                <th>الإيصال</th>
                                                <th>المستثمر</th>
                                                <th>المبلغ المصروف</th>
                                                <th>تاريخ السحب القادم</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results['detail'] as $dt): ?>
                                                <tr>
                                                    <td class="fw-bold text-gold"><?= htmlspecialchars($dt['receipt_no']) ?></td>
                                                    <td><?= htmlspecialchars($dt['investor']) ?></td>
                                                    <td class="fw-bold text-success"><?= formatMoney($dt['disbursed'], $dt['currency']) ?></td>
                                                    <td><?= formatDate($dt['due_date']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <a href="profit_run.php" class="btn btn-gold px-4">
                                <i class="bi bi-arrow-right me-1"></i> العودة لجدول الودائع المستحقة
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Interactive Due Deposits Table -->
                <div class="card bg-base border border-secondary p-3 mb-4" style="border-radius:12px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-gold mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>الودائع المستحقة للصرف اليوم</h5>
                        <span class="badge bg-gold text-black font-monospace fs-6 px-3 py-2"><?= count($dueDepositsList) ?> وديعة مستحقة</span>
                    </div>

                    <?php if (empty($dueDepositsList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-shield-check text-success display-3 mb-3 d-block"></i>
                            <h4 class="text-white fw-bold">لا توجد ودائع مستحقة للصرف اليوم</h4>
                            <p class="text-muted small mb-0">جميع الودائع نشطة ومحدثة، وستظهر هنا أي وديعة عندما يحل موعد استحقاق أرباحها.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark-custom table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>المستثمر</th>
                                        <th>نوع الوديعة</th>
                                        <th>مبلغ الوديعة</th>
                                        <th>دورية السحب</th>
                                        <th>الأرباح وفق النسب المعلنة</th>
                                        <th>تاريخ الاستحقاق</th>
                                        <th>الإجراء المطلوب</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dueDepositsList as $d):
                                        $nextW = calcNextWithdrawalDate($d);
                                        $nextWStr = $nextW ? $nextW->format('Y-m-d') : null;
                                        ?>
                                        <tr>
                                            <td class="text-muted font-monospace"><?= $d['id'] ?></td>
                                            <td class="fw-bold text-white"><?= htmlspecialchars($d['full_name']) ?></td>
                                            <td><span class="badge <?= typeBadge($d['code']) ?>"><?= htmlspecialchars($d['type_name']) ?></span></td>
                                            <td class="fw-bold text-gold"><?= formatMoney($d['amount'], $d['currency']) ?></td>
                                            <td>كل <?= $d['profit_payout_frequency'] ?> شهر</td>
                                            <td class="fw-bold <?= $d['accumulated_profit'] > 0 ? 'text-success' : 'text-muted' ?>">
                                                <?= formatMoney($d['accumulated_profit'], $d['currency']) ?>
                                            </td>
                                            <td class="text-success fw-bold">
                                                <i class="bi bi-check-circle me-1"></i><?= formatDate($nextWStr) ?>
                                            </td>
                                            <td>
                                                <a href="profit_run.php?deposit_id=<?= $d['id'] ?>" class="btn btn-gold btn-sm fw-bold px-3">
                                                    <i class="bi bi-wallet2 me-1"></i> تحديد وصرف الأرباح
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer.php';
?>