<?php
// config/auth.php — Session management + RBAC

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
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
