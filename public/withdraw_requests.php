<?php
// public/withdraw_requests.php — View & Manage Investor Withdrawal Requests
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('withdrawals.view');
$pdo = getPDO();

// Handle Actions (Link Legacy Deposit or Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    $action = $_POST['action'];
    $reqId = (int)($_POST['request_id'] ?? 0);

    // Legacy Request Link Deposit Action
    if ($action === 'link_deposit' && $reqId > 0) {
        $assignDepositId = (int)($_POST['assign_deposit_id'] ?? 0);
        if (!userCan('withdrawals.approve')) {
            setFlash('danger', 'ليس لديك الصلاحية المطلوبة بربط وتدقيق طلبات السحب.');
            header('Location: withdraw_requests.php');
            exit;
        }

        $wStmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ?");
        $wStmt->execute([$reqId]);
        $wReq = $wStmt->fetch();

        if (!$wReq || $wReq['deposit_id'] !== null) {
            setFlash('danger', 'الطلب غير متاح للربط أو تم ربطه مسبقاً.');
        } else {
            try {
                $pdo->beginTransaction();

                // Validate assigned deposit belongs to investor
                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? AND investor_id = ? FOR UPDATE");
                $depStmt->execute([$assignDepositId, $wReq['investor_id']]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة المختارة غير صالحة أو لا تعود لنفس المستثمر.');
                }

                // Update withdraw_request with deposit_id
                $upW = $pdo->prepare("UPDATE withdraw_requests SET deposit_id = ?, currency = ?, status = 'pending' WHERE id = ?");
                $upW->execute([$assignDepositId, $deposit['currency'], $reqId]);

                // Create linked Approval Request
                $appReqId = createApprovalRequest(
                    $pdo,
                    'withdrawals.approve',
                    'withdraw_request',
                    $reqId,
                    [
                        'withdraw_request_id' => $reqId
                    ]
                );

                $pdo->prepare("UPDATE withdraw_requests SET approval_request_id = ? WHERE id = ?")
                    ->execute([$appReqId, $reqId]);

                $pdo->commit();

                logActivity($pdo, 'LINK_DEPOSIT_TO_WITHDRAWAL', 'withdraw_requests', $reqId, null, [
                    'assigned_deposit_id' => $assignDepositId,
                    'approval_request_id' => $appReqId
                ]);

                setFlash('success', "تم ربط طلب السحب القديم #{$reqId} بالوديعة #{$assignDepositId} بنجاح وإنشاء طلب موافقة (رقم #{$appReqId}).");

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('danger', getSafeErrorMessage($e, 'حدث خطأ أثناء ربط طلب السحب بالوديعة.'));
            }
        }
        header('Location: withdraw_requests.php');
        exit;
    }

    if ($action === 'reject' && $reqId > 0) {
        $reason = trim($_POST['note'] ?? '');
        if (empty($reason)) {
            setFlash('danger', 'سبب الرفض إجباري.');
            header('Location: withdraw_requests.php');
            exit;
        }

        if (!userCan('withdrawals.approve')) {
            setFlash('danger', 'ليس لديك الصلاحية المطلوبة (withdrawals.approve) لرفض هذا الطلب.');
            header('Location: withdraw_requests.php');
            exit;
        }

        try {
            $chkApp = $pdo->prepare("SELECT id FROM approval_requests WHERE operation_type = 'withdrawals.approve' AND entity_id = ? AND status = 'pending'");
            $chkApp->execute([$reqId]);
            $appId = $chkApp->fetchColumn();

            if (!$appId) {
                $appId = createApprovalRequest($pdo, 'withdrawals.approve', 'withdraw_request', $reqId, ['withdraw_request_id' => $reqId]);
            }

            rejectApprovalRequest($pdo, (int)$appId, currentUserId(), $reason);
            setFlash('success', 'تم رفض طلب السحب وتسجيل السبب بنجاح.');

        } catch (Throwable $e) {
            setFlash('danger', getSafeErrorMessage($e, 'حدث خطأ أثناء رفض طلب السحب.'));
        }

        header('Location: withdraw_requests.php');
        exit;
    }
}

// Fetch withdrawal requests
$statusFilter = $_GET['status'] ?? '';
$where = ['1=1']; $params = [];

