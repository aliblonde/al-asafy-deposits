<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // Drop existing to recreate with image_url
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DROP TABLE IF EXISTS store_orders;");
    $pdo->exec("DROP TABLE IF EXISTS store_products;");
    $pdo->exec("DROP TABLE IF EXISTS store_categories;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 1. Create Tables
    $pdo->exec("
        CREATE TABLE store_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name_ar VARCHAR(100) NOT NULL,
            icon VARCHAR(50) DEFAULT 'bi-box',
            sort_order INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE store_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name_ar VARCHAR(150) NOT NULL,
            description TEXT,
            price DECIMAL(15,2) NOT NULL,
            currency ENUM('USD', 'IQD') NOT NULL DEFAULT 'IQD',
            image_url VARCHAR(255) NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE store_orders (
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

    // 2. Insert Professional Categories
    $categories = [
        ['name_ar' => 'بطاقات الاتصال والإنترنت', 'icon' => 'bi-phone', 'sort' => 1],
        ['name_ar' => 'بطاقات الألعاب (Gaming)', 'icon' => 'bi-controller', 'sort' => 2],
        ['name_ar' => 'التسوق والمتاجر الرقمية', 'icon' => 'bi-bag-heart', 'sort' => 3],
        ['name_ar' => 'المحافظ الإلكترونية', 'icon' => 'bi-wallet2', 'sort' => 4],
    ];
    
    $catStmt = $pdo->prepare("INSERT INTO store_categories (name_ar, icon, sort_order) VALUES (?, ?, ?)");
    foreach ($categories as $c) {
        $catStmt->execute([$c['name_ar'], $c['icon'], $c['sort']]);
    }

    // 3. Insert Products with Real Logos (Using Clearbit Logo API which is highly reliable)
    // Clearbit URL format: https://logo.clearbit.com/domain.com
    $products = [
        // Category 1: Telecom
        [1, 'آسيا سيل - 5,000', 'تعبئة رصيد خطوط آسيا سيل', 5000, 'IQD', 'https://logo.clearbit.com/asiacell.com'],
        [1, 'آسيا سيل - 10,000', 'تعبئة رصيد خطوط آسيا سيل', 10000, 'IQD', 'https://logo.clearbit.com/asiacell.com'],
        [1, 'زين العراق - 5,000', 'تعبئة رصيد خطوط زين العراق', 5000, 'IQD', 'https://logo.clearbit.com/iq.zain.com'],
        [1, 'زين العراق - 10,000', 'تعبئة رصيد خطوط زين العراق', 10000, 'IQD', 'https://logo.clearbit.com/iq.zain.com'],
        [1, 'كورك تليكوم - 10,000', 'تعبئة رصيد خطوط كورك', 10000, 'IQD', 'https://logo.clearbit.com/korektel.com'],
        [1, 'فاست لينك - 15,000', 'تجديد اشتراك Fastlink', 15000, 'IQD', 'https://logo.clearbit.com/fast-link.com'],

        // Category 2: Gaming
        [2, 'شدات ببجي - 60 UC', 'شحن شدات ببجي الرسمية', 1.50, 'USD', 'https://logo.clearbit.com/pubg.com'],
        [2, 'شدات ببجي - 325 UC', 'شحن شدات ببجي الرسمية', 5.00, 'USD', 'https://logo.clearbit.com/pubg.com'],
        [2, 'شدات ببجي - 660 UC', 'شحن شدات ببجي الرسمية', 10.00, 'USD', 'https://logo.clearbit.com/pubg.com'],
        [2, 'فري فاير - 110 جوهرة', 'شحن جواهر Garena Free Fire', 1.50, 'USD', 'https://logo.clearbit.com/ff.garena.com'],
        [2, 'بلايستيشن ستور (أمريكي) - 10$', 'رصيد PlayStation Network', 10.00, 'USD', 'https://logo.clearbit.com/playstation.com'],
        [2, 'بلايستيشن ستور (أمريكي) - 25$', 'رصيد PlayStation Network', 25.00, 'USD', 'https://logo.clearbit.com/playstation.com'],
        [2, 'إكس بوكس لايف - 10$', 'بطاقة شحن Xbox Live', 10.00, 'USD', 'https://logo.clearbit.com/xbox.com'],
        [2, 'روبلوكس - 800 Robux', 'بطاقة شحن روبلوكس', 10.00, 'USD', 'https://logo.clearbit.com/roblox.com'],

        // Category 3: Shopping & Stores
        [3, 'آبل آيتونز (أمريكي) - 10$', 'رصيد متجر Apple App Store', 10.00, 'USD', 'https://logo.clearbit.com/apple.com'],
        [3, 'جوجل بلاي (أمريكي) - 10$', 'رصيد متجر Google Play Store', 10.00, 'USD', 'https://logo.clearbit.com/play.google.com'],
        [3, 'أمازون (أمريكي) - 25$', 'بطاقة هدايا متجر Amazon', 25.00, 'USD', 'https://logo.clearbit.com/amazon.com'],
        [3, 'نتفليكس - 15$', 'تجديد اشتراك Netflix', 15.00, 'USD', 'https://logo.clearbit.com/netflix.com'],
        [3, 'شاهد VIP - اشتراك شهر', 'بطاقة تجديد اشتراك Shahid VIP', 8.00, 'USD', 'https://logo.clearbit.com/shahid.mbc.net'],

        // Category 4: Wallets
        [4, 'زين كاش - 25,000 دينار', 'تحويل رصيد مباشر لمحفظتك', 25000, 'IQD', 'https://logo.clearbit.com/iq.zain.com'],
        [4, 'زين كاش - 50,000 دينار', 'تحويل رصيد مباشر لمحفظتك', 50000, 'IQD', 'https://logo.clearbit.com/iq.zain.com'],
        [4, 'فاست باي - 25,000 دينار', 'شحن محفظة FastPay', 25000, 'IQD', 'https://logo.clearbit.com/fast-pay.cash'],
    ];

    $prodStmt = $pdo->prepare("INSERT INTO store_products (category_id, name_ar, description, price, currency, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $prodStmt->execute($p);
    }

    $pdo->commit();
    echo "V2 Store tables created and seeded successfully with images!";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
