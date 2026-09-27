<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // 1. Create Tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS store_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_ar VARCHAR(100) NOT NULL,
            icon VARCHAR(50) DEFAULT 'bi-box',
            sort_order INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS store_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name_ar VARCHAR(150) NOT NULL,
            description TEXT,
            price DECIMAL(15,2) NOT NULL,
            currency ENUM('USD', 'IQD') NOT NULL DEFAULT 'IQD',
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS store_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            investor_id INT NOT NULL,
            product_id INT NOT NULL,
            amount_deducted DECIMAL(15,2) NOT NULL,
            currency ENUM('USD', 'IQD') NOT NULL,
            status ENUM('pending', 'completed', 'rejected') DEFAULT 'pending',
            pin_code TEXT,
            admin_note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            processed_by INT NULL,
            FOREIGN KEY (investor_id) REFERENCES investors(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Clear existing to avoid duplicates if re-run
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE store_products; TRUNCATE TABLE store_categories; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Insert Categories
    $categories = [
        ['name_ar' => 'رصيد موبايل وإنترنت', 'icon' => 'bi-phone', 'sort' => 1],
        ['name_ar' => 'ألعاب وترفيه', 'icon' => 'bi-controller', 'sort' => 2],
        ['name_ar' => 'شحن محافظ مالية', 'icon' => 'bi-wallet2', 'sort' => 3],
        ['name_ar' => 'بطاقات تسوق عالمية', 'icon' => 'bi-cart4', 'sort' => 4],
    ];
    
    $catStmt = $pdo->prepare("INSERT INTO store_categories (name_ar, icon, sort_order) VALUES (?, ?, ?)");
    foreach ($categories as $c) {
        $catStmt->execute([$c['name_ar'], $c['icon'], $c['sort']]);
    }

    // 3. Insert Products
    $products = [
        // Telecom (Cat 1)
        [1, 'رصيد آسيا سيل - فئة 5 آلاف', 'بطاقة تعبئة رصيد خطوط آسيا سيل بقيمة 5,000 دينار', 5000, 'IQD'],
        [1, 'رصيد آسيا سيل - فئة 10 آلاف', 'بطاقة تعبئة رصيد خطوط آسيا سيل بقيمة 10,000 دينار', 10000, 'IQD'],
        [1, 'رصيد زين العراق - فئة 5 آلاف', 'بطاقة تعبئة رصيد خطوط زين العراق بقيمة 5,000 دينار', 5000, 'IQD'],
        [1, 'رصيد زين العراق - فئة 10 آلاف', 'بطاقة تعبئة رصيد خطوط زين العراق بقيمة 10,000 دينار', 10000, 'IQD'],
        [1, 'رصيد كورك - فئة 5 آلاف', 'بطاقة تعبئة رصيد كورك تليكوم', 5000, 'IQD'],
        
        // Gaming (Cat 2)
        [2, 'شدات ببجي - 60 UC', 'شحن شدات ببجي (60 UC) الرسمية', 1.50, 'USD'],
        [2, 'شدات ببجي - 325 UC', 'شحن شدات ببجي (325 UC) الرسمية', 5.00, 'USD'],
        [2, 'شدات ببجي - 660 UC', 'شحن شدات ببجي (660 UC) الرسمية', 10.00, 'USD'],
        [2, 'بطاقة بلايستيشن (ستور أمريكي) - 10$', 'رصيد بلايستيشن للحسابات الأمريكية', 10.00, 'USD'],
        
        // Wallets (Cat 3)
        [3, 'شحن محفظة زين كاش - 25,000 دينار', 'تحويل رصيد مباشر لمحفظة زين كاش الخاصة بك', 25000, 'IQD'],
        [3, 'شحن محفظة زين كاش - 50,000 دينار', 'تحويل رصيد مباشر لمحفظة زين كاش الخاصة بك', 50000, 'IQD'],
        
        // Shopping (Cat 4)
        [4, 'بطاقة آبل آيتونز (أمريكي) - 10$', 'بطاقة شحن رصيد Apple Store للحسابات الأمريكية', 10.00, 'USD'],
        [4, 'بطاقة جوجل بلاي (أمريكي) - 10$', 'بطاقة شحن رصيد Google Play للحسابات الأمريكية', 10.00, 'USD'],
    ];

    $prodStmt = $pdo->prepare("INSERT INTO store_products (category_id, name_ar, description, price, currency) VALUES (?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $prodStmt->execute($p);
    }

    $pdo->commit();
    echo "Store tables created and seeded successfully!";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
