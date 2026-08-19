<?php
/**
 * setup_passwords.php — Run ONCE after fresh DB import to regenerate hashed passwords.
 * DELETE THIS FILE from the server after running it!
 * Visit: http://localhost/al-asafy-deposits/public/setup_passwords.php
 */
require_once __DIR__ . '/../config/db.php';

$credentials = [
    'admin' => 'Admin@123',
    'staff' => 'Staff@123',
    'investor1' => 'Investor@123',
];

$pdo = getPDO();
echo '<html dir="rtl"><head><meta charset="UTF-8"><style>body{font-family:sans-serif;padding:20px;direction:rtl}</style></head><body>';
echo '<h2>🔐 إعادة تعيين كلمات المرور</h2>';

foreach ($credentials as $username => $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE username=?");
    $stmt->execute([$hash, $username]);
    echo "<p>✅ <strong>$username</strong> → <code>$password</code> — تم تحديث الهاش</p>";
}

echo '<hr><p style="color:red;font-weight:bold">⚠️ احذف هذا الملف الآن بعد انتهاء الإعداد!</p>';
echo '<p><a href="/al-asafy-deposits/public/index.php">← الذهاب إلى تسجيل الدخول</a></p>';
echo '</body></html>';
