<?php
// public/logout.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

if (isLoggedIn()) {
    $pdo = getPDO();
    logActivity($pdo, 'LOGOUT', 'users', currentUserId(), null, ['username' => currentUsername()]);
}
session_destroy();
header('Location: /index.php');
exit;
