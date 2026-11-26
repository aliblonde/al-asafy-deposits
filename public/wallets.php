<?php
// public/wallets.php — Digital Wallets Ledger, Management & Reports
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/notifications.php';
require_once __DIR__ . '/../config/wallet_schema.php';

requireLogin();
$pdo = getPDO();
ensureWalletSystemSchema($pdo);

if (!userCan('wallets.view') && !userCan('deposits.view') && currentRole() !== 'admin') {
    requirePermission('wallets.view');
}

$canAdjust = userCan('wallets.adjust') || currentRole() === 'admin';

// -----------------------------------------------------------------------------
// Handle Administrative Wallet Operations (Credit, Deduct, Zero Out)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!$canAdjust) {
        setFlash('danger', 'ليس لديك صلاحية لتعديل أرصدة المحافظ أو تصفيرها.');
        header('Location: wallets.php');
        exit;
    }

    $action = trim($_POST['action'] ?? '');
    $investorId = (int)($_POST['investor_id'] ?? 0);
    $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
    $amount = (float)($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($investorId <= 0) {
        setFlash('danger', 'يرجى تحديد حساب المستثمر بدقة.');
        header('Location: wallets.php');
        exit;
    }

    if (!in_array($currency, ['USD', 'IQD'], true)) {
        setFlash('danger', 'العملة المحددة غير صحيحة.');
        header('Location: wallets.php');
        exit;
    }

    // Fetch investor info
    $invStmt = $pdo->prepare("SELECT id, full_name, phone FROM investors WHERE id = ?");
    $invStmt->execute([$investorId]);
    $investor = $invStmt->fetch();
    if (!$investor) {
        setFlash('danger', 'المستثمر غير موجود.');
        header('Location: wallets.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Lock & get current wallet balance
        $wbStmt = $pdo->prepare("SELECT balance FROM wallet_balances WHERE investor_id = ? AND currency = ? FOR UPDATE");
        $wbStmt->execute([$investorId, $currency]);
        $currBalRow = $wbStmt->fetch();
        $currentBalance = $currBalRow ? (float)$currBalRow['balance'] : 0.0;

        if ($action === 'credit') {
            // Admin Direct Credit
            if ($amount <= 0) {
                throw new Exception('يرجى إدخال مبلغ شحن أكبر من الصفر.');
            }
            if (empty($note)) {
                $note = 'شحن رصيد إداري مباشر من الإدارة';
            }

            $newBalance = $currentBalance + $amount;
            $upWb = $pdo->prepare("
                INSERT INTO wallet_balances (investor_id, currency, balance, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance), updated_at = NOW()
            ");
            $upWb->execute([$investorId, $currency, $amount]);

            // Wallet Transaction
            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, note, created_by, created_at)
                VALUES (?, 'admin_credit', 'credit', ?, ?, ?, ?, ?, NOW())
            ");
            $insTx->execute([$investorId, $amount, $currency, $newBalance, "[شحن إداري] {$note}", currentUserId()]);

            // General Ledger Transaction
            $receiptNo = 'ADMC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $txMain = $pdo->prepare("
                INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
                VALUES (?, ?, 'deposit', 'credit', ?, ?, NOW(), ?)
            ");
            $txMain->execute([$receiptNo, $investorId, $amount, $currency, "[شحن محفظة إداري] {$note}"]);

            logActivity($pdo, 'ADMIN_WALLET_CREDIT', 'wallet_balances', $investorId, ['old_balance' => $currentBalance], [
                'added_amount' => $amount,
                'new_balance' => $newBalance,
                'currency' => $currency,
                'note' => $note
            ]);

            notifyInvestor(
                $pdo,
                $investorId,
                'إيداع رصيد في المحفظة 💰',
                "تمت إضافة مبلغ " . number_format($amount, 2) . " {$currency} إلى محفظتك من قبل الإدارة. البيان: {$note}",
                'wallet'
            );

            setFlash('success', "تم إضافة مبلغ " . formatMoney($amount, $currency) . " إلى محفظة المستثمر بنجاح.");

        } elseif ($action === 'deduct') {
            // Admin Deduction / Withdrawal
            if ($amount <= 0) {
                throw new Exception('يرجى إدخال مبلغ سحب/خصم أكبر من الصفر.');
            }
            if (empty($note)) {
                throw new Exception('يرجى كتابة سبب الخصم/السحب الإداري لتوثيق العملية في السجل.');
            }
            if ($amount > $currentBalance) {
                throw new Exception("المبلغ المراد خصمه (" . formatMoney($amount, $currency) . ") يتجاوز الرصيد المشحون المتاح في المحفظة حالياً (" . formatMoney($currentBalance, $currency) . ").");
            }

            $newBalance = max(0.0, $currentBalance - $amount);
            $upWb = $pdo->prepare("UPDATE wallet_balances SET balance = ?, updated_at = NOW() WHERE investor_id = ? AND currency = ?");
            $upWb->execute([$newBalance, $investorId, $currency]);

            // Wallet Transaction
            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, note, created_by, created_at)
                VALUES (?, 'admin_debit', 'debit', ?, ?, ?, ?, ?, NOW())
            ");
            $insTx->execute([$investorId, $amount, $currency, $newBalance, "[خصم/سحب إداري] {$note}", currentUserId()]);

            // General Ledger Transaction
            $receiptNo = 'ADMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $txMain = $pdo->prepare("
                INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
                VALUES (?, ?, 'withdrawal', 'debit', ?, ?, NOW(), ?)
            ");
            $txMain->execute([$receiptNo, $investorId, $amount, $currency, "[خصم محفظة إداري] {$note}"]);

            logActivity($pdo, 'ADMIN_WALLET_DEDUCT', 'wallet_balances', $investorId, ['old_balance' => $currentBalance], [
                'deducted_amount' => $amount,
                'new_balance' => $newBalance,
                'currency' => $currency,
                'note' => $note
            ]);

            notifyInvestor(
                $pdo,
                $investorId,
                'إشعار سحب/خصم من المحفظة ⚠️',
                "تم خصم مبلغ " . number_format($amount, 2) . " {$currency} من محفظتك من قبل الإدارة. السبب: {$note}",
                'wallet'
            );

            setFlash('success', "تم خصم مبلغ " . formatMoney($amount, $currency) . " من محفظة المستثمر وتوثيقه في السجل بنجاح.");

        } elseif ($action === 'reset_zero') {
            // Admin Zero-Out Wallet Balance
            if ($currentBalance <= 0) {
                throw new Exception("رصيد محفظة المستثمر بالـ {$currency} مصفّر بالفعل (0.00).");
            }
            if (empty($note)) {
                throw new Exception('يرجى توضيح سبب تصفير رصيد المحفظة لتوثيقه في السجل والرقابة.');
            }

            $deductedAmount = $currentBalance;
            $newBalance = 0.00;

            $upWb = $pdo->prepare("UPDATE wallet_balances SET balance = 0.00, updated_at = NOW() WHERE investor_id = ? AND currency = ?");
            $upWb->execute([$investorId, $currency]);

            // Wallet Transaction
            $insTx = $pdo->prepare("
                INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, note, created_by, created_at)
                VALUES (?, 'admin_reset', 'debit', ?, ?, 0.00, ?, ?, NOW())
            ");
            $insTx->execute([$investorId, $deductedAmount, $currency, "[تصفير المحفظة إدارياً] {$note}", currentUserId()]);

            // General Ledger Transaction
            $receiptNo = 'RST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $txMain = $pdo->prepare("
                INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
                VALUES (?, ?, 'withdrawal', 'debit', ?, ?, NOW(), ?)
            ");
            $txMain->execute([$receiptNo, $investorId, $deductedAmount, $currency, "[تصفير رصيد المحفظة] {$note}"]);

            logActivity($pdo, 'ADMIN_WALLET_RESET_ZERO', 'wallet_balances', $investorId, ['old_balance' => $currentBalance], [
                'reset_amount' => $deductedAmount,
                'currency' => $currency,
                'note' => $note
            ]);

            notifyInvestor(
                $pdo,
                $investorId,
                'تصفير رصيد المحفظة ℹ️',
                "تم تصفير رصيد محفظتك بقيمة " . number_format($deductedAmount, 2) . " {$currency} من قبل الإدارة. البيان: {$note}",
                'wallet'
            );

            setFlash('success', "تم تصفير رصيد محفظة المستثمر بالـ {$currency} بنجاح (المبلغ المصفّر: " . formatMoney($deductedAmount, $currency) . ").");
        } else {
            throw new Exception('إجراء غير معروف.');
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('danger', 'حدث خطأ أثناء تنفيذ العملية: ' . $e->getMessage());
    }

    header('Location: wallets.php');
    exit;
}

// -----------------------------------------------------------------------------
// Data Fetching: Filters & Navigation
// -----------------------------------------------------------------------------
$activeTab = $_GET['tab'] ?? 'wallets';
$filterInv = (int)($_GET['investor_id'] ?? 0);
$filterType = trim($_GET['type'] ?? 'all');
$filterCur = trim($_GET['currency'] ?? 'all');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

// 1. KPI Financial Summaries
$kpiDirect = ['total_usd_direct' => 0.0, 'total_iqd_direct' => 0.0];
try {
    $kpiStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN currency = 'USD' THEN balance ELSE 0 END), 0) AS total_usd_direct,
            COALESCE(SUM(CASE WHEN currency = 'IQD' THEN balance ELSE 0 END), 0) AS total_iqd_direct
        FROM wallet_balances
    ");
    $row = $kpiStmt ? $kpiStmt->fetch() : null;
    if ($row) {
        $kpiDirect = $row;
    }
} catch (\Throwable $e) {
    error_log("KPI Direct fetch error: " . $e->getMessage());
}

