<?php
// config/approval.php — Centralized Financial Approval Engine & Workflow Handler

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logger.php';

/**
 * Submit a new pending approval request.
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
    $idempotencyKey = hash('sha256', $operationType . ':' . ($entityId ?: '0') . ':' . json_encode($payload) . ':' . time());

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
        json_encode($payload, JSON_UNESCAPED_UNICODE),
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
}

/**
 * Approve and atomically execute a pending request in a single DB Transaction.
 */
function executeApprovalRequest(PDO $pdo, int $requestId, int $approverId): array
{
    $pdo->beginTransaction();

    try {
        // Lock request row
        $stmt = $pdo->prepare("SELECT * FROM approval_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new Exception('طلب الموافقة غير موجود.');
        }

        if ($req['status'] !== 'pending') {
            throw new Exception('هذا الطلب تم البت فيه سابقاً بالحالة: ' . $req['status']);
        }

        $opType = $req['operation_type'];
        $payload = json_decode($req['payload_json'], true) ?: [];
        $execRef = '';

        // Execute financial logic based on operation type
        switch ($opType) {

            case 'profits.payout':
                requirePermission('profits.approve_payout');
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $amount = (float)($payload['amount'] ?? 0);
                $note = trim($payload['note'] ?? 'صرف أرباح مقرة');

                if ($amount <= 0) {
                    throw new Exception('مبلغ الصرف يجب أن يكون أكبر من صفر.');
                }

                // Lock deposit row
                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || $deposit['status'] !== 'active') {
                    throw new Exception('الوديعة غير نشطة أو غير موجودة.');
                }

                $accumulated = (float)$deposit['accumulated_profit'];
                if ($amount > $accumulated) {
                    throw new Exception('المبلغ المطلوب (' . formatMoney($amount, $deposit['currency']) . ') أكبر من رصيد الأرباح المتراكمة المتاح (' . formatMoney($accumulated, $deposit['currency']) . ').');
                }

                $receiptNo = generateReceiptNo($pdo);

                // Update deposit balances
                $upDep = $pdo->prepare("
                    UPDATE deposits 
                    SET accumulated_profit = accumulated_profit - ?, 
                        paid_profit = paid_profit + ?,
                        last_withdrawal_date = NOW()
                    WHERE id = ? AND accumulated_profit >= ?
                ");
                $upDep->execute([$amount, $amount, $depositId, $amount]);

                if ($upDep->rowCount() !== 1) {
                    throw new Exception('فشل تحديث رصيد الوديعة بسبب تضارب محاذي.');
                }

                // Insert transaction record
                $insTx = $pdo->prepare("
                    INSERT INTO transactions (investor_id, deposit_id, type, amount, date, receipt_no, note, created_at)
                    VALUES (?, ?, 'withdraw', ?, NOW(), ?, ?, NOW())
                ");
                $insTx->execute([$deposit['investor_id'], $depositId, $amount, $receiptNo, $note]);
                $execRef = 'TX-' . $pdo->lastInsertId() . ' / ' . $receiptNo;
                break;

            case 'deposits.close':
                requirePermission('deposits.approve_close');
                $depositId = (int)($payload['deposit_id'] ?? 0);

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة غير موجودة.');
                }
                if ($deposit['principal_refunded'] == 1 || $deposit['status'] === 'completed') {
                    throw new Exception('تم إرجاع رأس المال وإنهاء الوديعة سابقاً.');
                }

                $accumulated = (float)$deposit['accumulated_profit'];
                if ($accumulated > 0) {
                    throw new Exception('لا يمكن إنهاء الوديعة قبل صرف كامل الأرباح المتراكمة المتبقية (' . formatMoney($accumulated, $deposit['currency']) . ').');
                }

                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("
                    UPDATE deposits 
                    SET status = 'completed', principal_refunded = 1 
                    WHERE id = ? AND principal_refunded = 0
                ");
                $upDep->execute([$depositId]);

                if ($upDep->rowCount() !== 1) {
                    throw new Exception('فشل إنهاء الوديعة لعدم تطابق الحالة.');
                }

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (investor_id, deposit_id, type, amount, date, receipt_no, note, created_at)
                    VALUES (?, ?, 'withdraw', ?, NOW(), ?, 'إرجاع رأس المال للوديعة المنتهية', NOW())
                ");
                $insTx->execute([$deposit['investor_id'], $depositId, $deposit['amount'], $receiptNo]);
                $execRef = 'REFUND-TX-' . $pdo->lastInsertId();
                break;

            case 'withdrawals.approve':
                requirePermission('withdrawals.approve');
                $reqId = (int)($payload['withdraw_request_id'] ?? 0);

                $wStmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ? FOR UPDATE");
                $wStmt->execute([$reqId]);
                $wReq = $wStmt->fetch();

                if (!$wReq || $wReq['status'] !== 'pending') {
                    throw new Exception('طلب السحب غير متاح للموافقة.');
                }

                $depositId = (int)$wReq['deposit_id'];
                $amount = (float)$wReq['withdraw_amount'];

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || (float)$deposit['accumulated_profit'] < $amount) {
                    throw new Exception('الرصيد المتاح حالياً غير كافٍ لتنفيذ طلب السحب.');
                }

                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("
                    UPDATE deposits 
                    SET accumulated_profit = accumulated_profit - ?, paid_profit = paid_profit + ?, last_withdrawal_date = NOW()
                    WHERE id = ? AND accumulated_profit >= ?
                ");
                $upDep->execute([$amount, $amount, $depositId, $amount]);

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (investor_id, deposit_id, type, amount, date, receipt_no, note, created_at)
                    VALUES (?, ?, 'withdraw', ?, NOW(), ?, ?, NOW())
                ");
                $insTx->execute([$deposit['investor_id'], $depositId, $amount, $receiptNo, 'صرف طلب سحب المستثمر رقم #' . $reqId]);
                $txId = (int)$pdo->lastInsertId();

                $upW = $pdo->prepare("UPDATE withdraw_requests SET status = 'approved', transaction_id = ? WHERE id = ?");
                $upW->execute([$txId, $reqId]);
                $execRef = 'WITHDRAW-REQ-' . $reqId . ' / TX-' . $txId;
                break;

            case 'profits.manual':
                requirePermission('profits.approve_manual');
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $amount = (float)($payload['amount'] ?? 0);
                $reason = trim($payload['reason'] ?? 'إضافة ربح يدوي');

                if ($amount <= 0) {
                    throw new Exception('مبلغ الربح اليدوي يجب أن يكون أكبر من صفر.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة غير موجودة.');
                }

                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ? WHERE id = ?");
                $upDep->execute([$amount, $depositId]);

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (investor_id, deposit_id, type, amount, date, receipt_no, note, created_at)
                    VALUES (?, ?, 'profit', ?, NOW(), ?, ?, NOW())
                ");
                $insTx->execute([$deposit['investor_id'], $depositId, $amount, $receiptNo, '[تعديل يدوي] ' . $reason]);
                $execRef = 'MANUAL-PROFIT-' . $pdo->lastInsertId();
                break;

            case 'deposits.financial_change':
                requirePermission('deposits.approve_financial_change');
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $newAmount = isset($payload['new_amount']) ? (float)$payload['new_amount'] : null;
                $newCurrency = isset($payload['new_currency']) ? trim($payload['new_currency']) : null;

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة غير موجودة.');
                }

                if ($newCurrency && $newCurrency !== $deposit['currency']) {
                    // Check if transactions exist
                    $txCount = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE deposit_id = ?");
                    $txCount->execute([$depositId]);
                    if ((int)$txCount->fetchColumn() > 0) {
                        throw new Exception('لا يمكن تغيير عملة وديعة مرتبطة بمعاملات مالية مسجلة سابقاً لتجنب التضارب المحاسبي. يجب أرشفة الوديعة وإنشاء وديعة جديدة.');
                    }
                }

                $updates = []; $params = [];
                if ($newAmount !== null && $newAmount > 0) {
                    $updates[] = 'amount = ?'; $params[] = $newAmount;
                }
                if ($newCurrency && in_array($newCurrency, ['IQD', 'USD'], true)) {
                    $updates[] = 'currency = ?'; $params[] = $newCurrency;
                }

                if (!empty($updates)) {
                    $params[] = $depositId;
                    $upStmt = $pdo->prepare("UPDATE deposits SET " . implode(', ', $updates) . " WHERE id = ?");
                    $upStmt->execute($params);
                }
                $execRef = 'FIN-CHANGE-' . $depositId;
                break;

            case 'rates.declaration':
                requirePermission('rates.approve_declaration');
                $month = trim($payload['month'] ?? '');
                $depositTypeId = (int)($payload['deposit_type_id'] ?? 0);
                $rate = (float)($payload['rate'] ?? 0);

                if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                    throw new Exception('صيغة الشهر غير صحيحة.');
                }
                if ($month > date('Y-m')) {
                    throw new Exception('لا يمكن إعلان نسب أرباح لشهر مستقبلي.');
                }

                // Insert into rate_declarations
                $insRate = $pdo->prepare("
                    INSERT INTO rate_declarations (month, deposit_type_id, declared_rate_monthly, status, created_by, approved_by, executed_at, created_at)
                    VALUES (?, ?, ?, 'executed', ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE declared_rate_monthly = VALUES(declared_rate_monthly), status = 'executed', approved_by = VALUES(approved_by), executed_at = NOW()
                ");
                $insRate->execute([$month, $depositTypeId, $rate, $req['requested_by'], $approverId]);

                // Calculate profits for active deposits of this type
                $depStmt = $pdo->prepare("
                    SELECT d.* FROM deposits d
                    WHERE d.deposit_type_id = ? AND d.status = 'active'
                ");
                $depStmt->execute([$depositTypeId]);
                $activeDeps = $depStmt->fetchAll();

                $cycleDate = $month . '-01';
                $calcCount = 0;

                foreach ($activeDeps as $dep) {
                    // Check UNIQUE(deposit_id, cycle_date)
                    $checkCycle = $pdo->prepare("SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ? AND cycle_date = ?");
                    $checkCycle->execute([$dep['id'], $cycleDate]);
                    if ((int)$checkCycle->fetchColumn() > 0) {
                        continue; // Cycle already calculated
                    }

                    $monthlyProfit = round((float)$dep['amount'] * ($rate / 100), 2);
                    if ($monthlyProfit <= 0) continue;

                    // Insert profit cycle
                    $insCycle = $pdo->prepare("
                        INSERT INTO profit_cycles (deposit_id, cycle_date, profit_amount, status, created_at)
                        VALUES (?, ?, ?, 'calculated', NOW())
                    ");
                    $insCycle->execute([$dep['id'], $cycleDate, $monthlyProfit]);

                    // Add to deposit accumulated_profit
                    $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ?, last_profit_date = ? WHERE id = ?")
                        ->execute([$monthlyProfit, $cycleDate, $dep['id']]);

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

        $pdo->commit();

        logActivity($pdo, 'EXECUTE_APPROVAL_SUCCESS', 'approval_requests', $requestId, null, [
            'operation' => $opType,
            'approver_id' => $approverId,
            'exec_ref' => $execRef
        ]);

        return ['success' => true, 'message' => 'تمت الموافقة والتنفيذ بنجاح.', 'reference' => $execRef];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Mark request as failed if rollback occurred
        try {
            $pdo->prepare("UPDATE approval_requests SET status = 'failed', rejection_reason = ? WHERE id = ?")
                ->execute([$e->getMessage(), $requestId]);
        } catch (Exception $ex) {
            error_log("Failed status update error: " . $ex->getMessage());
        }

        logActivity($pdo, 'EXECUTE_APPROVAL_FAILED', 'approval_requests', $requestId, null, [
            'error' => $e->getMessage()
        ]);

        return ['success' => false, 'message' => $e->getMessage()];
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

    $stmt = $pdo->prepare("
        UPDATE approval_requests 
        SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$rejecterId, trim($reason), $requestId]);

    $affected = $stmt->rowCount() > 0;
    if ($affected) {
        logActivity($pdo, 'REJECT_APPROVAL_REQUEST', 'approval_requests', $requestId, null, [
            'rejecter_id' => $rejecterId,
            'reason' => $reason
        ]);
    }

    return $affected;
}
