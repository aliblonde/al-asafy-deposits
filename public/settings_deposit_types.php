<?php
// public/settings_deposit_types.php — Edit Profit Rates
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin']);  // Admin only
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $ids = $_POST['id'] ?? [];
    $rates = $_POST['rate'] ?? [];
    $mins = $_POST['min'] ?? [];
    $maxs = $_POST['max'] ?? [];

    foreach ($ids as $i => $id) {
        $id = (int) $id;
        $rate = (float) ($rates[$i] ?? 0);
        $min = (int) ($mins[$i] ?? 0);
        $max = (int) ($maxs[$i] ?? 0);

        if ($rate <= 0 || $rate > 1) {
            setFlash('warning', "النسبة يجب أن تكون بين 0 و 1 (مثال: 0.03800)");
            continue;
        }
        if ($min <= 0 || $max < $min) {
            setFlash('warning', "الأيام غير صحيحة للسجل #$id");
            continue;
        }

        // Fetch old
        $old = $pdo->prepare("SELECT * FROM deposit_types WHERE id=?")->execute([$id]) ?
            $pdo->prepare("SELECT * FROM deposit_types WHERE id=?") : null;
        $oldStmt = $pdo->prepare("SELECT * FROM deposit_types WHERE id=?");
        $oldStmt->execute([$id]);
        $oldRow = $oldStmt->fetch();

        if (!$oldRow)
            continue;

        $pdo->prepare(
            "UPDATE deposit_types SET min_rate=?, min_days=?, max_days=? WHERE id=?"
        )->execute([$rate, $min, $max, $id]);

        logActivity(
            $pdo,
            'UPDATE_DEPOSIT_TYPE',
            'deposit_types',
            $id,
            ['rate' => $oldRow['min_rate'], 'min' => $oldRow['min_days'], 'max' => $oldRow['max_days']],
            ['rate' => $rate, 'min' => $min, 'max' => $max]
        );
    }

    setFlash('success', 'تم تحديث نسب الأرباح بنجاح. تُطبق على الودائع الجديدة فقط.');
    header('Location: settings_deposit_types.php');
    exit;
}

$types = $pdo->query("SELECT * FROM deposit_types ORDER BY min_days")->fetchAll();

$pageTitle = 'إعداد نسب الأرباح';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-gear me-2"></i>إعداد نسب الأرباح</h1>
            </div>

            <div class="alert flash-info border mb-4 d-flex align-items-center gap-2" style="border-radius:8px">
                <i class="bi bi-info-circle-fill"></i>
                <span>أي تعديل على نسب الأرباح يُطبَّق على الودائع الجديدة فقط. الودائع القائمة تحتفظ بالنسبة المقفلة
                    عند إنشائها.</span>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <form method="post" action="">
                        <?= csrfField() ?>
                        <div class="form-card">
                            <div class="table-responsive">
                                <table class="table table-dark-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>نوع الوديعة</th>
                                            <th>الكود</th>
                                            <th>النسبة الشهرية (0.00000)</th>
                                            <th>معادلها %</th>
                                            <th>الحد الأدنى (أيام)</th>
                                            <th>الحد الأقصى (أيام)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($types as $i => $t): ?>
                                            <tr>
                                                <input type="hidden" name="id[]" value="<?= $t['id'] ?>">
                                                <td class="fw-bold">
                                                    <?= htmlspecialchars($t['name_ar']) ?>
                                                </td>
                                                <td><span class="badge <?= typeBadge($t['code']) ?>">
                                                        <?= arabicType($t['code']) ?>
                                                    </span></td>
                                                <td>
                                                    <input type="number" name="rate[]"
                                                        class="form-control form-control-sm rate-input" step="0.00001"
                                                        min="0.001" max="1" value="<?= $t['min_rate'] ?>"
                                                        style="width:130px" oninput="updatePct(this)">
                                                </td>
                                                <td>
                                                    <code class="pct-display" style="color:var(--gold-light)">
                        <?= number_format($t['min_rate'] * 100, 3) ?>%
                      </code>
                                                </td>
                                                <td>
                                                    <input type="number" name="min[]" class="form-control form-control-sm"
                                                        value="<?= $t['min_days'] ?>" min="1" max="360" style="width:80px">
                                                </td>
                                                <td>
                                                    <input type="number" name="max[]" class="form-control form-control-sm"
                                                        value="<?= $t['max_days'] ?>" min="1" max="360" style="width:80px">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <hr class="divider my-3">
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-gold px-5">
                                    <i class="bi bi-save me-1"></i> حفظ الإعدادات
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
        <?php
        $extraScript = <<<JS
<script>
function updatePct(input) {
  const val = parseFloat(input.value) || 0;
  const td  = input.closest('tr').querySelector('.pct-display');
  if (td) td.textContent = (val * 100).toFixed(3) + '%';
}
</script>
JS;
        include __DIR__ . '/../includes/footer.php'; ?>