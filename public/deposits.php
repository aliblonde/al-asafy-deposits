<?php
// public/deposits.php — Deposits Dashboard & Close Approval Submission
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('deposits.view');
$pdo = getPDO();

// Handle Deposit Completion Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_deposit_id'])) {
    verifyCsrf();
    requirePermission('deposits.request_close');

    $dId = (int) $_POST['complete_deposit_id'];

    $stmt = $pdo->prepare("
        SELECT d.*, 
               (SELECT COUNT(*) FROM transactions t WHERE t.deposit_id = d.id AND t.type IN ('withdraw','withdrawal_payout')) as withdraw_count 
        FROM deposits d 
        WHERE d.id = ? AND d.status IN ('active', 'completed')
    ");
    $stmt->execute([$dId]);
    $dep = $stmt->fetch();

    if (!$dep) {
        setFlash('danger', 'الوديعة غير موجودة أو ملغاة.');
    } elseif ((int)$dep['principal_refunded'] === 1) {
        setFlash('warning', 'عذراً، تم إرجاع رأس المال لهذه الوديعة وتأكيد إنهائها مسبقاً.');
    } elseif ($dep['end_date'] > date('Y-m-d') && empty($_POST['is_break'])) {
        setFlash('warning', 'لا يمكن إغلاق هذه الوديعة لأن تاريخ نهايتها لم يحن بعد (' . formatDate($dep['end_date']) . ').');
    } elseif (((float)$dep['accumulated_profit'] > 0 || isDepositProfitDue($dep) || isDepositMonthlyProfitDue($dep)) && empty($_POST['forfeit_profit'])) {
        setFlash('warning', 'الوديعة لا تزال لها أرباح متراكمة أو قيد الانتظار لم تسحب. يرجى سحب أو تسوية جميع الأرباح أولاً.');
    } else {
        try {
            // Create Approval Request ONLY (Zero direct execution)
            $reqId = createApprovalRequest(
                $pdo,
                'deposits.close',
                'deposit',
                $dId,
                [
                    'deposit_id' => $dId,
                    'is_break' => !empty($_POST['is_break']) ? 1 : 0,
                    'forfeit_profit' => !empty($_POST['forfeit_profit']) ? 1 : 0
                ]
            );

            setFlash('info', 'تم تقديم طلب إنهاء الوديعة وإرجاع رأس المال للوديعة #' . $dId . ' بنجاح (طلب رقم #' . $reqId . '). لن يتغير وضع الوديعة أو يُصرف رأس المال حتى يتم اعتماده.');

        } catch (Throwable $e) {
            setFlash('danger', getSafeErrorMessage($e, 'حدث خطأ أثناء تقديم طلب إنهاء الوديعة.'));
        }
    }
    header('Location: deposits.php');
    exit;
}

// Filters
$fStatus = $_GET['status'] ?? '';
$fType = $_GET['type'] ?? '';
$fCurrency = $_GET['currency'] ?? '';
$fInvestor = (int) ($_GET['investor_id'] ?? 0);
$fDateFrom = $_GET['date_from'] ?? '';
$fDateTo = $_GET['date_to'] ?? '';

$where = ['1=1'];
$params = [];

if ($fStatus) {
    $where[] = 'd.status = ?';
    $params[] = $fStatus;
}
if ($fType) {
    $where[] = 'dt.code = ?';
    $params[] = $fType;
}
if ($fCurrency) {
    $where[] = 'd.currency = ?';
    $params[] = $fCurrency;
}
if ($fInvestor) {
    $where[] = 'd.investor_id = ?';
    $params[] = $fInvestor;
}
if ($fDateFrom) {
    $where[] = 'd.start_date >= ?';
    $params[] = $fDateFrom;
}
if ($fDateTo) {
    $where[] = 'd.start_date <= ?';
    $params[] = $fDateTo;
}

$whereClause = implode(' AND ', $where);

$deposits = $pdo->prepare(
    "SELECT d.*, i.full_name, dt.name_ar, dt.code,
     (SELECT COUNT(*) FROM transactions t WHERE t.deposit_id = d.id AND t.type IN ('withdraw','withdrawal_payout')) as withdraw_count
     FROM deposits d
     JOIN investors i   ON i.id = d.investor_id
     JOIN deposit_types dt ON dt.id = d.deposit_type_id
     WHERE $whereClause
     ORDER BY d.created_at DESC"
);
$deposits->execute($params);
$deposits = $deposits->fetchAll();

