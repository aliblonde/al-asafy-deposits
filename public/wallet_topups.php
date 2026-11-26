<?php
// public/wallet_topups.php — View & Manage Investor Wallet Top-Up Requests
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/notifications.php';

requirePermission('deposits.view');
$pdo = getPDO();

// Ensure wallet tables exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `wallet_balances` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `investor_id` INT UNSIGNED NOT NULL,
      `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
      `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_wb_investor_currency` (`investor_id`, `currency`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `wallet_topup_requests` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `investor_id` INT UNSIGNED NOT NULL,
      `amount` DECIMAL(14,2) NOT NULL,
      `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
      `payment_method` VARCHAR(50) NOT NULL,
      `transfer_reference` VARCHAR(100) NULL,
      `receipt_image` VARCHAR(255) NULL,
      `note` TEXT NULL,
      `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `admin_id` INT UNSIGNED NULL,
      `admin_note` VARCHAR(255) NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `processed_at` DATETIME NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Handle Actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    if (!userCan('deposits.create')) {
        setFlash('danger', 'ليس لديك الصلاحية المطلوبة لاعتماد أو رفض طلبات الشحن.');
        header('Location: wallet_topups.php');
        exit;
    }

    $action = $_POST['action'];
    $reqId = (int)($_POST['request_id'] ?? 0);
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));

    $stmt = $pdo->prepare("SELECT * FROM wallet_topup_requests WHERE id = ?");
    $stmt->execute([$reqId]);
    $req = $stmt->fetch();

    if (!$req || $req['status'] !== 'pending') {
        setFlash('danger', 'طلب الشحن غير متاح أو تمت معالجته مسبقاً.');
        header('Location: wallet_topups.php');
        exit;
    }

    $invId = (int)$req['investor_id'];
    $amount = (float)$req['amount'];
    $currency = $req['currency'];

    if ($action === 'approve') {
        try {
            $pdo->beginTransaction();

            // 1. Update wallet_topup_requests
            $up = $pdo->prepare("
                UPDATE wallet_topup_requests 
                SET status = 'approved', admin_id = ?, admin_note = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $up->execute([currentUserId(), $adminNote ?: 'تمت الموافقة وتأكيد الحوالة', $reqId]);

            // 2. Update wallet_balances
            $stmtWb = $pdo->prepare("
                INSERT INTO wallet_balances (investor_id, currency, balance, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance), updated_at = NOW()
            ");
            $stmtWb->execute([$invId, $currency, $amount]);

            // 3. Fetch new balance
            $bStmt = $pdo->prepare("SELECT balance FROM wallet_balances WHERE investor_id = ? AND currency = ?");
            $bStmt->execute([$invId, $currency]);
            $newBal = (float)$bStmt->fetchColumn();

            // 4. Insert into wallet_transactions
            $txW = $pdo->prepare("
                INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, reference_id, note, created_by, created_at)
                VALUES (?, 'topup_credit', 'credit', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $txW->execute([$invId, $amount, $currency, $newBal, $reqId, 'شحن محفظة - طلب رقم #' . $reqId . ($adminNote ? " ({$adminNote})" : ''), currentUserId()]);

            // 5. Insert into transactions
            $receiptNo = 'WTP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $txMain = $pdo->prepare("
                INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
                VALUES (?, ?, 'profit_payout', 'credit', ?, ?, NOW(), ?)
            ");
            $txMain->execute([$receiptNo, $invId, $amount, $currency, "[شحن محفظة معتمد #{$reqId}] " . ($adminNote ?: $req['payment_method'])]);

            // 6. Log Activity
            logActivity($pdo, 'APPROVE_WALLET_TOPUP', 'wallet_topup_requests', $reqId, null, [
                'admin_id' => currentUserId(),
                'investor_id' => $invId,
                'amount' => $amount,
                'currency' => $currency,
                'new_balance' => $newBal
            ]);

            $pdo->commit();

            // 7. Send Push Notification to Investor
            $fmtAmount = number_format($amount, 2);
            notifyInvestor(
                $pdo,
                $invId,
                'تمت الموافقة على شحن المحفظة ✅',
                "تمت مراجعة إشعار التحويل وإيداع مبلغ {$fmtAmount} {$currency} في محفظتك بنجاح.",
                'wallet'
            );

            setFlash('success', "تمت الموافقة على طلب الشحن رقم #{$reqId} وإضافة المبلغ لمحفظة المستثمر بنجاح.");
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', 'حدث خطأ أثناء اعتماد الطلب: ' . $e->getMessage());
        }
    } elseif ($action === 'reject') {
        try {
            $up = $pdo->prepare("
                UPDATE wallet_topup_requests 
                SET status = 'rejected', admin_id = ?, admin_note = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $up->execute([currentUserId(), $adminNote ?: 'تعذر التحقق من إشعار التحويل', $reqId]);

            logActivity($pdo, 'REJECT_WALLET_TOPUP', 'wallet_topup_requests', $reqId, null, [
                'admin_id' => currentUserId(),
                'investor_id' => $invId,
                'reason' => $adminNote
            ]);

            notifyInvestor(
                $pdo,
                $invId,
                'تنبيه بخصوص طلب الشحن ❌',
                "تم رفض طلب شحن المحفظة بمبلغ " . number_format($amount, 2) . " {$currency}. السبب: " . ($adminNote ?: 'تعذر مطابقة الوصل.'),
                'wallet'
            );

            setFlash('warning', "تم رفض طلب الشحن رقم #{$reqId} وإشعار المستثمر.");
        } catch (\Throwable $e) {
            setFlash('danger', 'حدث خطأ أثناء رفض الطلب: ' . $e->getMessage());
        }
    }

    header('Location: wallet_topups.php');
    exit;
}

// Fetch filter
$filterStatus = $_GET['status'] ?? 'pending';
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'pending';
}

$whereClause = $filterStatus === 'all' ? '1=1' : 'w.status = :status';
$sql = "
    SELECT w.*, i.full_name AS investor_name, i.phone AS investor_phone, u.username AS admin_username
    FROM wallet_topup_requests w
    JOIN investors i ON i.id = w.investor_id
    LEFT JOIN users u ON u.id = w.admin_id
    WHERE {$whereClause}
    ORDER BY (w.status = 'pending') DESC, w.created_at DESC
";
$stmt = $pdo->prepare($sql);
if ($filterStatus !== 'all') {
    $stmt->bindValue(':status', $filterStatus);
}
$stmt->execute();
$requests = $stmt->fetchAll();

// Counts
$countStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending_count,
        SUM(status = 'approved') AS approved_count,
        SUM(status = 'rejected') AS rejected_count
    FROM wallet_topup_requests
");
$counts = $countStmt->fetch();

$pageTitle = 'إدارة طلبات شحن المحافظ';
include __DIR__ . '/../includes/header.php';
?>

<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title"><i class="bi bi-wallet2 me-2"></i>طلبات شحن المحافظ الرقمية</h1>
                    <p class="text-muted small mb-0">تدقيق إشعارات التحويل البنكي ومحافظ الكاش واعتماد الرصيد الحر للمستثمرين</p>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <a href="wallet_topups.php?status=pending" class="text-decoration-none">
                        <div class="p-3 rounded bg-base border border-warning text-center <?= $filterStatus === 'pending' ? 'shadow' : '' ?>">
                            <div class="text-muted small">قيد المراجعة</div>
                            <div class="fs-4 fw-bold text-warning"><?= (int)($counts['pending_count'] ?? 0) ?></div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="wallet_topups.php?status=approved" class="text-decoration-none">
                        <div class="p-3 rounded bg-base border border-success text-center <?= $filterStatus === 'approved' ? 'shadow' : '' ?>">
                            <div class="text-muted small">المعتمدة</div>
                            <div class="fs-4 fw-bold text-success"><?= (int)($counts['approved_count'] ?? 0) ?></div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="wallet_topups.php?status=rejected" class="text-decoration-none">
                        <div class="p-3 rounded bg-base border border-danger text-center <?= $filterStatus === 'rejected' ? 'shadow' : '' ?>">
                            <div class="text-muted small">المرفوضة</div>
                            <div class="fs-4 fw-bold text-danger"><?= (int)($counts['rejected_count'] ?? 0) ?></div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="wallet_topups.php?status=all" class="text-decoration-none">
                        <div class="p-3 rounded bg-base border border-gold text-center <?= $filterStatus === 'all' ? 'shadow' : '' ?>">
                            <div class="text-muted small">كافة الطلبات</div>
                            <div class="fs-4 fw-bold text-gold"><?= (int)($counts['total'] ?? 0) ?></div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="form-card">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستثمر</th>
                                <th>المبلغ</th>
                                <th>طريقة التحويل</th>
                                <th>رقم المرجع / الحوالة</th>
                                <th>صورة الوصل</th>
                                <th>تاريخ الطلب</th>
                                <th>الحالة</th>
                                <th class="text-center">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        لا توجد طلبات شحن مطابقة في هذا القسم.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $r['id'] ?></td>
                                        <td>
                                            <a href="investor_view.php?id=<?= $r['investor_id'] ?>" class="text-gold fw-bold text-decoration-none">
                                                <?= htmlspecialchars($r['investor_name']) ?>
                                            </a>
                                            <div class="text-muted small"><?= htmlspecialchars($r['investor_phone'] ?: '—') ?></div>
                                        </td>
                                        <td>
                                            <span class="fs-6 fw-bold text-success">
                                                <?= formatMoney((float)$r['amount'], $r['currency']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($r['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td style="font-family: monospace;">
                                            <?= htmlspecialchars($r['transfer_reference'] ?: '—') ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['receipt_image'])): ?>
                                                <a href="/<?= htmlspecialchars($r['receipt_image']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-image me-1"></i>عرض الوصل
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">بدون وصل</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('Y/m/d H:i', strtotime($r['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>قيد المراجعة</span>
                                            <?php elseif ($r['status'] === 'approved'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>معتمد</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>مرفوض</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($r['status'] === 'pending' && userCan('deposits.create')): ?>
                                                <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#approveModal<?= $r['id'] ?>">
                                                    <i class="bi bi-check-lg"></i> اعتماد
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['id'] ?>">
                                                    <i class="bi bi-x-lg"></i> رفض
                                                </button>

                                                <!-- Approve Modal -->
                                                <div class="modal fade" id="approveModal<?= $r['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <form method="POST" class="modal-content text-start" dir="rtl">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-success"><i class="bi bi-check-circle me-2"></i>تأكيد اعتماد شحن المحفظة</h5>
                                                                <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?= csrfField() ?>
                                                                <input type="hidden" name="action" value="approve">
                                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                                
                                                                <p>هل أنت متأكد من مطابقة إشعار التحويل وإيداع المبلغ في محفظة <strong><?= htmlspecialchars($r['investor_name']) ?></strong>؟</p>
                                                                <div class="alert alert-success py-2 mb-3">
                                                                    المبلغ المراد إيداعه: <strong><?= formatMoney((float)$r['amount'], $r['currency']) ?></strong>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">ملاحظة التدقيق (اختياري)</label>
                                                                    <input type="text" name="admin_note" class="form-control" placeholder="مثال: تم التأكد من دخول الحوالة لحساب الشركة">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-success">تأكيد الإيداع الفوري</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>

                                                <!-- Reject Modal -->
                                                <div class="modal fade" id="rejectModal<?= $r['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <form method="POST" class="modal-content text-start" dir="rtl">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>رفض طلب الشحن</h5>
                                                                <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?= csrfField() ?>
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                                
                                                                <p>يرجى توضيح سبب رفض طلب الشحن للمستثمر:</p>
                                                                <div class="mb-3">
                                                                    <label class="form-label">سبب الرفض <span class="text-danger">*</span></label>
                                                                    <input type="text" name="admin_note" class="form-control" required placeholder="مثال: لم تصل الحوالة لحساب الشركة، أو إشعار غير واضح...">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small"><?= htmlspecialchars($r['admin_note'] ?: 'مكتمل') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
