<?php
// public/index.php — Login Page
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = currentRole();
    header('Location: ' . ($role === 'investor' ? 'investor_portal.php' : 'dashboard.php'));
    exit;
}

// Prevent caching of the login page to avoid stale CSRF tokens
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $pdo = getPDO();
    $rawUsername = trim($_POST['username'] ?? '');
    $normUsername = mb_strtolower($rawUsername);
    $password = $_POST['password'] ?? '';
    $role_sel = $_POST['role'] ?? '';

    // Privacy-conscious IP hashing using REMOTE_ADDR and env secret
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $secret = getenv('RATE_LIMIT_SECRET') ?: ($_ENV['RATE_LIMIT_SECRET'] ?? '');

    if (empty($secret) || strlen($secret) < 32) {
        error_log("SECURITY CRITICAL CONFIG ERROR: RATE_LIMIT_SECRET is not configured or is under 32 characters.");
        http_response_code(503);
        die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>503 — خدمة تسجيل الدخول غير متاحة</h2><p>حدث خطأ في الإعدادات الأمنية للنظام. يرجى مراجعة مسؤول النظام.</p></div>');
    }
    $ipHash = hash_hmac('sha256', $ip, $secret);

    // Helper functions for persistent rate limiting
    $isRateLimited = function(PDO $db, string $hash, string $uname): bool {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM login_attempts 
                WHERE (ip_address = ? OR username = ?) 
                  AND success = 0 
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$hash, $uname]);
            return ((int)$stmt->fetchColumn()) >= 5;
        } catch (Exception $e) {
            error_log("Rate limit query notice: " . $e->getMessage());
            return false;
        }
    };

    $recordAttempt = function(PDO $db, string $hash, string $uname, bool $success): void {
        try {
            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, attempted_at, success) VALUES (?, ?, NOW(), ?)");
            $stmt->execute([$hash, $uname, $success ? 1 : 0]);
            if ($success) {
                $del = $db->prepare("DELETE FROM login_attempts WHERE (ip_address = ? OR username = ?) AND success = 0");
                $del->execute([$hash, $uname]);
            }
            if (rand(1, 20) === 1) {
                $db->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            }
        } catch (Exception $e) {
            error_log("Record attempt notice: " . $e->getMessage());
        }
    };

    if ($normUsername !== '' && $isRateLimited($pdo, $ipHash, $normUsername)) {
        $error = "عفواً، تم تجاوز عدد محاولات الدخول المسموح بها. يرجى الانتظار لمدة 15 دقيقة قبل المحاولة مجدداً.";
    } else {
        if ($rawUsername !== '' && $password !== '') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$rawUsername]);
            $user = $stmt->fetch();

            // Verify password AND verify that database role matches requested role
            if ($user && $user['role'] === $role_sel && password_verify($password, $user['password_hash'])) {
                $recordAttempt($pdo, $ipHash, $normUsername, true);

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['investor_id'] = $user['investor_id'];
                $_SESSION['session_version'] = (int)($user['session_version'] ?? 1);
                $_SESSION['last_activity'] = time();

                // Update last_login_at
                $pdo->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$user['id']]);
                logActivity($pdo, 'LOGIN', 'users', $user['id'], null, ['role' => $user['role']]);

                header('Location: ' . ($user['role'] === 'investor' ? 'investor_portal.php' : 'dashboard.php'));
                exit;
            } else {
                $recordAttempt($pdo, $ipHash, $normUsername, false);
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة، أو الحساب غير متاح حالياً.';
            }
        } else {
            $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
        }
    }
}

$pageTitle = 'تسجيل الدخول';
$bodyClass = 'login-page';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة الودائع – العسافي للاستثمارات</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- تم إضافة ?v=3 ليقوم المتصفح بقراءة النسخة الجديدة وتطبيق حل الدائرة والأبعاد فوراً -->
    <link rel="stylesheet" href="/assets/css/theme.css?v=3">
</head>


<body class="login-page">

    <div class="login-card">
        <!-- Logo -->
        <img src="/assets/img/ag-logo.png" class="login-logo" alt="شعار العسافي"
            onerror="this.outerHTML='<div style=\'width:80px;height:80px;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;border-radius:50%;border:2px solid var(--gold);font-size:2rem;color:var(--gold)\'>&#9670;</div>'">

        <h1 class="login-title">العسافي للاستثمارات</h1>
        <p class="login-subtitle">نظام إدارة الودائع الاستثمارية — الإصدار 1.0</p>

        <?php if ($error): ?>
            <div class="alert alert-danger flash-danger border mb-3 d-flex align-items-center gap-2"
                style="border-radius:8px;font-size:0.88rem">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?= csrfField() ?>

            <!-- Role -->
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-shield-check me-1"></i> الدور الوظيفي</label>
                <select name="role" class="form-select" required>
                    <option value="">— اختر الدور —</option>
                    <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>مشرف (Admin)
                    </option>
                    <option value="staff" <?= ($_POST['role'] ?? '') === 'staff' ? 'selected' : '' ?>>موظف (Staff)
                    </option>
                    <option value="investor" <?= ($_POST['role'] ?? '') === 'investor' ? 'selected' : '' ?>>مستثمر
                        (Investor)</option>
                </select>
            </div>

            <!-- Username -->
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-person me-1"></i> اسم المستخدم</label>
                <input type="text" name="username" class="form-control"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="أدخل اسم المستخدم" required
                    autocomplete="username">
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="form-label"><i class="bi bi-lock me-1"></i> كلمة المرور</label>
                <div class="input-group">
                    <input type="password" name="password" id="pwdInput" class="form-control"
                        placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()"
                        style="border-color:var(--border);background:#1a1a1a;color:var(--text-muted)">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-gold w-100 py-2 fs-6">
                <i class="bi bi-box-arrow-in-right me-1"></i> تسجيل الدخول
            </button>
        </form>

        <div class="text-center mt-3" style="font-size:0.75rem;color:var(--text-muted)">
            <i class="bi bi-shield-lock me-1"></i>
            محمي بتشفير BCrypt + CSRF — جميع العمليات مسجلة
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePwd() {
            const inp = document.getElementById('pwdInput');
            const ico = document.getElementById('eyeIcon');
            if (inp.type === 'password') {
                inp.type = 'text';
                ico.className = 'bi bi-eye-slash';
            } else {
                inp.type = 'password';
                ico.className = 'bi bi-eye';
            }
        }
    </script>
</body>

</html>