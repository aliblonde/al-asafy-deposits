<?php
// reset_db.php - Tool to wipe all operational data

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// IMPORTANT: Ensure only Admin can run this
requireLogin();
if (currentRole() !== 'admin') {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif; color:red; direction:rtl'>
         <h2>عذراً! لا تملك صلاحية للقيام بهذا الإجراء.</h2>
         </div>");
}

$pdo = getPDO();

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    $tablesToTruncate = [
        'transactions',
        'withdraw_requests',
        'manual_profit_adjustments',
        'deposit_adjustments',
        'deposits',
        'approval_requests',
        'activity_logs',
        'rate_declarations',
        'monthly_rates',
        'profit_cycles',
        'archived_records',
        'audit_export_history',
        'audit_export_items',
        'investors'
    ];

    foreach ($tablesToTruncate as $table) {
        try {
            $pdo->exec("TRUNCATE TABLE `$table`");
        } catch (Exception $e) {
            // Ignore if table doesn't exist
        }
    }
    
    // Clear notifications if they exist
    try { $pdo->exec("TRUNCATE TABLE `notifications`"); } catch (Exception $e) {}
    try { $pdo->exec("TRUNCATE TABLE `user_notifications`"); } catch (Exception $e) {}

    // Delete Investor User Accounts
    $pdo->exec("DELETE FROM `users` WHERE `role` = 'investor'");
    
    // Delete orphaned permissions
    $pdo->exec("DELETE FROM `user_permissions` WHERE `user_id` NOT IN (SELECT `id` FROM `users`)");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<div style='text-align:center; padding: 50px; font-family: sans-serif; direction: rtl;'>
          <h2 style='color:green;'>✅ تم تصفير قاعدة البيانات بنجاح!</h2>
          <p style='font-size: 18px;'>
          (تم حذف جميع المستثمرين، الودائع، العمليات، الطلبات، الأرباح، وبقيت حسابات الإدارة والموظفين وأنواع الودائع والإعدادات فقط).
          </p>
          <br>
          <a href='dashboard.php' style='padding: 10px 20px; background: #cda45e; color: #fff; text-decoration: none; border-radius: 5px;'>العودة للوحة التحكم</a>
          </div>";
} catch (Exception $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>";
}
