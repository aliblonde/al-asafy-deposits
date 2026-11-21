<?php
// public/api/login.php — Mobile API Login Endpoint
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'طريقة الطلب غير مدعومة. يرجى استخدام POST.', 405);
}

$input = getJsonInput();
$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if (empty($username) || empty($password)) {
    apiResponse(false, null, 'يرجى إدخال اسم المستخدم وكلمة المرور.', 422);
}

$pdo = getPDO();

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.password_hash, u.role, u.session_version, u.investor_id,
           i.full_name, i.phone, i.city, i.national_id
    FROM users u
    LEFT JOIN investors i ON i.id = u.investor_id
    WHERE u.username = ?
");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    apiResponse(false, null, 'اسم المستخدم أو كلمة المرور غير صحيحة.', 401);
}

if ($user['role'] !== 'investor' || empty($user['investor_id'])) {
    apiResponse(false, null, 'هذا التطبيق مخصص لبوابة المستثمرين فقط.', 403);
}

// Generate JWT Token (90 days validity on mobile)
$token = generateApiToken(
    (int)$user['id'],
    (int)$user['investor_id'],
    $user['role'],
    (int)$user['session_version']
);

// Log Activity
logActivity($pdo, 'LOGIN', 'users', $user['id'], null, [
    'role' => $user['role'],
    'client' => 'Mobile App (Flutter)'
]);

$investorData = [
    'id' => (int)$user['investor_id'],
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'full_name' => $user['full_name'],
    'phone' => $user['phone'],
    'city' => $user['city'],
    'national_id' => $user['national_id']
];

apiResponse(true, [
    'token' => $token,
    'investor' => $investorData
], 'تم تسجيل الدخول بنجاح.');
