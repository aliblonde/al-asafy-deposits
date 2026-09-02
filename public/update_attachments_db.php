<?php
// update_attachments_db.php - Create the investor attachments table safely
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
if (currentRole() !== 'admin') {
    die("Access Denied.");
}

try {
    $pdo = getPDO();
    $sql = "
    CREATE TABLE IF NOT EXISTS `investor_attachments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `investor_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `uploaded_by` INT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`investor_id`) REFERENCES `investors`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    
    echo "<div style='text-align:center; padding: 50px; font-family: sans-serif; direction: rtl;'>
          <h2 style='color:green;'>✅ تم إضافة قاعدة بيانات المرفقات الإضافية بنجاح!</h2>
          <br>
          <a href='dashboard.php' style='padding: 10px 20px; background: #cda45e; color: #fff; text-decoration: none; border-radius: 5px;'>العودة للوحة التحكم</a>
          </div>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
