<?php
// public/activity_logs.php — Audit Trail
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/csrf.php';

requirePermission('audit.view');
$pdo = getPDO();

// Handle Export and Delete-Exported actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['post_action'] ?? '';
    
    if ($postAction === 'export_audit') {
        requirePermission('audit.export');
        $expFrom = $_POST['exp_from'] ?? '';
        $expTo = $_POST['exp_to'] ?? '';

        // Capture max ID at the moment of export to guarantee determinism
        $maxLogId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM activity_logs")->fetchColumn();

        $wExp = ['id <= ?']; $pExp = [$maxLogId];
        if ($expFrom) { $wExp[] = 'created_at >= ?'; $pExp[] = $expFrom . ' 00:00:00'; }
        if ($expTo) { $wExp[] = 'created_at <= ?'; $pExp[] = $expTo . ' 23:59:59'; }

        $expStmt = $pdo->prepare("SELECT * FROM activity_logs WHERE " . implode(' AND ', $wExp) . " ORDER BY created_at ASC");
        $expStmt->execute($pExp);
        $expRows = $expStmt->fetchAll(PDO::FETCH_ASSOC);

        $jsonContent = json_encode($expRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $fileHash = hash('sha256', $jsonContent);
        $recCount = count($expRows);

        $pdo->beginTransaction();
        try {
            // Record in audit_export_history
            $histStmt = $pdo->prepare("
                INSERT INTO audit_export_history (exported_by, export_time, period_start, period_end, record_count, file_hash)
                VALUES (?, NOW(), ?, ?, ?, ?)
            ");
            $histStmt->execute([
                currentUserId(),
                $expFrom ? $expFrom . ' 00:00:00' : null,
                $expTo ? $expTo . ' 23:59:59' : null,
                $recCount,
                $fileHash
            ]);
            $exportId = (int)$pdo->lastInsertId();

            // Populate audit_export_items manifest
            if (!empty($expRows)) {
                $insItem = $pdo->prepare("INSERT IGNORE INTO audit_export_items (export_id, activity_log_id) VALUES (?, ?)");
                foreach ($expRows as $row) {
                    $insItem->execute([$exportId, $row['id']]);
                }
            }

            logActivity($pdo, 'EXPORT_AUDIT_LOGS', 'activity_logs', $exportId, null, [
                'count' => $recCount,
                'hash' => $fileHash,
                'max_id' => $maxLogId
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setFlash('danger', 'فشل حفظ سجل التصدير: ' . $e->getMessage());
            header('Location: activity_logs.php');
            exit;
        }

        $fileName = 'audit_export_' . date('Y-m-d_H-i-s') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $jsonContent;
        exit;
    } elseif ($postAction === 'delete_exported_audit') {
        requirePermission('audit.delete_exported');
        $delFrom = $_POST['del_from'] ?? '';
        $delTo = $_POST['del_to'] ?? '';

        if (!$delFrom || !$delTo) {
            setFlash('danger', 'يرجى تحديد فترة الحذف بدقة.');
        } else {
            // Verify an export history record exists covering this period
            $chkExp = $pdo->prepare("
                SELECT COUNT(*) FROM audit_export_history 
                WHERE (period_start IS NULL OR period_start <= ?) 
                  AND (period_end IS NULL OR period_end >= ?)
            ");
            $chkExp->execute([$delFrom . ' 00:00:00', $delTo . ' 23:59:59']);
            
            if ((int)$chkExp->fetchColumn() === 0) {
                setFlash('danger', 'لا يمكن حذف أي سجلات تدقيق قبل تصديرها بنجاح وتسجيل عملية التصدير أولاً.');
            } else {
                $pdo->beginTransaction();
                try {
                    // Delete ONLY logs that exist in the manifest (audit_export_items)
                    $delStmt = $pdo->prepare("
                        DELETE al FROM activity_logs al
                        JOIN audit_export_items aei ON aei.activity_log_id = al.id
                        JOIN audit_export_history aeh ON aeh.id = aei.export_id
                        WHERE (aeh.period_start IS NULL OR aeh.period_start <= ?) 
                          AND (aeh.period_end IS NULL OR aeh.period_end >= ?)
                          AND al.created_at >= ? AND al.created_at <= ?
                    ");
                    $delStmt->execute([$delFrom . ' 00:00:00', $delTo . ' 23:59:59', $delFrom . ' 00:00:00', $delTo . ' 23:59:59']);
                    $deletedCount = $delStmt->rowCount();

                    logActivity($pdo, 'DELETE_EXPORTED_AUDIT_LOGS', 'activity_logs', null, null, [
                        'deleted_count' => $deletedCount,
                        'period' => "$delFrom to $delTo"
                    ]);

                    $pdo->commit();
                    setFlash('success', "تم حذف $deletedCount سجل تدقيق مصدّر سابقاً عبر بيان التصدير (Manifest) بنجاح.");
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    setFlash('danger', 'فشل حذف السجلات: ' . $e->getMessage());
                }
            }
        }
        header('Location: activity_logs.php');
        exit;
    }
}

$fUser = (int) ($_GET['user_id'] ?? 0);
$fAction = trim($_GET['action'] ?? '');
$fEntity = trim($_GET['entity'] ?? '');
$fDateFrom = $_GET['date_from'] ?? '';
$fDateTo = $_GET['date_to'] ?? '';

$where = ['1=1'];
$params = [];
if ($fUser) {
    $where[] = 'al.user_id=?';
    $params[] = $fUser;
}
if ($fAction) {
    $where[] = 'al.action LIKE ?';
    $params[] = '%' . $fAction . '%';
}
if ($fEntity) {
    $where[] = 'al.entity LIKE ?';
    $params[] = '%' . $fEntity . '%';
}
if ($fDateFrom) {
    $where[] = 'al.created_at>=?';
    $params[] = $fDateFrom . ' 00:00:00';
}
if ($fDateTo) {
    $where[] = 'al.created_at<=?';
    $params[] = $fDateTo . ' 23:59:59';
}

$stmt = $pdo->prepare(
    "SELECT al.*, u.username FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY al.created_at DESC LIMIT 500"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

$pageTitle = 'سجل العمليات';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-clock-history me-2"></i>سجل العمليات</h1>
            </div>

            <!-- Filters -->
            <form method="get" class="filter-bar mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">المستخدم</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $fUser === (int) $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">الإجراء</label>
                        <input type="text" name="action" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($fAction) ?>" placeholder="LOGIN, CREATE...">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">الكيان</label>
                        <input type="text" name="entity" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($fEntity) ?>" placeholder="deposits...">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($fDateFrom) ?>">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($fDateTo) ?>">
                    </div>
                    <div class="col-sm-6 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-gold btn-sm flex-fill"><i
                                class="bi bi-search"></i></button>
                        <a href="activity_logs.php" class="btn btn-outline-gold btn-sm"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            </form>

            <div class="table-wrapper">
                <div class="p-2 px-3 border-bottom border-gold">
                    <span class="section-title mb-0">النتائج:
                        <?= count($logs) ?> سجل (آخر 500)
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover mb-0" style="font-size:0.83rem">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستخدم</th>
                                <th>الإجراء</th>
                                <th>الكيان</th>
                                <th>معرف الكيان</th>
                                <th>البيانات السابقة</th>
                                <th>البيانات الجديدة</th>
                                <th>عنوان IP</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted">
                                        <?= $log['id'] ?>
                                    </td>
                                    <td><strong>
                                            <?= htmlspecialchars($log['username'] ?? 'نظام') ?>
                                        </strong></td>
                                    <td>
                                        <code
                                            style="color:var(--gold-light);font-size:0.78rem"><?= htmlspecialchars($log['action']) ?></code>
                                    </td>
                                    <td style="color:var(--text-muted)">
                                        <?= htmlspecialchars($log['entity']) ?>
                                    </td>
                                    <td>
                                        <?= $log['entity_id'] ?? '—' ?>
                                    </td>
                                    <td style="max-width:200px">
                                        <?php if ($log['old_data']): ?>
                                            <div class="json-display">
                                                <?= htmlspecialchars(json_encode(json_decode($log['old_data']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?>
                                            </div>
                                        <?php else:
                                            echo '<span class="text-muted">—</span>';
                                        endif; ?>
                                    </td>
                                    <td style="max-width:200px">
                                        <?php if ($log['new_data']): ?>
                                            <div class="json-display">
                                                <?= htmlspecialchars(json_encode(json_decode($log['new_data']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?>
                                            </div>
                                        <?php else:
                                            echo '<span class="text-muted">—</span>';
                                        endif; ?>
                                    </td>
                                    <td style="font-family:monospace;font-size:0.75rem">
                                        <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                    </td>
                                    <td style="white-space:nowrap">
                                        <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">لا توجد سجلات</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>