$topupKpi = ['total_topups' => 0, 'approved_usd' => 0.0, 'approved_iqd' => 0.0];
try {
    $topupStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_topups,
            COALESCE(SUM(CASE WHEN currency = 'USD' AND status = 'approved' THEN amount ELSE 0 END), 0) AS approved_usd,
            COALESCE(SUM(CASE WHEN currency = 'IQD' AND status = 'approved' THEN amount ELSE 0 END), 0) AS approved_iqd
        FROM wallet_topup_requests
    ");
    $row = $topupStmt ? $topupStmt->fetch() : null;
    if ($row) {
        $topupKpi = $row;
    }
} catch (\Throwable $e) {
    error_log("Topup KPI error: " . $e->getMessage());
}

$transferKpi = ['total_transfers' => 0, 'transfer_usd' => 0.0, 'transfer_iqd' => 0.0];
try {
    $trfStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_transfers,
            COALESCE(SUM(CASE WHEN currency = 'USD' THEN amount ELSE 0 END), 0) AS transfer_usd,
            COALESCE(SUM(CASE WHEN currency = 'IQD' THEN amount ELSE 0 END), 0) AS transfer_iqd
        FROM wallet_transfers
    ");
    $row = $trfStmt ? $trfStmt->fetch() : null;
    if ($row) {
        $transferKpi = $row;
    }
} catch (\Throwable $e) {
    error_log("Transfer KPI error: " . $e->getMessage());
}

