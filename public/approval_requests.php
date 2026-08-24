<?php
// public/approval_requests.php — Approval Workflows Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('approvals.view');
$pdo = getPDO();
$pageTitle = 'طلبات الموافقة المعلقة';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($action === 'approve' && $requestId > 0) {
        // Section 2: Pre-check permission BEFORE any state change
        try {
            preCheckApprovalPermission($pdo, $requestId, currentUserId());
        } catch (AuthorizationException $e) {
            http_response_code(403);
            setFlash('danger', $e->getMessage());
            header('Location: approval_requests.php');
            exit;
        } catch (BusinessRuleException $e) {
            setFlash('danger', $e->getMessage());
            header('Location: approval_requests.php');
            exit;
        }

        $res = executeApprovalRequest($pdo, $requestId, currentUserId());
        if ($res['success']) {
            setFlash('success', 'تمت الموافقة والتنفيذ بنجاح: ' . ($res['reference'] ?? ''));
        } else {
            if (!empty($res['is_auth_error'])) {
                http_response_code(403);
            }
            setFlash('danger', $res['safe_message'] ?? 'تعذر تنفيذ العملية.');
        }
    } elseif ($action === 'reject' && $requestId > 0) {
        $reason = trim($_POST['rejection_reason'] ?? '');
        try {
            // Section 2: Pre-check permission for rejection too
            preCheckApprovalPermission($pdo, $requestId, currentUserId());
            if (rejectApprovalRequest($pdo, $requestId, currentUserId(), $reason)) {
                setFlash('info', 'تم رفض طلب الموافقة وتسجيل السبب بنجاح.');
            } else {
                setFlash('danger', 'تعذر رفض الطلب.');
            }
        } catch (AuthorizationException $e) {
            http_response_code(403);
            setFlash('danger', $e->getMessage());
        } catch (Throwable $e) {
            setFlash('danger', getSafeErrorMessage($e, 'حدث خطأ أثناء رفض الطلب.'));
        }
    }
    header('Location: approval_requests.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'pending';
$where = ['1=1']; $params = [];

if ($statusFilter) {
    $where[] = 'ar.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT ar.*, u.full_name AS requester_name, u2.full_name AS approver_name
    FROM approval_requests ar
    LEFT JOIN users u ON u.id = ar.requested_by
    LEFT JOIN users u2 ON u2.id = ar.approved_by
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ar.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

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
                    <h1 class="page-title"><i class="bi bi-patch-check me-2"></i>إدارة طلبات الموافقة المالية</h1>
                    <p class="page-subtitle">إجمالي الطلبات: <?= count($requests) ?></p>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="d-flex gap-2 mb-3">
                <a href="approval_requests.php?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-gold' : 'btn-outline-gold' ?>">معلقة</a>
                <a href="approval_requests.php?status=executed" class="btn btn-sm <?= $statusFilter === 'executed' ? 'btn-gold' : 'btn-outline-gold' ?>">منفذة</a>
                <a href="approval_requests.php?status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-gold' : 'btn-outline-gold' ?>">مرفوضة</a>
                <a href="approval_requests.php?status=" class="btn btn-sm <?= $statusFilter === '' ? 'btn-gold' : 'btn-outline-gold' ?>">الكل</a>
            </div>

            <div class="table-wrapper">
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>نوع العملية</th>
                                <th>الكيان المستهدف</th>
                                <th>طالب العملية</th>
                                <th>تاريخ الطلب</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r):
                                $requiredPerm = getRequiredApprovalPermission($r['operation_type']);
                                $canApproveThis = userCan($requiredPerm);
                                ?>
                                <tr>
                                    <td><?= $r['id'] ?></td>
                                    <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($r['operation_type']) ?></span></td>
                                    <td><?= htmlspecialchars($r['entity_type']) ?> #<?= $r['entity_id'] ?: '—' ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($r['requester_name'] ?: 'مستثمر / نظام') ?></td>
                                    <td><?= formatDate($r['created_at']) ?></td>
                                    <td><span class="badge <?= statusBadge($r['status']) ?>"><?= arabicStatus($r['status']) ?></span></td>
                                    <td>
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <?php if ($canApproveThis): ?>
                                                <form method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('هل أنت متأكد من الاعتماد والتنفيذ المالي؟');">
                                                        <i class="bi bi-check-lg me-1"></i> موافقة وتنفيذ
                                                    </button>
                                                </form>

                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejModal<?= $r['id'] ?>">
                                                    <i class="bi bi-x-lg"></i> رفض
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">تتطلب صلاحية <?= htmlspecialchars($requiredPerm) ?></span>
                                            <?php endif; ?>

                                            <?php if ($canApproveThis): ?>
                                            <!-- Rejection Modal -->
                                            <div class="modal fade" id="rejModal<?= $r['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content bg-dark text-white border-secondary">
                                                        <form method="post">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                            <div class="modal-header border-secondary">
                                                                <h5 class="modal-title">رفض طلب الموافقة #<?= $r['id'] ?></h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <label class="form-label">سبب الرفض <span class="text-danger">*</span></label>
                                                                <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="أدخل سبب الرفض الإجباري..."></textarea>
                                                            </div>
                                                            <div class="modal-footer border-secondary">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; /* canApproveThis modal */ ?>
                                        <?php else: ?>
                                            <span class="text-muted small"><?= $r['status'] === 'executed' ? 'اعتمد بواسطة ' . htmlspecialchars($r['approver_name'] ?: 'المسؤول') : 'مرفوض' ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($requests)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">لا توجد طلبات موافقة في الوقت الحالي</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
