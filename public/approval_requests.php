<?php
// public/approval_requests.php — Approval Workflows Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';

requireLogin();

$pdo = getPDO();
$pageTitle = 'طلبات الموافقة المعلقة';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($action === 'approve' && $requestId > 0) {
        $res = executeApprovalRequest($pdo, $requestId, currentUserId());
        if ($res['success']) {
            setFlash('success', 'تمت الموافقة والتنفيذ بنجاح: ' . $res['reference']);
        } else {
            setFlash('danger', 'فشل التنفيذ: ' . $res['message']);
        }
    } elseif ($action === 'reject' && $requestId > 0) {
        $reason = trim($_POST['rejection_reason'] ?? '');
        try {
            if (rejectApprovalRequest($pdo, $requestId, currentUserId(), $reason)) {
                setFlash('info', 'تم رفض طلب الموافقة.');
            } else {
                setFlash('danger', 'تعذر رفض الطلب.');
            }
        } catch (Exception $e) {
            setFlash('danger', 'خطأ: ' . $e->getMessage());
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
    SELECT ar.*, u.username as requester_name, app.username as approver_name
    FROM approval_requests ar
    LEFT JOIN users u ON u.id = ar.requested_by
    LEFT JOIN users app ON app.id = ar.approved_by
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ar.created_at DESC LIMIT 200
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="bi bi-file-earmark-check me-2"></i><?= $pageTitle ?></h1>
                <p class="page-subtitle">مراجعة والموافقة على العمليات المالية والتعديلات الحساسة</p>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/alerts.php'; ?>

        <!-- Filter tabs -->
        <div class="d-flex gap-2 mb-3">
            <a href="approval_requests.php?status=pending" class="btn btn-sm <?= $statusFilter==='pending'?'btn-gold':'btn-outline-gold' ?>">المعلقة (Pending)</a>
            <a href="approval_requests.php?status=executed" class="btn btn-sm <?= $statusFilter==='executed'?'btn-gold':'btn-outline-gold' ?>">المنفذة (Executed)</a>
            <a href="approval_requests.php?status=rejected" class="btn btn-sm <?= $statusFilter==='rejected'?'btn-gold':'btn-outline-gold' ?>">المرفوضة (Rejected)</a>
            <a href="approval_requests.php?status=failed" class="btn btn-sm <?= $statusFilter==='failed'?'btn-gold':'btn-outline-gold' ?>">الفاشلة (Failed)</a>
        </div>

        <div class="table-wrapper">
            <table class="table table-dark-custom mb-0" style="font-size:0.88rem">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نوع العملية</th>
                        <th>الكيان</th>
                        <th>بواسطة</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td><code class="text-gold"><?= htmlspecialchars($r['operation_type']) ?></code></td>
                            <td><?= htmlspecialchars($r['entity_type']) ?> #<?= $r['entity_id'] ?: '—' ?></td>
                            <td><?= htmlspecialchars($r['requester_name'] ?: 'مستخدم #' . $r['requested_by']) ?></td>
                            <td>
                                <?php
                                $badgeClass = match($r['status']) {
                                    'pending' => 'bg-warning text-dark',
                                    'executed' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'failed' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($r['status']) ?></span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                            <td class="text-center">
                                <?php if ($r['status'] === 'pending'): ?>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('تأكيد الموافقة والتنفيذ التلقائي لهذه العملية؟')">
                                                <i class="bi bi-check-lg me-1"></i> موافقة وتنفيذ
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['id'] ?>">
                                            <i class="bi bi-x-lg me-1"></i> رفض
                                        </button>
                                    </div>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?= $r['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content bg-dark text-light border-secondary">
                                                <div class="modal-header border-secondary">
                                                    <h5 class="modal-title">رفض طلب الموافقة #<?= $r['id'] ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body text-start">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">سبب الرفض (إجباري)</label>
                                                            <input type="text" name="rejection_reason" class="form-control" required placeholder="ادخل سبب الرفض...">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-secondary">
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                        <button type="submit" class="btn btn-sm btn-danger">تأكيد الرفض</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">
                                        <?= htmlspecialchars($r['approver_name'] ?: '—') ?> 
                                        (<?= $r['rejection_reason'] ? htmlspecialchars($r['rejection_reason']) : htmlspecialchars($r['execution_reference'] ?: '—') ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$requests): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">لا توجد طلبات موافقة حالياً</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>