if ($statusFilter) {
    $where[] = 'wr.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT wr.*, i.full_name AS investor_name, 
           d.amount AS deposit_amount, d.currency AS deposit_currency,
           u.username AS staff_name
    FROM withdraw_requests wr
    JOIN investors i ON i.id = wr.investor_id
    LEFT JOIN deposits d ON d.id = wr.deposit_id
    LEFT JOIN users u ON u.id = wr.staff_user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY wr.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'طلبات السحب للمستثمرين';
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
                    <h1 class="page-title"><i class="bi bi-cash-coin me-2"></i>طلبات السحب من البوابة</h1>
                    <p class="page-subtitle">إجمالي الطلبات: <?= count($requests) ?></p>
                </div>
            </div>

            <div class="table-wrapper">
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستثمر</th>
                                <th>الوديعة المرتبطة</th>
                                <th>المبلغ والعملة</th>
                                <th>تاريخ الطلب</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?= $r['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($r['investor_name']) ?></td>
                                    <td>
                                        <?php if ($r['deposit_id']): ?>
                                            وديعة #<?= $r['deposit_id'] ?> (<?= formatMoney($r['deposit_amount'], $r['deposit_currency']) ?>)
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>طلب قديم بلا وديعة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="fw-bold text-gold"><?= formatMoney($r['amount'], $r['deposit_currency'] ?: $r['currency']) ?></span></td>
                                    <td><?= formatDate($r['request_date']) ?></td>
                                    <td><span class="badge <?= statusBadge($r['status']) ?>"><?= arabicStatus($r['status']) ?></span></td>
                                    <td>
                                        <?php if ($r['status'] === 'needs_review' || ($r['status'] === 'pending' && !$r['deposit_id'])): ?>
                                            <!-- Button to open link deposit modal -->
                                            <?php if (userCan('withdrawals.approve')): ?>
                                                <button type="button" class="btn btn-sm btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#linkModal<?= $r['id'] ?>">
                                                    <i class="bi bi-link-45deg me-1"></i> ربط بوديعة
                                                </button>

                                                <!-- Link Deposit Modal -->
                                                <div class="modal fade" id="linkModal<?= $r['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content bg-dark text-white border-secondary">
                                                            <form method="post">
                                                                <?= csrfField() ?>
                                                                <input type="hidden" name="action" value="link_deposit">
                                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                                <div class="modal-header border-secondary">
                                                                    <h5 class="modal-title">ربط طلب السحب القديم #<?= $r['id'] ?> بوديعة</h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body text-start">
                                                                    <p class="text-muted small">هذا الطلب تم إنشاؤه مسبقاً قبل ربط السحوبات بالودائع. يرجى تحديد الوديعة الصحيحة للمستثمر <strong><?= htmlspecialchars($r['investor_name']) ?></strong>:</p>
                                                                    <?php
                                                                    $invDeps = $pdo->prepare("SELECT d.*, dt.name_ar FROM deposits d JOIN deposit_types dt ON dt.id=d.deposit_type_id WHERE d.investor_id = ?");
                                                                    $invDeps->execute([$r['investor_id']]);
                                                                    $depsList = $invDeps->fetchAll();
                                                                    ?>
                                                                    <label class="form-label">الوديعة المستهدفة <span class="text-danger">*</span></label>
                                                                    <select name="assign_deposit_id" class="form-select" required>
                                                                        <option value="">— اختر الوديعة —</option>
                                                                        <?php foreach ($depsList as $ld): ?>
                                                                            <option value="<?= $ld['id'] ?>">
                                                                                وديعة #<?= $ld['id'] ?> (<?= formatMoney($ld['amount'], $ld['currency']) ?>) — أرباح متراكمة: <?= formatMoney($ld['accumulated_profit'], $ld['currency']) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="modal-footer border-secondary">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                    <button type="submit" class="btn btn-gold">تأكيد الربط وإحالة للموافقة</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-warning small">بانتظار تدقيق الإدارة</span>
                                            <?php endif; ?>
                                        <?php elseif ($r['status'] === 'pending'): ?>
                                            <div class="d-flex gap-1">
                                                <a href="approval_requests.php?status=pending" class="btn btn-sm btn-outline-gold">
                                                    <i class="bi bi-clock me-1"></i> قيد الموافقات
                                                </a>

                                                <?php if (userCan('withdrawals.approve')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['id'] ?>">
                                                        <i class="bi bi-x-circle"></i> رفض
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Reject Modal -->
                                            <div class="modal fade" id="rejectModal<?= $r['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content bg-dark text-white border-secondary">
                                                        <form method="post">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                            <div class="modal-header border-secondary">
                                                                <h5 class="modal-title">رفض طلب السحب #<?= $r['id'] ?></h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-start">
                                                                <label class="form-label">سبب الرفض <span class="text-danger">*</span></label>
                                                                <textarea name="note" class="form-control" rows="3" required placeholder="سبب الرفض..."></textarea>
                                                            </div>
                                                            <div class="modal-footer border-secondary">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small"><?= arabicStatus($r['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">لا توجد طلبات سحب</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>