$investors = $pdo->query("SELECT id, full_name FROM investors ORDER BY full_name")->fetchAll();

$pageTitle = 'الودائع';
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
                    <h1 class="page-title"><i class="bi bi-bank me-2"></i>الودائع</h1>
                    <p class="page-subtitle">إجمالي النتائج: <?= count($deposits) ?> وديعة</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if (userCan('deposits.create')): ?>
                        <a href="deposit_add.php" class="btn btn-gold">
                            <i class="bi bi-plus-lg me-1"></i> إضافة وديعة
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="filter-bar mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">— الكل —</option>
                            <?php foreach (['active' => 'نشطة', 'completed' => 'منتهية', 'cancelled' => 'ملغاة', 'defaulted' => 'متعثرة'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $fStatus === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">العملة</label>
                        <select name="currency" class="form-select form-select-sm">
                            <option value="">— الكل —</option>
                            <option value="IQD" <?= $fCurrency === 'IQD' ? 'selected' : '' ?>>د.ع دينار</option>
                            <option value="USD" <?= $fCurrency === 'USD' ? 'selected' : '' ?>>$ دولار</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">النوع</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">— الكل —</option>
                            <?php foreach (['6_months' => '6 أشهر', '1_year' => 'سنة', '2_years' => 'سنتين', '3_years' => '3 سنوات'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $fType === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">المستثمر</label>
                        <select name="investor_id" class="form-select form-select-sm">
                            <option value="">— الكل —</option>
                            <?php foreach ($investors as $inv): ?>
                                <option value="<?= $inv['id'] ?>" <?= $fInvestor === (int) $inv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($inv['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($fDateFrom) ?>">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($fDateTo) ?>">
                    </div>

                    <div class="col-12 mt-3 text-start">
                        <button type="submit" class="btn btn-gold btn-sm px-4"><i class="bi bi-search me-1"></i> تصفية</button>
                        <a href="deposits.php" class="btn btn-outline-gold btn-sm px-4"><i class="bi bi-x me-1"></i> إعادة ضبط</a>
                    </div>
                </div>
            </form>

            <div class="table-wrapper">
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستثمر</th>
                                <th>النوع</th>
                                <th>المبلغ والعملة</th>
                                <th>مدة الوديعة</th>
                                <th>دورية السحب</th>
                                <th>الأرباح المتراكمة</th>
                                <th>تاريخ آخر سحب</th>
                                <th>الاستحقاق للصرف</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deposits as $d):
                                $next = calcNextWithdrawalDate($d);
                                $nextStr = $next ? $next->format('Y-m-d') : null;
                                $hasProfit = $d['accumulated_profit'] > 0;
                                $isDue = isDepositProfitDue($d);
                                $isMonthlyProfitDue = isDepositMonthlyProfitDue($d);
                                $hasUndisbursedProfit = $hasProfit || $isDue || $isMonthlyProfitDue;

                                $isReadyToClose = ($d['end_date'] <= date('Y-m-d') && !$hasUndisbursedProfit && (int)$d['principal_refunded'] === 0);
                                $isPendingClosure = ($d['end_date'] <= date('Y-m-d')) && $hasUndisbursedProfit && (int)$d['principal_refunded'] === 0;
                                ?>
                                <tr>
                                    <td class="text-muted"><?= $d['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($d['full_name']) ?></td>
                                    <td><span class="badge <?= typeBadge($d['code']) ?>"><?= htmlspecialchars($d['name_ar']) ?></span></td>
                                    <td>
                                        <div class="fw-bold text-gold"><?= formatMoney($d['amount'], $d['currency'] ?? 'IQD') ?></div>
                                        <div><?= currencyBadge($d['currency'] ?? 'IQD') ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size:0.85rem"><i class="bi bi-play-circle text-success me-1"></i><?= formatDate($d['start_date']) ?></div>
                                        <div style="font-size:0.85rem"><i class="bi bi-stop-circle text-danger me-1"></i><?= formatDate($d['end_date']) ?></div>
                                    </td>
                                    <td>كل <?= $d['profit_payout_frequency'] ?> شهر</td>
                                    <td>
                                        <div class="fw-bold <?= $d['accumulated_profit'] > 0 ? 'text-success' : 'text-muted' ?>">
                                            <?= formatMoney($d['accumulated_profit'], $d['currency'] ?? 'IQD') ?>
                                        </div>
                                    </td>
                                    <td><?= formatDate($d['last_withdrawal_date']) ?></td>
                                    <td>
                                        <?php if ($d['status'] === 'active'): ?>
                                            <?php if ($nextStr): ?>
                                                <div style="font-size:0.9rem"><?= formatDate($nextStr) ?></div>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td><span class="badge <?= statusBadge($d['status']) ?>"><?= arabicStatus($d['status']) ?></span></td>
                                    <td>
                                          <div class="d-flex flex-wrap gap-1 align-items-center">
                                              <?php if ($d['status'] === 'active'): ?>
                                                  <?php if ($isDue && userCan('profits.request_payout')): ?>
                                                      <a href="profit_run.php?deposit_id=<?= $d['id'] ?>" class="btn btn-sm btn-gold fw-bold px-3" title="طلب صرف الأرباح">
                                                          <i class="bi bi-wallet2 me-1"></i> طلب صرف
                                                      </a>
                                                  <?php endif; ?>

                                                  <?php if ((int)$d['profit_payout_frequency'] > 1 && $isMonthlyProfitDue && userCan('profits.request_manual')): ?>
                                                      <a href="deposit_add_profit.php?deposit_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-success" title="طلب إضافة ربح شهري تراكمي">
                                                          <i class="bi bi-plus-circle me-1"></i> ربح تراكمي
                                                      </a>
                                                  <?php endif; ?>

                                                  <?php if (userCan('deposits.request_close')): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#closeModal<?= $d['id'] ?>" title="طلب إنهاء أو كسر الوديعة">
                                                        <i class="bi bi-x-octagon"></i> إنهاء
                                                    </button>
                                                    
                                                    <div class="modal fade" id="closeModal<?= $d['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <form method="post" class="modal-content bg-dark">
                                                                <div class="modal-header border-secondary">
                                                                    <h5 class="modal-title">طلب إنهاء الوديعة #<?= $d['id'] ?></h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body border-secondary text-start text-white">
                                                                    <p>هل أنت متأكد من تقديم طلب لإنهاء هذه الوديعة؟</p>
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="complete_deposit_id" value="<?= $d['id'] ?>">
                                                                    
                                                                    <?php if ($d['end_date'] > date('Y-m-d')): ?>
                                                                    <div class="form-check mt-3" dir="rtl">
                                                                        <input class="form-check-input float-end ms-2" type="checkbox" name="is_break" value="1" id="break<?= $d['id'] ?>">
                                                                        <label class="form-check-label text-warning pe-4" for="break<?= $d['id'] ?>">
                                                                            تأكيد كسر الوديعة قبل تاريخ الانتهاء (<?= date('Y/m/d', strtotime($d['end_date'])) ?>)
                                                                        </label>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    
                                                                    <div class="form-check mt-2" dir="rtl">
                                                                        <input class="form-check-input float-end ms-2" type="checkbox" name="forfeit_profit" value="1" id="forfeit<?= $d['id'] ?>">
                                                                        <label class="form-check-label text-danger pe-4" for="forfeit<?= $d['id'] ?>">
                                                                            مصادرة جميع الأرباح التراكمية (إرجاع رأس المال فقط)
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer border-secondary">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                    <button type="submit" class="btn btn-danger">تأكيد الطلب</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                  <?php if (userCan('deposits.update')): ?>
                                                      <a href="deposit_add.php?edit=<?= $d['id'] ?>" class="btn btn-sm btn-outline-gold" title="تعديل الوديعة">
                                                          <i class="bi bi-pencil"></i>
                                                      </a>
                                                  <?php endif; ?>
                                              <?php endif; ?>
                                          </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($deposits)): ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">لا توجد ودائع تطابق الفلاتر</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>