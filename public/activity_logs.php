<?php
// public/activity_logs.php — Audit Trail
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole(['admin']);
$pdo = getPDO();

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