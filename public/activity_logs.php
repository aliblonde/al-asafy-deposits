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

function arabicLogAction(string $action): array {
    return match($action) {
        'LOGIN' => ['تسجيل دخول', 'bg-success'],
        'LOGOUT' => ['تسجيل خروج', 'bg-secondary'],
        'REQUEST_WITHDRAW' => ['طلب سحب أرباح', 'bg-warning text-dark'],
        'CREATE_APPROVAL_REQUEST' => ['إنشاء طلب موافقة', 'bg-info text-dark'],
        'EXECUTE_APPROVAL_SUCCESS' => ['اعتماد وتنفيذ مالي', 'bg-success'],
        'EXECUTE_APPROVAL_FAILED' => ['فشل تنفيذ الاعتماد', 'bg-danger'],
        'REJECT_APPROVAL_REQUEST' => ['رفض طلب الاعتماد', 'bg-danger'],
        'CREATE_DEPOSIT' => ['إيداع جديد', 'bg-primary'],
        'UPDATE_DEPOSIT' => ['تعديل وديعة', 'bg-warning text-dark'],
        'CLOSE_DEPOSIT' => ['إغلاق وديعة', 'bg-danger'],
        'ADD_MANUAL_PROFIT' => ['إضافة ربح يدوي', 'bg-success'],
        'UPDATE_DEPOSIT_TYPE' => ['تعديل نسب الأرباح', 'bg-info text-dark'],
        'CREATE_INVESTOR' => ['إضافة مستثمر', 'bg-primary'],
        'UPDATE_INVESTOR' => ['تعديل مستثمر', 'bg-warning text-dark'],
        'RESET_INVESTOR_PASSWORD' => ['إعادة تعيين كلمة مرور', 'bg-warning text-dark'],
        'CHANGE_OWN_PASSWORD' => ['تغيير كلمة المرور', 'bg-secondary'],
        'LINK_DEPOSIT_TO_WITHDRAWAL' => ['ربط وديعة بسحب', 'bg-info text-dark'],
        'EXPORT_EXCEL' => ['تصدير Excel', 'bg-success'],
        'EXPORT_PDF' => ['تصدير PDF', 'bg-danger'],
        'EXPORT_AUDIT_LOGS' => ['تصدير سجل العمليات', 'bg-info text-dark'],
        'DELETE_EXPORTED_AUDIT_LOGS' => ['حذف سجلات مصدرة', 'bg-danger'],
        'ARCHIVE_RECORD' => ['أرشفة سجل', 'bg-secondary'],
        'RESTORE_ARCHIVED_RECORD' => ['استعادة سجل', 'bg-success'],
        'PERMANENT_DELETE_ARCHIVE' => ['حذف نهائي', 'bg-danger'],
        'CREATE_USER' => ['إضافة موظف', 'bg-primary'],
        'UPDATE_USER' => ['تعديل موظف', 'bg-warning text-dark'],
        default => [$action, 'bg-secondary']
    };
}

function arabicLogEntity(string $entity): string {
    return match($entity) {
        'users' => 'المستخدمون',
        'deposits' => 'الودائع',
        'investors' => 'المستثمرون',
        'withdraw_requests' => 'طلبات السحب',
        'approval_requests' => 'طلبات الاعتماد',
        'deposit_types' => 'أنواع الودائع',
        'reports' => 'التقارير',
        'activity_logs' => 'سجل العمليات',
        'archived_records' => 'الأرشيف',
        'transactions' => 'المعاملات المالية',
        'store_orders' => 'طلبات المتجر',
        default => $entity
    };
}

