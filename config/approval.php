<?php
// config/approval.php — Centralized Financial Approval Engine & Workflow Handler

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logger.php';

/**
 * Sort payload array recursively by keys for canonical JSON generation.
 */
function canonicalizePayload(array $data): array
{
    ksort($data);
    foreach ($data as $key => $val) {
        if (is_array($val)) {
            $data[$key] = canonicalizePayload($val);
        }
    }
    return $data;
}

/**
 * Map operation type to required approval permission.
 */
function getRequiredApprovalPermission(string $operationType): string
{
    return match ($operationType) {
        'profits.payout' => 'profits.approve_payout',
        'profits.manual' => 'profits.approve_manual',
        'deposits.close' => 'deposits.approve_close',
        'deposits.financial_change' => 'deposits.approve_financial_change',
        'withdrawals.approve' => 'withdrawals.approve',
        'rates.declaration' => 'rates.approve_declaration',
        default => 'admin'
    };
}

/**
 * Submit a new pending approval request.
 * Idempotent: returns existing pending request if duplicate payload is submitted.
 */
function createApprovalRequest(
    PDO $pdo,
    string $operationType,
    string $entityType,
    ?int $entityId,
    array $payload,
    ?array $oldData = null
): int {
    $requestedBy = currentUserId();
    $canonicalPayload = canonicalizePayload($payload);
    $canonicalJson = json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE);
    
    $idempotencyKey = hash('sha256', $operationType . ':' . $entityType . ':' . ($entityId ?: '0') . ':' . $requestedBy . ':' . $canonicalJson);

    // Idempotency check: Search for existing pending request
    $chkStmt = $pdo->prepare("SELECT id FROM approval_requests WHERE idempotency_key = ? AND status = 'pending' LIMIT 1");
    $chkStmt->execute([$idempotencyKey]);
    $existingId = $chkStmt->fetchColumn();

    if ($existingId) {
        return (int)$existingId;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO approval_requests (
                operation_type, entity_type, entity_id, requested_by,
                payload_json, old_data_json, status, idempotency_key, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([
            $operationType,
            $entityType,
            $entityId,
            $requestedBy,
            $canonicalJson,
            $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            $idempotencyKey
        ]);

        $reqId = (int)$pdo->lastInsertId();
        logActivity($pdo, 'CREATE_APPROVAL_REQUEST', 'approval_requests', $reqId, null, [
            'operation' => $operationType,
            'entity' => $entityType,
            'entity_id' => $entityId
        ]);

        return $reqId;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $chkStmt->execute([$idempotencyKey]);
            $existingId = $chkStmt->fetchColumn();
            if ($existingId) {
                return (int)$existingId;
            }
        }
        throw $e;
    }
}

/**
 * Approve and atomically execute a pending request in a single DB Transaction.
 */
