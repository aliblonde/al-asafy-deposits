<?php
// public/change_password.php — Allow any logged-in user to change their own password
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

requireLogin();

$pdo = getPDO();
$pageTitle = 'تغيير كلمة المرور';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    // Fetch current user hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([currentUserId()]);
    $user = $stmt->fetch();

    $passCheck = validatePasswordPolicy($newPwd);
    if (!$user || !password_verify($currentPwd, $user['password_hash'])) {
        $errors[] = 'كلمة المرور الحالية غير صحيحة.';
    } elseif (!$passCheck['valid']) {
        $errors[] = $passCheck['error'];
    } elseif ($newPwd !== $confirmPwd) {
        $errors[] = 'كلمة المرور الجديدة غير متطابقة مع تأكيد كلمة المرور.';
    }

    if (empty($errors)) {
        try {
            $hash = password_hash($newPwd, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?");
            $stmt->execute([$hash, currentUserId()]);

            // Sync current session version
            $verStmt = $pdo->prepare("SELECT session_version FROM users WHERE id = ?");
            $verStmt->execute([currentUserId()]);
            $_SESSION['session_version'] = (int)$verStmt->fetchColumn();

            logActivity($pdo, 'CHANGE_OWN_PASSWORD', 'users', currentUserId(), null, ['description' => 'User changed their own password']);
            $success = true;
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            $errors[] = 'حدث خطأ داخلي أثناء تحديث كلمة المرور.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
$isInvestor = (currentRole() === 'investor');
?>

<?php if (!$isInvestor): ?>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <?php else: ?>
            <!-- Investor Simple Topbar -->
            <div class="topbar">
                <div class="topbar-title">
                    <img src="/assets/img/ag-logo.png"
                        style="width:28px;height:28px;border-radius:50%;border:1px solid var(--gold);vertical-align:middle;margin-left:8px">
                    العسافي للاستثمارات — تغيير كلمة المرور
                </div>
                <div class="topbar-user">
                    <a href="investor_portal.php" class="btn btn-sm btn-outline-gold border-0 me-2">
                        <i class="bi bi-arrow-right"></i> العودة للبوابة
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="page-content">
            <div class="page-header" style="<?= $isInvestor ? 'max-width:800px;margin:0 auto 1.5rem auto' : '' ?>">
                <div>
                    <h1 class="page-title"><i class="bi bi-shield-lock me-2"></i>تغيير كلمة المرور</h1>
                    <p class="page-subtitle">تأكد من اختيار كلمة مرور قوية للحفاظ على أمان حسابك</p>
                </div>
                <?php if ($isInvestor): ?>
                    <a href="investor_portal.php" class="btn btn-outline-gold btn-sm">
                        <i class="bi bi-house me-1"></i> البوابة الرئيسية
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success mx-auto" style="max-width: 500px;">
                    <i class="bi bi-check-circle me-2"></i> تم تغيير كلمة المرور بنجاح.
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger mx-auto" style="max-width: 500px;">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-card mx-auto shadow-lg" style="max-width: 500px; border: 1px solid var(--border)">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">كلمة المرور الحالية</label>
                        <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <hr class="my-4" style="border-color: var(--border); opacity:0.5">
                    <div class="mb-3">
                        <label class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" name="new_password" class="form-control" autocomplete="new-password" placeholder="12 خانة على الأقل"
                            required minlength="12">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required minlength="12">
                    </div>

                    <button type="submit" class="btn btn-gold w-100 py-2">
                        <i class="bi bi-save me-1"></i> تحديث كلمة المرور
                    </button>

                    <?php if ($isInvestor): ?>
                        <div class="text-center mt-3">
                            <a href="investor_portal.php" class="text-muted small">إلغاء والعودة</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/footer.php'; ?>

        <?php if (!$isInvestor): ?>
        </div>
    </div>
<?php endif; ?>