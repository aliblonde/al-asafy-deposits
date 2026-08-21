<?php
// public/archived_records.php — View & Manage Archived (Soft-Deleted) Records
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/archive.php';
require_once __DIR__ . '/../config/csrf.php';

requirePermission('archive.view');

$pdo = getPDO();
$pageTitle = 'أرشيف السجلات المحذوفة';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $archiveId = (int)($_POST['archive_id'] ?? 0);

    if ($action === 'restore' && $archiveId > 0) {
        requirePermission('archive.restore');
        try {
            restoreArchivedRecord($pdo, $archiveId);
            setFlash('success', 'تمت استعادة السجل المحذوف وإعادته للنظام بنجاح.');
        } catch (Exception $e) {
            setFlash('danger', 'فشلت استعادة السجل: ' . $e->getMessage());
        }
    } elseif ($action === 'permanent_delete' && $archiveId > 0) {
        requirePermission('archive.permanent_delete');
        $confirm = trim($_POST['confirm_phrase'] ?? '');
        if ($confirm !== 'حذف نهائي') {
            setFlash('danger', 'عبارة التأكيد غير صحيحة. يجب كتابة "حذف نهائي".');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM archived_records WHERE id = ?");
                $stmt->execute([$archiveId]);
                logActivity($pdo, 'PERMANENT_DELETE_ARCHIVE', 'archived_records', $archiveId, null, ['deleted_by' => currentUserId()]);
                setFlash('success', 'تم الحذف النهائي للسجل من الأرشيف.');
            } catch (Exception $e) {
                setFlash('danger', 'حدث خطأ أثناء الحذف النهائي: ' . $e->getMessage());
            }
        }
    }
    header('Location: archived_records.php');
    exit;
}

// Fetch archived records
$stmt = $pdo->query("
    SELECT a.*, u.username as deleted_by_user 
    FROM archived_records a 
    LEFT JOIN users u ON u.id = a.deleted_by 
    ORDER BY a.deleted_at DESC
");
$archives = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="bi bi-archive me-2"></i><?= $pageTitle ?></h1>
                <p class="page-subtitle">سجل المحذوفات المؤرشفة والقدرة على استعادتها</p>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/alerts.php'; ?>

        <div class="table-wrapper">
            <table class="table table-dark-custom mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نوع السجل</th>
                        <th>المعرف الأصلي</th>
                        <th>سبب الحذف</th>
                        <th>بواسطة</th>
                        <th>التاريخ</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archives as $arc): ?>
                        <tr>
                            <td><?= $arc['id'] ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($arc['record_type']) ?></span></td>
                            <td class="fw-bold text-gold">#<?= $arc['original_id'] ?></td>
                            <td><?= htmlspecialchars($arc['deletion_reason'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($arc['deleted_by_user'] ?: 'مستخدم #' . $arc['deleted_by']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($arc['deleted_at'])) ?></td>
                            <td class="text-center d-flex gap-2 justify-content-center">
                                <?php if (userCan('archive.restore')): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="archive_id" value="<?= $arc['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('هل أنت متأكد من استعادة هذا السجل؟')">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> استعادة
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (userCan('archive.permanent_delete')): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $arc['id'] ?>">
                                        <i class="bi bi-trash me-1"></i> حذف نهائي
                                    </button>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?= $arc['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content bg-dark border-danger text-light">
                                                <div class="modal-header border-bottom border-secondary">
                                                    <h5 class="modal-title text-danger">حذف نهائي لا يمكن التراجع عنه</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body text-start">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="permanent_delete">
                                                        <input type="hidden" name="archive_id" value="<?= $arc['id'] ?>">
                                                        <p>هل أنت متأكد من الحذف النهائي لهذا السجل الأصلي #<?= $arc['original_id'] ?>؟</p>
                                                        <label class="form-label small text-muted">اكتب عبارت التأكيد التالية للمتابعة: <strong>حذف نهائي</strong></label>
                                                        <input type="text" name="confirm_phrase" class="form-control form-control-sm" required placeholder="حذف نهائي">
                                                    </div>
                                                    <div class="modal-footer border-top border-secondary">
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                        <button type="submit" class="btn btn-sm btn-danger">تأكيد الحذف النهائي</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$archives): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">لا توجد سجلات مؤرشفة حالياً</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>
