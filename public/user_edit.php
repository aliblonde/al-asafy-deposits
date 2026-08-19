<?php
// public/user_edit.php — Edit Staff/Admin User
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin']);

$pdo = getPDO();
$userId = (int) ($_GET['id'] ?? 0);
$errors = [];

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('admin', 'staff')");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'الموضف غير موجود أو غير قابل للتعديل.'];
    header('Location: users.php');
    exit;
}

$pageTitle = 'تعديل بيانات الموظف: ' . htmlspecialchars($user['username']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? $user['role'];
    $password = $_POST['password'] ?? '';

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

            // If password provided, update it
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل.');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $userId]);
                $newData['password_changed'] = true;
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

        <div class="form-card mx-auto" style="max-width: 600px;">
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">اسم المستخدم</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    <div class="form-text text-muted">لا يمكن تغيير اسم المستخدم.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label">الصلاحية</label>
                    <select name="role" class="form-select" required>
                        <option value="staff" <?= ($user['role'] === 'staff') ? 'selected' : '' ?>>موظف (Staff)</option>
                        <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>مشرف (Admin)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">كلمة المرور الجديدة (اختياري)</label>
                    <input type="password" name="password" class="form-control"
                        placeholder="اترك الحقل فارغاً إذا كنت لا تريد التغيير">
                    <div class="form-text text-muted">أدخل 6 خانات على الأقل لإعادة تعيين كلمة المرور.</div>
                </div>

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