// 2. Fetch Investors with Wallets & Profits
$invSql = "
    SELECT 
        i.id,
        i.full_name,
        i.phone,
        COALESCE(wb_usd.balance, 0) AS wallet_usd,
        COALESCE(wb_iqd.balance, 0) AS wallet_iqd,
        COALESCE(p_usd.avail_profit, 0) AS profit_usd,
        COALESCE(p_iqd.avail_profit, 0) AS profit_iqd
    FROM investors i
    LEFT JOIN wallet_balances wb_usd ON wb_usd.investor_id = i.id AND wb_usd.currency = 'USD'
    LEFT JOIN wallet_balances wb_iqd ON wb_iqd.investor_id = i.id AND wb_iqd.currency = 'IQD'
    LEFT JOIN (
        SELECT investor_id, SUM(accumulated_profit) AS avail_profit 
        FROM deposits WHERE status = 'active' AND currency = 'USD' GROUP BY investor_id
    ) p_usd ON p_usd.investor_id = i.id
    LEFT JOIN (
        SELECT investor_id, SUM(accumulated_profit) AS avail_profit 
        FROM deposits WHERE status = 'active' AND currency = 'IQD' GROUP BY investor_id
    ) p_iqd ON p_iqd.investor_id = i.id
    WHERE 1=1
