<?php
// public/deposit_add.php — Add New Deposit / Edit Existing Deposit
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireLogin();
$pdo = getPDO();

$editId = (int)($_GET['edit'] ?? 0);
$getInvestorId = (int)($_GET['investor_id'] ?? 0);

if ($editId) {
    requirePermission('deposits.update');
} else {
    requirePermission('deposits.create');
}

$investors = $pdo->query("SELECT id, full_name FROM investors ORDER BY full_name")->fetchAll();
$depositTypes = $pdo->query("SELECT * FROM deposit_types ORDER BY min_days")->fetchAll();

$deposit = null;
$form = [];

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ?");
    $stmt->execute([$editId]);
    $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deposit) {
        setFlash('danger', 'الوديعة غير موجودة.');
        header('Location: deposits.php');
        exit;
    }

    // Ownership & Supervisor Check
    $isOwner = ((int)($deposit['created_by'] ?? 0) === currentUserId());
    $isSupervisor = userCan('deposits.supervise_update') || currentRole() === 'admin';

    if (!$isOwner && !$isSupervisor) {
        setFlash('danger', 'عفواً، لا يملك الإذن بتعديل هذه الوديعة سوى منشئها الأول أو مسؤول النظام المشرف.');
        header('Location: deposits.php');
        exit;
    }

    $form = $deposit;
} elseif ($getInvestorId) {
    $form['investor_id'] = $getInvestorId;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $form['investor_id']             = (int)($_POST['investor_id']             ?? 0);
    $form['deposit_type_id']         = (int)($_POST['deposit_type_id']         ?? 0);
    $form['amount']                  = trim($_POST['amount']                   ?? '');
    $form['currency']                = in_array($_POST['currency'] ?? '', ['IQD','USD'], true) ? $_POST['currency'] : 'IQD';
    $form['start_date']              = trim($_POST['start_date']               ?? '');
    $payoutFrequency                 = (int)($_POST['profit_payout_frequency'] ?? 1);

    if (!$form['investor_id']) {
        $errors[] = 'يجب اختيار المستثمر.';
    }
    if (!$form['deposit_type_id']) {
        $errors[] = 'يجب اختيار نوع الوديعة.';
    }

    $amount = (float) $form['amount'];
    if ($amount <= 0 || $amount < 100) {
        $errors[] = 'المبلغ يجب أن يكون 100 أو أكثر.';
    }

    if (!$form['start_date']) {
        $errors[] = 'يجب تحديد تاريخ البداية.';
    }

    $selType = null;
    foreach ($depositTypes as $dt) {
        if ((int) $dt['id'] === $form['deposit_type_id']) {
            $selType = $dt;
            break;
        }
    }
    if (!$selType) {
        $errors[] = 'نوع الوديعة غير صالح.';
    }

    if ($payoutFrequency < 1 || $payoutFrequency > 12) {
        $errors[] = 'دورية صرف الأرباح يجب أن تكون بين شهر و 12 شهراً.';
    }

    $endDate = null;
    if ($selType && $form['start_date']) {
        $startDt = DateTimeImmutable::createFromFormat('Y-m-d', $form['start_date']);
        if (!$startDt) {
            $errors[] = 'تاريخ البداية غير صالح.';
        } else {
            $endDate = $startDt->modify('+' . $selType['max_days'] . ' days');
        }
    }

    if (empty($errors)) {
        $endDateStr = $endDate->format('Y-m-d');
        $startDateStr = $form['start_date'];

        try {
            if ($editId) {
                // ALL EDIT FIELDS ARE FINANCIAL/IMPACTING: MUST Create Approval Request (NO direct UPDATE path)
                $payload = [
                    'deposit_id' => $editId,
                    'new_investor_id' => $form['investor_id'],
                    'new_deposit_type_id' => $form['deposit_type_id'],
                    'new_amount' => $amount,
                    'new_currency' => $form['currency'],
                    'new_start_date' => $startDateStr,
                    'new_end_date' => $endDateStr,
                    'new_profit_payout_frequency' => $payoutFrequency
                ];

                $reqId = createApprovalRequest(
                    $pdo,
                    'deposits.financial_change',
                    'deposit',
                    $editId,
                    $payload,
                    $deposit
                );

                setFlash('info', 'تم تقديم طلب تعديل بيانات الوديعة رقم #' . $editId . ' (طلب موافقة رقم #' . $reqId . '). لن تتغير البيانات حتى الاعتماد.');
                header('Location: deposits.php');
                exit;

            } else {
                // CREATE NEW DEPOSIT (Initial creation transaction)
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO deposits (
                        investor_id, deposit_type_id, amount, currency, start_date, end_date,
                        profit_payout_frequency, accumulated_profit, paid_profit, principal_refunded, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 0, 'active', ?, NOW())
                ");
                $stmt->execute([
                    $form['investor_id'],
                    $form['deposit_type_id'],
                    $amount,
                    $form['currency'],
                    $startDateStr,
                    $endDateStr,
                    $payoutFrequency,
                    currentUserId()
                ]);
                $depositId = (int)$pdo->lastInsertId();

                $receiptNo = generateReceiptNo($pdo);
                $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, date, note)
                    VALUES (?, ?, ?, 'deposit', 'credit', ?, ?, NOW(), ?)
                ")->execute([
                    $receiptNo,
                    $form['investor_id'],
                    $depositId,
                    $amount,
                    $form['currency'],
                    'إيداع جديد — وديعة ' . $selType['name_ar']
                ]);

                $pdo->commit();

                logActivity($pdo, 'CREATE_DEPOSIT', 'deposits', $depositId, null, [
                    'investor_id' => $form['investor_id'],
                    'type' => $selType['code'],
                    'amount' => $amount,
                    'currency' => $form['currency'],
                    'start_date' => $startDateStr,
                    'end_date' => $endDateStr,
                    'receipt_no' => $receiptNo,
                ]);

                // --- Notifications ---
                // Telegram
                $invStmt = $pdo->prepare("SELECT full_name FROM investors WHERE id = ?");
                $invStmt->execute([$form['investor_id']]);
                $invName = $invStmt->fetchColumn() ?: 'غير معروف';

                $username = currentUsername() ?: 'النظام';
                $msg = "🔔 <b>تم إضافة وديعة جديدة (مباشرة)</b>\n";
                $msg .= "👤 المستثمر: <b>$invName</b>\n";
                $msg .= "💰 المبلغ: <code>" . number_format($amount, 2) . " {$form['currency']}</code>\n";
                $msg .= "📊 النوع: {$selType['name_ar']}\n";
                $msg .= "📅 البداية: $startDateStr\n";
                $msg .= "🧑‍💻 الموظف: $username";
                sendTelegramAlert($msg);

                // Investor In-App
                notifyInvestor($pdo, (int)$form['investor_id'], "وديعة جديدة 💰", "تم تفعيل وديعتك الجديدة بنجاح (الإيصال: $receiptNo).");
                // ---------------------

                setFlash('success', "تمت إضافة الوديعة بنجاح! رقم الإيصال: $receiptNo");
                header('Location: deposits.php');
                exit;
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = getSafeErrorMessage($e, 'حدث خطأ أثناء حفظ الوديعة.');
        }
    }
}

