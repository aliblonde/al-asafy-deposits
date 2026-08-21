<?php
// config/rbac.php — Flexible DB-backed Role & Permission System

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Check if a user possesses a specific permission.
 * Resolves:
 * 1. Admin role -> TRUE
 * 2. Explicit user_permissions 'deny' -> FALSE (overrides role)
 * 3. Explicit user_permissions 'allow' -> TRUE (overrides role)
 * 4. Role permissions -> TRUE if mapped to role
 */
function userCan(string $permissionName, ?int $userId = null): bool
{
    $uid = $userId ?: currentUserId();
    if ($uid <= 0) {
        return false;
    }

    $role = ($userId && $userId !== currentUserId()) ? getUserRoleFromDb($uid) : currentRole();
    if ($role === 'admin') {
        return true; // Admin possesses universal permissions
    }

    $pdo = getPDO();

    try {
        // 1. Check explicit user_permissions overrides
        $stmt = $pdo->prepare("
            SELECT up.permission_type 
            FROM user_permissions up
            JOIN permissions p ON p.id = up.permission_id
            WHERE up.user_id = ? AND p.name = ?
            LIMIT 1
        ");
        $stmt->execute([$uid, $permissionName]);
        $override = $stmt->fetchColumn();

        if ($override === 'deny') {
            return false;
        }
        if ($override === 'allow') {
            return true;
        }

        // 2. Check role_permissions
        $stmtRole = $pdo->prepare("
            SELECT COUNT(*) 
            FROM role_permissions rp
            JOIN roles r ON r.id = rp.role_id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE r.name = ? AND p.name = ?
        ");
        $stmtRole->execute([$role, $permissionName]);
        return ((int)$stmtRole->fetchColumn()) > 0;
    } catch (Exception $e) {
        error_log("userCan check error: " . $e->getMessage());
        return false;
    }
}

function requirePermission(string $permissionName): void
{
    requireLogin();
    if (!userCan($permissionName)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;color:#c00;padding:30px;text-align:center;direction:rtl">
            <h2>403 — غير مصرح</h2><p>ليس لديك الصلاحية المطلوبة (' . htmlspecialchars($permissionName) . ') لإتمام العملية.</p>
            <a href="index.php">العودة للرئيسية</a>
            </div>');
    }
}

function getUserRoleFromDb(int $userId): string
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return (string)($stmt->fetchColumn() ?: '');
    } catch (Exception $e) {
        return '';
    }
}
