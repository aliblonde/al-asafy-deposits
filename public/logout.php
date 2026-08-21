<?php
// public/logout.php — Strict POST-only Logout Handler with CSRF Protection
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>405 — Method Not Allowed</h2><p>تسجيل الخروج يتطلب طلب POST آمن.</p></div>');
}

verifyCsrf();

if (isLoggedIn()) {
    try {
        $pdo = getPDO();
        logActivity($pdo, 'LOGOUT', 'users', currentUserId(), null, ['role' => currentRole()]);
    } catch (Exception $e) {
        error_log('Logout activity log error: ' . $e->getMessage());
    }
}

// Complete session & cookie destruction with security attributes preserved
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
session_destroy();

header('Location: index.php');
exit;