function executeApprovalRequest(PDO $pdo, int $requestId, int $approverId): array
{
    $pdo->beginTransaction();

    try {
        // Lock request row FOR UPDATE
        $stmt = $pdo->prepare("SELECT * FROM approval_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new Exception('طلب الموافقة غير موجود.');
        }

        if ($req['status'] !== 'pending') {
            throw new Exception('طلب الموافقة ليس في حالة معلقة. الحالة الحالية: ' . $req['status']);
        }

        $opType = $req['operation_type'];
        $requiredPerm = getRequiredApprovalPermission($opType);

        if (!userCan($requiredPerm, $approverId)) {
            throw new Exception("ليس لديك الصلاحية المطلوبة ($requiredPerm) للموافقة على هذه العملية.");
        }

        $payload = json_decode($req['payload_json'], true) ?: [];
        $execRef = '';

        switch ($opType) {

            // ═══════════════════════════════════════════
            // profits.payout — Direct Profit Payout
            // ═══════════════════════════════════════════
            case 'profits.payout':
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $amount = (float)($payload['amount'] ?? 0);
                $note = trim($payload['note'] ?? 'صرف أرباح مقرة');

                if ($amount <= 0) {
                    throw new Exception('مبلغ الصرف يجب أن يكون أكبر من صفر.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                    throw new Exception('الوديعة غير نشطة أو غير موجودة.');
                }

                if (!isDepositProfitDue($deposit)) {
                    throw new Exception('عفواً، الأرباح لهذه الوديعة ليست مستحقة للصرف حالياً وقت الموافقة.');
                }

                $accumulated = (float)$deposit['accumulated_profit'];
                if ($amount > $accumulated) {
                    throw new Exception('المبلغ المطلوب أكبر من رصيد الأرباح المتراكمة المتاح.');
                }

                $currency = $deposit['currency'];
                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("
                    UPDATE deposits 
                    SET accumulated_profit = accumulated_profit - ?, 
                        paid_profit = paid_profit + ?,
                        last_withdrawal_date = NOW()
                    WHERE id = ? AND accumulated_profit >= ?
                ");
                $upDep->execute([$amount, $amount, $depositId, $amount]);

                if ($upDep->rowCount() !== 1) {
                    throw new Exception('فشل تحديث رصيد الوديعة بسبب تعارض تزامن.');
                }

                // NEW: Use profit_payout type
                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'profit_payout', 'debit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, $requestId, $note]);
                $txId = (int)$pdo->lastInsertId();
                $execRef = 'TX-' . $txId . ' / ' . $receiptNo;
                break;

            // ═══════════════════════════════════════════
            // deposits.close — Principal Refund
            // ═══════════════════════════════════════════
            case 'deposits.close':
                $depositId = (int)($payload['deposit_id'] ?? 0);

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة غير موجودة.');
                }

                if ($deposit['end_date'] > date('Y-m-d')) {
                    throw new Exception('لا يمكن إنهاء الوديعة قبل حلول تاريخ انتهائها.');
                }

                if ((int)$deposit['principal_refunded'] === 1) {
                    throw new Exception('تم إرجاع رأس المال وإنهاء الوديعة سابقاً.');
                }

                $accumulated = (float)$deposit['accumulated_profit'];
                if ($accumulated > 0) {
                    throw new Exception('لا يمكن إنهاء الوديعة قبل صرف كامل الأرباح المتراكمة المتبقية.');
                }

                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("
                    UPDATE deposits 
                    SET status = 'completed', principal_refunded = 1, last_withdrawal_date = NOW() 
                    WHERE id = ? AND principal_refunded = 0
                ");
                $upDep->execute([$depositId]);

                if ($upDep->rowCount() !== 1) {
                    throw new Exception('فشل إنهاء الوديعة لعدم تطابق الحالة.');
                }

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'principal_refund', 'debit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $deposit['amount'], $deposit['currency'], $requestId, 'إرجاع رأس المال للوديعة المنتهية']);
                $txId = (int)$pdo->lastInsertId();
                $execRef = 'REFUND-TX-' . $txId . ' / ' . $receiptNo;
                break;

            // ═══════════════════════════════════════════
            // withdrawals.approve — Investor Withdrawal
            // SECTION 1 FIX: approval_request_id conflict
            // ═══════════════════════════════════════════
            case 'withdrawals.approve':
                $wReqId = (int)($payload['withdraw_request_id'] ?? 0);

                $wStmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ? FOR UPDATE");
                $wStmt->execute([$wReqId]);
                $wReq = $wStmt->fetch();

                if (!$wReq || $wReq['status'] !== 'pending') {
                    throw new Exception('طلب السحب غير متاح للموافقة أو تم البت فيه سابقاً.');
                }

                // FIX: transaction_id must be NULL (not yet executed)
                if ($wReq['transaction_id'] !== null) {
                    throw new Exception('تم تنفيذ طلب السحب سابقاً ولا يمكن إعادة التنفيذ.');
                }

                // FIX: approval_request_id must match current requestId
                if (empty($wReq['approval_request_id']) || (int)$wReq['approval_request_id'] !== $requestId) {
                    throw new Exception('طلب السحب غير مرتبط بطلب الموافقة الحالي.');
                }

                // Validate entity_id matches
                if ((int)$req['entity_id'] !== $wReqId) {
                    throw new Exception('عدم تطابق بين طلب الموافقة وطلب السحب.');
                }

                $depositId = (int)($wReq['deposit_id'] ?? 0);
                if ($depositId <= 0) {
                    throw new Exception('طلب السحب غير مرتبط بوديعة. يرجى ربطه بوديعة قبل الاعتماد.');
                }

                $amount = (float)$wReq['amount'];
                if ($amount <= 0) {
                    throw new Exception('مبلغ طلب السحب يجب أن يكون أكبر من صفر.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                    throw new Exception('الوديعة المرتبطة بالسحب غير موجودة أو غير متاحة.');
                }

                if ((int)$wReq['investor_id'] !== (int)$deposit['investor_id']) {
                    throw new Exception('المستثمر في طلب السحب لا يطابق المستثمر مالك الوديعة.');
                }

                if ((float)$deposit['accumulated_profit'] < $amount) {
                    throw new Exception('الرصيد المتاح حالياً بالوديعة غير كافٍ لتنفيذ طلب السحب.');
                }

                // Verify no previous transaction linked to this withdraw_request
                $chkDupTx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE approval_request_id = ? AND deposit_id = ? AND type = 'withdrawal_payout'");
                $chkDupTx->execute([$requestId, $depositId]);
                if ((int)$chkDupTx->fetchColumn() > 0) {
                    throw new Exception('يوجد قيد مالي مسجل سابقاً لطلب الموافقة هذا.');
                }

                $currency = $deposit['currency'];
                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("
                    UPDATE deposits 
                    SET accumulated_profit = accumulated_profit - ?, paid_profit = paid_profit + ?, last_withdrawal_date = NOW()
                    WHERE id = ? AND accumulated_profit >= ?
                ");
                $upDep->execute([$amount, $amount, $depositId, $amount]);

                if ($upDep->rowCount() !== 1) {
                    throw new Exception('فشل الخصم المالي بسبب تعارض تزامن.');
                }

                // NEW: Use withdrawal_payout type
                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'withdrawal_payout', 'debit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, $requestId, 'صرف طلب سحب المستثمر رقم #' . $wReqId]);
                $txId = (int)$pdo->lastInsertId();

                // Update withdraw_request to paid - DO NOT overwrite approval_request_id
                $upW = $pdo->prepare("
                    UPDATE withdraw_requests 
                    SET status = 'paid', staff_user_id = ?, decision_date = NOW(), transaction_id = ?
                    WHERE id = ? AND status = 'pending'
                ");
                $upW->execute([$approverId, $txId, $wReqId]);

                if ($upW->rowCount() !== 1) {
                    throw new Exception('فشل تحديث حالة طلب السحب إلى مدفوع.');
                }

                $execRef = 'WITHDRAW-REQ-' . $wReqId . ' / TX-' . $txId;
                break;

            // ═══════════════════════════════════════════
            // profits.manual — Manual Profit Adjustment
            // SECTION 4 FIX: Use manual_profit_adjustments
            // ═══════════════════════════════════════════
            case 'profits.manual':
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $amount = (float)($payload['amount'] ?? 0);
                $reason = trim($payload['reason'] ?? '');
                $month = trim($payload['month'] ?? date('Y-m'));

                if ($amount <= 0) {
                    throw new Exception('مبلغ الربح اليدوي يجب أن يكون أكبر من صفر.');
                }

                if (empty($reason)) {
                    throw new Exception('سبب الإضافة اليدوية إجباري.');
                }

                if (!preg_match('/^\d{4}-\d{2}$/', $month) || $month > date('Y-m')) {
                    throw new Exception('صيغة الشهر غير صالحة أو أنها لشهر مستقبلي.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                    throw new Exception('الوديعة غير موجودة أو غير متاحة لربح يدوي.');
                }

                // FIX: Prevent duplicate execution via UNIQUE approval_request_id in manual_profit_adjustments
                $chkMpa = $pdo->prepare("SELECT COUNT(*) FROM manual_profit_adjustments WHERE approval_request_id = ?");
                $chkMpa->execute([$requestId]);
                if ((int)$chkMpa->fetchColumn() > 0) {
                    throw new Exception('تم تنفيذ هذا التعديل اليدوي سابقاً من طلب الموافقة نفسه.');
                }

                $currency = $deposit['currency'];
                $receiptNo = generateReceiptNo($pdo);

                // Insert manual_profit_adjustments record
                $insMpa = $pdo->prepare("
                    INSERT INTO manual_profit_adjustments (deposit_id, amount, currency, month, reason, approval_request_id, requested_by, approved_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insMpa->execute([$depositId, $amount, $currency, $month, $reason, $requestId, $req['requested_by'], $approverId]);

                // Update deposit accumulated_profit
                $upDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ? WHERE id = ?");
                $upDep->execute([$amount, $depositId]);

                if ($upDep->rowCount() !== 1) {
                    throw new Exception('فشل تحديث رصيد الوديعة.');
                }

                // NEW: Use profit_accrual type
                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'profit_accrual', 'credit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, $requestId, '[تعديل يدوي - ' . $month . '] ' . $reason]);
                $execRef = 'MANUAL-PROFIT-' . $pdo->lastInsertId();
                break;

            // ═══════════════════════════════════════════
            // deposits.financial_change — Deposit Edit
            // SECTION 5 & 6 FIX: direction + financial history guard
            // ═══════════════════════════════════════════
            case 'deposits.financial_change':
                $depositId = (int)($payload['deposit_id'] ?? 0);

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة غير موجودة.');
                }

                $newInvestorId = isset($payload['new_investor_id']) ? (int)$payload['new_investor_id'] : (int)$deposit['investor_id'];
                $newDepositTypeId = isset($payload['new_deposit_type_id']) ? (int)$payload['new_deposit_type_id'] : (int)$deposit['deposit_type_id'];
                $newAmount = isset($payload['new_amount']) ? (float)$payload['new_amount'] : (float)$deposit['amount'];
                $newCurrency = isset($payload['new_currency']) ? trim($payload['new_currency']) : $deposit['currency'];
                $newStartDate = isset($payload['new_start_date']) ? trim($payload['new_start_date']) : $deposit['start_date'];
                $newPayoutFreq = isset($payload['new_profit_payout_frequency']) ? (int)$payload['new_profit_payout_frequency'] : (int)$deposit['profit_payout_frequency'];

                // Validate new investor
                $invCheck = $pdo->prepare("SELECT COUNT(*) FROM investors WHERE id = ?");
                $invCheck->execute([$newInvestorId]);
                if ((int)$invCheck->fetchColumn() === 0) {
                    throw new Exception('المستثمر الجديد المحدد غير موجود.');
                }

                // Validate new deposit type
                $typeCheck = $pdo->prepare("SELECT * FROM deposit_types WHERE id = ?");
                $typeCheck->execute([$newDepositTypeId]);
                $dType = $typeCheck->fetch();
                if (!$dType) {
                    throw new Exception('نوع الوديعة الجديد غير صالح.');
                }

                $startDt = DateTimeImmutable::createFromFormat('Y-m-d', $newStartDate);
                if (!$startDt) {
                    throw new Exception('تاريخ البداية الجديد غير صالح.');
                }
                $newEndDate = $startDt->modify('+' . $dType['max_days'] . ' days')->format('Y-m-d');

                if ($newAmount <= 0) {
                    throw new Exception('المبلغ الجديد يجب أن يكون أكبر من صفر.');
                }

                if (!in_array($newCurrency, ['IQD', 'USD'], true)) {
                    throw new Exception('العملة الجديدة غير صالحة.');
                }

                // SECTION 6 FIX: Check ALL financial history tables, not just transactions
                $hasFinHistory = false;
                $historyTables = [
                    "SELECT COUNT(*) FROM transactions WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM withdraw_requests WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM manual_profit_adjustments WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM deposit_adjustments WHERE deposit_id = ?",
                ];
                foreach ($historyTables as $sql) {
                    $hStmt = $pdo->prepare($sql);
                    $hStmt->execute([$depositId]);
                    if ((int)$hStmt->fetchColumn() > 0) {
                        $hasFinHistory = true;
                        break;
                    }
                }

                if ($hasFinHistory) {
                    // Immutable fields after financial history exists
                    if ($newCurrency !== $deposit['currency']) {
                        throw new Exception('لا يمكن تغيير عملة وديعة مرتبطة بحركات مالية مسجلة.');
                    }
                    if ($newInvestorId !== (int)$deposit['investor_id']) {
                        throw new Exception('لا يمكن نقل ملكية الوديعة لمستثمر آخر بعد تسجيل حركات مالية عليها.');
                    }
                    if ($newStartDate !== $deposit['start_date']) {
                        throw new Exception('لا يمكن تغيير تاريخ بداية الوديعة بعد تسجيل حركات مالية.');
                    }
                    if ($newDepositTypeId !== (int)$deposit['deposit_type_id']) {
                        throw new Exception('لا يمكن تغيير نوع الوديعة بعد تسجيل حركات مالية.');
                    }

                    // Check profit_cycles and pending withdrawals for end_date/frequency changes
                    $hasCycles = $pdo->prepare("SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ?");
                    $hasCycles->execute([$depositId]);
                    $hasPendingWr = $pdo->prepare("SELECT COUNT(*) FROM withdraw_requests WHERE deposit_id = ? AND status = 'pending'");
                    $hasPendingWr->execute([$depositId]);

                    if ((int)$hasCycles->fetchColumn() > 0 || (int)$hasPendingWr->fetchColumn() > 0) {
                        if ($newEndDate !== $deposit['end_date']) {
                            throw new Exception('لا يمكن تغيير تاريخ انتهاء الوديعة بعد وجود دورات أرباح أو طلبات سحب معلقة.');
                        }
                        if ($newPayoutFreq !== (int)$deposit['profit_payout_frequency']) {
                            throw new Exception('لا يمكن تغيير دورية صرف الأرباح بعد وجود دورات أرباح أو طلبات سحب معلقة.');
                        }
                    }
                }

                // Handle amount change -> deposit_adjustments with direction
                $oldAmount = (float)$deposit['amount'];
                if ($newAmount !== $oldAmount) {
                    $diff = $newAmount - $oldAmount;
                    $direction = $diff > 0 ? 'increase' : 'decrease';

                    $insAdj = $pdo->prepare("
                        INSERT INTO deposit_adjustments (deposit_id, old_amount, new_amount, difference, direction, currency, approval_request_id, requested_by, approved_by, reason, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $insAdj->execute([
                        $depositId,
                        $oldAmount,
                        $newAmount,
                        $diff,
                        $direction,
                        $deposit['currency'],
                        $requestId,
                        $req['requested_by'],
                        $approverId,
                        'تعديل قيمة الوديعة بناءً على طلب موافقة #' . $requestId
                    ]);

                    // Record adjustment transaction with direction
                    $receiptNo = generateReceiptNo($pdo);
                    $txDirection = $diff > 0 ? 'credit' : 'debit';
                    $insTx = $pdo->prepare("
                        INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                        VALUES (?, ?, ?, 'deposit_adjustment', ?, ?, ?, ?, NOW(), ?)
                    ");
                    $insTx->execute([
                        $receiptNo,
                        $deposit['investor_id'],
                        $depositId,
                        $txDirection,
                        abs($diff),
                        $deposit['currency'],
                        $requestId,
                        ($direction === 'increase' ? 'زيادة' : 'تخفيض') . ' رأس المال (من ' . formatMoney($oldAmount, $deposit['currency']) . ' إلى ' . formatMoney($newAmount, $deposit['currency']) . ')'
                    ]);
                }

                // Update deposit atomically
                $upDep = $pdo->prepare("
                    UPDATE deposits 
                    SET investor_id = ?, deposit_type_id = ?, amount = ?, currency = ?, start_date = ?, end_date = ?, profit_payout_frequency = ?
                    WHERE id = ?
                ");
                $upDep->execute([
                    $newInvestorId,
                    $newDepositTypeId,
                    $newAmount,
                    $newCurrency,
                    $newStartDate,
                    $newEndDate,
                    $newPayoutFreq,
                    $depositId
                ]);

                $execRef = 'FIN-CHANGE-' . $depositId;
                break;

            // ═══════════════════════════════════════════
            // rates.declaration — Monthly Rate Declaration
            // SECTION 3 FIX: Record profit_accrual transactions
            // ═══════════════════════════════════════════
            case 'rates.declaration':
                $month = trim($payload['month'] ?? '');
                $depositTypeId = (int)($payload['deposit_type_id'] ?? 0);
                $rate = (float)($payload['rate'] ?? 0);

                if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                    throw new Exception('صيغة الشهر غير صحيحة.');
                }
                if ($month > date('Y-m')) {
                    throw new Exception('لا يمكن إعلان نسب أرباح لشهر مستقبلي.');
                }

                $typeStmt = $pdo->prepare("SELECT * FROM deposit_types WHERE id = ?");
                $typeStmt->execute([$depositTypeId]);
                $dType = $typeStmt->fetch();

                if (!$dType) {
                    throw new Exception('نوع الوديعة غير صالح.');
                }

                $minRate = (float)$dType['min_rate'] * 100;
                $maxRate = (float)$dType['max_rate'] * 100;
                if ($rate < $minRate || $rate > $maxRate) {
                    throw new Exception("النسبة المعلنة ($rate%) خارج النطاق المسموح ($minRate% - $maxRate%).");
                }

                $chkRate = $pdo->prepare("SELECT status FROM rate_declarations WHERE month = ? AND deposit_type_id = ?");
                $chkRate->execute([$month, $depositTypeId]);
                $existingStatus = $chkRate->fetchColumn();

                if ($existingStatus === 'executed') {
                    throw new Exception("تم إعلان وتنفيذ نسب الأرباح لشهر $month لنوع الوديعة مسبقاً.");
                }

                $insRate = $pdo->prepare("
                    INSERT INTO rate_declarations (month, deposit_type_id, declared_rate_monthly, status, created_by, approved_by, executed_at, created_at)
                    VALUES (?, ?, ?, 'executed', ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE declared_rate_monthly = VALUES(declared_rate_monthly), status = 'executed', approved_by = VALUES(approved_by), executed_at = NOW()
                ");
                $insRate->execute([$month, $depositTypeId, $rate, $req['requested_by'], $approverId]);

                $insMr = $pdo->prepare("
                    INSERT INTO monthly_rates (month, deposit_type_id, rate_percent)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE rate_percent = VALUES(rate_percent)
                ");
                $insMr->execute([$month, $depositTypeId, ($rate / 100)]);

                // Calculate profit for eligible deposits
                $depStmt = $pdo->prepare("SELECT d.* FROM deposits d WHERE d.deposit_type_id = ? AND d.status = 'active'");
                $depStmt->execute([$depositTypeId]);
                $activeDeps = $depStmt->fetchAll();

                $calcCount = 0;
                foreach ($activeDeps as $dep) {
                    if (empty($dep['start_date'])) continue;

                    $nextProfitDt = calcNextProfitDate($dep);
                    if (!$nextProfitDt) continue;

                    $nextMonthStr = $nextProfitDt->format('Y-m');
                    $actualCycleDate = $nextProfitDt->format('Y-m-d');

                    if ($nextMonthStr !== $month) continue;
                    if ($actualCycleDate < $dep['start_date'] || $actualCycleDate > $dep['end_date']) continue;

                    // Idempotency: skip if cycle already exists
                    $checkCycle = $pdo->prepare("SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ? AND cycle_date = ?");
                    $checkCycle->execute([$dep['id'], $actualCycleDate]);
                    if ((int)$checkCycle->fetchColumn() > 0) continue;

                    $monthlyProfit = round((float)$dep['amount'] * ($rate / 100), 2);
                    if ($monthlyProfit <= 0) continue;

                    // 1. Create profit_cycle
                    $insCycle = $pdo->prepare("
                        INSERT INTO profit_cycles (deposit_id, cycle_date, profit_amount, status, created_at)
                        VALUES (?, ?, ?, 'calculated', NOW())
                    ");
                    $insCycle->execute([$dep['id'], $actualCycleDate, $monthlyProfit]);

                    // 2. Update accumulated_profit
                    $upDepAccum = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ?, last_profit_date = ? WHERE id = ?");
                    $upDepAccum->execute([$monthlyProfit, $actualCycleDate, $dep['id']]);

                    if ($upDepAccum->rowCount() !== 1) {
                        throw new Exception('فشل تحديث رصيد الوديعة رقم #' . $dep['id']);
                    }

                    // 3. NEW: Create profit_accrual transaction
                    $accrualReceiptNo = generateReceiptNo($pdo);
                    $insAccrualTx = $pdo->prepare("
                        INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                        VALUES (?, ?, ?, 'profit_accrual', 'credit', ?, ?, ?, NOW(), ?)
                    ");
                    $insAccrualTx->execute([
                        $accrualReceiptNo,
                        $dep['investor_id'],
                        $dep['id'],
                        $monthlyProfit,
                        $dep['currency'],
                        $requestId,
                        'استحقاق أرباح شهر ' . $month . ' بنسبة ' . $rate . '%'
                    ]);

                    $calcCount++;
                }

                $execRef = "RATES-$month-TYPE-$depositTypeId (Calculated $calcCount deposits)";
                break;

            default:
                throw new Exception('نوع العملية غير معروف: ' . htmlspecialchars($opType));
        }

        // Update approval request status to executed
        $upReq = $pdo->prepare("
            UPDATE approval_requests 
            SET status = 'executed', approved_by = ?, approved_at = NOW(), executed_at = NOW(), execution_reference = ?
            WHERE id = ? AND status = 'pending'
        ");
        $upReq->execute([$approverId, $execRef, $requestId]);

        if ($upReq->rowCount() !== 1) {
            throw new Exception('فشل تحديث حالة طلب الموافقة بسبب تعارض أمان أو تغير الحالة.');
        }

        $pdo->commit();

        logActivity($pdo, 'EXECUTE_APPROVAL_SUCCESS', 'approval_requests', $requestId, null, [
            'operation' => $opType,
            'approver_id' => $approverId,
            'exec_ref' => $execRef
        ]);

        return ['success' => true, 'safe_message' => 'تمت الموافقة والتنفيذ بنجاح.', 'reference' => $execRef];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errRef = 'ERR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        error_log("[$errRef] Approval Execution Failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        $safeReason = "فشل التنفيذ — المرجع: " . $errRef;

        try {
            $pdo->prepare("UPDATE approval_requests SET status = 'failed', rejection_reason = ? WHERE id = ?")
                ->execute([$safeReason, $requestId]);
        } catch (Throwable $ex) {
            error_log("Failed status update error: " . $ex->getMessage());
        }

        logActivity($pdo, 'EXECUTE_APPROVAL_FAILED', 'approval_requests', $requestId, null, [
            'error_ref' => $errRef
        ]);

        return [
            'success' => false, 
            'safe_message' => 'تعذر تنفيذ العملية. يرجى مراجعة مسؤول النظام. (رمز الخطأ: ' . $errRef . ')',
            'error_reference' => $errRef
        ];
    }
}

/**
 * Reject an approval request with mandatory reason.
 */
function rejectApprovalRequest(PDO $pdo, int $requestId, int $rejecterId, string $reason): bool
{
    if (trim($reason) === '') {
        throw new Exception('سبب الرفض إجباري.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT * FROM approval_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new Exception('طلب الموافقة غير موجود.');
        }

        if ($req['status'] !== 'pending') {
            throw new Exception('طلب الموافقة ليس في حالة معلقة.');
        }

        $requiredPerm = getRequiredApprovalPermission($req['operation_type']);
        if (!userCan($requiredPerm, $rejecterId)) {
            throw new Exception("ليس لديك الصلاحية المطلوبة ($requiredPerm) لرفض هذا الطلب.");
        }

        $up = $pdo->prepare("
            UPDATE approval_requests 
            SET status = 'rejected', approved_by = ?, rejected_by = ?, approved_at = NOW(), rejected_at = NOW(), rejection_reason = ?
            WHERE id = ? AND status = 'pending'
        ");
        $up->execute([$rejecterId, $rejecterId, trim($reason), $requestId]);

        if ($up->rowCount() !== 1) {
            throw new Exception('فشل تحديث حالة طلب الموافقة إلى مرفوض.');
        }

        if ($req['operation_type'] === 'withdrawals.approve') {
            $payload = json_decode($req['payload_json'], true) ?: [];
            $wReqId = (int)($payload['withdraw_request_id'] ?? 0);
            if ($wReqId > 0) {
                $upW = $pdo->prepare("UPDATE withdraw_requests SET status = 'rejected', staff_user_id = ?, decision_date = NOW(), note = ? WHERE id = ?");
                $upW->execute([$rejecterId, trim($reason), $wReqId]);
            }
        }

        logActivity($pdo, 'REJECT_APPROVAL_REQUEST', 'approval_requests', $requestId, null, [
            'rejecter_id' => $rejecterId,
            'reason' => $reason
        ]);

        $pdo->commit();
        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
