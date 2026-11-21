<?php
// public/api/app_version.php — Mobile App Version & Force Update Config
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Version settings (controlled by admin)
$versionConfig = [
    'latest_version'        => '1.0.0',
    'min_supported_version' => '1.0.0',
    'force_update'          => false,
    'update_title'          => 'تحديث جديد متوفر 🚀',
    'update_message'        => 'يتوفر إصدار جديد من تطبيق مجموعة الصافي للاستثمار يحتوي على تحسينات أمنية وميزات هامة. يرجى تحديث التطبيق للمتابعة.',
    'download_url'          => 'https://alasafygroup.xyz/download/AlAsafy-Investor.apk',
    'store_url_android'     => 'https://play.google.com/store/apps/details?id=com.alasafygroup.alasafy_investor',
    'store_url_ios'         => 'https://apps.apple.com/app/alasafy-investor/id1234567890'
];

apiResponse(true, $versionConfig);
