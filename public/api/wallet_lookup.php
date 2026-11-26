<?php
// public/api/wallet_lookup.php — Lookup Recipient Investor by Wallet ID, Username, or Phone
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = requireApiAuth();
$currentInvestorId = (int)$user['investor_id'];
$pdo = getPDO();

$query = trim((string)($_GET['query'] ?? ''));

if (empty($query)) {
    apiResponse(false, null, 'يرجى إدخال رقم المحفظة أو اسم المستخدم للبحث.', 422);
}

// Parse Wallet ID if formatted as W-000123
$searchId = 0;
if (preg_match('/^W[-_]?(\d+)$/i', $query, $matches)) {
    $searchId = (int)$matches[1];
} elseif (ctype_digit($query)) {
    $searchId = (int)$query;
}

$recipient = null;

if ($searchId > 0) {
    $stmt = $pdo->prepare("
        SELECT i.id, i.full_name, i.phone, i.city, u.username
        FROM investors i
        LEFT JOIN users u ON u.investor_id = i.id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$searchId]);
    $recipient = $stmt->fetch();
}

if (!$recipient) {
    // Try username or phone
    $stmt = $pdo->prepare("
        SELECT i.id, i.full_name, i.phone, i.city, u.username
        FROM investors i
        LEFT JOIN users u ON u.investor_id = i.id
        WHERE u.username = ? OR i.phone = ?
        LIMIT 1
    ");
    $stmt->execute([$query, $query]);
    $recipient = $stmt->fetch();
}

if (!$recipient) {
    apiResponse(false, null, 'لم يتم العثور على أي مستثمر مطابق لرقم المحفظة أو اسم المستخدم المدخل.', 404);
}

if ((int)$recipient['id'] === $currentInvestorId) {
    apiResponse(false, null, 'لا يمكنك التحويل لنفس حسابك.', 422);
}

// Return masked/formatted recipient info for safety confirmation
$fullName = (string)$recipient['full_name'];
$walletIdStr = 'W-' . str_pad((string)$recipient['id'], 6, '0', STR_PAD_LEFT);

apiResponse(true, [
    'recipient_id' => (int)$recipient['id'],
    'wallet_id' => $walletIdStr,
    'full_name' => $fullName,
    'username' => $recipient['username'] ?: '—',
    'phone' => $recipient['phone'] ?: '—',
], 'تم العثور على المستثمر بنجاح.');
