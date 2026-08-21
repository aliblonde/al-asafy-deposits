<?php
// public/logout.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
} elseif (isset($_GET['token'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_GET['token'])) {
        http_response_code(403);
        die('رمز أمان غير صالح (CSRF).');
    }
}

if (isLoggedIn()) {
    try {
        $pdo = getPDO();
        logActivity($pdo, 'LOGOUT', 'users', currentUserId(), null, ['username' => currentUsername()]);
    } catch (Exception $e) {
        error_log('Logout audit log error: ' . $e->getMessage());
    }
}

// Complete session & cookie destruction
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
