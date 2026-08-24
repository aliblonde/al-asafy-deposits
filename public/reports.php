<?php
// public/reports.php — Reports + Search by Receipt No
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/rbac.php';

requirePermission('reports.view');
$pdo = getPDO();

$report = $_GET['report'] ?? 'deposits';
$receiptNo = trim($_GET['receipt_no'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fType = $_GET['type'] ?? '';
$fInvestor = (int) ($_GET['investor_id'] ?? 0);
$fDateFrom = $_GET['date_from'] ?? '';
$fDateTo = $_GET['date_to'] ?? '';
$fTxType = $_GET['tx_type'] ?? '';

$rows = [];
$investors = $pdo->query("SELECT id, full_name FROM investors ORDER BY full_name")->fetchAll();

// ── Receipt No Search (overrides everything) ──────────────────
if ($receiptNo) {
    $stmt = $pdo->prepare(
        "SELECT t.*, i.full_name FROM transactions t
         JOIN investors i ON i.id = t.investor_id
         WHERE t.receipt_no LIKE ? ORDER BY t.date DESC"
    );
    $stmt->execute(['%' . $receiptNo . '%']);
    $rows = $stmt->fetchAll();
    $report = 'receipt_search';
} else {

    switch ($report) {
        case 'deposits':
            $where = ['1=1'];
            $params = [];
            if ($fStatus) {
                $where[] = 'd.status=?';
                $params[] = $fStatus;
            }
            if ($fType) {
                $where[] = 'dt.code=?';
                $params[] = $fType;
            }
            if ($fInvestor) {
                $where[] = 'd.investor_id=?';
                $params[] = $fInvestor;
            }
            if ($fDateFrom) {
                $where[] = 'd.start_date>=?';
                $params[] = $fDateFrom;
            }
            if ($fDateTo) {
                $where[] = 'd.start_date<=?';
                $params[] = $fDateTo;
            }
            $stmt = $pdo->prepare(
                "SELECT d.*, i.full_name, dt.name_ar, dt.code
             FROM deposits d JOIN investors i ON i.id=d.investor_id
             JOIN deposit_types dt ON dt.id=d.deposit_type_id
             WHERE " . implode(' AND ', $where) . " ORDER BY d.created_at DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            break;

        case 'profits':
            $where = ["t.type IN ('profit','profit_accrual','profit_payout')"];
            $params = [];
            if ($fDateFrom) {
                $where[] = 't.date>=?';
                $params[] = $fDateFrom . ' 00:00:00';
            }
            if ($fDateTo) {
                $where[] = 't.date<=?';
                $params[] = $fDateTo . ' 23:59:59';
            }
            if ($fInvestor) {
                $where[] = 't.investor_id=?';
                $params[] = $fInvestor;
            }
            $stmt = $pdo->prepare(
                "SELECT t.*, i.full_name FROM transactions t
             JOIN investors i ON i.id=t.investor_id
             WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            break;

        case 'investor_statement':
            $where = ['1=1'];
            $params = [];
            if ($fInvestor) {
                $where[] = 't.investor_id=?';
                $params[] = $fInvestor;
            }
            if ($fDateFrom) {
                $where[] = 't.date>=?';
                $params[] = $fDateFrom . ' 00:00:00';
            }
            if ($fDateTo) {
                $where[] = 't.date<=?';
                $params[] = $fDateTo . ' 23:59:59';
            }
            $stmt = $pdo->prepare(
                "SELECT t.*, i.full_name FROM transactions t
             JOIN investors i ON i.id=t.investor_id
             WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            break;

        case 'transactions':
            $where = ['1=1'];
            $params = [];
            if ($fTxType) {
                $where[] = 't.type=?';
                $params[] = $fTxType;
            }
            if ($fDateFrom) {
                $where[] = 't.date>=?';
                $params[] = $fDateFrom . ' 00:00:00';
            }
            if ($fDateTo) {
                $where[] = 't.date<=?';
                $params[] = $fDateTo . ' 23:59:59';
            }
            if ($fInvestor) {
                $where[] = 't.investor_id=?';
                $params[] = $fInvestor;
            }
            $stmt = $pdo->prepare(
                "SELECT t.*, i.full_name FROM transactions t
             JOIN investors i ON i.id=t.investor_id
             WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            break;
    }
}

$pageTitle = 'التقارير';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-bar-chart-line me-2"></i>التقارير</h1>
                <div class="d-flex gap-2">
                    <a href="/export_pdf.php?<?= htmlspecialchars(http_build_query($_GET)) ?>"
                        class="btn btn-outline-gold btn-sm" target="_blank">
                        <i class="bi bi-file-pdf me-1"></i> PDF
                    </a>
                    <a href="/export_excel.php?<?= htmlspecialchars(http_build_query($_GET)) ?>"
                        class="btn btn-outline-gold btn-sm">
                        <i class="bi bi-file-excel me-1"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Report Tabs -->
            <form method="get" action="">
                <!-- الاحتفاظ باسم التاب أثناء البحث -->
                <input type="hidden" name="report" value="<?= htmlspecialchars($report) ?>">

                <ul class="nav nav-tabs mb-3" style="border-color:var(--border)">
                    <?php
                    $tabs = ['deposits' => 'الودائع', 'profits' => 'الأرباح', 'investor_statement' => 'كشف مستثمر', 'transactions' => 'المعاملات'];
                    foreach ($tabs as $k => $l):
                        $ac = ($report === $k || ($report === 'receipt_search' && $k === 'transactions')) ? ' active' : '';
                        ?>
                        <li class="nav-item">
                            <!-- روابط للحفاظ على خصائص البحث -->
                            <a href="?report=<?= $k ?>" class="nav-link<?= $ac ?>"
                                style="<?= $ac ? 'color:var(--gold);border-color:var(--border) var(--border) var(--bg-base)' : 'color:var(--text-muted)' ?>">
                                <?= $l ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Filters -->
                <div class="filter-bar mb-3">
                    <div class="row g-2 align-items-end">
                        <!-- Receipt No Search -->
                        <div class="col-md-3">
                            <label class="form-label">بحث برقم الإيصال</label>
                            <input type="text" name="receipt_no" class="form-control form-control-sm"
                                value="<?= htmlspecialchars($receiptNo) ?>" placeholder="AG-202602-000001">
                        </div>
                        <?php if ($report !== 'receipt_search'): ?>
                            <?php if (in_array($report, ['deposits'])): ?>
                                <div class="col-md-2">
                                    <label class="form-label">الحالة</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="">الكل</option>
                                        <?php foreach (['active' => 'نشطة', 'completed' => 'منتهية', 'cancelled' => 'ملغاة', 'defaulted' => 'متعثرة'] as $v => $l): ?>
                                            <option value="<?= $v ?>" <?= $fStatus === $v ? 'selected' : '' ?>>
                                                <?= $l ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">النوع</label>
                                    <select name="type" class="form-select form-select-sm">
                                        <option value="">الكل</option>
                                        <?php foreach (['short' => 'قصيرة', 'medium' => 'متوسطة', 'long' => 'طويلة'] as $v => $l): ?>
                                            <option value="<?= $v ?>" <?= $fType === $v ? 'selected' : '' ?>>
                                                <?= $l ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <?php if ($report === 'transactions'): ?>
                                <div class="col-md-2">
                                    <label class="form-label">نوع المعاملة</label>
                                    <select name="tx_type" class="form-select form-select-sm">
                                        <option value="">الكل</option>
                                        <?php foreach (['deposit','profit_accrual','profit_payout','withdrawal_payout','principal_refund','deposit_adjustment','profit','withdraw'] as $v): ?>
                                            <option value="<?= $v ?>" <?= $fTxType === $v ? 'selected' : '' ?>>
                                                <?= arabicTxType($v) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-2">
                                <label class="form-label">المستثمر</label>
                                <select name="investor_id" class="form-select form-select-sm">
                                    <option value="">الكل</option>
                                    <?php foreach ($investors as $inv): ?>
                                        <option value="<?= $inv['id'] ?>" <?= $fInvestor === (int) $inv['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($inv['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">من</label>
                                <input type="date" name="date_from" class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($fDateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">إلى</label>
                                <input type="date" name="date_to" class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($fDateTo) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-gold btn-sm"><i
                                    class="bi bi-search me-1"></i>بحث</button>
                            <a href="reports.php?report=<?= $report ?>" class="btn btn-outline-gold btn-sm ms-1">مسح</a>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Results -->
            <div class="table-wrapper">
                <div class="p-3 border-bottom border-gold d-flex justify-content-between">
                    <span class="section-title mb-0">النتائج:
                        <?= count($rows) ?> سجل
                    </span>
                </div>
                <div class="table-responsive">

                    <?php if ($report === 'deposits'): ?>
                        <table class="table table-dark-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المستثمر</th>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>العملة</th>
                                    <th>بداية</th>
                                    <th>نهاية</th>
                                    <th>النسبة</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= $r['id'] ?></td>
                                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                                        <td><span class="badge <?= typeBadge($r['code']) ?>"><?= htmlspecialchars($r['name_ar']) ?></span></td>
                                        <td class="text-gold"><?= formatMoney($r['amount'], $r['currency'] ?? 'IQD') ?></td>
                                        <td><?= currencyBadge($r['currency'] ?? 'IQD') ?></td>
                                        <td><?= formatDate($r['start_date']) ?></td>
                                        <td><?= formatDate($r['end_date']) ?></td>
                                        <td><?= number_format($r['profit_rate_monthly'] * 100, 3) ?>%</td>
                                        <td><span class="badge <?= statusBadge($r['status']) ?>"><?= arabicStatus($r['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-3">لا نتائج</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php else: /* profits / investor_statement / transactions / receipt_search */ ?>
                        <table class="table table-dark-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>رقم الإيصال</th>
                                    <th>المستثمر</th>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>العملة</th>
                                    <th>التاريخ</th>
                                    <th>ملاحظة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><span class="receipt-no"><?= htmlspecialchars($r['receipt_no']) ?></span></td>
                                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                                        <td>
                                            <?php
                                            echo arabicTxType($r['type']);
                                            ?>
                                        </td>
                                        <td class="text-gold"><?= formatMoney($r['amount'], $r['currency'] ?? 'IQD') ?></td>
                                        <td><?= currencyBadge($r['currency'] ?? 'IQD') ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($r['date'])) ?></td>
                                        <td style="max-width:200px"><?= htmlspecialchars($r['note'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">لا نتائج</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
