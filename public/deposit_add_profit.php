<?php
// public/deposit_add_profit.php — Add manual monthly profit to accumulation pool
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

$depositId = (int)($_GET['deposit_id'] ?? 0);
if (!$depositId) {
    header('Location: deposits.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.*, i.full_name, dt.name_ar AS type_name 
    FROM deposits d
    JOIN investors i ON i.id = d.investor_id
    JOIN deposit_types dt ON dt.id = d.deposit_type_id
    WHERE d.id = ? AND d.status = 'active'
");
$stmt->execute([$depositId]);
$deposit = $stmt->fetch();

if (!$deposit) {
    setFlash('danger', 'الوديعة غير موجودة، ملغاة، أو غير نشطة.');
    header('Location: deposits.php');
    exit;
}

$nextProfitDate = calcNextProfitDate($deposit);
$defaultMonth = $nextProfitDate ? $nextProfitDate->format('Y-m') : date('Y-m');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $month = trim($_POST['month'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        $errors[] = 'الشهر المدخل غير صالح.';
    } elseif ($month > date('Y-m')) {
        $errors[] = 'عفواً، لا يجوز إضافة أو صرف أرباح لشهر مستقبلي قبل حلول موعد استحقاقه.';
    }
    
    if ($amount <= 0) {
        $errors[] = 'قيمة الربح يجب أن تكون أكبر من الصفر.';
    }

    if (empty($errors)) {
        $cycleDate = date('Y-m-t', strtotime($month . '-01'));

        // Check if a profit cycle already exists for this exact deposit & month to prevent double entry
        $pcCheck = $pdo->prepare("SELECT id FROM profit_cycles WHERE deposit_id = ? AND cycle_date = ?");
        $pcCheck->execute([$depositId, $cycleDate]);

        if ($pcCheck->rowCount() > 0) {
            $errors[] = "لقد تم احتساب أو إضافة الأرباح لهذه الوديعة لشهر {$month} مسبقاً.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert into profit_cycles
                $pcIns = $pdo->prepare("INSERT INTO profit_cycles (deposit_id, cycle_date, processed_at) VALUES (?, ?, NOW())");
                $pcIns->execute([$depositId, $cycleDate]);

                // 2. Anniversary date in the target month (e.g. 2026-07-15)
                $dayOfDeposit = date('d', strtotime($deposit['start_date']));
                $daysInTarget = date('t', strtotime($month . '-01'));
                if ($dayOfDeposit > $daysInTarget) {
                    $dayOfDeposit = $daysInTarget;
                }
                $anniversaryDate = $month . '-' . str_pad($dayOfDeposit, 2, '0', STR_PAD_LEFT);

                // 3. Update the deposit's accumulated profit & last_profit_date
                $upd = $pdo->prepare("
                    UPDATE deposits 
                    SET accumulated_profit = accumulated_profit + ?,
                        last_profit_date = ? 
                    WHERE id = ?
                ");
                $upd->execute([$amount, $anniversaryDate, $depositId]);

                $pdo->commit();

                // 4. Log the action
                logActivity($pdo, 'ADD_MANUAL_PROFIT', 'deposits', $depositId, null, [
                    'amount' => $amount,
                    'currency' => $deposit['currency'],
                    'month' => $month,
                    'anniversary_date' => $anniversaryDate,
                    'note' => $note ?: "إضافة ربح تراكمي يدوي لشهر $month"
                ]);

                setFlash('success', "تم إضافة ربح يدوي بقيمة " . formatMoney($amount, $deposit['currency']) . " للوديعة #{$depositId} بنجاح وتراكمها للتسليم.");
                header('Location: deposits.php');
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'حدث خطأ أثناء الحفظ: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'إضافة ربح يدوي للوديعة';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-plus-circle me-2"></i>إضافة ربح يدوي تراكمي</h1>
                </div>
                <a href="deposits.php" class="btn btn-outline-gold">
                    <i class="bi bi-arrow-right me-1"></i> العودة للودائع
                </a>
            </div>

            <?php if ($errors): ?>
                <div class="alert flash-danger border mb-3" style="border-radius:8px">
                    <ul class="mb-0 pe-3">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="form-card">
                        <form method="post" action="">
                            <?= csrfField() ?>

                            <div class="row g-3">
                                <!-- Deposit Details Card -->
                                <div class="col-12">
                                    <div class="card bg-base border border-secondary p-3 mb-2" style="border-radius:8px;">
                                        <h6 class="text-gold mb-3 fw-bold"><i class="bi bi-shield-check me-2"></i>معلومات الوديعة</h6>
                                        <div class="row g-2 text-start">
                                            <div class="col-md-6 mb-2">
                                                <span class="text-muted small d-block">المستثمر:</span>
                                                <span class="fw-bold text-white"><?= htmlspecialchars($deposit['full_name']) ?></span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="text-muted small d-block">نوع وقيمة الوديعة:</span>
                                                <span class="fw-bold text-gold"><?= formatMoney($deposit['amount'], $deposit['currency']) ?> <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($deposit['type_name']) ?></span></span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="text-muted small d-block">الأرباح المتراكمة الحالية:</span>
                                                <span class="fw-bold text-success"><?= formatMoney($deposit['accumulated_profit'], $deposit['currency']) ?></span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="text-muted small d-block">تاريخ البداية والنهاية:</span>
                                                <span class="text-white small"><?= formatDate($deposit['start_date']) ?> إلى <?= formatDate($deposit['end_date']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Month Selection -->
                                <div class="col-md-6">
                                    <label class="form-label text-white">الشهر المستهدف للربح <span class="text-danger">*</span></label>
                                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($defaultMonth) ?>" required>
                                    <div class="form-text text-muted small mt-1">يُقترح تلقائياً الشهر المستحق القادم للوديعة.</div>
                                </div>

                                <!-- Profit Amount -->
                                <div class="col-md-6">
                                    <label class="form-label text-white">قيمة ربح هذا الشهر <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="amount" class="form-control fw-bold" step="0.01" min="0.01" required placeholder="0.00">
                                        <span class="input-group-text bg-gold text-black fw-bold"><?= currencySymbol($deposit['currency']) ?></span>
                                    </div>
                                    <div class="form-text text-muted small mt-1">أدخل القيمة يدوياً ليتم تراكمها في حافظة الوديعة.</div>
                                </div>

                                <!-- Note -->
                                <div class="col-12">
                                    <label class="form-label text-white">الملاحظة (اختياري)</label>
                                    <textarea name="note" class="form-control" rows="2" placeholder="أدخل أي ملاحظات حول كيفية حساب الأرباح..."></textarea>
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="deposits.php" class="btn btn-outline-gold">إلغاء</a>
                                <button type="submit" class="btn btn-gold px-4">
                                    <i class="bi bi-check-circle me-1"></i> حفظ وتراكم الأرباح
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer.php';
?>
