<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPDO();
$hash1 = password_hash('Admin@123', PASSWORD_DEFAULT);
$hash2 = password_hash('Staff@123', PASSWORD_DEFAULT);
$hash3 = password_hash('Investor@123', PASSWORD_DEFAULT);

$pdo->exec("UPDATE users SET password_hash = '{$hash1}' WHERE username = 'admin'");
$pdo->exec("UPDATE users SET password_hash = '{$hash2}' WHERE username = 'staff'");
$pdo->exec("UPDATE users SET password_hash = '{$hash3}' WHERE username = 'investor1'");

echo "Passwords updated successfully.";
