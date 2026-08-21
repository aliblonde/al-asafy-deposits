<?php
// public/investor_portal.php — Investor Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['investor']);
$pdo = getPDO();
$investorId = currentInvestorId();

if (!$investorId) {
    session_destroy();
    header('Location: /index.php');
    exit;
}

// Investor info
$investor = $pdo->prepare("SELECT * FROM investors WHERE id=?");
$investor->execute([$investorId]);
$investor = $investor->fetch();

// Stats
$activeDepositsCount = (int) $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE investor_id=? AND status='active'")->execute([$investorId]) ?
    $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE investor_id=? AND status='active'")->execute([$investorId]) && 0 : 0;

// redo properly
$stmt = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE investor_id=? AND status='active'");
$stmt->execute([$investorId]);
$activeDepositsCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT currency, COALESCE(SUM(amount),0) FROM transactions WHERE investor_id=? AND type='profit' GROUP BY currency");
$stmt->execute([$investorId]);
$totalProfits = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
if (!isset($totalProfits['IQD']))
    $totalProfits['IQD'] = 0;
if (!isset($totalProfits['USD']))
    $totalProfits['USD'] = 0;

// Currencies from active deposits
$stmt = $pdo->prepare("SELECT DISTINCT currency FROM deposits WHERE investor_id=? AND status='active'");
$stmt->execute([$investorId]);
$availableCurrencies = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($availableCurrencies))
    $availableCurrencies = ['IQD']; // fallback

// Deposits
$deposits = $pdo->prepare(
    "SELECT d.*, dt.name_ar, dt.code
     FROM deposits d JOIN deposit_types dt ON dt.id=d.deposit_type_id
     WHERE d.investor_id=?
     ORDER BY d.created_at DESC"
);
$deposits->execute([$investorId]);
$deposits = $deposits->fetchAll();

// Profits due now (next_profit_date <= today and status=active)
$today = date('Y-m-d');
$profitsDueNow = 0;
$nearestEnd = null;
foreach ($deposits as $dep) {
    if ($dep['status'] !== 'active')
        continue;
    $next = calcNextProfitDate($dep);
    if ($next && $next->format('Y-m-d') <= $today) {
        $profitsDueNow++;
    }
    if (!$nearestEnd || $dep['end_date'] < $nearestEnd)
        $nearestEnd = $dep['end_date'];
}

// Transactions
$txStmt = $pdo->prepare(
    "SELECT * FROM transactions WHERE investor_id=? ORDER BY date DESC LIMIT 50"
);
$txStmt->execute([$investorId]);
$transactions = $txStmt->fetchAll();

// Withdrawal Requests
$wrStmt = $pdo->prepare(
    "SELECT * FROM withdraw_requests WHERE investor_id=? ORDER BY request_date DESC"
);
$wrStmt->execute([$investorId]);
$withdrawRequests = $wrStmt->fetchAll();

