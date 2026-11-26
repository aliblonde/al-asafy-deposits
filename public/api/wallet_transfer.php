<?php
// public/api/wallet_transfer.php — Mobile API P2P Transfer Between Investors
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../config/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'طريقة الطلب غير مدعومة. يرجى استخدام POST.', 405);
}

$user = requireApiAuth();
$senderInvestorId = (int)$user['investor_id'];
$pdo = getPDO();

$input = getJsonInput();
$receiverId = (int)($input['receiver_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0);
$currency = strtoupper(trim((string)($input['currency'] ?? 'USD')));
$note = trim((string)($input['note'] ?? 'تحويل رصيد داخلي'));

if ($receiverId <= 0) {
    apiResponse(false, null, 'يرجى تحديد المستثمر المستلم للتحويل.', 422);
}

if ($receiverId === $senderInvestorId) {
    apiResponse(false, null, 'لا يمكن إجراء تحويل مالي لنفس الحساب.', 422);
}

if ($amount <= 0) {
    apiResponse(false, null, 'مبلغ التحويل يجب أن يكون أكبر من الصفر.', 422);
}

if (!in_array($currency, ['USD', 'IQD'], true)) {
    apiResponse(false, null, 'العملة المختارة غير مدعومة.', 422);
}

// Ensure receiver exists and is active
$rStmt = $pdo->prepare("SELECT id, full_name, phone FROM investors WHERE id = ?");
$rStmt->execute([$receiverId]);
$receiver = $rStmt->fetch();

if (!$receiver) {
    apiResponse(false, null, 'المستثمر المستلم غير موجود أو تم إيقاف حسابه.', 404);
}

try {
    $pdo->beginTransaction();

    // 1. Calculate Sender's Available Balance (Profits + Direct Wallet Funds - Pending Withdrawals)
    // Direct wallet balance
    $wbStmt = $pdo->prepare("SELECT balance FROM wallet_balances WHERE investor_id = ? AND currency = ? FOR UPDATE");
    $wbStmt->execute([$senderInvestorId, $currency]);
    $senderDirectBal = (float)($wbStmt->fetchColumn() ?: 0.0);

    // Accumulated profits on active deposits
    $pStmt = $pdo->prepare("
        SELECT id, accumulated_profit 
        FROM deposits 
        WHERE investor_id = ? AND currency = ? AND status = 'active' AND accumulated_profit > 0
        ORDER BY id ASC
        FOR UPDATE
    ");
    $pStmt->execute([$senderInvestorId, $currency]);
    $activeDepositsWithProfit = $pStmt->fetchAll();

    $totalAccumulated = 0.0;
    foreach ($activeDepositsWithProfit as $ad) {
        $totalAccumulated += (float)$ad['accumulated_profit'];
    }

    // Pending withdrawals
    $pwStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM withdraw_requests 
        WHERE investor_id = ? AND currency = ? AND status = 'pending'
    ");
    $pwStmt->execute([$senderInvestorId, $currency]);
    $totalPendingWithdrawals = (float)$pwStmt->fetchColumn();

    $netProfitsAvailable = max(0.0, $totalAccumulated - $totalPendingWithdrawals);
    $totalSenderAvailable = $senderDirectBal + $netProfitsAvailable;

    if ($amount > $totalSenderAvailable) {
        throw new Exception(
            'عفواً، رصيدك المتاح للتحويل حالياً هو ' . number_format($totalSenderAvailable, 2) . " $currency فقط (بعد احتساب الأرباح والطلبات المعلقة). لا يمكن المساس برأس مال الودائع."
        );
    }

    // 2. Perform Deduction from Sender
    $remainingToDeduct = $amount;

    // A. Deduct from direct wallet balance first
    if ($senderDirectBal > 0) {
        $deductFromDirect = min($senderDirectBal, $remainingToDeduct);
        $newDirectBal = $senderDirectBal - $deductFromDirect;
        $upWb = $pdo->prepare("UPDATE wallet_balances SET balance = ?, updated_at = NOW() WHERE investor_id = ? AND currency = ?");
        $upWb->execute([$newDirectBal, $senderInvestorId, $currency]);
        $remainingToDeduct -= $deductFromDirect;
    }

    // B. If remaining amount needed, deduct from accumulated profits of active deposits
    if ($remainingToDeduct > 0.0001) {
        foreach ($activeDepositsWithProfit as $dep) {
            if ($remainingToDeduct <= 0) break;
            $depProfit = (float)$dep['accumulated_profit'];
            $deductFromDep = min($depProfit, $remainingToDeduct);
            $newDepProfit = $depProfit - $deductFromDep;

            $upDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = ?, last_withdrawal_date = NOW() WHERE id = ?");
            $upDep->execute([$newDepProfit, $dep['id']]);

            $remainingToDeduct -= $deductFromDep;
        }
    }

    // 3. Credit Receiver's Wallet Balance
    $stmtCredit = $pdo->prepare("
        INSERT INTO wallet_balances (investor_id, currency, balance, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance), updated_at = NOW()
    ");
    $stmtCredit->execute([$receiverId, $currency, $amount]);

    // Fetch receiver new balance
    $rbStmt = $pdo->prepare("SELECT balance FROM wallet_balances WHERE investor_id = ? AND currency = ?");
    $rbStmt->execute([$receiverId, $currency]);
    $receiverNewBal = (float)$rbStmt->fetchColumn();

    // 4. Record Transfer in wallet_transfers
    $transferNo = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $insTrf = $pdo->prepare("
        INSERT INTO wallet_transfers (transfer_no, sender_investor_id, receiver_investor_id, amount, currency, note, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
    ");
    $insTrf->execute([$transferNo, $senderInvestorId, $receiverId, $amount, $currency, $note]);
    $transferId = (int)$pdo->lastInsertId();

    // 5. Record in wallet_transactions for Sender (Debit)
    $senderWalletAfter = max(0.0, $totalSenderAvailable - $amount);
    $txSender = $pdo->prepare("
        INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, reference_id, note, created_at)
        VALUES (?, 'withdrawal', 'debit', ?, ?, ?, ?, ?, NOW())
    ");
    $txSender->execute([
        $senderInvestorId,
        $amount,
        $currency,
        $senderWalletAfter,
        $transferId,
        "تحويل مالي إلى المستثمر: {$receiver['full_name']} (حوالة #{$transferNo})" . ($note ? " - {$note}" : '')
    ]);

    // 6. Record in wallet_transactions for Receiver (Credit)
    $txReceiver = $pdo->prepare("
        INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, reference_id, note, created_at)
        VALUES (?, 'topup_credit', 'credit', ?, ?, ?, ?, ?, NOW())
    ");
    $txReceiver->execute([
        $receiverId,
        $amount,
        $currency,
        $receiverNewBal,
        $transferId,
        "حوالة مستلمة من المستثمر: {$user['full_name']} (حوالة #{$transferNo})" . ($note ? " - {$note}" : '')
    ]);

    // 7. Record in main ledger transactions for audit trail
    $txMainS = $pdo->prepare("
        INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
        VALUES (?, ?, 'withdrawal_payout', 'debit', ?, ?, NOW(), ?)
    ");
    $txMainS->execute([$transferNo . '-S', $senderInvestorId, $amount, $currency, "تحويل إلى {$receiver['full_name']}"]);

    $txMainR = $pdo->prepare("
        INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
        VALUES (?, ?, 'profit_payout', 'credit', ?, ?, NOW(), ?)
    ");
    $txMainR->execute([$transferNo . '-R', $receiverId, $amount, $currency, "استلام حوالة من {$user['full_name']}"]);

    // 8. Log Activity
    logActivity($pdo, 'P2P_WALLET_TRANSFER', 'wallet_transfers', $transferId, null, [
        'transfer_no' => $transferNo,
        'sender_id' => $senderInvestorId,
        'receiver_id' => $receiverId,
        'amount' => $amount,
        'currency' => $currency,
        'note' => $note
    ]);

    $pdo->commit();

    // 9. Send Instant Push Notifications to both Sender and Receiver
    $fmtAmount = number_format($amount, 2);
    
    // Receiver Notification
    notifyInvestor(
        $pdo,
        $receiverId,
        'استلام حوالة مالية جديدة 📥',
        "وصلتك حوالة بقيمة {$fmtAmount} {$currency} من المستثمر {$user['full_name']} في محفظتك بنجاح.",
        'wallet'
    );

    // Sender Notification
    notifyInvestor(
        $pdo,
        $senderInvestorId,
        'تم إرسال الحوالة بنجاح 📤',
        "تم تحويل مبلغ {$fmtAmount} {$currency} إلى المستثمر {$receiver['full_name']}. رقم الحوالة: {$transferNo}",
        'wallet'
    );

    apiResponse(true, [
        'transfer_no' => $transferNo,
        'amount' => $amount,
        'currency' => $currency,
        'receiver_name' => $receiver['full_name'],
        'sender_name' => $user['full_name'],
        'new_available_balance' => $senderWalletAfter,
        'date' => date('Y-m-d H:i:s'),
        'message_ar' => "تم تحويل مبلغ {$fmtAmount} {$currency} إلى {$receiver['full_name']} بنجاح."
    ], 'تم تنفيذ التحويل بنجاح.');

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiResponse(false, null, $e->getMessage(), 422);
}
