<?php
// public/profit_run.php — Submit Profit Payout Approval Request
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('profits.request_payout');
$pdo = getPDO();

$depositId = isset($_GET['deposit_id']) ? (int) $_GET['deposit_id'] : null;
$targetDeposit = null;

if ($depositId) {
    $stmt = $pdo->prepare("
        SELECT d.*, i.full_name, dt.name_ar AS type_name 
        FROM deposits d 
        JOIN investors i ON i.id = d.investor_id 
        JOIN deposit_types dt ON dt.id = d.deposit_type_id 
        WHERE d.id = ?
    ");
    $stmt->execute([$depositId]);
    $targetDeposit = $stmt->fetch();

    if (!$targetDeposit || !in_array($targetDeposit['status'], ['active', 'completed'], true)) {
        setFlash('danger', 'الوديعة غير موجودة أو ملغاة.');
        header('Location: deposits.php');
        exit;
    }

    if (!isDepositProfitDue($targetDeposit)) {
        $nextW = calcNextWithdrawalDate($targetDeposit);
        $nextWStr = $nextW ? $nextW->format('Y-m-d') : '';
        setFlash('warning', 'عفواً، لا يجوز طلب صرف أرباح هذه الوديعة قبل حلول موعد استحقاق دوريتها القادمة بتاريخ: ' . formatDate($nextWStr));
        header('Location: deposits.php');
        exit;
    }
} else {
    setFlash('danger', 'يرجى اختيار وديعة محددة لطلب صرف الأرباح.');
    header('Location: deposits.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $manualAmount = isset($_POST['disburse_amount']) ? (float)$_POST['disburse_amount'] : null;
    $customNote = isset($_POST['note']) ? trim($_POST['note']) : '';
    $accumulated = (float)$targetDeposit['accumulated_profit'];

    $amountToDisburse = ($manualAmount !== null && $manualAmount > 0) ? $manualAmount : $accumulated;
    $note = $customNote ?: 'طلب صرف أرباح تراكمية مستحقة';

    if ($amountToDisburse <= 0) {
        $errors[] = 'مبلغ الصرف الفعلي يجب أن يكون أكبر من الصفر.';
    } elseif ($amountToDisburse > $accumulated) {
        $errors[] = 'عفواً، المبلغ المطلوب أكبر من رصيد الأرباح المتراكمة المتاح بالوديعة (' . formatMoney($accumulated, $targetDeposit['currency']) . ').';
    }

    $today = date('Y-m-d');
    $nextWithdrawal = calcNextWithdrawalDate($targetDeposit);
    $dueStr = $nextWithdrawal ? $nextWithdrawal->format('Y-m-d') : null;

    if (!$dueStr || $dueStr > $today) {
        $errors[] = 'عفواً، لا يجوز طلب صرف الأرباح للوديعة قبل موعد استحقاقها القادم بتاريخ: ' . formatDate($dueStr);
    }

    if (empty($errors)) {
        try {
            // Create Approval Request ONLY (Zero direct execution)
            $reqId = createApprovalRequest(
                $pdo,
                'profits.payout',
                'deposit',
                $targetDeposit['id'],
                [
                    'deposit_id' => $targetDeposit['id'],
                    'amount' => $amountToDisburse,
                    'note' => $note
                ]
            );

            setFlash('success', "تم رفع طلب موافقة لصرف أرباح الوديعة #{$targetDeposit['id']} بقيمة " . formatMoney($amountToDisburse, $targetDeposit['currency']) . " بنجاح (طلب رقم #{$reqId}). لن يتم الخصم أو الصرف حتى يتم اعتماده.");
            header('Location: deposits.php');
            exit;

        } catch (Throwable $e) {
            $errors[] = getSafeErrorMessage($e, 'حدث خطأ أثناء إرسال طلب صرف الأرباح.');
        }
    }
}

$pageTitle = 'طلب صرف أرباح وديعة';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-wallet2 me-2"></i>طلب صرف أرباح الوديعة #<?= $targetDeposit['id'] ?></h1>
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
                                <div class="col-12">
                                    <div class="card bg-base border border-secondary p-3 mb-2" style="border-radius:8px;">
                                        <h6 class="text-gold mb-3 fw-bold"><i class="bi bi-shield-check me-2"></i>معلومات الاستحقاق</h6>
                                        <div class="row g-2 text-start">
                                            <div class="col-md-6 mb-2">
                                                <span class="text-muted small d-block">المستثمر:</span>
                                                <span class="fw-bold text-white"><?= htmlspecialchars($targetDeposit['full_name']) ?></span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="text-muted small d-block">مبلغ الوديعة ونوعها:</span>
                                                <span class="fw-bold text-gold"><?= formatMoney($targetDeposit['amount'], $targetDeposit['currency']) ?> <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($targetDeposit['type_name']) ?></span></span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="text-muted small d-block">الأرباح المتراكمة المتاحة للصرف:</span>
                                                <span class="fw-bold text-success font-monospace" style="font-size:1.2rem"><?= formatMoney($targetDeposit['accumulated_profit'], $targetDeposit['currency']) ?></span>
                                            </div>
                                            <div class="col-md-6">
                                                <span class="text-muted small d-block">عملة الوديعة الإلزامية:</span>
                                                <span class="badge bg-gold text-dark fw-bold"><?= htmlspecialchars($targetDeposit['currency']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-white">المبلغ المطلوب صرفه <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="disburse_amount" class="form-control fw-bold" step="0.01" min="0.01" max="<?= (float)$targetDeposit['accumulated_profit'] ?>" value="<?= (float)$targetDeposit['accumulated_profit'] ?>" required>
                                        <span class="input-group-text bg-gold text-black fw-bold"><?= htmlspecialchars($targetDeposit['currency']) ?></span>
                                    </div>
                                    <div class="form-text text-muted small mt-1">يمكنك طلب صرف المبلغ بالكامل أو جزء منه.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-white">ملاحظات الطلب (اختياري)</label>
                                    <input type="text" name="note" class="form-control" placeholder="صرف أرباح تراكمية...">
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="deposits.php" class="btn btn-outline-gold">إلغاء</a>
                                <button type="submit" class="btn btn-gold px-4">
                                    <i class="bi bi-send me-1"></i> تقديم طلب الصرف للموافقة
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>