<?php
// public/deposits.php — Deposits List with Filters
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

// Handle Deposit Completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_deposit_id'])) {
    verifyCsrf();
    $dId = (int) $_POST['complete_deposit_id'];

    $stmt = $pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM transactions t WHERE t.deposit_id = d.id AND t.type = 'withdraw') as withdraw_count FROM deposits d WHERE d.id = ? AND d.status IN ('active', 'completed')");
    $stmt->execute([$dId]);
    $dep = $stmt->fetch();

    if (!$dep) {
        setFlash('danger', 'الوديعة غير موجودة أو ملغاة.');
    } elseif ($dep['withdraw_count'] > 0) {
        setFlash('warning', 'عذراً، تم إرجاع رأس المال لهذه الوديعة مسبقاً.');
    } elseif ($dep['end_date'] > date('Y-m-d')) {
        setFlash('warning', 'لا يمكن إنهاء الوديعة قبل تاريخ استحقاقها.');
    } elseif ((float) $dep['accumulated_profit'] > 0 || isDepositProfitDue($dep) || isDepositMonthlyProfitDue($dep)) {
        setFlash('warning', 'عفواً، لا يمكن إنهاء الوديعة وإرجاع رأس المال حتى يتم صرف جميع أرباحها التراكمية والشهرية المستحقة أولاً.');
    } else {
        try {
            $pdo->beginTransaction();
            $receiptNo = generateReceiptNo($pdo);

            $pdo->prepare(
                "INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                 VALUES (?, ?, ?, 'withdraw', ?, ?, NOW(), ?)"
            )->execute([
                        $receiptNo,
                        $dep['investor_id'],
                        $dep['id'],
                        $dep['amount'],
                        $dep['currency'] ?? 'IQD',
                        'إرجاع رأس المال وإنهاء الوديعة'
                    ]);

            $pdo->prepare("UPDATE deposits SET status = 'completed', last_withdrawal_date = CURDATE() WHERE id = ?")
                ->execute([$dep['id']]);

            $pdo->commit();
            logActivity($pdo, 'COMPLETE_DEPOSIT', 'deposits', $dep['id'], null, [
                'receipt_no' => $receiptNo,
                'refunded_amount' => $dep['amount'],
                'currency' => $dep['currency'] ?? 'IQD'
            ]);

            setFlash('success', 'تم إنهاء الوديعة وإرجاع رأس المال بنجاح. بموجب الإيصال رقم: ' . $receiptNo);
        } catch (Exception $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            setFlash('danger', 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage());
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
     (SELECT COUNT(*) FROM transactions t WHERE t.deposit_id = d.id AND t.type = 'withdraw') as withdraw_count
     FROM deposits d
     JOIN investors i   ON i.id = d.investor_id
     JOIN deposit_types dt ON dt.id = d.deposit_type_id
     WHERE $whereClause
     ORDER BY d.created_at DESC"
);
$deposits->execute($params);
$deposits = $deposits->fetchAll();

// For investor dropdown
$investors = $pdo->query("SELECT id, full_name FROM investors ORDER BY full_name")->fetchAll();

$today = new DateTimeImmutable(date('Y-m-d'));

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
                    <p class="page-subtitle">إجمالي النتائج:
                        <?= count($deposits) ?> وديعة
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="profit_run.php" class="btn btn-outline-gold"
                        onclick="return confirm('هل أنت متأكد من رغبتك في الانتقال لصفحة صرف أرباح جميع الودائع؟');">
                        <i class="bi bi-cash-stack me-1"></i> صرف أرباح الكل
                    </a>
                    <a href="/al-asafy-deposits/public/deposit_add.php" class="btn btn-gold">
                        <i class="bi bi-plus-lg me-1"></i> إضافة وديعة
                    </a>
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
                                <option value="<?= $v ?>" <?= $fStatus === $v ? 'selected' : '' ?>>
                                    <?= $l ?>
                                </option>
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
                                <option value="<?= $v ?>" <?= $fType === $v ? 'selected' : '' ?>>
                                    <?= $l ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">المستثمر</label>
                        <select name="investor_id" class="form-select form-select-sm">
                            <option value="">— الكل —</option>
                            <?php foreach ($investors as $inv): ?>
                                <option value="<?= $inv['id'] ?>" <?= $fInvestor === (int) $inv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inv['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($fDateFrom) ?>">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($fDateTo) ?>">
                    </div>

                    <div class="col-12 mt-3 text-start">
                        <button type="submit" class="btn btn-gold btn-sm px-4"><i class="bi bi-search me-1"></i>
                            تصفية</button>
                        <a href="deposits.php" class="btn btn-outline-gold btn-sm px-4"><i class="bi bi-x me-1"></i>
                            إعادة ضبط</a>
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
                                <th>الأرباح التراكمية</th>
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

                                $isReadyToClose = ($d['end_date'] <= date('Y-m-d') && !$hasUndisbursedProfit && $d['withdraw_count'] == 0) || ($d['status'] === 'completed' && !$hasUndisbursedProfit && $d['withdraw_count'] == 0);
                                $isPendingClosure = ($d['end_date'] <= date('Y-m-d') || $d['status'] === 'completed') && $hasUndisbursedProfit && $d['withdraw_count'] == 0;

                                $diffExpiry = null;
                                if ($d['status'] === 'active' && $d['end_date']) {
                                    $endDateObj = new DateTimeImmutable($d['end_date']);
                                    $todayObj = new DateTimeImmutable(date('Y-m-d'));
                                    $diffExpiry = (int) $todayObj->diff($endDateObj)->format('%r%a'); // negative if passed
                                }
                                ?>
                                <tr>
                                    <td class="text-muted"><?= $d['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($d['full_name']) ?></td>
                                    <td><span
                                            class="badge <?= typeBadge($d['code']) ?>"><?= htmlspecialchars($d['name_ar']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-gold">
                                            <?= formatMoney($d['amount'], $d['currency'] ?? 'IQD') ?>
                                        </div>
                                        <div><?= currencyBadge($d['currency'] ?? 'IQD') ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size:0.85rem"><i
                                                class="bi bi-play-circle text-success me-1"></i><?= formatDate($d['start_date']) ?>
                                        </div>
                                        <div style="font-size:0.85rem"><i
                                                class="bi bi-stop-circle text-danger me-1"></i><?= formatDate($d['end_date']) ?>
                                            <?php if ($diffExpiry !== null): ?>
                                                <?php if ($diffExpiry < 0): ?>
                                                    <span class="badge bg-danger mt-1 d-block" style="font-size:0.7rem">منتهية
                                                        الصلاحية</span>
                                                <?php elseif ($diffExpiry === 0): ?>
                                                    <span class="badge bg-danger mt-1 d-block" style="font-size:0.7rem">تنتهي
                                                        اليوم</span>
                                                <?php elseif ($diffExpiry <= 3): ?>
                                                    <span class="badge bg-warning mt-1 d-block" style="font-size:0.7rem">تنتهي خلال
                                                        <?= $diffExpiry ?> أيام</span>
                                                <?php elseif ($diffExpiry <= 7): ?>
                                                    <span class="badge bg-info mt-1 d-block" style="font-size:0.7rem">تنتهي خلال
                                                        <?= $diffExpiry ?> أيام</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        كل <?= $d['profit_payout_frequency'] ?> شهر
                                    </td>
                                    <td>
                                        <div
                                            class="fw-bold <?= $d['accumulated_profit'] > 0 ? 'text-success' : 'text-muted' ?>">
                                            <?= formatMoney($d['accumulated_profit'], $d['currency'] ?? 'IQD') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= formatDate($d['last_withdrawal_date']) ?>
                                    </td>
                                    <td>
                                        <?php if ($d['status'] === 'active'): ?>
                                            <?php if ($nextStr): ?>
                                                <div style="font-size:0.9rem">
                                                    <?= formatDate($nextStr) ?>
                                                </div>
                                                <?php if ($isDue): ?>
                                                    <div style="font-size:0.75rem"
                                                        class="<?= $hasProfit ? 'text-success fw-bold' : 'text-warning' ?>">
                                                        <?= $hasProfit ? '<i class="bi bi-check-circle me-1"></i>مستحق الآن' : '<i class="bi bi-hourglass-split me-1"></i>بانتظار إعلان الأرباح' ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="font-size:0.75rem" class="text-muted">
                                                        <i class="bi bi-calendar me-1"></i>جاري الانتظار
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        <?php else:
                                            echo '—';
                                        endif; ?>
                                    </td>
                                    <td><span class="badge <?= statusBadge($d['status']) ?>">
                                            <?= arabicStatus($d['status']) ?>
                                        </span></td>
                                    <td>
                                         <div class="d-flex flex-wrap gap-1 align-items-center">
                                             <?php if ($d['status'] === 'active'): ?>
                                                 <?php if ($isDue): ?>
                                                     <a href="profit_run.php?deposit_id=<?= $d['id'] ?>" class="btn btn-sm btn-gold fw-bold px-3"
                                                         title="صرف الأرباح (تراكمية أو مبلغ يدوي مخصص)">
                                                         <i class="bi bi-wallet2 me-1"></i> صرف الأرباح
                                                     </a>
                                                 <?php else: ?>
                                                     <button class="btn btn-sm btn-outline-secondary" disabled
                                                         title="غير مستحقة للصرف بعد (تستحق بتاريخ: <?= formatDate($nextStr) ?>)">
                                                         <i class="bi bi-lock me-1"></i> غير مستحقة
                                                     </button>
                                                 <?php endif; ?>

                                                  <?php if ((int) $d['profit_payout_frequency'] > 1): ?>
                                                      <?php if ($isMonthlyProfitDue): ?>
                                                          <a href="deposit_add_profit.php?deposit_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-success"
                                                              title="إضافة ربح شهري يتراكم في حافظة الوديعة">
                                                              <i class="bi bi-plus-circle me-1"></i> ربح تراكمي
                                                          </a>
                                                      <?php else: ?>
                                                          <?php
                                                          $nextP = calcNextProfitDate($d);
                                                          $nextPStr = $nextP ? $nextP->format('Y-m-d') : null;
                                                          ?>
                                                          <button class="btn btn-sm btn-outline-secondary" disabled
                                                              title="لا يمكن إضافة ربح شهري قبل حلول الذكرى الشهرية (تستحق بتاريخ: <?= formatDate($nextPStr) ?>)">
                                                              <i class="bi bi-lock me-1"></i> ربح تراكمي
                                                          </button>
                                                      <?php endif; ?>
                                                  <?php endif; ?>

                                                  <?php if ($isReadyToClose): ?>
                                                      <form method="post" class="d-inline m-0"
                                                          onsubmit="return confirm('هل أنت متأكد من إنهاء هذه الوديعة وإرجاع مبلغ رأس المال للمستثمر بصفة نهائية؟');">
                                                          <?= csrfField() ?>
                                                          <input type="hidden" name="complete_deposit_id" value="<?= $d['id'] ?>">
                                                          <button type="submit" class="btn btn-sm btn-danger"
                                                              title="إنهاء الوديعة وإرجاع رأس المال">
                                                              <i class="bi bi-x-octagon"></i> إنهاء الوديعة
                                                          </button>
                                                      </form>
                                                  <?php elseif ($isPendingClosure): ?>
                                                      <button class="btn btn-sm btn-outline-danger" disabled
                                                          title="انتهت مدة الوديعة. يجب صرف جميع أرباحها المستحقة التراكمية أو الشهرية أولاً ليمكن إغلاقها.">
                                                          <i class="bi bi-x-octagon"></i> إنهاء الوديعة
                                                      </button>
                                                  <?php endif; ?>

                                                  <a href="deposit_add.php?edit=<?= $d['id'] ?>"
                                                      class="btn btn-sm btn-outline-gold" title="تعديل الوديعة">
                                                      <i class="bi bi-pencil"></i>
                                                  </a>
                                              </div>
                                          <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($deposits)): ?>
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">لا توجد ودائع تطابق الفلاتر</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>