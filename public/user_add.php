<?php
// public/user_add.php — Add Staff/Admin User
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('users.manage');

$pdo = getPDO();
$pageTitle = 'إضافة موظف جديد';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';

    $passCheck = validatePasswordPolicy($password);

    // Validation
    if (strlen($username) < 3)
        $errors[] = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل.';
    if (!$passCheck['valid'])
        $errors[] = $passCheck['error'];
    if (!in_array($role, ['admin', 'staff']))
        $errors[] = 'صلاحية غير صحيحة.';

    if (empty($errors)) {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'اسم المستخدم موجود مسبقاً، اختر اسماً آخر.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, role, password_hash, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$username, $role, $hash]);
                $newId = $pdo->lastInsertId();

                logActivity($pdo, 'CREATE_USER', 'users', $newId, null, ['username' => $username, 'role' => $role]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'تم إنشاء حساب الموظف بنجاح.'];
                header('Location: users.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage();
            }
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
                <p class="page-subtitle">إنشاء حساب جديد لموظف أو مشرف نظام</p>
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
                <?= csrfField() ?>
                <div class="mb-4">
                    <label class="form-label">اسم المستخدم</label>
                    <input type="text" name="username" class="form-control" required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="مثال: ahmed_dev">
                </div>

                <div class="mb-4">
                    <label class="form-label">الصلاحية</label>
                    <select name="role" class="form-select" required>
                        <option value="staff" <?= (($_POST['role'] ?? '') === 'staff') ? 'selected' : '' ?>>موظف (Staff)
                        </option>
                        <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>مشرف (Admin)
                        </option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required
                        placeholder="على الأقل 6 خانات">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-gold btn-lg">
                        <i class="bi bi-check-circle me-1"></i> حفظ الحساب
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>