// Handle withdraw request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $amount = (float) ($_POST['withdraw_amount'] ?? 0);
    $currency = in_array($_POST['currency'] ?? '', ['IQD', 'USD']) ? $_POST['currency'] : 'IQD';
    $note = trim($_POST['note'] ?? '');

    if ($amount <= 0) {
        setFlash('danger', 'المبلغ يجب أن يكون أكبر من صفر.');
    } else {
        // Calculate Net Available Profit Balance for the requested currency
        $profitStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE investor_id = ? AND type = 'profit' AND currency = ?");
        $profitStmt->execute([$investorId, $currency]);
        $totalEarnedProfits = (float) $profitStmt->fetchColumn();

        $withdrawnStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE investor_id = ? AND type = 'withdraw' AND currency = ?");
        $withdrawnStmt->execute([$investorId, $currency]);
        $totalPaidWithdrawals = (float) $withdrawnStmt->fetchColumn();

        $pendingStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdraw_requests WHERE investor_id = ? AND status IN ('pending', 'approved') AND currency = ?");
        $pendingStmt->execute([$investorId, $currency]);
        $totalPendingWithdrawals = (float) $pendingStmt->fetchColumn();

        $netAvailableBalance = max(0.00, $totalEarnedProfits - $totalPaidWithdrawals - $totalPendingWithdrawals);

        if ($amount > $netAvailableBalance) {
            setFlash('danger', 'عفواً، رصيد الأرباح المتاح للسحب لديك بـ (' . $currency . ') هو ' . formatMoney($netAvailableBalance, $currency) . ' فقط. لا يمكنك طلب سحب مبلغ أكبر.');
        } else {
            $pdo->prepare(
                "INSERT INTO withdraw_requests (investor_id, amount, currency, request_date, status, note)
                 VALUES (?, ?, ?, NOW(), 'pending', ?)"
            )->execute([$investorId, $amount, $currency, $note]);
            logActivity(
                $pdo,
                'REQUEST_WITHDRAW',
                'withdraw_requests',
                null,
                null,
                ['investor_id' => $investorId, 'amount' => $amount, 'currency' => $currency]
            );
            setFlash('success', 'تم تقديم طلب السحب بنجاح. سيتم مراجعته من قِبل الإدارة.');
        }
    }
    header('Location: investor_portal.php');
    exit;
}

$investorName = $investor['full_name'] ?? '';
$pageTitle = 'بوابة المستثمر';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة المستثمر —
        <?= htmlspecialchars($investorName) ?>
    </title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet"
        href="/assets/css/theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/theme.css') ?>">
    <script>
        // Apply theme early to prevent FOUC
        let currentTheme = localStorage.getItem('theme');
        if (!currentTheme) {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                currentTheme = 'light';
            } else {
                currentTheme = 'dark';
            }
        }
        document.documentElement.setAttribute('data-theme', currentTheme);
    </script>
</head>

