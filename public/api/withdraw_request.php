<?php
// public/api/withdraw_request.php — Mobile API Submit Profit Withdrawal Request
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'طريقة الطلب غير مدعومة. يرجى استخدام POST.', 405);
}

$user = requireApiAuth();
$investorId = (int)$user['investor_id'];
$pdo = getPDO();

$input = getJsonInput();
$depositId = (int)($input['deposit_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0);
$note = trim((string)($input['note'] ?? 'طلب سحب عبر تطبيق الهاتف'));

if ($depositId <= 0) {
    apiResponse(false, null, 'يرجى اختيار الوديعة المطلوب سحب أرباحها.', 422);
}

if ($amount <= 0) {
    apiResponse(false, null, 'مبلغ السحب يجب أن يكون أكبر من الصفر.', 422);
}

try {
    $pdo->beginTransaction();

    $depStmt = $pdo->prepare("
        SELECT d.*, dt.is_locked, dt.name_ar AS type_name 
        FROM deposits d 
        JOIN deposit_types dt ON dt.id = d.deposit_type_id 
        WHERE d.id = ? AND d.investor_id = ? AND d.status IN ('active', 'completed')
        FOR UPDATE
    ");
    $depStmt->execute([$depositId, $investorId]);
    $deposit = $depStmt->fetch();

    if (!$deposit) {
        throw new Exception('الوديعة المحددة غير موجودة أو غير تابعة لحسابك.');
    }

    $currency = $deposit['currency'];
    $accumulated = (float)$deposit['accumulated_profit'];

    // Calculate pending withdraw requests for this deposit
    $pendingStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdraw_requests WHERE deposit_id = ? AND status = 'pending'");
    $pendingStmt->execute([$depositId]);
    $totalPendingForDep = (float)$pendingStmt->fetchColumn();

    $netAvailable = max(0.00, $accumulated - $totalPendingForDep);

    if ($deposit['is_locked']) {
        $note = $note ? $note . ' [استثنائي - وديعة مقفلة]' : '[استثنائي - وديعة مقفلة]';
    }

    if ($amount > $netAvailable) {
        throw new Exception('عفواً، رصيد الأرباح المتاح للسحب من هذه الوديعة هو ' . number_format($netAvailable, 2) . " $currency فقط (بعد خصم الطلبات المعلقة).");
    }

    // Insert into withdraw_requests
    $insW = $pdo->prepare("
        INSERT INTO withdraw_requests (investor_id, deposit_id, amount, currency, request_date, status, note)
        VALUES (?, ?, ?, ?, NOW(), 'pending', ?)
    ");
    $insW->execute([$investorId, $depositId, $amount, $currency, $note]);
    $wReqId = (int)$pdo->lastInsertId();

    // Automatically create Approval Request in Admin Panel
    $appReqId = createApprovalRequest(
        $pdo,
        'withdrawals.approve',
        'withdraw_request',
        $wReqId,
        [
            'withdraw_request_id' => $wReqId,
            'amount' => $amount,
            'currency' => $currency,
            'deposit_id' => $depositId,
            'note' => $note
        ]
    );

    // Link approval_request_id
    $pdo->prepare("UPDATE withdraw_requests SET approval_request_id = ? WHERE id = ?")
        ->execute([$appReqId, $wReqId]);

    $pdo->commit();

    logActivity($pdo, 'REQUEST_WITHDRAW', 'withdraw_requests', $wReqId, null, [
        'investor_id' => $investorId,
        'deposit_id' => $depositId,
        'amount' => $amount,
        'currency' => $currency,
        'approval_request_id' => $appReqId,
        'client' => 'Mobile App'
    ]);

    apiResponse(true, [
        'withdraw_request_id' => $wReqId,
        'approval_request_id' => $appReqId,
        'amount' => $amount,
        'currency' => $currency,
        'status' => 'pending',
        'status_ar' => 'قيد المراجعة والاعتماد'
    ], 'تم إرسال طلب سحب الأرباح بنجاح، وهو قيد المراجعة والاعتماد لدى الإدارة.');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiResponse(false, null, $e->getMessage(), 400);
}