";

$invParams = [];
if (!empty($searchQuery)) {
    $invSql .= " AND (i.full_name LIKE :q OR i.phone LIKE :q OR i.id = :qid)";
    $invParams[':q'] = "%$searchQuery%";
    $invParams[':qid'] = (int)$searchQuery;
}
$invSql .= " ORDER BY (COALESCE(wb_usd.balance, 0) + COALESCE(wb_iqd.balance, 0)) DESC, i.id DESC";

$investorWallets = [];
try {
    $invStmt = $pdo->prepare($invSql);
    $invStmt->execute($invParams);
    $investorWallets = $invStmt->fetchAll();
} catch (\Throwable $e) {
    error_log("investorWallets error: " . $e->getMessage());
}

// 3. Fetch Full Wallet Transactions Ledger
$txSql = "
    SELECT 
        wt.*,
        i.full_name AS investor_name,
        i.phone AS investor_phone,
        u.username AS creator_username
    FROM wallet_transactions wt
    JOIN investors i ON i.id = wt.investor_id
    LEFT JOIN users u ON u.id = wt.created_by
    WHERE 1=1
";
$txParams = [];

if ($filterInv > 0) {
    $txSql .= " AND wt.investor_id = :inv_id";
    $txParams[':inv_id'] = $filterInv;
}
if ($filterType !== 'all') {
    $txSql .= " AND wt.type = :type";
    $txParams[':type'] = $filterType;
}
if ($filterCur !== 'all') {
    $txSql .= " AND wt.currency = :cur";
    $txParams[':cur'] = $filterCur;
}
if (!empty($dateFrom)) {
    $txSql .= " AND wt.created_at >= :d_from";
    $txParams[':d_from'] = $dateFrom . ' 00:00:00';
}
if (!empty($dateTo)) {
    $txSql .= " AND wt.created_at <= :d_to";
    $txParams[':d_to'] = $dateTo . ' 23:59:59';
}
$txSql .= " ORDER BY wt.id DESC LIMIT 150";

$transactions = [];
try {
    $txStmt = $pdo->prepare($txSql);
    $txStmt->execute($txParams);
    $transactions = $txStmt->fetchAll();
} catch (\Throwable $e) {
    error_log("transactions error: " . $e->getMessage());
}

// Helper for type badges
function getTxBadge(string $type, string $direction): array {
    switch ($type) {
        case 'topup_credit':
            return ['bg' => 'bg-success', 'icon' => 'bi-box-arrow-in-down', 'text' => 'شحن محفظة معتمد'];
        case 'admin_credit':
            return ['bg' => 'bg-info text-dark', 'icon' => 'bi-plus-circle', 'text' => 'شحن إداري مباشر'];
        case 'admin_debit':
            return ['bg' => 'bg-danger', 'icon' => 'bi-dash-circle', 'text' => 'خصم/سحب إداري'];
        case 'admin_reset':
            return ['bg' => 'bg-dark border border-danger text-danger', 'icon' => 'bi-arrow-counterclockwise', 'text' => 'تصفير رصيد إداري'];
        case 'withdrawal':
            return ['bg' => 'bg-warning text-dark', 'icon' => 'bi-arrow-up-right', 'text' => 'سحب رصيد'];
        default:
            return $direction === 'credit' 
                ? ['bg' => 'bg-success', 'icon' => 'bi-arrow-down-left', 'text' => 'إيداع وارد']
                : ['bg' => 'bg-secondary', 'icon' => 'bi-arrow-up-right', 'text' => 'خصم صادر'];
    }
}

