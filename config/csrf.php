<?php
// config/csrf.php — CSRF token management

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        // Ensure session settings are robust
        @ini_set('session.cookie_httponly', 1);
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('<div style="direction:rtl;text-align:center;padding:40px;color:red;font-family: Tajawal, sans-serif;">
             <h2>خطأ في التحقق من البيانات</h2>
             <p>رمز CSRF غير صالح أو انتهت صلاحية الجلسة.</p>
             <a href="index.php" style="display:inline-block;margin-top:20px;padding:10px 20px;background:#d4af37;color:#000;text-decoration:none;border-radius:8px;font-weight:bold;">يرجى تحديث الصفحة والمحاولة مرة أخرى</a>
             </div>');
    }
}