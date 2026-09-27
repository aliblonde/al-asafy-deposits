<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

try {
    echo "<h3>جاري تحديث هيكل المتجر (بناءً على نظام Qi Card Digital Zone)...</h3>";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE store_orders;");
    $pdo->exec("TRUNCATE TABLE store_products;");
    $pdo->exec("TRUNCATE TABLE store_categories;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "- تم تفريغ المتجر القديم.<br>";

    // 1. Categories
    $categories = [
        ['name_ar' => 'بطاقات الاتصال والإنترنت', 'icon' => 'bi-phone-vibrate', 'sort' => 1],
        ['name_ar' => 'بطاقات الألعاب (Gaming)', 'icon' => 'bi-controller', 'sort' => 2],
        ['name_ar' => 'التسوق والتطبيقات', 'icon' => 'bi-google-play', 'sort' => 3],
        ['name_ar' => 'الترفيه والاشتراكات', 'icon' => 'bi-film', 'sort' => 4],
    ];
    
    $catStmt = $pdo->prepare("INSERT INTO store_categories (name_ar, icon, sort_order) VALUES (?, ?, ?)");
    foreach ($categories as $c) {
        $catStmt->execute([$c['name_ar'], $c['icon'], $c['sort']]);
    }

    // Reliable image URLs for demo (they can change them later from DB or Admin panel)
    $products = [
        // Category 1: Telecom
        [1, 'زين العراق - 5,000', 'تعبئة رصيد زين (الدفع المسبق)', 5000, 'IQD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cd/Zain_logo.svg/512px-Zain_logo.svg.png'],
        [1, 'زين العراق - 10,000', 'تعبئة رصيد زين (الدفع المسبق)', 10000, 'IQD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cd/Zain_logo.svg/512px-Zain_logo.svg.png'],
        [1, 'آسيا سيل - 5,000', 'تعبئة رصيد خطوط آسياسيل', 5000, 'IQD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/52/Asiacell_logo.svg/512px-Asiacell_logo.svg.png'],
        [1, 'آسيا سيل - 10,000', 'تعبئة رصيد خطوط آسياسيل', 10000, 'IQD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/52/Asiacell_logo.svg/512px-Asiacell_logo.svg.png'],
        [1, 'كورك تليكوم - 10,000', 'تعبئة رصيد خطوط كورك', 10000, 'IQD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cb/Korek_Telecom_Logo.svg/512px-Korek_Telecom_Logo.svg.png'],

        // Category 2: Gaming
        [2, 'شدات ببجي - 60 UC', 'PUBG Mobile UC Global', 1.50, 'USD', 'https://upload.wikimedia.org/wikipedia/en/thumb/0/07/PUBG_logo.svg/512px-PUBG_logo.svg.png'],
        [2, 'شدات ببجي - 325 UC', 'PUBG Mobile UC Global', 5.00, 'USD', 'https://upload.wikimedia.org/wikipedia/en/thumb/0/07/PUBG_logo.svg/512px-PUBG_logo.svg.png'],
        [2, 'شدات ببجي - 660 UC', 'PUBG Mobile UC Global', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/en/thumb/0/07/PUBG_logo.svg/512px-PUBG_logo.svg.png'],
        [2, 'فري فاير - 110 جوهرة', 'Garena Free Fire Diamonds', 1.50, 'USD', 'https://upload.wikimedia.org/wikipedia/en/thumb/3/36/Free_Fire_logo.svg/512px-Free_Fire_logo.svg.png'],
        [2, 'بلايستيشن (أمريكي) - 10$', 'PlayStation Network Card US', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Playstation_logo_colour.svg/512px-Playstation_logo_colour.svg.png'],
        [2, 'إكس بوكس لايف - 10$', 'Xbox Live Gift Card', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d7/Xbox_logo_%282019%29.svg/512px-Xbox_logo_%282019%29.svg.png'],
        [2, 'روبلوكس - 800 Robux', 'Roblox Digital Card', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Roblox_player_icon_black.svg/512px-Roblox_player_icon_black.svg.png'],
        [2, 'رازر جولد (عالمي) - 10$', 'Razer Gold Global PIN', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/en/thumb/4/40/Razer_Inc_logo.svg/512px-Razer_Inc_logo.svg.png'],

        // Category 3: Shopping & Apps
        [3, 'آبل آيتونز (أمريكي) - 10$', 'Apple App Store & iTunes US', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Apple_logo_black.svg/512px-Apple_logo_black.svg.png'],
        [3, 'جوجل بلاي (أمريكي) - 10$', 'Google Play Store Gift Card US', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d0/Google_Play_Arrow_logo.svg/512px-Google_Play_Arrow_logo.svg.png'],
        [3, 'أمازون (أمريكي) - 25$', 'Amazon Gift Card US', 25.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a9/Amazon_logo.svg/512px-Amazon_logo.svg.png'],

        // Category 4: Entertainment
        [4, 'نتفليكس - 15$', 'Netflix Gift Card', 15.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/08/Netflix_2015_logo.svg/512px-Netflix_2015_logo.svg.png'],
        [4, 'شاهد VIP - شهر', 'Shahid VIP Subscription', 8.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Shahid_Logo.svg/512px-Shahid_Logo.svg.png'],
        [4, 'سبوتيفاي - 10$', 'Spotify Premium', 10.00, 'USD', 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/26/Spotify_logo_with_text.svg/512px-Spotify_logo_with_text.svg.png'],
    ];

    $prodStmt = $pdo->prepare("INSERT INTO store_products (category_id, name_ar, description, price, currency, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $prodStmt->execute($p);
    }
    
    echo "- تم تصنيف الأقسام وإضافة البطاقات والصور الفعلية بنجاح.<br>";
    echo "<h3 style='color:green'>✅ اكتمل تحديث المتجر بنجاح!</h3>";

} catch (Throwable $e) {
    echo "<h3 style='color:red'>❌ حدث خطأ:</h3>";
    echo "<b>رسالة الخطأ:</b> " . $e->getMessage() . "<br>";
    echo "<b>السطر:</b> " . $e->getLine() . "<br>";
}