<body>

    <!-- Investor Topbar -->
    <div class="topbar" style="position:sticky;top:0;z-index:99">
        <div class="topbar-title">
            <img src="/assets/img/ag-logo.png"
                style="width:28px;height:28px;border-radius:50%;border:1px solid var(--gold);vertical-align:middle;margin-left:8px"
                onerror="this.style.display='none'">
            بوابة المستثمر — العسافي للاستثمارات
        </div>
        <div class="topbar-user">
            <button id="themeToggle" class="btn btn-sm btn-outline-secondary border-0 p-1 me-2" title="تغيير المظهر">
                <i class="bi bi-brightness-high fs-5 text-warning"></i>
            </button>
            <i class="bi bi-person-circle" style="color:var(--gold)"></i>
            <span class="user-name">
                <?= htmlspecialchars($investorName) ?>
            </span>
            <a href="change_password.php" class="btn btn-sm btn-outline-gold border-0 me-2"
                title="تغيير كلمة المرور">
                <i class="bi bi-shield-lock"></i>
            </a>
            <a href="logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-left"></i> خروج
            </a>
        </div>
    </div>

    <div class="page-content" style="max-width:1100px;margin:0 auto">
        <?php include __DIR__ . '/../includes/alerts.php'; ?>

        <!-- Welcome -->
        <div class="page-header mt-2">
            <div>
                <h1 class="page-title"><i class="bi bi-person-check me-2"></i>مرحباً،
                    <?= htmlspecialchars($investorName) ?>
                </h1>
                <p class="page-subtitle">
                    <?= date('d/m/Y') ?> — بوابة متابعة الودائع والأرباح
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="change_password.php" class="btn btn-gold btn-sm">
                    <i class="bi bi-shield-lock me-1"></i>تغيير كلمة المرور
                </a>
                <a href="export_pdf.php?investor_id=<?= $investorId ?>&report=investor_statement"
                    class="btn btn-outline-gold btn-sm" target="_blank">
                    <i class="bi bi-file-pdf me-1"></i>PDF
                </a>
                <a href="export_excel.php?investor_id=<?= $investorId ?>&report=investor_statement"
                    class="btn btn-outline-gold btn-sm">
                    <i class="bi bi-file-excel me-1"></i>Excel
                </a>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="bi bi-bank"></i></div>
                    <div class="stat-card-value">
                        <?= $activeDepositsCount ?>
                    </div>
                    <div class="stat-card-label">الودائع النشطة</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="bi bi-wallet2 text-success"></i></div>
                    <div class="stat-card-value" style="font-size:1.1rem">
                        <div><?= formatMoney($totalProfits['IQD'], 'IQD') ?></div>
                        <div class="mt-1" style="color:#2ecc71"><?= formatMoney($totalProfits['USD'], 'USD') ?></div>
                    </div>
                    <div class="stat-card-label">إجمالي الأرباح المستلمة</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="bi bi-bell"></i></div>
                    <div class="stat-card-value" style="color:<?= $profitsDueNow > 0 ? 'var(--danger)' : 'inherit' ?>">
                        <?= $profitsDueNow ?>
                    </div>
                    <div class="stat-card-label">أرباح مستحقة الآن</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-card-value" style="font-size:1.1rem">
                        <?= $nearestEnd ? formatDate($nearestEnd) : '—' ?>
                    </div>
                    <div class="stat-card-label">أقرب انتهاء وديعة</div>
                </div>
            </div>
        </div>

        <!-- Deposits Table -->
        <div class="section-title"><i class="bi bi-bank me-1"></i>ودائعي</div>
        <div class="table-wrapper mb-4">
            <div class="table-responsive">
                <table class="table table-dark-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>النوع</th>
                            <th>العملة</th>
                            <th>المبلغ</th>
                            <th>بداية</th>
                            <th>نهاية</th>
                            <th>النسبة المقفلة</th>
                            <th>آخر ربح</th>
                            <th>الربح القادم</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposits as $d):
                            $next = calcNextProfitDate($d);
                            $nextStr = $next ? $next->format('Y-m-d') : null;
                            $isDue = $nextStr && $nextStr <= $today && $d['status'] === 'active';
                            ?>
                            <tr>
                                <td class="text-muted">#<?= $d['id'] ?></td>
                                <td><span
                                        class="badge <?= typeBadge($d['code']) ?>"><?= htmlspecialchars($d['name_ar']) ?></span>
                                </td>
                                <td><?= currencyBadge($d['currency'] ?? 'IQD') ?></td>
                                <td class="text-gold fw-bold"><?= formatMoney($d['amount'], $d['currency'] ?? 'IQD') ?></td>
                                <td>
                                    <?= formatDate($d['start_date']) ?>
                                </td>
                                <td>
                                    <?= formatDate($d['end_date']) ?>
                                </td>
                                <td>
                                    <?= number_format($d['profit_rate_monthly'] * 100, 3) ?>%
                                </td>
                                <td>
                                    <?= formatDate($d['last_profit_date']) ?>
                                </td>
                                <td>
                                    <?php if ($nextStr && $d['status'] === 'active'): ?>
                                        <span style="color:<?= $isDue ? 'var(--danger)' : '' ?>">
                                            <?= $isDue ? '<i class="bi bi-exclamation-circle me-1"></i>' : '' ?>
                                            <?= formatDate($nextStr) ?>
                                        </span>
                                    <?php else:
                                        echo '—';
                                    endif; ?>
                                </td>
                                <td><span class="badge <?= statusBadge($d['status']) ?>">
                                        <?= arabicStatus($d['status']) ?>
                                    </span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($deposits)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">لا توجد ودائع</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Withdraw Requests Table -->
        <div class="section-title"><i class="bi bi-arrow-up-circle me-1"></i>طلبات السحب الخاصة بي</div>
        <div class="table-wrapper mb-4">
            <div class="table-responsive">
                <table class="table table-dark-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المبلغ</th>
                            <th>العملة</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th>ملاحظة الإدارة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawRequests as $wr): ?>
                            <tr>
                                <td class="text-muted"><?= $wr['id'] ?></td>
                                <td class="text-gold fw-bold"><?= formatMoney($wr['amount'], $wr['currency']) ?></td>
                                <td><?= currencyBadge($wr['currency']) ?></td>
                                <td><?= date('d/m/Y', strtotime($wr['request_date'])) ?></td>
                                <td><span
                                        class="badge <?= statusBadge($wr['status']) ?>"><?= arabicStatus($wr['status']) ?></span>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($wr['note'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($withdrawRequests)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">لا توجد طلبات سحب</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="section-title"><i class="bi bi-list-ul me-1"></i>سجل معاملاتي</div>
        <div class="table-wrapper mb-4">
            <div class="table-responsive">
                <table class="table table-dark-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>رقم الإيصال</th>
                            <th>النوع</th>
                            <th>العملة</th>
                            <th>المبلغ</th>
                            <th>التاريخ</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <td><?= currencyBadge($t['currency'] ?? 'IQD') ?></td>
                            <td class="text-gold"><?= formatMoney($t['amount'], $t['currency'] ?? 'IQD') ?></td>
                            <td>
                                <?= date('d/m/Y H:i', strtotime($t['date'])) ?>
                            </td>
                            <td style="max-width:200px">
                                <?= htmlspecialchars($t['note'] ?? '—') ?>
                            </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">لا توجد معاملات</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Withdraw Request Form -->
        <div class="section-title"><i class="bi bi-arrow-up-circle me-1"></i>طلب سحب أرباح</div>
        <div class="form-card mb-5">
            <form method="post" action="">
                <?= csrfField() ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">العملة</label>
                        <select name="currency" class="form-select">
                            <?php foreach ($availableCurrencies as $cur): ?>
                                <option value="<?= $cur ?>"><?= $cur == 'IQD' ? 'د.ع دينار عراقي' : '$ دولار أمريكي' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">المبلغ المطلوب سحبه</label>
                        <input type="number" name="withdraw_amount" class="form-control" min="1" step="0.01"
                            placeholder="0" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ملاحظة (اختياري)</label>
                        <input type="text" name="note" class="form-control" placeholder="سبب الطلب...">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-gold w-100"
                            onclick="return confirm('هل أنت متأكد من تقديم طلب السحب؟')">
                            <i class="bi bi-send me-1"></i> تقديم الطلب
                        </button>
                    </div>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    سيتم مراجعة الطلب من قِبل الإدارة والموافقة عليه قبل الصرف.
                </div>
            </form>
        </div>

    </div><!-- /.page-content -->
    <footer class="text-center py-3"
        style="border-top:1px solid var(--border);font-size:0.75rem;color:var(--text-muted)">
        نظام إدارة الودائع الاستثمارية &copy;
        <?= date('Y') ?> — العسافي للاستثمارات
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggleBtn = document.getElementById('themeToggle');
            if (themeToggleBtn) {
                // Update icon on load
                updateThemeIcon(document.documentElement.getAttribute('data-theme'));

                themeToggleBtn.addEventListener('click', () => {
                    let currentTheme = document.documentElement.getAttribute('data-theme');
                    let newTheme = currentTheme === 'light' ? 'dark' : 'light';

                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);

                    updateThemeIcon(newTheme);
                });
            }

            function updateThemeIcon(theme) {
                if (!themeToggleBtn) return;
                const icon = themeToggleBtn.querySelector('i');
                if (theme === 'light') {
                    icon.className = 'bi bi-moon-fill fs-5 text-dark';
                } else {
                    icon.className = 'bi bi-brightness-high fs-5 text-warning';
                }
            }
        });
    </script>
</body>

</html>