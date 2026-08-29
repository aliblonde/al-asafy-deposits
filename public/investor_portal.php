<?php
// public/investor_portal.php — Investor Self-Service Portal
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireLogin();

$user = ['role' => currentRole(), 'investor_id' => currentInvestorId()];
$pdo = getPDO();

$investorId = $user['investor_id'] ?? 0;

// Admin/Staff impersonation override for viewing investor portal
if (isset($_GET['as_investor_id'])) {
    if (!userCan('investors.impersonate')) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;color:#c00;padding:50px;text-align:center;direction:rtl">403 — غير مصرح لك بانتحال صفة المستثمر (تتطلب صلاحية investors.impersonate).</div>');
    }
    $targetInvId = (int)$_GET['as_investor_id'];
    if ($targetInvId > 0) {
        $investorId = $targetInvId;
        logActivity($pdo, 'IMPERSONATE_INVESTOR_VIEW', 'investors', $investorId, null, [
            'admin_user_id' => currentUserId(),
            'target_investor_id' => $investorId
        ]);
    }
} elseif ($user['role'] !== 'investor') {
    header('Location: dashboard.php');
    exit;
}

if (!$investorId) {
    die('<div style="font-family:sans-serif;color:#c00;padding:50px;text-align:center;direction:rtl">عذراً، هذا الحساب غير مرتبط ببيانات مستثمر. يرجى التواصل مع الدعم.</div>');
}

// Fetch investor info
$invStmt = $pdo->prepare("SELECT * FROM investors WHERE id = ?");
$invStmt->execute([$investorId]);
$investor = $invStmt->fetch();

if (!$investor) {
    die('<div style="font-family:sans-serif;color:#c00;padding:50px;text-align:center">بيانات المستثمر غير موجودة.</div>');
}