$pageTitle = $editId ? 'تعديل وديعة' : 'إضافة وديعة';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-<?= $editId ? 'pencil-square' : 'plus-circle' ?> me-2"></i><?= $editId ? 'تعديل بيانات وديعة' : 'إضافة وديعة جديدة' ?></h1>
                </div>
                <a href="deposits.php" class="btn btn-outline-gold">
                    <i class="bi bi-arrow-right me-1"></i> عودة للودائع
                </a>
            </div>

            <?php if ($errors): ?>
                <div class="alert flash-danger border mb-3" style="border-radius:8px">
                    <ul class="mb-0 pe-3">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="form-card">
                        <form method="post" action="" id="depositForm">
                            <?= csrfField() ?>

                            <div class="row g-3">
                                <!-- Investor -->
                                <div class="col-md-6">
                                    <label class="form-label">المستثمر <span class="text-danger">*</span></label>
                                    <select name="investor_id" class="form-select" required>
                                        <option value="">— اختر المستثمر —</option>
                                        <?php foreach ($investors as $inv): ?>
                                            <option value="<?= $inv['id'] ?>" <?= (int) ($form['investor_id'] ?? 0) === (int) $inv['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($inv['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Deposit Type -->
                                <div class="col-md-6">
                                    <label class="form-label">نوع الوديعة <span class="text-danger">*</span></label>
                                    <select name="deposit_type_id" id="depositTypeSelect" class="form-select" required>
                                        <option value="">— اختر النوع —</option>
                                        <?php foreach ($depositTypes as $dt): ?>
                                            <option value="<?= $dt['id'] ?>" data-code="<?= $dt['code'] ?>" data-locked="<?= $dt['is_locked'] ?? 0 ?>"
                                                <?= (int) ($form['deposit_type_id'] ?? 0) === (int) $dt['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dt['name_ar']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Amount + Currency -->
                                <div class="col-md-4">
                                    <label class="form-label">المبلغ <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" min="1" step="0.01"
                                        value="<?= htmlspecialchars($form['amount'] ?? '') ?>"
                                        placeholder="0" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">العملة <span class="text-danger">*</span></label>
                                    <select name="currency" class="form-select">
                                        <option value="IQD" <?= ($form['currency'] ?? 'IQD') === 'IQD' ? 'selected' : '' ?>>د.ع دينار عراقي</option>
                                        <option value="USD" <?= ($form['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>$ دولار أمريكي</option>
                                    </select>
                                </div>

                                <!-- Start Date -->
                                <div class="col-md-6">
                                    <label class="form-label">تاريخ البداية <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" class="form-control"
                                        value="<?= htmlspecialchars($form['start_date'] ?? date('Y-m-d')) ?>" required>
                                </div>

                                <!-- Payout Frequency -->
                                <div class="col-md-6">
                                    <label class="form-label">دورية سحب الأرباح التراكمية <span class="text-danger">*</span></label>
                                    <select name="profit_payout_frequency" id="profitPayoutFrequency" class="form-select" required>
                                        <option value="1" <?= (int)($form['profit_payout_frequency'] ?? 1) === 1 ? 'selected' : '' ?>>كل شهر</option>
                                        <option value="2" <?= (int)($form['profit_payout_frequency'] ?? 1) === 2 ? 'selected' : '' ?>>كل شهرين</option>
                                        <option value="3" <?= (int)($form['profit_payout_frequency'] ?? 1) === 3 ? 'selected' : '' ?>>كل 3 أشهر</option>
                                        <option value="6" <?= (int)($form['profit_payout_frequency'] ?? 1) === 6 ? 'selected' : '' ?>>كل 6 أشهر</option>
                                        <option value="12" <?= (int)($form['profit_payout_frequency'] ?? 1) === 12 ? 'selected' : '' ?>>كل سنة</option>
                                        <option value="24" <?= (int)($form['profit_payout_frequency'] ?? 1) === 24 ? 'selected' : '' ?>>سنتان (مقفلة)</option>
                                        <option value="36" <?= (int)($form['profit_payout_frequency'] ?? 1) === 36 ? 'selected' : '' ?>>3 سنوات (مقفلة)</option>
                                        <option value="60" <?= (int)($form['profit_payout_frequency'] ?? 1) === 60 ? 'selected' : '' ?>>5 سنوات (مقفلة)</option>
                                    </select>
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="deposits.php" class="btn btn-outline-gold">إلغاء</a>
                                <button type="submit" class="btn btn-gold px-4">
                                    <i class="bi bi-save me-1"></i> <?= $editId ? 'إرسال طلب التعديل للموافقة' : 'حفظ الوديعة' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('depositTypeSelect');
    const freqSelect = document.getElementById('profitPayoutFrequency');
    
    function updateFrequency() {
        const selected = typeSelect.options[typeSelect.selectedIndex];
        if (!selected || selected.value === '') {
            freqSelect.disabled = false;
            return;
        }
        
        const isLocked = selected.getAttribute('data-locked') === '1';
        const code = selected.getAttribute('data-code');
        
        // Hide locked options for normal deposits
        Array.from(freqSelect.options).forEach(opt => {
            const v = parseInt(opt.value);
            if (v >= 24) {
                if (isLocked) {
                    opt.hidden = false;
                    opt.disabled = false;
                } else {
                    opt.hidden = true;
                    opt.disabled = true;
                }
            }
        });
        
        if (isLocked) {
            let months = 12;
            if (code === 'L1Y' || code === 'asaify_start_1y') months = 12;
            if (code === 'L2Y' || code === 'asaify_advance_2y') months = 24;
            if (code === 'L3Y' || code === 'asaify_prestige_3y') months = 36;
            if (code === 'L5Y' || code === 'asaify_signature_5y') months = 60;
            
            freqSelect.value = months;
            freqSelect.disabled = true;
            
            let hiddenInput = document.getElementById('hidden_profit_payout_frequency');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'profit_payout_frequency';
                hiddenInput.id = 'hidden_profit_payout_frequency';
                freqSelect.parentNode.appendChild(hiddenInput);
            }
            hiddenInput.value = months;
        } else {
            freqSelect.disabled = false;
            if (parseInt(freqSelect.value) >= 24) {
                freqSelect.value = "1";
            }
            let hiddenInput = document.getElementById('hidden_profit_payout_frequency');
            if (hiddenInput) {
                hiddenInput.remove();
            }
        }
    }
    
    typeSelect.addEventListener('change', updateFrequency);
    updateFrequency(); 
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>