function renderLogData(?string $json): string {
    if (!$json) return '<span class="text-muted">—</span>';
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data)) return '<span class="text-muted">—</span>';

    $labels = [
        'amount' => 'المبلغ',
        'currency' => 'العملة',
        'deposit_id' => 'رقم الوديعة',
        'investor_id' => 'رقم المستثمر',
        'withdraw_request_id' => 'طلب السحب',
        'approval_request_id' => 'طلب الاعتماد',
        'request_id' => 'رقم الطلب',
        'entity_id' => 'رقم الكيان',
        'rejecter_id' => 'المشرف الرافض',
        'approver_id' => 'المشرف المعتمد',
        'role' => 'نوع الحساب',
        'month' => 'الشهر المستهدف',
        'note' => 'الملاحظة',
        'reason' => 'السبب',
        'description' => 'الوصف',
        'operation' => 'نوع العملية',
        'entity' => 'الكيان المعني',
        'action' => 'الإجراء',
        'deleted_count' => 'المحذوف',
        'period' => 'الفترة',
        'rows' => 'الصفوف',
        'report' => 'نوع التقرير',
        'username' => 'اسم الحساب',
        'full_name' => 'الاسم الكامل',
        'phone' => 'الهاتف',
        'city' => 'المدينة',
        'anniversary_date' => 'تاريخ الاستحقاق',
        'rate' => 'النسبة',
        'min' => 'الحد الأدنى',
        'max' => 'الحد الأقصى',
        'status' => 'الحالة',
        'old_amount' => 'المبلغ القديم',
        'new_amount' => 'المبلغ الجديد',
        'difference' => 'الفرق',
        'direction' => 'نوع التعديل',
        'new_deposit_type_id' => 'نوع الوديعة الجديد',
        'new_start_date' => 'تاريخ البدء الجديد',
        'count' => 'العدد',
        'hash' => 'رمز التشفير',
        'max_id' => 'آخر معرّف',
        'error_ref' => 'رمز الخطأ',
    ];

    $valuesMap = [
        'withdrawals.approve' => 'اعتماد صرف أرباح',
        'profits.payout' => 'صرف أرباح',
        'profits.manual' => 'إضافة ربح يدوي',
        'deposits.financial_change' => 'تعديل مالي للوديعة',
        'deposits.close' => 'إغلاق الوديعة',
        'rates.declaration' => 'إعلان نسب الأرباح',
        'withdraw_request' => 'طلب سحب',
        'withdraw_requests' => 'طلبات السحب',
        'deposit' => 'وديعة',
        'deposits' => 'الودائع',
        'approval_request' => 'طلب اعتماد',
        'approval_requests' => 'طلبات الاعتماد',
        'user' => 'مستخدم',
        'users' => 'المستخدمون',
        'investor' => 'مستثمر',
        'investors' => 'المستثمرون',
        'deposit_type' => 'نوع الوديعة',
        'admin' => 'إدارة النظام (Admin)',
        'staff' => 'موظف (Staff)',
        'increase' => 'زيادة رأس المال',
        'decrease' => 'تخفيض رأس المال',
        'pending' => 'معلق',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
        'executed' => 'منفذ',
        'paid' => 'مدفوع',
    ];

    $html = '<div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 6px; padding: 6px 10px; font-size: 0.82rem;">';
    foreach ($data as $k => $v) {
        $label = $labels[$k] ?? $k;
        if (is_array($v)) {
            $valStr = json_encode($v, JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($v)) {
            $valStr = $v ? 'نعم' : 'لا';
        } else {
            $rawVal = (string)$v;
            $valStr = $valuesMap[$rawVal] ?? $rawVal;
            if ($k === 'amount' && isset($data['currency'])) {
                $valStr = number_format((float)$v, 2) . ' ' . $data['currency'];
            }
        }
        $html .= '<div class="d-flex justify-content-between align-items-center gap-2 py-1 border-bottom border-secondary border-opacity-25">';
        $html .= '<span style="color: #ffffff; font-weight: 600; white-space: nowrap;">' . htmlspecialchars($label) . ':</span>';
        $html .= '<span style="color: #ffd700; font-weight: bold; text-align: left; word-break: break-all;">' . htmlspecialchars($valStr) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
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
    "SELECT al.*, u.username, u.role, inv.full_name AS investor_name 
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     LEFT JOIN investors inv ON inv.id = u.investor_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY al.created_at DESC LIMIT 500"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query("
    SELECT u.id, u.username, inv.full_name AS investor_name 
    FROM users u 
    LEFT JOIN investors inv ON inv.id = u.investor_id 
    ORDER BY u.role, u.username
")->fetchAll();

$pageTitle = 'سجل العمليات';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-clock-history me-2"></i>سجل العمليات والرقابة</h1>
            </div>

            <!-- Filters -->
            <form method="get" class="filter-bar mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">المستخدم / المستثمر</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">كل المستخدمين والمستثمرين</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $fUser === (int) $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(!empty($u['investor_name']) ? $u['investor_name'] . ' (' . $u['username'] . ')' : $u['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">نوع الإجراء</label>
                        <select name="action" class="form-select form-select-sm">
                            <option value="">كل الإجراءات</option>
                            <option value="LOGIN" <?= $fAction === 'LOGIN' ? 'selected' : '' ?>>تسجيل دخول (LOGIN)</option>
                            <option value="LOGOUT" <?= $fAction === 'LOGOUT' ? 'selected' : '' ?>>تسجيل خروج (LOGOUT)</option>
                            <option value="REQUEST_WITHDRAW" <?= $fAction === 'REQUEST_WITHDRAW' ? 'selected' : '' ?>>طلب سحب أرباح</option>
                            <option value="CREATE_APPROVAL_REQUEST" <?= $fAction === 'CREATE_APPROVAL_REQUEST' ? 'selected' : '' ?>>إنشاء طلب اعتماد</option>
                            <option value="EXECUTE_APPROVAL_SUCCESS" <?= $fAction === 'EXECUTE_APPROVAL_SUCCESS' ? 'selected' : '' ?>>اعتماد وتنفيذ مالي</option>
                            <option value="REJECT_APPROVAL_REQUEST" <?= $fAction === 'REJECT_APPROVAL_REQUEST' ? 'selected' : '' ?>>رفض طلب اعتماد</option>
                            <option value="CREATE_DEPOSIT" <?= $fAction === 'CREATE_DEPOSIT' ? 'selected' : '' ?>>إنشاء وديعة جديدة</option>
                            <option value="ADD_MANUAL_PROFIT" <?= $fAction === 'ADD_MANUAL_PROFIT' ? 'selected' : '' ?>>إضافة ربح يدوي</option>
                            <option value="CREATE_INVESTOR" <?= $fAction === 'CREATE_INVESTOR' ? 'selected' : '' ?>>إضافة مستثمر</option>
                            <option value="UPDATE_INVESTOR" <?= $fAction === 'UPDATE_INVESTOR' ? 'selected' : '' ?>>تعديل مستثمر</option>
                            <option value="RESET_INVESTOR_PASSWORD" <?= $fAction === 'RESET_INVESTOR_PASSWORD' ? 'selected' : '' ?>>إعادة تعيين كلمة مرور</option>
                            <option value="CHANGE_OWN_PASSWORD" <?= $fAction === 'CHANGE_OWN_PASSWORD' ? 'selected' : '' ?>>تغيير كلمة المرور</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">الكيان / القسم</label>
                        <select name="entity" class="form-select form-select-sm">
                            <option value="">كل الأقسام</option>
                            <option value="withdraw_requests" <?= $fEntity === 'withdraw_requests' ? 'selected' : '' ?>>طلبات السحب</option>
                            <option value="approval_requests" <?= $fEntity === 'approval_requests' ? 'selected' : '' ?>>طلبات الاعتماد</option>
                            <option value="deposits" <?= $fEntity === 'deposits' ? 'selected' : '' ?>>الودائع</option>
                            <option value="investors" <?= $fEntity === 'investors' ? 'selected' : '' ?>>المستثمرون</option>
                            <option value="users" <?= $fEntity === 'users' ? 'selected' : '' ?>>المستخدمون</option>
                            <option value="deposit_types" <?= $fEntity === 'deposit_types' ? 'selected' : '' ?>>أنواع الودائع</option>
                        </select>
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
                    <div class="col-sm-6 col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-gold btn-sm flex-fill" title="بحث وتصفية"><i
                                class="bi bi-search"></i></button>
                        <a href="activity_logs.php" class="btn btn-outline-gold btn-sm" title="إعادة ضبط"><i class="bi bi-x"></i></a>
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
                                <th>المستخدم / المستثمر</th>
                                <th>الإجراء</th>
                                <th>الكيان المستهدف</th>
                                <th>رقم الكيان</th>
                                <th style="min-width:180px">البيانات السابقة</th>
                                <th style="min-width:200px">التفاصيل والبيانات الجديدة</th>
                                <th>عنوان IP</th>
                                <th>التاريخ والوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php [$actLabel, $actBadge] = arabicLogAction($log['action']); ?>
                                <tr>
                                    <td class="text-muted">
                                        <?= $log['id'] ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($log['investor_name'])): ?>
                                            <span class="fw-bold text-white"><?= htmlspecialchars($log['investor_name']) ?></span>
                                            <div class="text-muted small" style="font-size:0.75rem">(<?= htmlspecialchars($log['username']) ?>)</div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($log['username'] ?? 'النظام') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $actBadge ?>"><?= htmlspecialchars($actLabel) ?></span>
                                        <div class="text-muted small font-monospace mt-1" style="font-size:0.7rem"><?= htmlspecialchars($log['action']) ?></div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-white"><?= htmlspecialchars(arabicLogEntity($log['entity'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['entity_id']): ?>
                                            <span class="badge bg-dark border border-warning text-gold fw-bold" style="font-size:0.8rem">#<?= $log['entity_id'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= renderLogData($log['old_data']) ?>
                                    </td>
                                    <td>
                                        <?= renderLogData($log['new_data']) ?>
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
                                    <td colspan="9" class="text-center text-muted py-4">لا توجد سجلات مطابقة للبحث</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>