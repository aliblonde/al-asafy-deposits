<?php
// public/user_edit.php — Edit Staff/Admin User
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/rbac.php';

requirePermission('users.manage');

$pdo = getPDO();
$userId = (int) ($_GET['id'] ?? 0);
$errors = [];

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('admin', 'staff')");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'الموظف غير موجود أو غير قابل للتعديل.'];
    header('Location: users.php');
    exit;
}

$canManagePerms = userCan('permissions.manage');
$allPermissions = [];
$userPermOverrides = [];

if ($canManagePerms) {
    $allPermissions = $pdo->query("SELECT * FROM permissions ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
    $upStmt = $pdo->prepare("SELECT permission_id, permission_type FROM user_permissions WHERE user_id = ?");
    $upStmt->execute([$userId]);
    while ($r = $upStmt->fetch(PDO::FETCH_ASSOC)) {
        $userPermOverrides[(int)$r['permission_id']] = $r['permission_type'];
    }
}

$pageTitle = 'تعديل بيانات الموظف: ' . htmlspecialchars($user['username']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $role = $_POST['role'] ?? $user['role'];
    $password = $_POST['password'] ?? '';
    $postedPerms = $_POST['perm_override'] ?? [];

    // Prevent demoting the last admin user
    if ($user['role'] === 'admin' && $role !== 'admin') {
        $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        if ((int)$adminCountStmt->fetchColumn() <= 1) {
            $errors[] = 'لا يمكن تغيير صلاحيات المدير الأخير للنظام لتجنب قفل حساب الإدارة.';
        }
    }

    // Validation
    if (!in_array($role, ['admin', 'staff']))
        $errors[] = 'صلاحية غير صحيحة.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $oldData = ['role' => $user['role']];
            $newData = ['role' => $role];

            // Update role
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $userId]);

            // If password provided, update it and invalidate sessions
            if (!empty($password)) {
                $passCheck = validatePasswordPolicy($password);
                if (!$passCheck['valid']) {
                    throw new Exception($passCheck['error']);
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?");
                $stmt->execute([$hash, $userId]);
                $newData['password_changed'] = true;
            }

            // If user has permissions.manage, update user_permissions overrides
            if ($canManagePerms && is_array($postedPerms)) {
                $delUp = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $delUp->execute([$userId]);

                $insUp = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id, permission_type) VALUES (?, ?, ?)");
                foreach ($postedPerms as $pId => $pType) {
                    $pId = (int)$pId;
                    if (in_array($pType, ['allow', 'deny'], true)) {
                        $insUp->execute([$userId, $pId, $pType]);
                    }
                }
                $newData['permission_overrides_updated'] = true;
            }

            logActivity($pdo, 'UPDATE_USER', 'users', $userId, $oldData, $newData);
            $pdo->commit();

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم تحديث بيانات الحساب بنجاح.'];
            header('Location: users.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= $pageTitle ?>
                </h1>
                <p class="page-subtitle">تعديل الصلاحيات أو إعادة تعيين كلمة المرور</p>
            </div>
            <a href="users.php" class="btn btn-outline-gold">
                <i class="bi bi-arrow-right me-1"></i> العودة للقائمة
            </a>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li>
                            <?= htmlspecialchars($err) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-card mx-auto" style="max-width: 700px;">
            <form method="POST">
                <?= csrfField() ?>
                <div class="mb-4">
                    <label class="form-label">اسم المستخدم</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    <div class="form-text text-muted">لا يمكن تغيير اسم المستخدم.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label">الدور الأساسي</label>
                    <select name="role" class="form-select" required>
                        <option value="staff" <?= ($user['role'] === 'staff') ? 'selected' : '' ?>>موظف (Staff)</option>
                        <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>مشرف (Admin)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">كلمة المرور الجديدة (اختياري)</label>
                    <input type="password" name="password" class="form-control"
                        placeholder="اترك الحقل فارغاً إذا كنت لا تريد التغيير">
                    <div class="form-text text-muted">12 خانة على الأقل تحتوي أحرفاً كبيرة وصغيرة وأرقاماً.</div>
                </div>

                <?php if ($canManagePerms && !empty($allPermissions)): ?>
                    <hr class="my-4 border-secondary">
                    <div class="mb-4">
                        <h5 class="text-gold mb-2"><i class="bi bi-shield-lock me-1"></i> تخصيص الصلاحيات الفردية (Allow / Deny)</h5>
                        <p class="small text-muted mb-3">تطبيق استثناءات صريحة على صلاحيات المستخدم الموروثة من دوره.</p>
                        
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-sm table-dark-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>الصلاحية</th>
                                        <th>القسم</th>
                                        <th>الحالة الفردية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allPermissions as $perm): 
                                        $currentOverride = $userPermOverrides[(int)$perm['id']] ?? 'inherit';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($perm['label_ar']) ?></div>
                                                <code class="small text-muted"><?= htmlspecialchars($perm['name']) ?></code>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($perm['category'] ?? 'عام') ?></span></td>
                                            <td>
                                                <select name="perm_override[<?= $perm['id'] ?>]" class="form-select form-select-sm">
                                                    <option value="inherit" <?= $currentOverride === 'inherit' ? 'selected' : '' ?>>حسب الدور (تلقائي)</option>
                                                    <option value="allow" <?= $currentOverride === 'allow' ? 'selected' : '' ?> class="text-success fw-bold">سماح صريح (Allow)</option>
                                                    <option value="deny" <?= $currentOverride === 'deny' ? 'selected' : '' ?> class="text-danger fw-bold">حظر صريح (Deny)</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-grid">
                    <button type="submit" class="btn btn-gold btn-lg">
                        <i class="bi bi-check-circle me-1"></i> حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>