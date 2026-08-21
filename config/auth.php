<?php
// config/auth.php — Session management + RBAC

require_once __DIR__ . '/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    if ($isSecure) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

function destroySessionAndCookie(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $isDownloadOrExport = str_contains($_SERVER['PHP_SELF'] ?? '', 'download_file.php') 
                           || str_contains($_SERVER['PHP_SELF'] ?? '', 'export_pdf.php') 
                           || str_contains($_SERVER['PHP_SELF'] ?? '', 'export_excel.php');
        if ($isDownloadOrExport) {
            http_response_code(401);
            die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>401 — غير مصرح</h2><p>انتهت الجلسة. يرجى تسجيل الدخول مجدداً.</p></div>');
        }
        header('Location: index.php');
        exit;
    }

    // Check Session Idle Timeout (default 1800s = 30 mins)
    $idleTimeout = (int)(getenv('SESSION_IDLE_TIMEOUT') ?: ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800));
    if ($idleTimeout < 60) {
        $idleTimeout = 1800;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $idleTimeout)) {
        destroySessionAndCookie();
        
        $isDownloadOrExport = str_contains($_SERVER['PHP_SELF'] ?? '', 'download_file.php') 
                           || str_contains($_SERVER['PHP_SELF'] ?? '', 'export_pdf.php') 
                           || str_contains($_SERVER['PHP_SELF'] ?? '', 'export_excel.php');
        if ($isDownloadOrExport) {
            http_response_code(401);
            die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>401 — انتهت مدة الجلسة</h2><p>انتهت مدة الجلسة بسبب عدم النشاط. يرجى إعادة تسجيل الدخول.</p></div>');
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'انتهت مدة الجلسة بسبب عدم النشاط. يرجى إعادة تسجيل الدخول.'];
        header('Location: index.php?expired=1');
        exit;
    }

    // Check Session Revocation (session_version)
    if (isset($_SESSION['user_id'], $_SESSION['session_version'])) {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT session_version, role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || (int)$user['session_version'] !== (int)$_SESSION['session_version']) {
                destroySessionAndCookie();
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'تم إلغاء الجلسة بسبب تغيير كلمة المرور أو تحديث الصلاحيات. يرجى تسجيل الدخول مجدداً.'];
                header('Location: index.php?revoked=1');
                exit;
            }
            $_SESSION['role'] = $user['role'];
        } catch (Exception $e) {
            error_log("Session version check notice: " . $e->getMessage());
        }
    }

    $_SESSION['last_activity'] = time();
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;color:#c00;padding:30px;text-align:center;direction:rtl">
            <h2>403 — غير مصرح</h2><p>ليس لديك صلاحية للوصول إلى هذه الصفحة.</p>
            <a href="index.php">العودة لتسجيل الدخول</a>
            </div>');
    }
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentRole(): string {
    return $_SESSION['role'] ?? '';
}

function currentUsername(): string {
    return $_SESSION['username'] ?? '';
}

function currentInvestorId(): ?int {
    return isset($_SESSION['investor_id']) ? (int)$_SESSION['investor_id'] : null;
}
