<?php
// public/export_excel.php — Smart Excel Export
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

requireLogin();
$pdo = getPDO();

$report = $_GET['report'] ?? 'transactions';
$investorId = (int) ($_GET['investor_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$receiptNo = trim($_GET['receipt_no'] ?? '');

// ── Fetch Data ─────────────────────────────────────────────
$rows = [];
$headers = [];
$filename = 'export';
$fTxType = $_GET['tx_type'] ?? '';
$fStatus = $_GET['status'] ?? '';
$fType = $_GET['type'] ?? '';

if ($receiptNo) {
    $stmt = $pdo->prepare("SELECT t.receipt_no AS 'رقم الإيصال', i.full_name AS المستثمر, t.type AS النوع, t.amount AS المبلغ, t.date AS التاريخ, t.note AS ملاحظة FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE t.receipt_no LIKE ?");
    $stmt->execute(['%' . $receiptNo . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'إيصال-' . $receiptNo;
    if (!empty($rows)) {
        $filename = 'إيصال-' . $rows[0]['رقم الإيصال'] . '-' . str_replace(' ', '_', $rows[0]['المستثمر']);
    }

} elseif ($report === 'investor_statement') {
    $where = ['1=1']; $params = [];
    if ($investorId) { $where[] = 't.investor_id=?'; $params[] = $investorId; }
    if ($dateFrom) { $where[] = 't.date>=?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 't.date<=?'; $params[] = $dateTo . ' 23:59:59'; }
    
    $stmt = $pdo->prepare("SELECT t.receipt_no AS 'رقم الإيصال', i.full_name AS المستثمر, t.type AS النوع, t.amount AS المبلغ, t.date AS التاريخ, t.note AS ملاحظة FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'كشف-حساب-عام';
    if (!empty($rows) && $investorId) {
        $filename = 'كشف-حساب-' . str_replace(' ', '_', $rows[0]['المستثمر']);
    }

} elseif ($report === 'profits') {
    $where = ["t.type='profit'"]; $params = [];
    if ($investorId) { $where[] = 't.investor_id=?'; $params[] = $investorId; }
    if ($dateFrom) { $where[] = 't.date>=?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 't.date<=?'; $params[] = $dateTo . ' 23:59:59'; }
    
    $stmt = $pdo->prepare("SELECT t.receipt_no AS 'رقم الإيصال', i.full_name AS المستثمر, t.type AS النوع, t.amount AS المبلغ, t.date AS التاريخ, t.note AS ملاحظة FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'تقرير-الأرباح-عام';
    if (!empty($rows) && $investorId) {
        $filename = 'تقرير-الأرباح-' . str_replace(' ', '_', $rows[0]['المستثمر']);
    }

} elseif ($report === 'transactions') {
    $where = ['1=1']; $params = [];
    if ($fTxType) { $where[] = 't.type=?'; $params[] = $fTxType; }
    if ($investorId) { $where[] = 't.investor_id=?'; $params[] = $investorId; }
    if ($dateFrom) { $where[] = 't.date>=?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 't.date<=?'; $params[] = $dateTo . ' 23:59:59'; }
    
    $stmt = $pdo->prepare("SELECT t.receipt_no AS 'رقم الإيصال', i.full_name AS المستثمر, t.type AS النوع, t.amount AS المبلغ, t.date AS التاريخ, t.note AS ملاحظة FROM transactions t JOIN investors i ON i.id=t.investor_id WHERE " . implode(' AND ', $where) . " ORDER BY t.date DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'تقرير-المعاملات-عام';
    if (!empty($rows) && $investorId) {
        $filename = 'تقرير-المعاملات-' . str_replace(' ', '_', $rows[0]['المستثمر']);
    }

} elseif ($report === 'deposits') {
    $where = ['1=1']; $params = [];
    if ($fStatus) { $where[] = 'd.status=?'; $params[] = $fStatus; }
    if ($fType) { $where[] = 'dt.code=?'; $params[] = $fType; }
    if ($investorId) { $where[] = 'd.investor_id=?'; $params[] = $investorId; }
    if ($dateFrom) { $where[] = 'd.start_date>=?'; $params[] = $dateFrom; }
    if ($dateTo) { $where[] = 'd.start_date<=?'; $params[] = $dateTo; }

    $stmt = $pdo->prepare("SELECT d.id AS '#', i.full_name AS المستثمر, dt.name_ar AS 'نوع الوديعة', d.amount AS المبلغ, d.start_date AS البداية, d.end_date AS النهاية, d.profit_rate_monthly AS 'النسبة الشهرية', d.status AS الحالة FROM deposits d JOIN investors i ON i.id=d.investor_id JOIN deposit_types dt ON dt.id=d.deposit_type_id WHERE " . implode(' AND ', $where) . " ORDER BY d.created_at DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'تقرير-الودائع-عام';
    if (!empty($rows) && $investorId) {
        $filename = 'تقرير-ودائع-' . str_replace(' ', '_', $rows[0]['المستثمر']);
    }
}

// تعديل نصوص المعاملات للعربية قبل تصدير الإكسيل
$tArMap = ['deposit' => 'إيداع', 'profit' => 'ربح', 'withdraw' => 'سحب'];
$stMap = ['active' => 'نشطة', 'completed' => 'منتهية', 'cancelled' => 'ملغاة', 'defaulted' => 'متعثرة'];

foreach ($rows as $key => $row) {
    if (isset($row['النوع']) && isset($tArMap[$row['النوع']])) {
        $rows[$key]['النوع'] = $tArMap[$row['النوع']];
    }
    if (isset($row['الحالة']) && isset($stMap[$row['الحالة']])) {
        $rows[$key]['الحالة'] = $stMap[$row['الحالة']];
    }
}

logActivity($pdo, 'EXPORT_EXCEL', 'reports', null, null, ['report' => $report, 'rows' => count($rows)]);

// ── Try PhpSpreadsheet ─────────────────────────────────────
$spreadsheetPath = __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';

if (file_exists($spreadsheetPath)) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);
    $sheet->setTitle('تقرير');

    if (!empty($rows)) {
        $headers = array_keys($rows[0]);
        foreach ($headers as $c => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1);
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF8B6914'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                ],
            ]);
        }
        foreach ($rows as $ri => $row) {
            foreach (array_values($row) as $c => $val) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1);
                $sheet->setCellValue($col . ($ri + 2), $val);
            }
        }
        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ── Fallback: CSV ──────────────────────────────────────────
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $r)
        fputcsv($out, $r);
}
fclose($out);
exit;
