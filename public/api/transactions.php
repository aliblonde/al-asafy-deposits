<?php
// public/api/transactions.php — Mobile API Transactions / Account Statement
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = requireApiAuth();
$investorId = (int)$user['investor_id'];
$pdo = getPDO();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$currencyFilter = trim((string)($_GET['currency'] ?? ''));
$where = ['t.investor_id = ?'];
$params = [$investorId];

if ($currencyFilter && in_array($currencyFilter, ['IQD', 'USD'], true)) {
    $where[] = 't.currency = ?';
    $params[] = $currencyFilter;
}

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE " . implode(' AND ', $where));
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Fetch records
$sql = "
    SELECT t.id, t.receipt_no, t.deposit_id, t.type, t.direction, t.amount, t.currency, t.date, t.note,
           d.amount AS deposit_amount, dt.name_ar AS deposit_type_name
    FROM transactions t
    LEFT JOIN deposits d ON d.id = t.deposit_id
    LEFT JOIN deposit_types dt ON dt.id = d.deposit_type_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY t.date DESC, t.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$transactions = [];
foreach ($rows as $r) {
    $transactions[] = [
        'id' => (int)$r['id'],
        'receipt_no' => $r['receipt_no'],
        'deposit_id' => $r['deposit_id'] ? (int)$r['deposit_id'] : null,
        'deposit_type_name' => $r['deposit_type_name'] ?? null,
        'type' => $r['type'],
        'type_ar' => arabicTxType($r['type']),
        'direction' => $r['direction'],
        'direction_ar' => arabicDirection($r['direction']),
        'amount' => (float)$r['amount'],
        'currency' => $r['currency'],
        'date' => $r['date'],
        'note' => $r['note']
    ];
}

apiResponse(true, [
    'transactions' => $transactions,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total_records' => $totalRecords,
        'total_pages' => ceil($totalRecords / $limit)
    ]
], 'تم جلب كشف الحساب بنجاح.');
