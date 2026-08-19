<?php
// public/profit_run.php — Disburse Accumulated Profits
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
    $stmt = $pdo->prepare("SELECT d.*, i.full_name FROM deposits d JOIN investors i ON i.id = d.investor_id WHERE d.id = ?");
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

    // Step 2: Fetch active deposits (either one or all)
    if ($depositId) {
        $activeDeposits = [$targetDeposit];
    } else {
        $activeDeposits = $pdo->query(
            "SELECT d.*, i.full_name
             FROM deposits d
             JOIN investors i ON i.id = d.investor_id
             WHERE d.status IN ('active', 'completed') AND d.accumulated_profit > 0"
        )->fetchAll();
    }

    $today = date('Y-m-d');
    $processed = 0;
    $skipped = 0;
    $totalDisbursed = 0.0;
    $detail = [];
    $runErrors = [];

    // Read manual amount or custom note if it's a single deposit payout
    $manualAmount = isset($_POST['disburse_amount']) ? (float)$_POST['disburse_amount'] : null;
    $customNote = isset($_POST['note']) ? trim($_POST['note']) : '';

    foreach ($activeDeposits as $dep) {
        $accumulated = (float) $dep['accumulated_profit'];

        $isManual = ($depositId && $manualAmount !== null);
        $amountToDisburse = $isManual ? $manualAmount : $accumulated;
        $note = $isManual ? ($customNote ?: 'صرف أرباح يدوية') : 'صرف أرباح تراكمية مستحقة';

        // Skip if no profit to disburse
        if ($amountToDisburse <= 0 && !$isManual) {
            if ($depositId)
                $runErrors[] = "لا توجد أرباح تراكمية لهذه الوديعة بعد.";
            $skipped++;
            continue;
        }

        if ($amountToDisburse <= 0) {
            if ($depositId)
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
                        $dep['currency'] ?? 'IQD',
                        $note
                    ]);

            // 2. Update accumulated_profit and last_withdrawal_date
            $useDueDate = ($dueStr && $dueStr <= $today) ? $dueStr : $today;
            $newAccumulated = max(0.00, $accumulated - $amountToDisburse);

            $pdo->prepare("UPDATE deposits SET accumulated_profit = ?, last_withdrawal_date = ? WHERE id = ?")
                ->execute([$newAccumulated, $useDueDate, $dep['id']]);

            $pdo->commit();

            $processed++;
            $totalDisbursed += $amountToDisburse;
            $detail[] = [
                'investor' => $dep['full_name'],
                'deposit_id' => $dep['id'],
                'amount' => $dep['amount'],
                'disbursed' => $amountToDisburse,
                'currency' => $dep['currency'] ?? 'IQD',
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

$pageTitle = 'صرف الأرباح التراكمية';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-wallet2 me-2"></i>صرف الأرباح التراكمية المستحقة</h1>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="run-card">
                        <div class="run-icon"><i class="bi bi-cash-stack"></i></div>
                        <h2 style="color:var(--gold);font-weight:700">
                            <?= $targetDeposit ? 'صرف الأرباح للوديعة #' . $targetDeposit['id'] : 'صرف الأرباح المستحقة لجميع الودائع' ?>
                        </h2>
                        <p class="text-muted" style="max-width:550px;margin:0 auto 1.5rem">
                            <?php if ($targetDeposit): ?>
                                سيتم فحص الوديعة <strong>#<?= $targetDeposit['id'] ?></strong> للمستثمر
                                <strong><?= htmlspecialchars($targetDeposit['full_name']) ?></strong>.
                                إذا كان قد حان موعد السحب التراكمي ولديها أرباح مجمعة، سيتم صرفها كعملية مالية رسمية.
                            <?php else: ?>
                                سيتم فحص <strong>جميع الودائع النشطة</strong>. أي وديعة وصلت لموعد السحب المتفق عليه (دورية
                                الربح) ولديها أرباح تراكمية سيتم تحويل هذه الأرباح مباشرة إلى سجل الصرفيات للمستثمرين.
                            <?php endif; ?>
                        </p>

                        <?php if ($results === null): ?>
                            <form method="post" action=""
                                onsubmit="if(!confirm('هل أنت متأكد من رغبتك في صرف الأرباح للمستحقين الآن؟ لا يمكن التراجع عن هذه العملية.')) return false; this.querySelector('button[type=submit]').disabled=true;">
                                <?= csrfField() ?>
                                
                                <?php if ($targetDeposit): ?>
                                    <div class="card bg-base border border-gold text-start mx-auto p-4 mb-4" style="max-width: 550px; border-radius: 12px;">
                                        <h5 class="text-gold mb-3 border-bottom pb-2" style="font-weight: 700;">
                                            <i class="bi bi-info-circle me-2"></i>تفاصيل الصرف اليدوي والتراكمي
                                        </h5>
                                        <div class="row g-3 mb-3">
                                            <div class="col-6">
                                                <span class="text-muted small d-block">المستثمر:</span>
                                                <span class="fw-bold text-white"><?= htmlspecialchars($targetDeposit['full_name']) ?></span>
                                            </div>
                                            <div class="col-6">
                                                <span class="text-muted small d-block">قيمة الوديعة:</span>
                                                <span class="fw-bold text-gold"><?= formatMoney($targetDeposit['amount'], $targetDeposit['currency']) ?></span>
                                            </div>
                                            <div class="col-6">
                                                <span class="text-muted small d-block">الأرباح التراكمية بالنظام:</span>
                                                <span class="fw-bold text-success"><?= formatMoney($targetDeposit['accumulated_profit'], $targetDeposit['currency']) ?></span>
                                            </div>
                                            <div class="col-6">
                                                <span class="text-muted small d-block">تاريخ البداية:</span>
                                                <span class="text-white"><?= formatDate($targetDeposit['start_date']) ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-gold fw-bold mb-1">مبلغ الصرف الفعلي (إدخال يدوي):</label>
                                            <div class="input-group">
                                                <input type="number" name="disburse_amount" class="form-control text-center fw-bold form-control-lg" step="0.01" min="0.01" 
                                                       value="<?= htmlspecialchars((float)$targetDeposit['accumulated_profit']) ?>" required placeholder="0.00">
                                                <span class="input-group-text bg-gold text-black fw-bold"><?= currencySymbol($targetDeposit['currency']) ?></span>
                                            </div>
                                            <div class="form-text text-muted small mt-1">يمكنك تعديل هذا المبلغ وإدخاله يدوياً وسيتم تصفير الأرباح التراكمية لهذه الدورة.</div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label text-gold fw-bold mb-1">الملاحظة:</label>
                                            <input type="text" name="note" class="form-control form-control-sm" value="صرف أرباح تراكمية مستحقة">
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="submit" class="btn btn-gold btn-lg px-5">
                                        <i class="bi bi-play-fill me-2"></i> تأكيد وصرف الآن
                                    </button>
                                    <a href="deposits.php" class="btn btn-outline-secondary btn-lg px-4">إلغاء</a>
                                </div>
                            </form>
                        <?php else: ?>

                            <!-- Errors if any -->
                            <?php if (!empty($results['runErrors'])): ?>
                                <div class="alert alert-danger mb-4">
                                    <h6 class="fw-bold mb-2">ملاحظات / أخطاء:</h6>
                                    <ul class="mb-0 small">
                                        <?php foreach ($results['runErrors'] as $err): ?>
                                            <li><?= htmlspecialchars($err) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Results -->
                            <div class="result-card">
                                <h5 style="color:#2ecc71"><i class="bi bi-check-circle-fill me-2"></i>اكتملت عملية المعالجة
                                </h5>
                                <div class="row g-3 mt-2 text-center">
                                    <div class="col-4">
                                        <div style="font-size:2rem;font-weight:800;color:var(--gold)">
                                            <?= $results['processed'] ?>
                                        </div>
                                        <div class="text-muted small">عمليات صرف نُفذت</div>
                                    </div>
                                    <div class="col-4">
                                        <div style="font-size:2rem;font-weight:800;color:var(--text-muted)">
                                            <?= $results['skipped'] ?>
                                        </div>
                                        <div class="text-muted small">تُركت لعدم الاستحقاق</div>
                                    </div>
                                    <div class="col-4">
                                        <div style="font-size:2rem;font-weight:800;color:#5dade2">
                                            <?= $results['closed'] ?>
                                        </div>
                                        <div class="text-muted small">ودائع أُغلقت تلقائياً</div>
                                    </div>
                                </div>
                                <hr class="divider my-3">
                                <div style="font-size:1.2rem;font-weight:800;color:var(--gold)">
                                    إجمالي الأرباح المصروفة:
                                    <?= formatMoney($results['totalDisbursed']) ?>
                                    <small class="text-muted" style="font-size:0.7rem">(Mixed)</small>
                                </div>
                            </div>

                            <?php if (!empty($results['detail'])): ?>
                                <div class="table-wrapper mt-3 text-start">
                                    <div class="table-responsive">
                                        <table class="table table-dark-custom table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>المستثمر</th>
                                                    <th>رقم الوديعة</th>
                                                    <th>العملة</th>
                                                    <th>القيمة المنصرفة</th>
                                                    <th>رقم الإيصال</th>
                                                    <th>تاريخ الاستحقاق</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($results['detail'] as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['investor']) ?></td>
                                                        <td>#<?= $row['deposit_id'] ?></td>
                                                        <td><?= currencyBadge($row['currency']) ?></td>
                                                        <td class="text-gold fw-bold">
                                                            <?= formatMoney($row['disbursed'], $row['currency']) ?>
                                                        </td>
                                                        <td><span
                                                                class="receipt-no"><?= htmlspecialchars($row['receipt_no']) ?></span>
                                                        </td>
                                                        <td><?= formatDate($row['due_date']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-4 d-flex gap-2 justify-content-center">
                                <a href="profit_run.php" class="btn btn-outline-gold">فحص مرة أخرى</a>
                                <a href="deposits.php" class="btn btn-gold">العودة للودائع</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>