<?php
// public/admin_seed_test_data.php — Admin tool to reset & seed database with all test cases
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

$appEnv = strtolower(trim(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production')));
if (!in_array($appEnv, ['development', 'local', 'testing'], true)) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>404 — الصفحة غير موجودة</h2><p>هذه أداة تطوير وغير متاحة في بيئة الإنتاج.</p></div>');
}

requireRole(['admin']);
$pdo = getPDO();

$message = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        // Disable FK checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $pdo->exec("TRUNCATE TABLE transactions");
        $pdo->exec("TRUNCATE TABLE withdraw_requests");
        $pdo->exec("TRUNCATE TABLE profit_cycles");
        $pdo->exec("TRUNCATE TABLE deposits");
        $pdo->exec("TRUNCATE TABLE investors");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 1. Insert Test Investors
        $investorsData = [
            [1, 'علي محمد العسافي', '07700000001', 'بغداد', 'بغداد - المنصور', 'مستثمر رئيسي - ودائع بالدولار والدينار'],
            [2, 'مراد علي سليم', '07700000002', 'أربيل', 'أربيل - العرصات', 'مستثمر ودائع مستحقة للصرف'],
            [3, 'أناغيم فهيم طراف', '07700000003', 'البصرة', 'البصرة - الجزاير', 'مستثمر ودائع غير مستحقة بعد'],
            [4, 'خالد عمر القحطاني', '07700000004', 'كركوك', 'كركوك - طريق بغداد', 'مستثمر ودائع منتهية الأجل']
        ];

        $insInv = $pdo->prepare("INSERT INTO investors (id, full_name, phone, city, address, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        foreach ($investorsData as $inv) {
            $insInv->execute($inv);
        }

        // 2. Fetch Deposit Types
        $types = $pdo->query("SELECT id, code FROM deposit_types")->fetchAll(PDO::FETCH_KEY_PAIR);

        $type6m = $types['6_months'] ?? 1;
        $type1y = $types['1_year'] ?? 2;
        $type2y = $types['2_years'] ?? 3;
        $type3y = $types['3_years'] ?? 4;

        $today = date('Y-m-d');
        $pastMonth = date('Y-m-d', strtotime('-1 month'));
        $past2Months = date('Y-m-d', strtotime('-2 months'));
        $future3Months = date('Y-m-d', strtotime('+3 months'));
        $future1Year = date('Y-m-d', strtotime('+1 year'));

        // 3. Insert Deposits covering ALL POSSIBLE SCENARIOS
        $depositsData = [
            // حالة 1: وديعة نشطة مستحقة للصرف اليوم بالدولار (دورية شهرية - استحقاق حان وبها أرباح معلنة)
            [1, 1, $type1y, 10000.00, 'USD', $past2Months, $future1Year, 1, 3.50, $pastMonth, 'active', 350.00, $past2Months],

            // حالة 2: وديعة نشطة غير مستحقة للصرف بعد (دورية 3 أشهر - بدأت مؤخراً وتستحق في المستقبل)
            [2, 3, $type3y, 15000.00, 'USD', date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+3 years')), 3, 3.50, null, 'active', 0.00, null],

            // حالة 3: وديعة نشطة بالدينار العراقي مستحقة للصرف اليوم (دورية شهرية)
            [3, 1, $type6m, 5000000.00, 'IQD', $past2Months, $future3Months, 1, 3.50, $pastMonth, 'active', 175000.00, $past2Months],

            // حالة 4: وديعة نشطة دورية 6 أشهر حلت ذكراها الشهرية لإضافة ربح تراكمي يدوي
            [4, 2, $type2y, 20000.00, 'USD', $pastMonth, date('Y-m-d', strtotime('+2 years')), 6, 3.50, null, 'active', 0.00, null],

            // حالة 5: وديعة مكتملة الأجل وتنتظر صرف أرباحها التراكمية الأخيرة قبل الإغلاق
            [5, 4, $type1y, 1200000.00, 'IQD', date('Y-m-d', strtotime('-13 months')), $pastMonth, 1, 3.50, $pastMonth, 'active', 42000.00, $past2Months],

            // حالة 6: وديعة مكتملة الأجل ومصروفة ومغلقة نهائياً (Completed & Closed)
            [6, 4, $type6m, 2000000.00, 'IQD', date('Y-m-d', strtotime('-8 months')), $past2Months, 1, 3.50, $past2Months, 'completed', 0.00, $past2Months]
        ];

        $insDep = $pdo->prepare("
            INSERT INTO deposits 
            (id, investor_id, deposit_type_id, amount, currency, start_date, end_date, profit_payout_frequency, profit_rate_monthly, last_profit_date, status, accumulated_profit, last_withdrawal_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($depositsData as $dep) {
            $insDep->execute($dep);
        }

        // 4. Insert Transactions & Withdrawal Requests
        $pdo->prepare("
            INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
            VALUES ('AG-202607-000001', 1, 1, 'profit', 350.00, 'USD', NOW(), 'صرف أرباح تراكمية سابقة')
        ")->execute();

        $pdo->prepare("
            INSERT INTO withdraw_requests (investor_id, amount, currency, request_date, status, note)
            VALUES (1, 100.00, 'USD', NOW(), 'pending', 'طلب سحب جزء من الأرباح المتاحة')
        ")->execute();

        logActivity($pdo, 'SEED_DATABASE', 'system', null, null, ['status' => 'success']);

        $message = "تم تفريغ وإعادة تهيئة قاعدة البيانات بنجاح، وتعبئة 4 مستثمرين و 6 ودائع تجريبية تغطي كافة الحالات التشغيلية!";

    } catch (Exception $e) {
        $errors[] = "حدث خطأ أثناء التهيئة: " . $e->getMessage();
    }
}

$pageTitle = 'تفريغ وتعبئة بيانات الاختبار';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-database-fill-gear me-2"></i>تفريغ وتعبئة بيانات الاختبار</h1>
                <p class="text-muted small">أداة الإدارة لتفريغ الودائع الحالية وتوليد ودائع تجريبية شاملة لكل الحالات الممكنة.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success border mb-4" style="border-radius:10px">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border mb-4" style="border-radius:10px">
                    <ul class="mb-0 pe-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card bg-base border border-warning p-4 text-center" style="border-radius:14px">
                        <div class="text-warning display-4 mb-3"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <h4 class="text-white fw-bold mb-2">إعادة تهيئة وتغذية قاعدة البيانات</h4>
                        <p class="text-muted small mb-4" style="max-width:550px;margin:0 auto">
                            سيقوم هذا الخيار بمحاي وتفريغ كافة الودائع والمستثمرين وسجلات السحوبات الحالية، وتوليد <strong>6 ودائع تجريبية نموذجية</strong> تغطي كافة حالات النظام التالية:
                        </p>

                        <div class="row text-start g-2 mb-4 bg-dark p-3 rounded border border-secondary text-muted small">
                            <div class="col-md-6">🟢 1. وديعة نشطة مستحقة للصرف اليوم بالدولار ($)</div>
                            <div class="col-md-6">🟡 2. وديعة نشطة غير مستحقة بعد (تستحق مستقبلاً)</div>
                            <div class="col-md-6">🟢 3. وديعة نشطة مستحقة للصرف اليوم بالدينار (د.ع)</div>
                            <div class="col-md-6">🔵 4. وديعة دورية 6 أشهر حلت ذكراها للربح اليدوي</div>
                            <div class="col-md-6">🟠 5. وديعة منتهية الأجل وبها أرباح متبقية قبل الإغلاق</div>
                            <div class="col-md-6">⚪ 6. وديعة منتهية ومصروفة ومغلقة نهائياً</div>
                        </div>

                        <form method="post" action="" onsubmit="return confirm('⚠️ هل أنت متأكد من مسح كافة الودائع الحالية وتوليد بيانات الاختبار؟ لا يمكن التراجع عن هذه العملية.');">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-warning btn-lg px-5 fw-bold">
                                <i class="bi bi-arrow-counterclockwise me-2"></i> مسح وإعادة تعبئة بيانات الاختبار الآن
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer.php';
?>