$pageTitle = 'سجل وتقارير المحافظ الرقمية';
include __DIR__ . '/../includes/header.php';
?>

<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <!-- Page Header -->
            <div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="page-title"><i class="bi bi-wallet2 text-gold me-2"></i>سجل وتقارير المحافظ الرقمية</h1>
                    <p class="text-muted small mb-0">نظام الرقابة المالية الشامل، تدقيق أرصدة المستثمرين، والتسويات والتصفير الإداري</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wallet_topups.php" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-clock-history me-1"></i>طلبات الشحن المعلقة
                    </a>
                    <a href="company_payment_methods.php" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-credit-card me-1"></i>قنوات التحويل للشركة
                    </a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>طباعة التقرير
                    </button>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 rounded bg-card border border-gold shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small">إجمالي الأرصدة المشحونة (USD)</span>
                            <i class="bi bi-currency-dollar fs-4 text-gold"></i>
                        </div>
                        <div class="fs-4 fw-bold text-gold"><?= formatMoney((float)$kpiDirect['total_usd_direct'], 'USD') ?></div>
                        <div class="small text-muted mt-1">أرصدة حرة ومتاحة للسحب والتحويل</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 rounded bg-card border border-info shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small">إجمالي الأرصدة المشحونة (IQD)</span>
                            <i class="bi bi-cash-stack fs-4 text-info"></i>
                        </div>
                        <div class="fs-4 fw-bold text-info"><?= formatMoney((float)$kpiDirect['total_iqd_direct'], 'IQD') ?></div>
                        <div class="small text-muted mt-1">أرصدة حرة بالدينار العراقي</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 rounded bg-card border border-success shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small">عمليات الشحن المعتمدة</span>
                            <i class="bi bi-check2-circle fs-4 text-success"></i>
                        </div>
                        <div class="fs-4 fw-bold text-success"><?= (int)$topupKpi['total_topups'] ?> عملية</div>
                        <div class="small text-muted mt-1">USD: <?= formatMoney((float)$topupKpi['approved_usd'], 'USD') ?></div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 rounded bg-card border border-secondary shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small">حوالات المستثمرين (P2P)</span>
                            <i class="bi bi-arrow-left-right fs-4 text-primary"></i>
                        </div>
                        <div class="fs-4 fw-bold text-white"><?= (int)$transferKpi['total_transfers'] ?> حوالة</div>
                        <div class="small text-muted mt-1">حجم التداول الداخلي</div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-pills mb-4 gap-2 bg-dark p-2 rounded border border-secondary">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'wallets' ? 'active bg-gold text-dark fw-bold' : 'text-white' ?>" href="wallets.php?tab=wallets">
                        <i class="bi bi-wallet2 me-1"></i>أرصدة محافظ المستثمرين والتحكم الإداري
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'ledger' ? 'active bg-gold text-dark fw-bold' : 'text-white' ?>" href="wallets.php?tab=ledger">
                        <i class="bi bi-receipt-cutoff me-1"></i>سجل المعاملات والعمليات الشامل (Audit Ledger)
                    </a>
                </li>
            </ul>

            <?php if ($activeTab === 'wallets'): ?>
                <!-- ---------------------------------------------------------------- -->
                <!-- Tab 1: Investor Wallets & Balance Adjustments                     -->
                <!-- ---------------------------------------------------------------- -->
                <div class="form-card mb-4">
                    <form method="GET" action="wallets.php" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="wallets">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="q" class="form-control" placeholder="ابحث باسم المستثمر أو رقم الهاتف..." value="<?= htmlspecialchars($searchQuery) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-gold w-100">بحث المستثمرين</button>
                        </div>
                        <?php if (!empty($searchQuery)): ?>
                            <div class="col-md-2">
                                <a href="wallets.php?tab=wallets" class="btn btn-outline-secondary w-100">إلغاء الفلتر</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="form-card">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>المستثمر</th>
                                    <th>معرف المحفظة</th>
                                    <th>الرصيد المشحون (USD)</th>
                                    <th>الرصيد المشحون (IQD)</th>
                                    <th>الأرباح المستحقة المتاحة</th>
                                    <th>إجمالي القابل للتصرف</th>
                                    <th class="text-center">إجراءات الإدارة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($investorWallets)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-folder-x fs-2 d-block mb-2"></i>
                                            لم يتم العثور على أي مستثمرين مطابقين.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($investorWallets as $w): 
                                        $totalUsd = (float)$w['wallet_usd'] + (float)$w['profit_usd'];
                                        $totalIqd = (float)$w['wallet_iqd'] + (float)$w['profit_iqd'];
                                        $walletId = 'W-' . str_pad($w['id'], 6, '0', STR_PAD_LEFT);
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="investor_view.php?id=<?= $w['id'] ?>" class="text-gold fw-bold text-decoration-none">
                                                    <?= htmlspecialchars($w['full_name']) ?>
                                                </a>
                                                <div class="text-muted small"><?= htmlspecialchars($w['phone'] ?: '—') ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark border border-secondary font-monospace">
                                                    <i class="bi bi-qr-code me-1 text-gold"></i><?= $walletId ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?= (float)$w['wallet_usd'] > 0 ? 'text-gold' : 'text-muted' ?>">
                                                    <?= formatMoney((float)$w['wallet_usd'], 'USD') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?= (float)$w['wallet_iqd'] > 0 ? 'text-info' : 'text-muted' ?>">
                                                    <?= formatMoney((float)$w['wallet_iqd'], 'IQD') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <span class="text-muted">USD:</span> <?= formatMoney((float)$w['profit_usd'], 'USD') ?><br>
                                                    <span class="text-muted">IQD:</span> <?= formatMoney((float)$w['profit_iqd'], 'IQD') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small fw-bold">
                                                    <span class="text-success"><?= formatMoney($totalUsd, 'USD') ?></span><br>
                                                    <span class="text-info"><?= formatMoney($totalIqd, 'IQD') ?></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <?php if ($canAdjust): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#adjustModal<?= $w['id'] ?>" onclick="setModalAction(<?= $w['id'] ?>, 'deduct')">
                                                            <i class="bi bi-dash-circle me-1"></i>سحب
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#adjustModal<?= $w['id'] ?>" onclick="setModalAction(<?= $w['id'] ?>, 'reset_zero')">
                                                            <i class="bi bi-arrow-counterclockwise me-1"></i>تصفير
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#adjustModal<?= $w['id'] ?>" onclick="setModalAction(<?= $w['id'] ?>, 'credit')">
                                                            <i class="bi bi-plus-circle me-1"></i>شحن
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="wallets.php?tab=ledger&investor_id=<?= $w['id'] ?>" class="btn btn-sm btn-outline-secondary" title="عرض سجل العمليات">
                                                        <i class="bi bi-journal-text"></i>
                                                    </a>
                                                </div>

                                                <!-- Balance Adjustment Modal -->
                                                <?php if ($canAdjust): ?>
                                                    <div class="modal fade" id="adjustModal<?= $w['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content bg-card border-secondary text-white text-start">
                                                                <form method="POST">
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="investor_id" value="<?= $w['id'] ?>">
                                                                    
                                                                    <div class="modal-header border-secondary">
                                                                        <h5 class="modal-title text-gold">
                                                                            <i class="bi bi-wallet2 me-2"></i>إدارة محفظة: <?= htmlspecialchars($w['full_name']) ?>
                                                                        </h5>
                                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                    </div>

                                                                    <div class="modal-body p-3">
                                                                        <!-- Current Balances Display -->
                                                                        <div class="p-3 rounded bg-dark border border-secondary mb-3">
                                                                            <div class="row text-center">
                                                                                <div class="col-6 border-end border-secondary">
                                                                                    <div class="text-muted small">رصيد المحفظة (USD)</div>
                                                                                    <div class="fs-5 fw-bold text-gold"><?= formatMoney((float)$w['wallet_usd'], 'USD') ?></div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <div class="text-muted small">رصيد المحفظة (IQD)</div>
                                                                                    <div class="fs-5 fw-bold text-info"><?= formatMoney((float)$w['wallet_iqd'], 'IQD') ?></div>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Operation Type Selector -->
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">نوع العملية الإدارية المطلوبة:</label>
                                                                            <select name="action" class="form-select form-select-sm action-select-<?= $w['id'] ?>" required onchange="handleActionChange(<?= $w['id'] ?>)">
                                                                                <option value="deduct">🔻 سحب رصيد (خصم مبلغ محدد من المحفظة)</option>
                                                                                <option value="reset_zero">⚠️ تصفير المحفظة بالكامل (إعادة الرصيد إلى 0.00)</option>
                                                                                <option value="credit">➕ شحن إضافي مباشر (إيداع رصيد حر)</option>
                                                                            </select>
                                                                        </div>

                                                                        <!-- Currency -->
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">العملة:</label>
                                                                            <select name="currency" class="form-select form-select-sm" required>
                                                                                <option value="USD">دولار أمريكي (USD)</option>
                                                                                <option value="IQD">دينار عراقي (IQD)</option>
                                                                            </select>
                                                                        </div>

                                                                        <!-- Amount Input (Hidden on Reset) -->
                                                                        <div class="mb-3" id="amountGroup<?= $w['id'] ?>">
                                                                            <label class="form-label fw-bold">المبلغ:</label>
                                                                            <input type="number" step="0.01" min="0.01" name="amount" id="amountInput<?= $w['id'] ?>" class="form-control" placeholder="أدخل المبلغ المطلوب...">
                                                                        </div>

                                                                        <!-- Admin Note (Mandatory) -->
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">سبب الإجراء / البيان الإداري: <span class="text-danger">*</span></label>
                                                                            <textarea name="note" class="form-control" rows="2" placeholder="اكتب سبب السحب أو التصفير أو الشحن لتوثيقه في السجل..." required></textarea>
                                                                            <div class="form-text text-muted">سيتم تسجيل اسمك وتاريخ العملية في سجل الرقابة المالي، وإشعار المستثمر فوراً.</div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="modal-footer border-secondary justify-content-between">
                                                                        <button type="submit" class="btn btn-warning" onclick="return confirm('هل أنت متأكد من تنفيذ هذه العملية المالية على محفظة المستثمر؟')">
                                                                            <i class="bi bi-check-circle me-1"></i>تأكيد التنفيذ
                                                                        </button>
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- ---------------------------------------------------------------- -->
                <!-- Tab 2: Full Transactions Ledger & Audit                          -->
                <!-- ---------------------------------------------------------------- -->
                <div class="form-card mb-4">
                    <form method="GET" action="wallets.php" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="ledger">
                        
                        <div class="col-md-3">
                            <label class="small text-muted mb-1">المستثمر:</label>
                            <select name="investor_id" class="form-select form-select-sm">
                                <option value="0">كافة المستثمرين</option>
                                <?php foreach ($investorWallets as $inv): ?>
                                    <option value="<?= $inv['id'] ?>" <?= $filterInv === (int)$inv['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($inv['full_name']) ?> (<?= $inv['phone'] ?: 'بدون هاتف' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="small text-muted mb-1">نوع العملية:</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>كافة العمليات</option>
                                <option value="topup_credit" <?= $filterType === 'topup_credit' ? 'selected' : '' ?>>شحن معتمد</option>
                                <option value="admin_credit" <?= $filterType === 'admin_credit' ? 'selected' : '' ?>>شحن إداري</option>
                                <option value="admin_debit" <?= $filterType === 'admin_debit' ? 'selected' : '' ?>>خصم/سحب إداري</option>
                                <option value="admin_reset" <?= $filterType === 'admin_reset' ? 'selected' : '' ?>>تصفير رصيد إداري</option>
                                <option value="withdrawal" <?= $filterType === 'withdrawal' ? 'selected' : '' ?>>سحب أرباح/محفظة</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="small text-muted mb-1">العملة:</label>
                            <select name="currency" class="form-select form-select-sm">
                                <option value="all" <?= $filterCur === 'all' ? 'selected' : '' ?>>الكل</option>
                                <option value="USD" <?= $filterCur === 'USD' ? 'selected' : '' ?>>USD</option>
                                <option value="IQD" <?= $filterCur === 'IQD' ? 'selected' : '' ?>>IQD</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="small text-muted mb-1">من تاريخ:</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>

                        <div class="col-md-2">
                            <label class="small text-muted mb-1">إلى تاريخ:</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-gold btn-sm w-100" title="تطبيق الفلتر">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="form-card">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>التاريخ والوقت</th>
                                    <th>المستثمر</th>
                                    <th>نوع العملية</th>
                                    <th>الاتجاه</th>
                                    <th>المبلغ</th>
                                    <th>الرصيد بعد العملية</th>
                                    <th>البيان والملاحظات</th>
                                    <th>المنفذ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            لا توجد معاملات مسجلة تطابق محددات البحث.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $t): 
                                        $badge = getTxBadge($t['type'], $t['direction']);
                                        $isCredit = ($t['direction'] === 'credit');
                                    ?>
                                        <tr>
                                            <td class="text-muted small">#<?= $t['id'] ?></td>
                                            <td class="small text-muted font-monospace">
                                                <?= date('Y/m/d H:i', strtotime($t['created_at'])) ?>
                                            </td>
                                            <td>
                                                <a href="investor_view.php?id=<?= $t['investor_id'] ?>" class="text-gold fw-bold text-decoration-none">
                                                    <?= htmlspecialchars($t['investor_name']) ?>
                                                </a>
                                                <div class="text-muted small"><?= htmlspecialchars($t['investor_phone'] ?: '') ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $badge['bg'] ?>">
                                                    <i class="bi <?= $badge['icon'] ?> me-1"></i><?= $badge['text'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($isCredit): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success">إيداع (+)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger">خصم (-)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?= $isCredit ? 'text-success' : 'text-danger' ?>">
                                                    <?= $isCredit ? '+' : '-' ?><?= formatMoney((float)$t['amount'], $t['currency']) ?>
                                                </span>
                                            </td>
                                            <td class="font-monospace fw-bold text-white">
                                                <?= formatMoney((float)$t['balance_after'], $t['currency']) ?>
                                            </td>
                                            <td class="small" style="max-width: 250px;">
                                                <?= htmlspecialchars($t['note'] ?: '—') ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= htmlspecialchars($t['creator_username'] ?: 'النظام') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function setModalAction(id, action) {
    const select = document.querySelector('.action-select-' + id);
    if (select) {
        select.value = action;
        handleActionChange(id);
    }
}

function handleActionChange(id) {
    const select = document.querySelector('.action-select-' + id);
    const amountGroup = document.getElementById('amountGroup' + id);
    const amountInput = document.getElementById('amountInput' + id);
    if (!select || !amountGroup) return;

    if (select.value === 'reset_zero') {
        amountGroup.style.display = 'none';
        if (amountInput) amountInput.removeAttribute('required');
    } else {
        amountGroup.style.display = 'block';
        if (amountInput) amountInput.setAttribute('required', 'required');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
