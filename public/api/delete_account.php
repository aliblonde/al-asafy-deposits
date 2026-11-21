<?php
// public/api/delete_account.php — Mobile API In-App Account Deletion Request (Apple Guideline 5.1.1 & Google Play Policy)
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'طريقة الطلب غير مدعومة. يرجى استخدام POST.', 405);
}

$user = requireApiAuth();
$userId = (int)$user['id'];
$investorId = (int)$user['investor_id'];
$pdo = getPDO();

$input = getJsonInput();
$password = (string)($input['password'] ?? '');
$reason = trim((string)($input['reason'] ?? 'طلب حذف الحساب عبر تطبيق الهاتف'));

if (empty($password)) {
    apiResponse(false, null, 'يرجى إدخال كلمة المرور لتأكيد طلب إغلاق وحذف الحساب.', 422);
}

// Verify current password
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData || !password_verify($password, $userData['password_hash'])) {
    apiResponse(false, null, 'كلمة المرور غير صحيحة.', 401);
}

// Check if user has active deposits
$depCheck = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE investor_id = ? AND status = 'active'");
$depCheck->execute([$investorId]);
$activeDepositsCount = (int)$depCheck->fetchColumn();

// Log the deletion request officially
logActivity($pdo, 'REQUEST_ACCOUNT_DELETION', 'users', $userId, null, [
    'investor_id' => $investorId,
    'full_name' => $user['full_name'],
    'reason' => $reason,
    'active_deposits' => $activeDepositsCount,
    'client' => 'Mobile App Store Compliance'
]);

if ($activeDepositsCount > 0) {
    // If investor has active deposits, regulatory compliance requires administrative settlement
    apiResponse(true, [
        'status' => 'pending_review',
        'has_active_deposits' => true,
        'message_ar' => 'تم استلام طلب حذف الحساب بنجاح. نظراً لوجود عقود وودائع نشطة، سيقوم فريق خدمة العملاء بالتواصل معك لتسوية الحسابات وتأكيد الإغلاق النهائي.'
    ], 'تم استلام طلب حذف الحساب بنجاح وهو قيد المعالجة الإدارية.');
} else {
    // If no active deposits, invalidate session
    $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$userId]);
    
    apiResponse(true, [
        'status' => 'submitted',
        'has_active_deposits' => false,
        'message_ar' => 'تم تسجيل طلب إغلاق وحذف بيانات الدخول الخاصة بحسابك بنجاح. سيتم تعطيل الوصول وإنهاء الحساب.'
    ], 'تم تسجيل طلب حذف الحساب بنجاح.');
}
