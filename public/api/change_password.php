<?php
// public/api/change_password.php — Mobile API Change Password Endpoint
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'طريقة الطلب غير مدعومة. يرجى استخدام POST.', 405);
}

$user = requireApiAuth();
$userId = (int)$user['id'];
$pdo = getPDO();

$input = getJsonInput();
$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

if (empty($currentPassword) || empty($newPassword)) {
    apiResponse(false, null, 'يرجى إدخال كلمة المرور الحالية وكلمة المرور الجديدة.', 422);
}

if ($newPassword !== $confirmPassword) {
    apiResponse(false, null, 'كلمة المرور الجديدة وتأكيدها غير متطابقين.', 422);
}

// Password Policy Check
$policyErrors = validatePasswordPolicy($newPassword);
if (!empty($policyErrors)) {
    apiResponse(false, null, implode(' ', $policyErrors), 422);
}

// Check current password
$stmt = $pdo->prepare("SELECT password_hash, session_version FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
    apiResponse(false, null, 'كلمة المرور الحالية غير صحيحة.', 401);
}

$newHash = password_hash($newPassword, PASSWORD_BCRYPT);
$newSessionVersion = (int)$userData['session_version'] + 1;

// Update Password and increment session_version
$upd = $pdo->prepare("UPDATE users SET password_hash = ?, session_version = ? WHERE id = ?");
$upd->execute([$newHash, $newSessionVersion, $userId]);

logActivity($pdo, 'CHANGE_OWN_PASSWORD', 'users', $userId, null, [
    'description' => 'User changed password via Mobile App'
]);

// Generate fresh token with new session version
$newToken = generateApiToken(
    $userId,
    (int)$user['investor_id'],
    $user['role'],
    $newSessionVersion
);

apiResponse(true, [
    'token' => $newToken
], 'تم تغيير كلمة المرور بنجاح.');
