<?php
// public/deposit_add_profit.php — Submit Manual Profit Approval Request
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('profits.request_manual');
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
$nextProfitStr = $nextProfitDate ? $nextProfitDate->format('Y-m-d') : null;

if (!$nextProfitStr || $nextProfitStr > date('Y-m-d')) {
    setFlash('danger', 'عفواً، لا يجوز طلب إضافة ربح تراكمي شهري لهذه الوديعة قبل حلول موعد استحقاق شهرها القادم بتاريخ: ' . formatDate($nextProfitStr));
    header('Location: deposits.php');
    exit;
}

$defaultMonth = $nextProfitDate ? $nextProfitDate->format('Y-m') : date('Y-m');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $month = trim($_POST['month'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $reason = trim($_POST['note'] ?? '');

    if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        $errors[] = 'الشهر المدخل غير صالح.';
    } elseif ($month > date('Y-m')) {
        $errors[] = 'عفواً، لا يجوز طلب ربح لشهر مستقبلي قبل حلول موعد استحقاقه.';
    }

    if ($amount <= 0) {
        $errors[] = 'قيمة الربح يجب أن تكون أكبر من الصفر.';
    }

    if (empty($reason)) {
        $errors[] = 'سبب الإضافة اليدوية للربح إجباري ولا يمكن تركه فارغاً.';
    }

    if (empty($errors)) {
        try {
            // Create Approval Request ONLY (Zero direct execution)
            $reqId = createApprovalRequest(
                $pdo,
                'profits.manual',
                'deposit',
                $depositId,
                [
                    'deposit_id' => $depositId,
                    'amount' => $amount,
                    'reason' => $reason,
                    'month' => $month
                ]
            );

            setFlash('success', "تم تقديم طلب إضافة الربح اليدوي بقيمة " . formatMoney($amount, $deposit['currency']) . " للوديعة #{$depositId} بنجاح (طلب رقم #{$reqId}). لن يُضاف الرصيد حتى يتم اعتماده من المسؤول.");
            header('Location: deposits.php');
            exit;

        } catch (Throwable $e) {
            $errors[] = getSafeErrorMessage($e, 'حدث خطأ أثناء رفع طلب إضافة الربح اليدوي.');
        }
    }
}

$pageTitle = 'طلب إضافة ربح يدوي للوديعة';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-plus-circle me-2"></i>طلب إضافة ربح يدوي</h1>
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
                                                <span class="text-muted small d-block">عملة الوديعة (مقفولة):</span>
                                                <span class="badge bg-gold text-dark fw-bold"><?= htmlspecialchars($deposit['currency']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-white">الشهر المستهدف للربح <span class="text-danger">*</span></label>
                                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($defaultMonth) ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-white">قيمة ربح هذا الشهر <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="amount" class="form-control fw-bold" step="0.01" min="0.01" required placeholder="0.00">
                                        <span class="input-group-text bg-gold text-black fw-bold"><?= htmlspecialchars($deposit['currency']) ?></span>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label text-white">سبب الإضافة اليدوية <span class="text-danger">*</span></label>
                                    <textarea name="note" class="form-control" rows="2" placeholder="أدخل السبب الفعلي لإضافة الربح..." required></textarea>
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="deposits.php" class="btn btn-outline-gold">إلغاء</a>
                                <button type="submit" class="btn btn-gold px-4">
                                    <i class="bi bi-send me-1"></i> إرسال طلب الموافقة
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
