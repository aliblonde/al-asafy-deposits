<?php
// config/approval.php — Centralized Financial Approval Engine & Workflow Handler
// Sections 1,2,7,9,12: Atomicity, Exception Types, Idempotency, Schema Version, Currency

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/logger.php';

// ═══════════════════════════════════════════
// Custom Exception Types (Section 1)
// ═══════════════════════════════════════════

/** Authorization failure — request stays pending, no state change */
class AuthorizationException extends RuntimeException {}

/** Business rule violation — request can be marked failed */
class BusinessRuleException extends RuntimeException {}

/** Technical/transient error — request stays pending for retry */
class TechnicalExecutionException extends RuntimeException {}

// ═══════════════════════════════════════════
// Schema Version Guard (Section 9)
// ═══════════════════════════════════════════

const REQUIRED_SCHEMA_VERSION = 7;

function getSchemaVersion(PDO $pdo): int
{
    try {
        $chk = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'");
        if ((int)$chk->fetchColumn() === 0) {
            return 0;
        }
        $stmt = $pdo->query("SELECT MAX(version) FROM schema_migrations");
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function requireMinSchemaVersion(PDO $pdo): void
{
    $ver = getSchemaVersion($pdo);
    if ($ver < REQUIRED_SCHEMA_VERSION) {
        throw new TechnicalExecutionException(
            'النظام بحاجة لتحديث قاعدة البيانات (الحد الأدنى المطلوب: v' . REQUIRED_SCHEMA_VERSION . '، الحالي: v' . $ver . '). يرجى التواصل مع مسؤول النظام.'
        );
    }
}

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
 * Section 7: Only blocks on status='pending'.
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

    // Only return existing if PENDING (Section 7)
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

        // Notify Admins via Telegram
        $username = currentUsername() ?: 'النظام';
        $operationName = function_exists('arabicTxType') ? arabicTxType($operationType) : $operationType;
        if ($operationName === $operationType) {
            $operationName = match($operationType) {
                'deposits.financial_change' => 'تعديل بيانات وديعة',
                'deposits.close' => 'كسر / إغلاق وديعة',
                'withdrawals.approve' => 'طلب سحب رصيد',
                'profits.declare' => 'إعلان نسب الأرباح',
                'profits.run' => 'التشغيل الآلي للأرباح',
                'deposits.manual_profit' => 'إضافة ربح يدوي',
                default => $operationType
            };
        }
        
        $msg = "🔔 <b>طلب اعتماد مالي جديد!</b>\n";
        $msg .= "الطلب: <b>$operationName</b>\n";
        $msg .= "رقم الكيان: <code>$entityId</code>\n";
        $msg .= "بواسطة: $username\n\n";
        $msg .= "يرجى الدخول للنظام لمراجعته.";
        sendTelegramAlert($msg);

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
 * Pre-check: verify the approver has permission for this request's operation type.
 * Section 2: Call this BEFORE executeApprovalRequest to prevent state corruption.
 * Returns the request row or throws AuthorizationException.
 */
function preCheckApprovalPermission(PDO $pdo, int $requestId, int $approverId): array
{
    $stmt = $pdo->prepare("SELECT * FROM approval_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) {
        throw new BusinessRuleException('طلب الموافقة غير موجود.');
    }

    if ($req['status'] !== 'pending') {
        throw new BusinessRuleException('طلب الموافقة ليس في حالة معلقة. الحالة الحالية: ' . $req['status']);
    }

    $requiredPerm = getRequiredApprovalPermission($req['operation_type']);
    if (!userCan($requiredPerm, $approverId)) {
        throw new AuthorizationException(
            "ليس لديك الصلاحية المطلوبة ($requiredPerm) للموافقة على هذه العملية."
        );
    }

    return $req;
}

/**
 * Approve and atomically execute a pending request in a single DB Transaction.
 * Section 1: logActivity INSIDE transaction, before commit.
 * Section 1: Typed exceptions control post-failure behavior.
 */
function executeApprovalRequest(PDO $pdo, int $requestId, int $approverId): array
{
    // Section 9: Schema version guard
    requireMinSchemaVersion($pdo);

    $pdo->beginTransaction();

    try {
        // Lock request row FOR UPDATE
        $stmt = $pdo->prepare("SELECT * FROM approval_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new BusinessRuleException('طلب الموافقة غير موجود.');
        }

        if ($req['status'] !== 'pending') {
            throw new BusinessRuleException('طلب الموافقة ليس في حالة معلقة. الحالة الحالية: ' . $req['status']);
        }

        $opType = $req['operation_type'];
        $requiredPerm = getRequiredApprovalPermission($opType);

        if (!userCan($requiredPerm, $approverId)) {
            throw new AuthorizationException("ليس لديك الصلاحية المطلوبة ($requiredPerm) للموافقة على هذه العملية.");
        }

        $payload = json_decode($req['payload_json'], true) ?: [];
        $execRef = '';

        switch ($opType) {

            case 'profits.payout':
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $amount = (float)($payload['amount'] ?? 0);
                $note = trim($payload['note'] ?? 'صرف أرباح مقرة');

                if ($amount <= 0) {
                    throw new BusinessRuleException('مبلغ الصرف يجب أن يكون أكبر من صفر.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                    throw new BusinessRuleException('الوديعة غير نشطة أو غير موجودة.');
                }

                if (!isDepositProfitDue($deposit)) {
                    throw new BusinessRuleException('الأرباح لهذه الوديعة ليست مستحقة للصرف حالياً.');
                }

                $accumulated = (float)$deposit['accumulated_profit'];
                if ($amount > $accumulated) {
                    throw new BusinessRuleException('المبلغ المطلوب أكبر من رصيد الأرباح المتراكمة.');
                }

                $currency = $deposit['currency'];
                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("
                    UPDATE deposits SET accumulated_profit = accumulated_profit - ?, paid_profit = paid_profit + ?, last_withdrawal_date = NOW()
                    WHERE id = ? AND accumulated_profit >= ?
                ");
                $upDep->execute([$amount, $amount, $depositId, $amount]);

                if ($upDep->rowCount() !== 1) {
                    throw new TechnicalExecutionException('فشل تحديث رصيد الوديعة بسبب تعارض تزامن.');
                }

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'profit_payout', 'debit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, $requestId, $note]);
                $txId = (int)$pdo->lastInsertId();
                $execRef = 'TX-' . $txId . ' / ' . $receiptNo;
                break;

            case 'deposits.close':
                $depositId = (int)($payload['deposit_id'] ?? 0);

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) {
                    throw new BusinessRuleException('الوديعة غير موجودة.');
                }
                if ($deposit['end_date'] > date('Y-m-d')) {
                    throw new BusinessRuleException('لا يمكن إنهاء الوديعة قبل حلول تاريخ انتهائها.');
                }
                if ((int)$deposit['principal_refunded'] === 1) {
                    throw new BusinessRuleException('تم إرجاع رأس المال سابقاً.');
                }
                if ((float)$deposit['accumulated_profit'] > 0) {
                    throw new BusinessRuleException('لا يمكن إنهاء الوديعة قبل صرف كامل الأرباح المتراكمة.');
                }

                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("UPDATE deposits SET status = 'completed', principal_refunded = 1, last_withdrawal_date = NOW() WHERE id = ? AND principal_refunded = 0");
                $upDep->execute([$depositId]);

                if ($upDep->rowCount() !== 1) {
                    throw new TechnicalExecutionException('فشل إنهاء الوديعة لعدم تطابق الحالة.');
                }

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'principal_refund', 'debit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $deposit['amount'], $deposit['currency'], $requestId, 'إرجاع رأس المال للوديعة المنتهية']);
                $txId = (int)$pdo->lastInsertId();
                $execRef = 'REFUND-TX-' . $txId . ' / ' . $receiptNo;
                break;

            case 'withdrawals.approve':
                $wReqId = (int)($payload['withdraw_request_id'] ?? 0);

                $wStmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ? FOR UPDATE");
                $wStmt->execute([$wReqId]);
                $wReq = $wStmt->fetch();

                if (!$wReq || $wReq['status'] !== 'pending') {
                    throw new BusinessRuleException('طلب السحب غير متاح للموافقة.');
                }
                if ($wReq['transaction_id'] !== null) {
                    throw new BusinessRuleException('تم تنفيذ طلب السحب سابقاً.');
                }
                if (empty($wReq['approval_request_id']) || (int)$wReq['approval_request_id'] !== $requestId) {
                    throw new BusinessRuleException('طلب السحب غير مرتبط بطلب الموافقة الحالي.');
                }
                if ((int)$req['entity_id'] !== $wReqId) {
                    throw new BusinessRuleException('عدم تطابق بين طلب الموافقة وطلب السحب.');
                }

                $depositId = (int)($wReq['deposit_id'] ?? 0);
                if ($depositId <= 0) {
                    throw new BusinessRuleException('طلب السحب غير مرتبط بوديعة.');
                }

                $amount = (float)$wReq['amount'];
                if ($amount <= 0) {
                    throw new BusinessRuleException('مبلغ طلب السحب يجب أن يكون أكبر من صفر.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                    throw new BusinessRuleException('الوديعة المرتبطة غير متاحة.');
                }
                if ((int)$wReq['investor_id'] !== (int)$deposit['investor_id']) {
                    throw new BusinessRuleException('المستثمر في طلب السحب لا يطابق مالك الوديعة.');
                }
                if ((float)$deposit['accumulated_profit'] < $amount) {
                    throw new BusinessRuleException('الرصيد المتاح غير كافٍ لتنفيذ السحب.');
                }

                // Duplicate prevention
                $chkDupTx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE approval_request_id = ? AND deposit_id = ? AND type = 'withdrawal_payout'");
                $chkDupTx->execute([$requestId, $depositId]);
                if ((int)$chkDupTx->fetchColumn() > 0) {
                    throw new BusinessRuleException('يوجد قيد مالي مسجل سابقاً لهذا الطلب.');
                }

                $currency = $deposit['currency'];
                $receiptNo = generateReceiptNo($pdo);

                $upDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit - ?, paid_profit = paid_profit + ?, last_withdrawal_date = NOW() WHERE id = ? AND accumulated_profit >= ?");
                $upDep->execute([$amount, $amount, $depositId, $amount]);

                if ($upDep->rowCount() !== 1) {
                    throw new TechnicalExecutionException('فشل الخصم المالي بسبب تعارض تزامن.');
                }

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'withdrawal_payout', 'debit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, $requestId, 'صرف طلب سحب #' . $wReqId]);
                $txId = (int)$pdo->lastInsertId();

                $upW = $pdo->prepare("UPDATE withdraw_requests SET status = 'paid', staff_user_id = ?, decision_date = NOW(), transaction_id = ? WHERE id = ? AND status = 'pending'");
                $upW->execute([$approverId, $txId, $wReqId]);

                if ($upW->rowCount() !== 1) {
                    throw new TechnicalExecutionException('فشل تحديث حالة طلب السحب.');
                }

                $execRef = 'WITHDRAW-REQ-' . $wReqId . ' / TX-' . $txId;
                break;

            case 'profits.manual':
                $depositId = (int)($payload['deposit_id'] ?? 0);
                $amount = (float)($payload['amount'] ?? 0);
                $reason = trim($payload['reason'] ?? '');
                $month = trim($payload['month'] ?? date('Y-m'));

                if ($amount <= 0) throw new BusinessRuleException('مبلغ الربح اليدوي يجب أن يكون أكبر من صفر.');
                if (empty($reason)) throw new BusinessRuleException('سبب الإضافة اليدوية إجباري.');
                if (!preg_match('/^\d{4}-\d{2}$/', $month) || $month > date('Y-m')) {
                    throw new BusinessRuleException('صيغة الشهر غير صالحة أو مستقبلي.');
                }

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit || !in_array($deposit['status'], ['active', 'completed'], true)) {
                    throw new BusinessRuleException('الوديعة غير متاحة لربح يدوي.');
                }

                $chkMpa = $pdo->prepare("SELECT COUNT(*) FROM manual_profit_adjustments WHERE approval_request_id = ?");
                $chkMpa->execute([$requestId]);
                if ((int)$chkMpa->fetchColumn() > 0) {
                    throw new BusinessRuleException('تم تنفيذ هذا التعديل سابقاً.');
                }

                $currency = $deposit['currency'];
                $receiptNo = generateReceiptNo($pdo);

                $insMpa = $pdo->prepare("INSERT INTO manual_profit_adjustments (deposit_id, amount, currency, month, reason, approval_request_id, requested_by, approved_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $insMpa->execute([$depositId, $amount, $currency, $month, $reason, $requestId, $req['requested_by'], $approverId]);

                $upDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ? WHERE id = ?");
                $upDep->execute([$amount, $depositId]);

                if ($upDep->rowCount() !== 1) {
                    throw new TechnicalExecutionException('فشل تحديث رصيد الوديعة.');
                }

                $insTx = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                    VALUES (?, ?, ?, 'profit_accrual', 'credit', ?, ?, ?, NOW(), ?)
                ");
                $insTx->execute([$receiptNo, $deposit['investor_id'], $depositId, $amount, $currency, $requestId, '[تعديل يدوي - ' . $month . '] ' . $reason]);
                $execRef = 'MANUAL-PROFIT-' . $pdo->lastInsertId();
                break;

            case 'deposits.financial_change':
                $depositId = (int)($payload['deposit_id'] ?? 0);

                $depStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
                $depStmt->execute([$depositId]);
                $deposit = $depStmt->fetch();

                if (!$deposit) throw new BusinessRuleException('الوديعة غير موجودة.');

                $newInvestorId = isset($payload['new_investor_id']) ? (int)$payload['new_investor_id'] : (int)$deposit['investor_id'];
                $newDepositTypeId = isset($payload['new_deposit_type_id']) ? (int)$payload['new_deposit_type_id'] : (int)$deposit['deposit_type_id'];
                $newAmount = isset($payload['new_amount']) ? (float)$payload['new_amount'] : (float)$deposit['amount'];
                $newCurrency = isset($payload['new_currency']) ? trim($payload['new_currency']) : $deposit['currency'];
                $newStartDate = isset($payload['new_start_date']) ? trim($payload['new_start_date']) : $deposit['start_date'];
                $newPayoutFreq = isset($payload['new_profit_payout_frequency']) ? (int)$payload['new_profit_payout_frequency'] : (int)$deposit['profit_payout_frequency'];

                $invCheck = $pdo->prepare("SELECT COUNT(*) FROM investors WHERE id = ?");
                $invCheck->execute([$newInvestorId]);
                if ((int)$invCheck->fetchColumn() === 0) throw new BusinessRuleException('المستثمر الجديد غير موجود.');

                $typeCheck = $pdo->prepare("SELECT * FROM deposit_types WHERE id = ?");
                $typeCheck->execute([$newDepositTypeId]);
                $dType = $typeCheck->fetch();
                if (!$dType) throw new BusinessRuleException('نوع الوديعة غير صالح.');

                $startDt = DateTimeImmutable::createFromFormat('Y-m-d', $newStartDate);
                if (!$startDt) throw new BusinessRuleException('تاريخ البداية غير صالح.');
                $newEndDate = $startDt->modify('+' . $dType['max_days'] . ' days')->format('Y-m-d');

                if ($newAmount <= 0) throw new BusinessRuleException('المبلغ يجب أن يكون أكبر من صفر.');
                if (!in_array($newCurrency, ['IQD', 'USD'], true)) throw new BusinessRuleException('العملة غير صالحة.');

                // Check ALL financial history (Section 6)
                $hasFinHistory = false;
                $historyQueries = [
                    "SELECT COUNT(*) FROM transactions WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM withdraw_requests WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM manual_profit_adjustments WHERE deposit_id = ?",
                    "SELECT COUNT(*) FROM deposit_adjustments WHERE deposit_id = ?",
                ];
                foreach ($historyQueries as $sql) {
                    $hStmt = $pdo->prepare($sql);
                    $hStmt->execute([$depositId]);
                    if ((int)$hStmt->fetchColumn() > 0) { $hasFinHistory = true; break; }
                }

                // Section 12: Currency correction rules
                if ($newCurrency !== $deposit['currency']) {
                    if ($hasFinHistory) {
                        // Count non-initial-deposit transactions
                        $txCntStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE deposit_id = ? AND type != 'deposit'");
                        $txCntStmt->execute([$depositId]);
                        $nonDepositTxCount = (int)$txCntStmt->fetchColumn();

                        $hasCycles = $pdo->prepare("SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ?");
                        $hasCycles->execute([$depositId]);

                        $hasMpa = $pdo->prepare("SELECT COUNT(*) FROM manual_profit_adjustments WHERE deposit_id = ?");
                        $hasMpa->execute([$depositId]);

                        $hasAdj = $pdo->prepare("SELECT COUNT(*) FROM deposit_adjustments WHERE deposit_id = ?");
                        $hasAdj->execute([$depositId]);

                        $hasWr = $pdo->prepare("SELECT COUNT(*) FROM withdraw_requests WHERE deposit_id = ?");
                        $hasWr->execute([$depositId]);

                        if ($nonDepositTxCount > 0 || (int)$hasCycles->fetchColumn() > 0 ||
                            (int)$hasMpa->fetchColumn() > 0 || (int)$hasAdj->fetchColumn() > 0 ||
                            (int)$hasWr->fetchColumn() > 0 ||
                            (float)$deposit['accumulated_profit'] != 0 || (float)$deposit['paid_profit'] != 0) {
                            throw new BusinessRuleException('لا يمكن تغيير عملة وديعة مرتبطة بحركات مالية. مسموح فقط إذا لم توجد سوى قيد الإيداع الأولي.');
                        }

                        // Allowed: only initial deposit tx exists. Create reversal + restatement
                        $oldReceiptNo = generateReceiptNo($pdo);
                        $pdo->prepare("
                            INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                            VALUES (?, ?, ?, 'deposit_currency_reversal', 'debit', ?, ?, ?, NOW(), ?)
                        ")->execute([$oldReceiptNo, $deposit['investor_id'], $depositId, $deposit['amount'], $deposit['currency'], $requestId,
                            'عكس قيد الإيداع الأولي لتصحيح العملة من ' . $deposit['currency'] . ' إلى ' . $newCurrency]);

                        $newReceiptNo = generateReceiptNo($pdo);
                        $pdo->prepare("
                            INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                            VALUES (?, ?, ?, 'deposit_currency_restatement', 'credit', ?, ?, ?, NOW(), ?)
                        ")->execute([$newReceiptNo, $deposit['investor_id'], $depositId, $newAmount, $newCurrency, $requestId,
                            'إثبات قيد الإيداع بالعملة الصحيحة ' . $newCurrency]);
                    }
                }

                if ($hasFinHistory && $newCurrency === $deposit['currency']) {
                    if ($newInvestorId !== (int)$deposit['investor_id']) throw new BusinessRuleException('لا يمكن نقل الملكية بعد وجود حركات مالية.');
                    if ($newStartDate !== $deposit['start_date']) throw new BusinessRuleException('لا يمكن تغيير تاريخ البداية بعد وجود حركات مالية.');
                    if ($newDepositTypeId !== (int)$deposit['deposit_type_id']) throw new BusinessRuleException('لا يمكن تغيير نوع الوديعة بعد وجود حركات مالية.');

                    $cyclesCnt = $pdo->prepare("SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ?");
                    $cyclesCnt->execute([$depositId]);
                    $pendingWr = $pdo->prepare("SELECT COUNT(*) FROM withdraw_requests WHERE deposit_id = ? AND status = 'pending'");
                    $pendingWr->execute([$depositId]);

                    if ((int)$cyclesCnt->fetchColumn() > 0 || (int)$pendingWr->fetchColumn() > 0) {
                        if ($newEndDate !== $deposit['end_date']) throw new BusinessRuleException('لا يمكن تغيير تاريخ الانتهاء بعد وجود دورات أرباح أو سحوبات معلقة.');
                        if ($newPayoutFreq !== (int)$deposit['profit_payout_frequency']) throw new BusinessRuleException('لا يمكن تغيير دورية الأرباح.');
                    }
                }

                // Amount change → deposit_adjustments with direction
                $oldAmount = (float)$deposit['amount'];
                if ($newAmount !== $oldAmount) {
                    $diff = $newAmount - $oldAmount;
                    $direction = $diff > 0 ? 'increase' : 'decrease';

                    $insAdj = $pdo->prepare("INSERT INTO deposit_adjustments (deposit_id, old_amount, new_amount, difference, direction, currency, approval_request_id, requested_by, approved_by, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $insAdj->execute([$depositId, $oldAmount, $newAmount, $diff, $direction, $deposit['currency'], $requestId, $req['requested_by'], $approverId, 'تعديل قيمة الوديعة بناءً على طلب #' . $requestId]);

                    $receiptNo = generateReceiptNo($pdo);
                    $txDir = $diff > 0 ? 'credit' : 'debit';
                    $pdo->prepare("
                        INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                        VALUES (?, ?, ?, 'deposit_adjustment', ?, ?, ?, ?, NOW(), ?)
                    ")->execute([$receiptNo, $deposit['investor_id'], $depositId, $txDir, abs($diff), $deposit['currency'], $requestId,
                        ($direction === 'increase' ? 'زيادة' : 'تخفيض') . ' رأس المال']);
                }

                $upDep = $pdo->prepare("UPDATE deposits SET investor_id=?, deposit_type_id=?, amount=?, currency=?, start_date=?, end_date=?, profit_payout_frequency=? WHERE id=?");
                $upDep->execute([$newInvestorId, $newDepositTypeId, $newAmount, $newCurrency, $newStartDate, $newEndDate, $newPayoutFreq, $depositId]);

                $execRef = 'FIN-CHANGE-' . $depositId;
                break;

            case 'rates.declaration':
                $month = trim($payload['month'] ?? '');
                $depositTypeId = (int)($payload['deposit_type_id'] ?? 0);
                $rate = (float)($payload['rate'] ?? 0);

                if (!preg_match('/^\d{4}-\d{2}$/', $month)) throw new BusinessRuleException('صيغة الشهر غير صحيحة.');
                if ($month > date('Y-m')) throw new BusinessRuleException('لا يمكن إعلان نسب لشهر مستقبلي.');

                $typeStmt = $pdo->prepare("SELECT * FROM deposit_types WHERE id = ?");
                $typeStmt->execute([$depositTypeId]);
                $dType = $typeStmt->fetch();
                if (!$dType) throw new BusinessRuleException('نوع الوديعة غير صالح.');

                $minRate = (float)$dType['min_rate'] * 100;
                $maxRate = (float)$dType['max_rate'] * 100;
                if ($rate < $minRate || $rate > $maxRate) throw new BusinessRuleException("النسبة ($rate%) خارج النطاق ($minRate% - $maxRate%).");

                $chkRate = $pdo->prepare("SELECT status FROM rate_declarations WHERE month = ? AND deposit_type_id = ?");
                $chkRate->execute([$month, $depositTypeId]);
                $existingStatus = $chkRate->fetchColumn();
                if ($existingStatus === 'executed') throw new BusinessRuleException("تم إعلان نسب شهر $month مسبقاً.");

                $insRate = $pdo->prepare("INSERT INTO rate_declarations (month, deposit_type_id, declared_rate_monthly, status, created_by, approved_by, executed_at, created_at) VALUES (?, ?, ?, 'executed', ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE declared_rate_monthly = VALUES(declared_rate_monthly), status = 'executed', approved_by = VALUES(approved_by), executed_at = NOW()");
                $insRate->execute([$month, $depositTypeId, $rate, $req['requested_by'], $approverId]);

                $insMr = $pdo->prepare("INSERT INTO monthly_rates (month, deposit_type_id, rate_percent) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rate_percent = VALUES(rate_percent)");
                $insMr->execute([$month, $depositTypeId, ($rate / 100)]);

                $depStmt = $pdo->prepare("SELECT d.* FROM deposits d WHERE d.deposit_type_id = ? AND d.status = 'active'");
                $depStmt->execute([$depositTypeId]);
                $activeDeps = $depStmt->fetchAll();

                $calcCount = 0;
                foreach ($activeDeps as $dep) {
                    if (empty($dep['start_date'])) continue;
                    $nextProfitDt = calcNextProfitDate($dep);
                    if (!$nextProfitDt) continue;
                    if ($nextProfitDt->format('Y-m') !== $month) continue;
                    $actualCycleDate = $nextProfitDt->format('Y-m-d');
                    if ($actualCycleDate < $dep['start_date'] || $actualCycleDate > $dep['end_date']) continue;

                    $checkCycle = $pdo->prepare("SELECT COUNT(*) FROM profit_cycles WHERE deposit_id = ? AND cycle_date = ?");
                    $checkCycle->execute([$dep['id'], $actualCycleDate]);
                    if ((int)$checkCycle->fetchColumn() > 0) continue;

                    $monthlyProfit = round((float)$dep['amount'] * ($rate / 100), 2);
                    if ($monthlyProfit <= 0) continue;

                    $pdo->prepare("INSERT INTO profit_cycles (deposit_id, cycle_date, profit_amount, status, created_at) VALUES (?, ?, ?, 'calculated', NOW())")->execute([$dep['id'], $actualCycleDate, $monthlyProfit]);

                    $upDepAccum = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ?, last_profit_date = ? WHERE id = ?");
                    $upDepAccum->execute([$monthlyProfit, $actualCycleDate, $dep['id']]);

                    $accrualReceiptNo = generateReceiptNo($pdo);
                    $pdo->prepare("INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note) VALUES (?, ?, ?, 'profit_accrual', 'credit', ?, ?, ?, NOW(), ?)")
                        ->execute([$accrualReceiptNo, $dep['investor_id'], $dep['id'], $monthlyProfit, $dep['currency'], $requestId, 'استحقاق أرباح شهر ' . $month . ' بنسبة ' . $rate . '%']);

                    $calcCount++;
                }

                $execRef = "RATES-$month-TYPE-$depositTypeId ($calcCount deposits)";
                break;

            default:
                throw new BusinessRuleException('نوع العملية غير معروف: ' . htmlspecialchars($opType));
        }

        // Update approval request to executed — WHERE status='pending' prevents re-execution
        $upReq = $pdo->prepare("UPDATE approval_requests SET status = 'executed', approved_by = ?, approved_at = NOW(), executed_at = NOW(), execution_reference = ? WHERE id = ? AND status = 'pending'");
        $upReq->execute([$approverId, $execRef, $requestId]);

        if ($upReq->rowCount() !== 1) {
            throw new TechnicalExecutionException('فشل تحديث حالة طلب الموافقة — ربما تم تنفيذه بالتزامن.');
        }

        // Section 1 FIX: logActivity INSIDE transaction, BEFORE commit
        logActivity($pdo, 'EXECUTE_APPROVAL_SUCCESS', 'approval_requests', $requestId, null, [
            'operation' => $opType,
            'approver_id' => $approverId,
            'exec_ref' => $execRef
        ]);

        // --- Notifications ---
        sendNotification($pdo, $req['requested_by'], "تم الاعتماد ✅", "تمت الموافقة على طلب: " . arabicTxType($opType));
        
        // Notify Investor if applicable
        if ($req['entity_type'] === 'deposits') {
            if ($opType === 'create') {
                notifyInvestor($pdo, (int)$payload['investor_id'], "وديعة جديدة 💰", "تم تفعيل وديعتك الجديدة بنجاح.");
            } elseif ($opType === 'withdraw' || $opType === 'early_closure') {
                notifyInvestor($pdo, (int)$payload['investor_id'], "سحب/كسر وديعة", "تمت الموافقة على طلب إغلاق/سحب الوديعة.");
            } elseif ($opType === 'profit_payout') {
                notifyInvestor($pdo, (int)$payload['investor_id'], "صرف أرباح 💵", "تم إيداع الأرباح في حسابك بنجاح.");
            }
        }
        // ---------------------

        $pdo->commit();

        return ['success' => true, 'safe_message' => 'تمت الموافقة والتنفيذ بنجاح.', 'reference' => $execRef];

    } catch (AuthorizationException $e) {
        // Authorization failure: rollback, do NOT change request status
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'safe_message' => $e->getMessage(),
            'is_auth_error' => true
        ];

    } catch (BusinessRuleException $e) {
        // Business rule violation: rollback, then mark failed in separate transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errRef = 'BIZ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        error_log("[$errRef] Business Rule: " . $e->getMessage());

        // Best-effort: mark as failed in a NEW, separate transaction
        try {
            $pdo->beginTransaction();
            $failStmt = $pdo->prepare("UPDATE approval_requests SET status = 'failed', rejection_reason = ? WHERE id = ? AND status = 'pending'");
            $failStmt->execute(['[' . $errRef . '] ' . mb_substr($e->getMessage(), 0, 200), $requestId]);
            logActivity($pdo, 'EXECUTE_APPROVAL_FAILED', 'approval_requests', $requestId, null, ['error_ref' => $errRef]);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("[$errRef] Failed to mark request as failed: " . $ex->getMessage());
        }

        return [
            'success' => false,
            'safe_message' => $e->getMessage(),
            'error_reference' => $errRef
        ];

    } catch (Throwable $e) {
        // Technical/transient error: rollback, do NOT change request status (allow retry)
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errRef = 'ERR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        error_log("[$errRef] Technical Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        // Best-effort logging only (no status change)
        try {
            logActivity($pdo, 'EXECUTE_APPROVAL_FAILED', 'approval_requests', $requestId, null, ['error_ref' => $errRef]);
        } catch (Throwable $ex) {
            error_log("[$errRef] Failed to log error: " . $ex->getMessage());
        }

        return [
            'success' => false,
            'safe_message' => 'تعذر تنفيذ العملية. يرجى المحاولة لاحقاً أو مراجعة مسؤول النظام. (' . $errRef . ')',
            'error_reference' => $errRef
        ];
    }
}

/**
 * Reject an approval request with mandatory reason.
 * Section 2: Checks permission before modifying state.
 */
function rejectApprovalRequest(PDO $pdo, int $requestId, int $rejecterId, string $reason): bool
{
    if (trim($reason) === '') {
        throw new BusinessRuleException('سبب الرفض إجباري.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT * FROM approval_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) throw new BusinessRuleException('طلب الموافقة غير موجود.');
        if ($req['status'] !== 'pending') throw new BusinessRuleException('الطلب ليس في حالة معلقة.');

        $requiredPerm = getRequiredApprovalPermission($req['operation_type']);
        if (!userCan($requiredPerm, $rejecterId)) {
            throw new AuthorizationException("ليس لديك الصلاحية ($requiredPerm) لرفض هذا الطلب.");
        }

        $up = $pdo->prepare("UPDATE approval_requests SET status = 'rejected', approved_by = ?, rejected_by = ?, approved_at = NOW(), rejected_at = NOW(), rejection_reason = ? WHERE id = ? AND status = 'pending'");
        $up->execute([$rejecterId, $rejecterId, trim($reason), $requestId]);

        if ($up->rowCount() !== 1) throw new TechnicalExecutionException('فشل تحديث حالة الطلب.');

        if ($req['operation_type'] === 'withdrawals.approve') {
            $payload = json_decode($req['payload_json'], true) ?: [];
            $wReqId = (int)($payload['withdraw_request_id'] ?? 0);
            if ($wReqId > 0) {
                $pdo->prepare("UPDATE withdraw_requests SET status = 'rejected', staff_user_id = ?, decision_date = NOW(), note = ? WHERE id = ?")->execute([$rejecterId, trim($reason), $wReqId]);
            }
        }

        logActivity($pdo, 'REJECT_APPROVAL_REQUEST', 'approval_requests', $requestId, null, [
            'rejecter_id' => $rejecterId,
            'reason' => $reason
        ]);

        // --- Notifications ---
        sendNotification($pdo, $req['requested_by'], "تم الرفض ❌", "تم رفض طلب: " . arabicTxType($req['operation_type']) . " - السبب: $reason");
        // ---------------------

        $pdo->commit();
        return true;

    } catch (AuthorizationException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e; // Re-throw so caller can handle 403
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
