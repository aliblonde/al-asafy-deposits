<?php
// public/declare_rates.php — Declare Monthly Profit Rates Approval Request
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('rates.request_declaration');
$pdo = getPDO();

$types = $pdo->query("SELECT * FROM deposit_types ORDER BY min_days")->fetchAll();
$month = $_POST['month'] ?? date('Y-m');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rates'])) {
    verifyCsrf();

    $submittedRates = $_POST['rates']; // array: type_id => rate_percent

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $errors[] = 'الشهر المختار غير صالح.';
    } elseif ($month > date('Y-m')) {
        $errors[] = 'عفواً، لا يجوز طلب إعلان نسب أرباح لشهر مستقبلي قبل حلول موعد استحقاقه.';
    }

    if (empty($errors)) {
        $submittedCount = 0;
        try {
            foreach ($types as $t) {
                $tid = (int)$t['id'];
                if (!isset($submittedRates[$tid])) continue;

                $rate = (float)$submittedRates[$tid];
                if ($rate <= 0) continue;

                $minRate = (float)$t['min_rate'] * 100;
                $maxRate = (float)$t['max_rate'] * 100;

                if ($rate < $minRate || $rate > $maxRate) {
                    $errors[] = "النسبة للنوع {$t['name_ar']} ($rate%) خارج الحدود المسموحة ($minRate% - $maxRate%).";
                    continue;
                }

                // Create Approval Request ONLY (Zero direct execution)
                createApprovalRequest(
                    $pdo,
                    'rates.declaration',
                    'deposit_type',
                    $tid,
                    [
                        'month' => $month,
                        'deposit_type_id' => $tid,
                        'rate' => $rate
                    ]
                );
                $submittedCount++;
            }

            if (empty($errors) && $submittedCount > 0) {
                setFlash('success', "تم إرسال طلبات إعلان نسب الأرباح لشهر $month بنجاح للأنواع المحددة. لن يتم تطبيق النسب والتأثير المحاسبي حتى يتم اعتماد الطلبات.");
                header('Location: declare_rates.php?month=' . urlencode($month));
                exit;
            }

        } catch (Throwable $e) {
            $errors[] = getSafeErrorMessage($e, 'حدث خطأ أثناء رفع طلب إعلان النسب.');
        }
    }
}

// Fetch historical declarations
$history = $pdo->query("
    SELECT rd.*, dt.name_ar, u.full_name AS creator_name
    FROM rate_declarations rd
    JOIN deposit_types dt ON dt.id = rd.deposit_type_id
    LEFT JOIN users u ON u.id = rd.created_by
    ORDER BY rd.month DESC, dt.min_days ASC
")->fetchAll();

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
                <div>
                    <h1 class="page-title"><i class="bi bi-percent me-2"></i>طلب إعلان نسب الأرباح الشهرية</h1>
                </div>
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

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="form-card">
                        <form method="post" action="">
                            <?= csrfField() ?>

                            <div class="mb-3">
                                <label class="form-label text-white">الشهر المستهدف <span class="text-danger">*</span></label>
                                <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>" required>
                            </div>

                            <div class="table-responsive mb-3">
                                <table class="table table-dark-custom align-middle">
                                    <thead>
                                        <tr>
                                            <th>نوع الوديعة</th>
                                            <th>الحد الأدنى والأقصى</th>
                                            <th>النسبة الشهرية %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($types as $t): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($t['name_ar']) ?></td>
                                                <td class="text-muted small"><?= ($t['min_rate']*100) ?>% - <?= ($t['max_rate']*100) ?>%</td>
                                                <td>
                                                    <input type="number" name="rates[<?= $t['id'] ?>]" class="form-control form-control-sm fw-bold" step="0.01" min="<?= $t['min_rate']*100 ?>" max="<?= $t['max_rate']*100 ?>" placeholder="0.00">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-gold w-100">
                                <i class="bi bi-send me-1"></i> إرسال طلب إعلان النسب للموافقة
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card bg-base border border-secondary p-3">
                        <h6 class="text-gold mb-3 fw-bold"><i class="bi bi-clock-history me-2"></i>سجل الإعلانات السابقة</h6>
                        <div class="table-responsive">
                            <table class="table table-dark-custom table-sm text-center">
                                <thead>
                                    <tr>
                                        <th>الشهر</th>
                                        <th>النوع</th>
                                        <th>النسبة %</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $h): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($h['month']) ?></td>
                                            <td><?= htmlspecialchars($h['name_ar']) ?></td>
                                            <td class="fw-bold text-success"><?= $h['declared_rate_monthly'] ?>%</td>
                                            <td><span class="badge <?= statusBadge($h['status']) ?>"><?= arabicStatus($h['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($history)): ?>
                                        <tr><td colspan="4" class="text-muted">لا توجد إعلانات مسجلة</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
