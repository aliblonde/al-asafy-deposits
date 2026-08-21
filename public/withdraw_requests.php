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

// Handle Submit Approval Request for Pending Investor Withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    $action = $_POST['action'];
    $reqId = (int)($_POST['request_id'] ?? 0);

    if ($action === 'submit_approval' && $reqId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ?");
        $stmt->execute([$reqId]);
        $wReq = $stmt->fetch();

        if (!$wReq || $wReq['status'] !== 'pending') {
            setFlash('danger', 'طلب السحب غير متاح أو تم التخاذ قرار بشأنه مسبقاً.');
        } else {
            try {
                // Create Approval Request ONLY (Zero direct execution)
                $appReqId = createApprovalRequest(
                    $pdo,
                    'withdrawals.approve',
                    'withdraw_request',
                    $reqId,
                    [
                        'withdraw_request_id' => $reqId
                    ]
                );

                setFlash('info', 'تم رفع طلب موافقة لاعتماد طلب السحب #' . $reqId . ' (طلب موافقة رقم #' . $appReqId . '). سيتم خصم المبلغ وصرفه تلقائياً عند الموافقة.');

            } catch (Throwable $e) {
                setFlash('danger', getSafeErrorMessage($e, 'حدث خطأ أثناء تقديم طلب الموافقة للسحب.'));
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
            // Find linked pending approval request or create & reject it directly
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
    SELECT wr.*, i.full_name AS investor_name, d.amount AS deposit_amount, d.currency AS deposit_currency,
           u.full_name AS staff_name
    FROM withdraw_requests wr
    JOIN investors i ON i.id = wr.investor_id
    JOIN deposits d ON d.id = wr.deposit_id
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
                                <th>الوديعة</th>
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
                                    <td>وديعة #<?= $r['deposit_id'] ?> (<?= formatMoney($r['deposit_amount'], $r['deposit_currency']) ?>)</td>
                                    <td><span class="fw-bold text-gold"><?= formatMoney($r['amount'], $r['deposit_currency']) ?></span></td>
                                    <td><?= formatDate($r['request_date']) ?></td>
                                    <td><span class="badge <?= statusBadge($r['status']) ?>"><?= arabicStatus($r['status']) ?></span></td>
                                    <td>
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <div class="d-flex gap-1">
                                                <form method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="submit_approval">
                                                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-gold">
                                                        <i class="bi bi-send me-1"></i> إرسال للموافقة
                                                    </button>
                                                </form>

                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['id'] ?>">
                                                    <i class="bi bi-x-circle"></i> رفض
                                                </button>
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
                                                            <div class="modal-content p-3">
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
                                            <span class="text-muted small">تم البت فيه</span>
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