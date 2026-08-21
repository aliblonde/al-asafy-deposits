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
        // Handle race condition duplicate key
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
        // Lock request row
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

        // Execute financial logic based on operation type
        switch ($opType) {

            case 'profits.payout':
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

                if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                    throw new Exception('الوديعة غير نشطة أو غير موجودة.');
                }

                $accumulated = (float)$deposit['accumulated_profit'];
                if ($amount > $accumulated) {
                    throw new Exception('المبلغ المطلوب (' . formatMoney($amount, $deposit['currency']) . ') أكبر من رصيد الأرباح المتراكمة المتاح (' . formatMoney($accumulated, $deposit['currency']) . ').');
                }

                // STRICT: Payout currency is ALWAYS deposit currency
                $payoutCurrency = $deposit['currency'];
                $receiptNo = generateReceiptNo($pdo);

                // Update deposit balances atomically
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

                // Insert transaction record
                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                    VALUES (?, ?, ?, 'profit', ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $payoutCurrency, $note]);
                $txId = (int)$pdo->lastInsertId();
                $execRef = 'TX-' . $txId . ' / ' . $receiptNo;
                break;

            case 'deposits.close':
                $depositId = (int)($payload['deposit_id'] ?? 0);

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة غير موجودة.');
                }

                if ($deposit['end_date'] > date('Y-m-d')) {
                    throw new Exception('لا يمكن إنهاء الوديعة قبل حلول تاريخ انتنائها المستحق (' . formatDate($deposit['end_date']) . ').');
                }

                if ((int)$deposit['principal_refunded'] === 1) {
                    throw new Exception('تم إرجاع رأس المال وإنهاء الوديعة سابقاً.');
                }

                $accumulated = (float)$deposit['accumulated_profit'];
                if ($accumulated > 0) {
                    throw new Exception('لا يمكن إنهاء الوديعة قبل صرف كامل الأرباح المتراكمة المتبقية (' . formatMoney($accumulated, $deposit['currency']) . ').');
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
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                    VALUES (?, ?, ?, 'withdraw', ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $deposit['amount'], $deposit['currency'], 'إرجاع رأس المال للوديعة المنتهية']);
                $txId = (int)$pdo->lastInsertId();
                $execRef = 'REFUND-TX-' . $txId . ' / ' . $receiptNo;
                break;

            case 'withdrawals.approve':
                $wReqId = (int)($payload['withdraw_request_id'] ?? 0);

                $wStmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ? FOR UPDATE");
                $wStmt->execute([$wReqId]);
                $wReq = $wStmt->fetch();

                if (!$wReq || $wReq['status'] !== 'pending') {
                    throw new Exception('طلب السحب غير متاح للموافقة أو تم البت فيه سابقاً.');
                }

                $depositId = (int)$wReq['deposit_id'];
                $amount = (float)$wReq['amount'];

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || (float)$deposit['accumulated_profit'] < $amount) {
                    throw new Exception('الرصيد المتاح حالياً بالوديعة غير كافٍ لتنفيذ طلب السحب.');
                }

                // Currency locked to deposit currency
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

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                    VALUES (?, ?, ?, 'withdraw', ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, 'صرف طلب سحب المستثمر رقم #' . $wReqId]);
                $txId = (int)$pdo->lastInsertId();

                $upW = $pdo->prepare("UPDATE withdraw_requests SET status = 'paid', staff_user_id = ?, decision_date = NOW(), transaction_id = ?, approval_request_id = ? WHERE id = ?");
                $upW->execute([$approverId, $txId, $requestId, $wReqId]);
                $execRef = 'WITHDRAW-REQ-' . $wReqId . ' / TX-' . $txId;
                break;

            case 'profits.manual':
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $amount = (float)($payload['amount'] ?? 0);
                $reason = trim($payload['reason'] ?? 'إضافة ربح يدوي');
                $month = trim($payload['month'] ?? date('Y-m'));

                if ($amount <= 0) {
                    throw new Exception('مبلغ الربح اليدوي يجب أن يكون أكبر من صفر.');
                }

                if (empty($reason)) {
                    throw new Exception('سبب الإضافة اليدوية إجباري.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new Exception('الوديعة غير موجودة.');
                }

                $receiptNo = generateReceiptNo($pdo);
                $currency = $deposit['currency'];

                $upDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ? WHERE id = ?");
                $upDep->execute([$amount, $depositId]);

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                    VALUES (?, ?, ?, 'profit', ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, '[تعديل يدوي - ' . $month . '] ' . $reason]);
                $execRef = 'MANUAL-PROFIT-' . $pdo->lastInsertId();
                break;

            case 'deposits.financial_change':
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
                        throw new Exception('لا يمكن تغيير عملة وديعة مرتبطة بمعاملات مالية مسجلة سابقاً لتجنب التضارب المحاسبي.');
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
                $month = trim($payload['month'] ?? '');
                $depositTypeId = (int)($payload['deposit_type_id'] ?? 0);
                $rate = (float)($payload['rate'] ?? 0);

                if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                    throw new Exception('صيغة الشهر غير صحيحة.');
                }
                if ($month > date('Y-m')) {
                    throw new Exception('لا يمكن إعلان نسب أرباح لشهر مستقبلي.');
                }

                // Check rate bounds
                $typeStmt = $pdo->prepare("SELECT * FROM deposit_types WHERE id = ?");
                $typeStmt->execute([$depositTypeId]);
                $dType = $typeStmt->fetch();

                if (!$dType) {
                    throw new Exception('نوع الوديعة غير صالحة.');
                }

                $minRate = (float)$dType['min_rate'] * 100;
                $maxRate = (float)$dType['max_rate'] * 100;
                if ($rate < $minRate || $rate > $maxRate) {
                    throw new Exception("النسبة المعلنة ($rate%) خارج النطاق المسموح ($minRate% - $maxRate%).");
                }

                // Check if already executed for this month and deposit type
                $chkRate = $pdo->prepare("SELECT status FROM rate_declarations WHERE month = ? AND deposit_type_id = ?");
                $chkRate->execute([$month, $depositTypeId]);
                $existingStatus = $chkRate->fetchColumn();

                if ($existingStatus === 'executed') {
                    throw new Exception("تم إعلان وتنفيذ نسب الأرباح لشهر $month لنوع الوديعة مسبقاً. التعديل يتطلب طلب تصحيح جديد.");
                }

                // Insert into rate_declarations
                $insRate = $pdo->prepare("
                    INSERT INTO rate_declarations (month, deposit_type_id, declared_rate_monthly, status, created_by, approved_by, executed_at, created_at)
                    VALUES (?, ?, ?, 'executed', ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE declared_rate_monthly = VALUES(declared_rate_monthly), status = 'executed', approved_by = VALUES(approved_by), executed_at = NOW()
                ");
                $insRate->execute([$month, $depositTypeId, $rate, $req['requested_by'], $approverId]);

                // Also update monthly_rates for legacy queries
                $insMr = $pdo->prepare("
                    INSERT INTO monthly_rates (month, deposit_type_id, rate_percent)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE rate_percent = VALUES(rate_percent)
                ");
                $insMr->execute([$month, $depositTypeId, ($rate / 100)]);

                // Calculate profits for active deposits of this type whose anniversary is due
                $depStmt = $pdo->prepare("SELECT d.* FROM deposits d WHERE d.deposit_type_id = ? AND d.status = 'active'");
                $depStmt->execute([$depositTypeId]);
                $activeDeps = $depStmt->fetchAll();

                $cycleDate = date('Y-m-t', strtotime($month . '-01'));
                $calcCount = 0;

                foreach ($activeDeps as $dep) {
                    // Idempotency check: UNIQUE(deposit_id, cycle_date)
                    $checkCycle = $pdo->prepare("SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ? AND cycle_date = ?");
                    $checkCycle->execute([$dep['id'], $cycleDate]);
                    if ((int)$checkCycle->fetchColumn() > 0) {
                        continue;
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
 * Executed inside DB Transaction with FOR UPDATE lock and permission validation.
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

        // Verify rejecter has permission for this operation
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

        // If linked to a withdraw_request, update withdraw_request status
        if ($req['operation_type'] === 'withdrawals.approve') {
            $payload = json_decode($req['payload_json'], true) ?: [];
            $wReqId = (int)($payload['withdraw_request_id'] ?? 0);
            if ($wReqId > 0) {
                $pdo->prepare("UPDATE withdraw_requests SET status = 'rejected', staff_user_id = ?, decision_date = NOW(), note = ? WHERE id = ?")
                    ->execute([$rejecterId, trim($reason), $wReqId]);
            }
        }

        logActivity($pdo, 'REJECT_APPROVAL_REQUEST', 'approval_requests', $requestId, null, [
            'rejecter_id' => $rejecterId,
            'reason' => $reason
        ]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