// Fetch Deposits
$depStmt = $pdo->prepare("
    SELECT d.*, dt.name_ar AS type_name, dt.code
    FROM deposits d
    JOIN deposit_types dt ON dt.id = d.deposit_type_id
    WHERE d.investor_id = ?
    ORDER BY d.created_at DESC
");
$depStmt->execute([$investorId]);
$deposits = $depStmt->fetchAll();

// Pending withdraw requests map by deposit_id
$pendingMap = [];
$pStmt = $pdo->prepare("
    SELECT deposit_id, COALESCE(SUM(amount), 0) AS pending_total 
    FROM withdraw_requests 
    WHERE investor_id = ? AND status = 'pending' AND deposit_id IS NOT NULL 
    GROUP BY deposit_id
");
$pStmt->execute([$investorId]);
while ($r = $pStmt->fetch()) {
    $pendingMap[$r['deposit_id']] = (float)$r['pending_total'];
}

// Handle withdraw request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $depositId = (int)($_POST['deposit_id'] ?? 0);
    $amount = (float)($_POST['withdraw_amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if (!$depositId) {
        setFlash('danger', 'يرجى اختيار الوديعة المراد السحب منها.');
    } elseif ($amount <= 0) {
        setFlash('danger', 'المبلغ المطلوب يجب أن يكون أكبر من صفر.');
    } else {
        try {
            $pdo->beginTransaction();

            // Lock deposit FOR UPDATE
            $stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? AND investor_id = ? FOR UPDATE");
            $stmt->execute([$depositId, $investorId]);
            $deposit = $stmt->fetch();

            if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                throw new Exception('الوديعة المحددة غير صالحة أو غير مجهزة للسحب.');
            }

            $currency = $deposit['currency'];
            $accumulated = (float)$deposit['accumulated_profit'];

            // Calculate pending withdraw requests for THIS deposit_id
            $pendingStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdraw_requests WHERE deposit_id = ? AND status = 'pending'");
            $pendingStmt->execute([$depositId]);
            $totalPendingForDep = (float)$pendingStmt->fetchColumn();

            $netAvailable = max(0.00, $accumulated - $totalPendingForDep);

            if ($amount > $netAvailable) {
                throw new Exception('عفواً، رصيد الأرباح المتاح للسحب لهذه الوديعة هو ' . formatMoney($netAvailable, $currency) . ' فقط (بعد خصم الطلبات المعلقة).');
            }

            // Insert withdraw_request with deposit_id
            $insW = $pdo->prepare("
                INSERT INTO withdraw_requests (investor_id, deposit_id, amount, currency, request_date, status, note)
                VALUES (?, ?, ?, ?, NOW(), 'pending', ?)
            ");
            $insW->execute([$investorId, $depositId, $amount, $currency, $note]);
            $wReqId = (int)$pdo->lastInsertId();

            // Automatically create Approval Request (Section 3)
            $appReqId = createApprovalRequest(
                $pdo,
                'withdrawals.approve',
                'withdraw_request',
                $wReqId,
                [
                    'withdraw_request_id' => $wReqId
                ]
            );

            // Store approval_request_id immediately in withdraw_requests
            $pdo->prepare("UPDATE withdraw_requests SET approval_request_id = ? WHERE id = ?")
                ->execute([$appReqId, $wReqId]);

            $pdo->commit();

            logActivity($pdo, 'REQUEST_WITHDRAW', 'withdraw_requests', $wReqId, null, [
                'investor_id' => $investorId,
                'deposit_id' => $depositId,
                'amount' => $amount,
                'currency' => $currency,
                'approval_request_id' => $appReqId
            ]);

            setFlash('success', 'تم تقديم طلب السحب بنجاح (طلب موافقة رقم #' . $appReqId . '). سيتم مراجعته واعتماده من الإدارة.');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', getSafeErrorMessage($e, 'حدث خطأ أثناء تقديم طلب السحب.'));
        }
    }
    header('Location: investor_portal.php');
    exit;
}

// Fetch Withdrawal Requests
$wrStmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE investor_id = ? ORDER BY request_date DESC");
$wrStmt->execute([$investorId]);
$withdrawRequests = $wrStmt->fetchAll();

// Fetch Transactions
$txStmt = $pdo->prepare("SELECT * FROM transactions WHERE investor_id = ? ORDER BY date DESC LIMIT 50");
$txStmt->execute([$investorId]);
$transactions = $txStmt->fetchAll();

$investorName = $investor['full_name'] ?? '';
$pageTitle = 'بوابة المستثمر';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة المستثمر — <?= htmlspecialchars($investorName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
</head>

<body>
    <?php
    $unreadNotifCount = getUnreadNotificationsCount(getPDO(), currentUserId());
    ?>
    <header class="topbar">
        <div class="d-flex align-items-center justify-content-between w-100 px-3">
            <div class="brand flex-grow-1 text-end" style="letter-spacing: normal;">
                <i class="bi bi-person-circle text-gold me-2"></i>
                <span class="text-white fw-bold">بوابة المستثمر</span>
                <span class="text-gold opacity-75 ms-2">| <?= htmlspecialchars($investorName) ?></span>
            </div>
            
            <div class="d-flex align-items-center gap-3" dir="rtl">
                <!-- Notifications Bell -->
                <div class="dropdown me-3">
                    <button class="btn btn-sm btn-outline-secondary border-0 p-1 position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell fs-5 text-white"></i>
                        <?php if ($unreadNotifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;">
                            <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifDropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li><h6 class="dropdown-header d-flex justify-content-between align-items-center">
                            الإشعارات
                            <?php if ($unreadNotifCount > 0): ?>
                            <button class="btn btn-sm btn-link p-0 text-decoration-none" onclick="markAllRead(event)">تحديد الكل كمقروء</button>
                            <?php endif; ?>
                        </h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php
                        $latestNotifs = getLatestNotifications(getPDO(), currentUserId(), 5);
                        if (empty($latestNotifs)):
                        ?>
                            <li><span class="dropdown-item text-center text-muted small">لا توجد إشعارات جديدة</span></li>
                        <?php else: foreach ($latestNotifs as $n): ?>
                            <li>
                                <a class="dropdown-item <?= $n['is_read'] ? 'text-muted' : 'fw-bold' ?>" href="<?= htmlspecialchars($n['link'] ?? '#') ?>" onclick="markRead(<?= $n['id'] ?>)">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-gold"><?= htmlspecialchars($n['title']) ?></small>
                                        <small class="text-muted" style="font-size: 0.65rem;"><?= date('m/d H:i', strtotime($n['created_at'])) ?></small>
                                    </div>
                                    <div style="font-size: 0.8rem; white-space: normal; line-height: 1.4;"><?= htmlspecialchars($n['message']) ?></div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>

                <button id="themeToggle" class="btn btn-sm btn-outline-secondary border-0 p-1 me-2" title="تغيير المظهر">
                    <i class="bi bi-brightness-high fs-5 text-warning"></i>
                </button>
                
                <form method="POST" action="logout.php" class="d-inline m-0">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="تسجيل الخروج">
                        <i class="bi bi-box-arrow-right me-1"></i> خروج
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="page-content container py-4">
        <?php include __DIR__ . '/../includes/alerts.php'; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-gold"><i class="bi bi-piggy-bank"></i></div>
                    <div class="stat-label">عدد الودائع</div>
                    <div class="stat-value"><?= count($deposits) ?></div>
                </div>
            </div>
        </div>

        <!-- Deposits Table -->
        <div class="section-title"><i class="bi bi-bank me-1"></i>ودائعي الاستثمارية</div>
        <div class="table-wrapper mb-4">
            <div class="table-responsive">
                <table class="table table-dark-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نوع الوديعة</th>
                            <th>المبلغ والعملة</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ الانتهاء</th>
                            <th>الأرباح المتراكمة المتاحة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposits as $d):
                            $pendingForDep = (float)($pendingMap[$d['id']] ?? 0);
                            $availableForDep = max(0.00, (float)$d['accumulated_profit'] - $pendingForDep);
                            ?>
                            <tr>
                                <td><?= $d['id'] ?></td>
                                <td><span class="badge <?= typeBadge($d['code']) ?>"><?= htmlspecialchars($d['type_name']) ?></span></td>
                                <td class="fw-bold text-gold"><?= formatMoney($d['amount'], $d['currency']) ?></td>
                                <td><?= formatDate($d['start_date']) ?></td>
                                <td><?= formatDate($d['end_date']) ?></td>
                                <td>
                                    <span class="fw-bold text-success"><?= formatMoney($d['accumulated_profit'], $d['currency']) ?></span>
                                    <?php if ($pendingForDep > 0): ?>
                                        <div class="small text-warning mt-1">(قيد السحب المعلق: <?= formatMoney($pendingForDep, $d['currency']) ?>)</div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= statusBadge($d['status']) ?>"><?= arabicStatus($d['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($deposits)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">لا توجد ودائع نشطة</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Withdraw Request Form (Linked to Specific Deposit) -->
        <div class="section-title"><i class="bi bi-arrow-up-circle me-1"></i>طلب سحب أرباح</div>
        <div class="form-card mb-5">
            <form method="post" action="">
                <?= csrfField() ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-white">اختر الوديعة <span class="text-danger">*</span></label>
                        <select name="deposit_id" class="form-select" required>
                            <option value="">— اختر الوديعة —</option>
                            <?php foreach ($deposits as $d):
                                if ((float)$d['accumulated_profit'] <= 0 || !in_array($d['status'], ['active','completed'], true)) continue;
                                $pendingForDep = (float)($pendingMap[$d['id']] ?? 0);
                                $availableForDep = max(0.00, (float)$d['accumulated_profit'] - $pendingForDep);
                                if ($availableForDep <= 0) continue;
                                ?>
                                <option value="<?= $d['id'] ?>">
                                    وديعة #<?= $d['id'] ?> (<?= formatMoney($d['amount'], $d['currency']) ?>) — رصيد متاح للسحب: <?= formatMoney($availableForDep, $d['currency']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-white">المبلغ المطلوب سحبه <span class="text-danger">*</span></label>
                        <input type="number" name="withdraw_amount" class="form-control fw-bold" step="0.01" min="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-gold w-100" onclick="return confirm('هل أنت متأكد من تقديم طلب السحب للوديعة المحددة؟')">
                            <i class="bi bi-send me-1"></i> إرسال الطلب للموافقة
                        </button>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-white">ملاحظة (اختياري)</label>
                        <input type="text" name="note" class="form-control" placeholder="سبب طلب السحب...">
                    </div>
                </div>
            </form>
        </div>

        <!-- Withdraw Requests List -->
        <div class="section-title"><i class="bi bi-clock-history me-1"></i>طلبات السحب السابقة</div>
        <div class="table-wrapper mb-4">
            <div class="table-responsive">
                <table class="table table-dark-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الوديعة</th>
                            <th>المبلغ والعملة</th>
                            <th>تاريخ الطلب</th>
                            <th>الحالة</th>
                            <th>الملاحظة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawRequests as $wr): ?>
                            <tr>
                                <td><?= $wr['id'] ?></td>
                                <td>وديعة #<?= $wr['deposit_id'] ?: '—' ?></td>
                                <td class="text-gold fw-bold"><?= formatMoney($wr['amount'], $wr['currency']) ?></td>
                                <td><?= formatDate($wr['request_date']) ?></td>
                                <td><span class="badge <?= statusBadge($wr['status']) ?>"><?= arabicStatus($wr['status']) ?></span></td>
                                <td class="small text-muted"><?= htmlspecialchars($wr['note'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($withdrawRequests)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">لا توجد طلبات سحب سابقة</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transactions Ledger -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="section-title mb-0"><i class="bi bi-list-ul me-1"></i>سجل الحركات المالية</div>
            <a href="export_excel.php?report=transactions" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i> تصدير Excel
            </a>
        </div>
        <div class="table-wrapper mb-4">
            <div class="table-responsive">
                <table class="table table-dark-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>رقم الإيصال</th>
                            <th>نوع الحركة</th>
                            <th>العملة</th>
                            <th>المبلغ</th>
                            <th>التاريخ</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="font-monospace text-muted"><?= htmlspecialchars($t['receipt_no']) ?></td>
                                <td><span class="badge bg-secondary"><?= arabicTxType($t['type']) ?></span></td>
                                <td><?= currencyBadge($t['currency'] ?? 'IQD') ?></td>
                                <td class="text-gold fw-bold"><?= formatMoney($t['amount'], $t['currency'] ?? 'IQD') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($t['date'])) ?></td>
                                <td><?= htmlspecialchars($t['note'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">لا توجد معاملات مسجلة</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <footer class="text-center py-3" style="border-top:1px solid var(--border);font-size:0.75rem;color:var(--text-muted)">
        نظام إدارة الودائع الاستثمارية &copy; <?= date('Y') ?> — العسافي للاستثمارات
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>