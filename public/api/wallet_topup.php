<?php
// public/api/wallet_topup.php — Mobile API Submit Wallet Top-up Request with Receipt
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'طريقة الطلب غير مدعومة. يرجى استخدام POST.', 405);
}

$user = requireApiAuth();
$investorId = (int)$user['investor_id'];
$pdo = getPDO();

// Ensure receipts directory exists
$uploadDir = __DIR__ . '/../../uploads/receipts';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$amount = 0.0;
$currency = 'USD';
$paymentMethod = '';
$transferRef = '';
$note = '';
$receiptPath = null;

// Check if multipart form-data or JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'multipart/form-data')) {
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
    $transferRef = trim((string)($_POST['transfer_reference'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));

    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['receipt'];
        if ($file['size'] > 10 * 1024 * 1024) {
            apiResponse(false, null, 'حجم صورة الوصل يتجاوز 10 ميغابايت.', 422);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
            apiResponse(false, null, 'صيغة الملف غير مدعومة. المسموح: JPG, PNG, WEBP, PDF.', 422);
        }
        $filename = 'topup_' . $investorId . '_' . uniqid() . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $receiptPath = 'uploads/receipts/' . $filename;
        }
    }
} else {
    $input = getJsonInput();
    $amount = (float)($input['amount'] ?? 0);
    $currency = strtoupper(trim((string)($input['currency'] ?? 'USD')));
    $paymentMethod = trim((string)($input['payment_method'] ?? ''));
    $transferRef = trim((string)($input['transfer_reference'] ?? ''));
    $note = trim((string)($input['note'] ?? ''));

    if (!empty($input['receipt_base64'])) {
        $base64 = $input['receipt_base64'];
        // Strip data:image/...;base64, prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) {
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $base64 = substr($base64, strpos($base64, ',') + 1);
        } else {
            $ext = 'jpg';
        }
        $decoded = base64_decode($base64);
        if ($decoded !== false && strlen($decoded) > 0) {
            $filename = 'topup_' . $investorId . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . '/' . $filename;
            if (file_put_contents($dest, $decoded)) {
                $receiptPath = 'uploads/receipts/' . $filename;
            }
        }
    }
}

if ($amount <= 0) {
    apiResponse(false, null, 'يرجى إدخال مبلغ شحن صحيح أكبر من الصفر.', 422);
}

if (!in_array($currency, ['USD', 'IQD'], true)) {
    apiResponse(false, null, 'العملة المختارة غير مدعومة (المتاح: USD, IQD).', 422);
}

if (empty($paymentMethod)) {
    apiResponse(false, null, 'يرجى اختيار طريقة التحويل المستخدمة.', 422);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO wallet_topup_requests (
            investor_id, amount, currency, payment_method, transfer_reference, receipt_image, note, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $investorId,
        $amount,
        $currency,
        $paymentMethod,
        $transferRef ?: null,
        $receiptPath,
        $note ?: 'طلب شحن محفظة عبر تطبيق الهاتف'
    ]);

    $reqId = (int)$pdo->lastInsertId();

    // Log Activity
    logActivity($pdo, 'WALLET_TOPUP_REQUEST', 'wallet_topup_requests', $reqId, null, [
        'investor_id' => $investorId,
        'amount' => $amount,
        'currency' => $currency,
        'payment_method' => $paymentMethod,
        'receipt_image' => $receiptPath,
        'client' => 'Mobile App'
    ]);

    apiResponse(true, [
        'request_id' => $reqId,
        'amount' => $amount,
        'currency' => $currency,
        'payment_method' => $paymentMethod,
        'receipt_image' => $receiptPath,
        'status' => 'pending',
        'message_ar' => 'تم إرسال طلب الشحن وإشعار التحويل بنجاح. سيتم تدقيق الوصل وإضافة الرصيد لمحفظتك فوراً.'
    ], 'تم استلام طلب الشحن بنجاح.');

} catch (\Throwable $e) {
    apiResponse(false, null, 'حدث خطأ أثناء حفظ طلب الشحن: ' . $e->getMessage(), 500);
}
