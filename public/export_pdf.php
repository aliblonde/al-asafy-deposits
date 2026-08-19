<?php
// public/export_pdf.php — Smart PDF Export with Perfect Arabic Support
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

requireLogin();
$pdo = getPDO();

$report = $_GET['report'] ?? 'investor_statement';
$investorId = (int) ($_GET['investor_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$receiptNo = trim($_GET['receipt_no'] ?? '');


// ── Fetch Data ─────────────────────────────────────────────
$rows = [];
$title = 'تقرير';
$fTxType = $_GET['tx_type'] ?? '';

if ($receiptNo) {
    $stmt = $pdo->prepare("SELECT t.*, i.full_name FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE t.receipt_no LIKE ?");
    $stmt->execute(['%' . $receiptNo . '%']);
    $rows = $stmt->fetchAll();
    $title = 'إيصال ' . $receiptNo;
    if (!empty($rows)) {
        $title = 'إيصال ' . $rows[0]['receipt_no'] . ' - ' . $rows[0]['full_name'];
    }
} elseif ($report === 'investor_statement') {
    $where = ['1=1']; $params = [];
    if ($investorId) { $where[] = 't.investor_id=?'; $params[] = $investorId; }
    if ($dateFrom) { $where[] = 't.date>=?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 't.date<=?'; $params[] = $dateTo . ' 23:59:59'; }
    $stmt = $pdo->prepare("SELECT t.*, i.full_name FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC");
    $stmt->execute($params); $rows = $stmt->fetchAll();
    $title = 'كشف حساب عام';
    if (!empty($rows) && $investorId) { $title = 'كشف حساب - ' . $rows[0]['full_name']; }

} elseif ($report === 'profits') {
    $where = ["t.type='profit'"]; $params = [];
    if ($dateFrom) { $where[] = 't.date>=?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 't.date<=?'; $params[] = $dateTo . ' 23:59:59'; }
    if ($investorId) { $where[] = 't.investor_id=?'; $params[] = $investorId; }
    $stmt = $pdo->prepare("SELECT t.*, i.full_name FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC");
    $stmt->execute($params); $rows = $stmt->fetchAll();
    
    // إضافة الاسم ديناميكياً
    $title = 'تقرير الأرباح العام';
    if (!empty($rows) && $investorId) { $title = 'تقرير الأرباح - ' . $rows[0]['full_name']; }

} elseif ($report === 'transactions') {
    $where = ['1=1']; $params = [];
    if ($fTxType) { $where[] = 't.type=?'; $params[] = $fTxType; }
    if ($dateFrom) { $where[] = 't.date>=?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 't.date<=?'; $params[] = $dateTo . ' 23:59:59'; }
    if ($investorId) { $where[] = 't.investor_id=?'; $params[] = $investorId; }
    $stmt = $pdo->prepare("SELECT t.*, i.full_name FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC");
    $stmt->execute($params); $rows = $stmt->fetchAll();
    
    // إضافة الاسم ديناميكياً
    $title = 'تقرير المعاملات العام';
    if (!empty($rows) && $investorId) { $title = 'تقرير المعاملات - ' . $rows[0]['full_name']; }

} elseif ($report === 'deposits') {
    $stmt = $pdo->prepare("SELECT d.*, i.full_name, dt.name_ar FROM deposits d JOIN investors i ON i.id=d.investor_id JOIN deposit_types dt ON dt.id=d.deposit_type_id WHERE 1=1 ORDER BY d.created_at DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $title = 'تقرير الودائع';
}

logActivity($pdo, 'EXPORT_PDF', 'reports', null, null, ['report' => $report, 'rows' => count($rows)]);


$logoSrc = '';
$logoPath = __DIR__ . '/assets/img/ag-logo.png';
if (file_exists($logoPath)) {
    $imgData = base64_encode(file_get_contents($logoPath));
    $logoSrc = 'data:image/png;base64,' . $imgData;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <!-- استيراد خط تجوال العربي -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', Tahoma, sans-serif;
            direction: rtl;
            font-size: 13px;
            color: #111;
            background: #fff;
            padding: 30px;
            margin: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 3px solid #d4af37;
            padding-bottom: 20px;
        }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        
        .company-title {
            font-size: 22px;
            font-weight: 800;
            color: #8B6914;
            margin-bottom: 5px;
        }
        .company-subtitle { font-size: 15px; color: #666; font-weight: 700; }
        .logo-img { max-height: 100px; width: auto; display: block; margin: 0 auto; }
        .meta-info { font-size: 13px; color: #555; line-height: 1.8; text-align: left; }
        
        .report-main-title {
            text-align: center;
            font-size: 22px;
            font-weight: 800;
            color: #222;
            margin: 15px auto;
            padding: 8px 30px;
            background: rgba(212, 175, 55, 0.15);
            border: 1px dashed #d4af37;
            border-radius: 8px;
            display: inline-block;
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th {
            background: #d4af37 !important;
            color: #000;
            padding: 12px;
            text-align: right;
            border-bottom: 2px solid #a08020;
            font-weight: 800;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        td { border: 1px solid #e0e0e0; padding: 10px; text-align: right; font-weight: 700; }
        tr:nth-child(even) td { background: #fafafa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        .receipt { font-family: monospace; color: #8B6914; font-size: 14px; }
        .badge { background: #eee; padding: 2px 8px; border-radius: 4px; font-size: 12px;}
        
        .footer {
            text-align: center; color: #888; font-size: 12px;
            margin-top: 50px; border-top: 1px solid #ccc; padding-top: 15px;
            font-weight: 700;
        }
        
        .action-buttons { text-align: center; margin-bottom: 30px; }
        .btn-print {
            background: linear-gradient(135deg, #d4af37, #b38b1d);
            color: #fff; border: none; padding: 12px 30px; font-weight: bold;
            font-size: 16px; border-radius: 8px; cursor: pointer; font-family: 'Tajawal', sans-serif;
            box-shadow: 0 4px 10px rgba(212,175,55,0.3);
        }
        
        @media print {
            .action-buttons { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="action-buttons">
        <button class="btn-print" onclick="window.print()">🖨️ طباعة أو حفظ التقرير كملف PDF</button>
    </div>

    <table class="header-table">
        <tr>
            <td style="width: 33%; text-align: right;">
                <div class="meta-info" style="text-align: right;">
                    <strong>تاريخ التصدير:</strong> <?= date('Y/m/d') ?><br>
                    <strong>الوقت:</strong> <?= date('H:i') ?><br>
                    <strong>رقم الإصدار:</strong> <span dir="ltr">#<?= rand(1000, 9999) ?></span>
                </div>
            </td>
            <td style="width: 34%; text-align: center;">
                <?php if ($logoSrc): ?>
                    <img src="<?= $logoSrc ?>" class="logo-img">
                <?php else: ?>
                    <div style="font-size:16px; font-weight:bold; color:#d4af37;">العسافي للاستثمارات</div>
                <?php endif; ?>
            </td>
            <td style="width: 33%; text-align: left;">
                <div class="company-title" style="text-align: left;">العسافي للاستثمارات</div>
                <div class="company-subtitle" style="text-align: left;">نظام إدارة الودائع - التقرير الشامل</div>
            </td>
        </tr>
    </table>

    <div style="text-align: center;">
        <div class="report-main-title"><?= htmlspecialchars($title) ?></div>
        <div style="color: #666; font-size: 14px; margin-bottom: 20px; font-weight:700;">إجمالي عدد السجلات في هذا التقرير: <?= count($rows) ?> سجل</div>
    </div>

    <?php if ($report === 'deposits' && !$receiptNo): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>المستثمر</th>
                    <th>النوع</th>
                    <th>المبلغ</th>
                    <th>بداية</th>
                    <th>نهاية</th>
                    <th>النسبة</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= htmlspecialchars($r['name_ar']) ?></td>
                        <td dir="ltr" style="text-align:right;"><?= formatMoney($r['amount']) ?></td>
                        <td><?= formatDate($r['start_date']) ?></td>
                        <td><?= formatDate($r['end_date']) ?></td>
                        <td dir="ltr" style="text-align:right;"><?= number_format($r['profit_rate_monthly'] * 100, 3) ?>%</td>
                        <td><span class="badge"><?= arabicStatus($r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>رقم الإيصال</th>
                    <th>المستثمر</th>
                    <th>النوع</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                    <th>ملاحظة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $tArMap = ['deposit' => 'إيداع', 'profit' => 'ربح', 'withdraw' => 'سحب'];
                    ?>
                    <tr>
                        <td class="receipt">#<?= htmlspecialchars($r['receipt_no']) ?></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><span class="badge"><?= $tArMap[$r['type']] ?? $r['type'] ?></span></td>
                        <td dir="ltr" style="text-align:right;"><?= formatMoney($r['amount']) ?></td>
                        <td><?= date('Y/m/d', strtotime($r['date'])) ?></td>
                        <td><?= htmlspecialchars($r['note'] ?? 'لا يوجد') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        أُصدر هذا التقرير آلياً من نظام إدارة الودائع الاستثمارية — مجموعة العسافي التجارية &copy; <?= date('Y') ?>
    </div>
</body>
</html>
