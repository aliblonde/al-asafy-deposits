<?php
// fix_names.php - Quick tool to fix typo in deposit package names
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
if (currentRole() !== 'admin') {
    die("Access Denied.");
}

try {
    $pdo = getPDO();
    $pdo->exec("UPDATE deposit_types SET name_ar = REPLACE(name_ar, 'ASAIFY', 'ASAFY') WHERE is_locked = 1");
    echo "<div style='text-align:center; padding: 50px; font-family: sans-serif; direction: rtl;'>
          <h2 style='color:green;'>✅ تم تحديث الأسماء بنجاح!</h2>
          <p style='font-size: 18px;'>تم تغيير ASAIFY إلى ASAFY.</p>
          <br>
          <a href='settings_deposit_types.php' style='padding: 10px 20px; background: #cda45e; color: #fff; text-decoration: none; border-radius: 5px;'>العودة إلى أنواع الودائع</a>
          </div>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
