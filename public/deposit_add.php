<?php
// public/deposit_add.php â€” Add New Deposit / Edit Existing Deposit
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
        setFlash('danger', 'ط§ظ„ظˆط¯ظٹط¹ط© ط؛ظٹط± ظ…ظˆط¬ظˆط¯ط©.');
        header('Location: deposits.php');
        exit;
    }

    // Ownership & Supervisor Check
    $isOwner = ((int)($deposit['created_by'] ?? 0) === currentUserId());
    $isSupervisor = userCan('deposits.supervise_update') || currentRole() === 'admin';

    if (!$isOwner && !$isSupervisor) {
        setFlash('danger', 'ط¹ظپظˆط§ظ‹طŒ ظ„ط§ ظٹظ…ظ„ظƒ ط§ظ„ط¥ط°ظ† ط¨طھط¹ط¯ظٹظ„ ظ‡ط°ظ‡ ط§ظ„ظˆط¯ظٹط¹ط© ط³ظˆظ‰ ظ…ظ†ط´ط¦ظ‡ط§ ط§ظ„ط£ظˆظ„ ط£ظˆ ظ…ط³ط¤ظˆظ„ ط§ظ„ظ†ط¸ط§ظ… ط§ظ„ظ…ط´ط±ظپ.');
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
        $errors[] = 'ظٹط¬ط¨ ط§ط®طھظٹط§ط± ط§ظ„ظ…ط³طھط«ظ…ط±.';
    }
    if (!$form['deposit_type_id']) {
        $errors[] = 'ظٹط¬ط¨ ط§ط®طھظٹط§ط± ظ†ظˆط¹ ط§ظ„ظˆط¯ظٹط¹ط©.';
    }

    $amount = (float) $form['amount'];
    if ($amount <= 0 || $amount < 100) {
        $errors[] = 'ط§ظ„ظ…ط¨ظ„ط؛ ظٹط¬ط¨ ط£ظ† ظٹظƒظˆظ† 100 ط£ظˆ ط£ظƒط«ط±.';
    }

    if (!$form['start_date']) {
        $errors[] = 'ظٹط¬ط¨ طھط­ط¯ظٹط¯ طھط§ط±ظٹط® ط§ظ„ط¨ط¯ط§ظٹط©.';
    }

    $selType = null;
    foreach ($depositTypes as $dt) {
        if ((int) $dt['id'] === $form['deposit_type_id']) {
            $selType = $dt;
            break;
        }
    }
    if (!$selType) {
        $errors[] = 'ظ†ظˆط¹ ط§ظ„ظˆط¯ظٹط¹ط© ط؛ظٹط± طµط§ظ„ط­.';
    }

    if ($payoutFrequency < 1 || $payoutFrequency > 12) {
        $errors[] = 'ط¯ظˆط±ظٹط© طµط±ظپ ط§ظ„ط£ط±ط¨ط§ط­ ظٹط¬ط¨ ط£ظ† طھظƒظˆظ† ط¨ظٹظ† ط´ظ‡ط± ظˆ 12 ط´ظ‡ط±ط§ظ‹.';
    }

    $endDate = null;
    if ($selType && $form['start_date']) {
        $startDt = DateTimeImmutable::createFromFormat('Y-m-d', $form['start_date']);
        if (!$startDt) {
            $errors[] = 'طھط§ط±ظٹط® ط§ظ„ط¨ط¯ط§ظٹط© ط؛ظٹط± طµط§ظ„ط­.';
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

                setFlash('info', 'طھظ… طھظ‚ط¯ظٹظ… ط·ظ„ط¨ طھط¹ط¯ظٹظ„ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظˆط¯ظٹط¹ط© ط±ظ‚ظ… #' . $editId . ' (ط·ظ„ط¨ ظ…ظˆط§ظپظ‚ط© ط±ظ‚ظ… #' . $reqId . '). ظ„ظ† طھطھط؛ظٹط± ط§ظ„ط¨ظٹط§ظ†ط§طھ ط­طھظ‰ ط§ظ„ط§ط¹طھظ…ط§ط¯.');
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
                    'ط¥ظٹط¯ط§ط¹ ط¬ط¯ظٹط¯ â€” ظˆط¯ظٹط¹ط© ' . $selType['name_ar']
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

                setFlash('success', "طھظ…طھ ط¥ط¶ط§ظپط© ط§ظ„ظˆط¯ظٹط¹ط© ط¨ظ†ط¬ط§ط­! ط±ظ‚ظ… ط§ظ„ط¥ظٹطµط§ظ„: $receiptNo");
                header('Location: deposits.php');
                exit;
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $editId ? 'طھط¹ط¯ظٹظ„ ظˆط¯ظٹط¹ط©' : 'ط¥ط¶ط§ظپط© ظˆط¯ظٹط¹ط©';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-<?= $editId ? 'pencil-square' : 'plus-circle' ?> me-2"></i><?= $editId ? 'طھط¹ط¯ظٹظ„ ط¨ظٹط§ظ†ط§طھ ظˆط¯ظٹط¹ط©' : 'ط¥ط¶ط§ظپط© ظˆط¯ظٹط¹ط© ط¬ط¯ظٹط¯ط©' ?></h1>
                </div>
                <a href="deposits.php" class="btn btn-outline-gold">
                    <i class="bi bi-arrow-right me-1"></i> ط¹ظˆط¯ط© ظ„ظ„ظˆط¯ط§ط¦ط¹
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
                                    <label class="form-label">ط§ظ„ظ…ط³طھط«ظ…ط± <span class="text-danger">*</span></label>
                                    <select name="investor_id" class="form-select" required>
                                        <option value="">â€” ط§ط®طھط± ط§ظ„ظ…ط³طھط«ظ…ط± â€”</option>
                                        <?php foreach ($investors as $inv): ?>
                                            <option value="<?= $inv['id'] ?>" <?= (int) ($form['investor_id'] ?? 0) === (int) $inv['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($inv['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Deposit Type -->
                                <div class="col-md-6">
                                    <label class="form-label">ظ†ظˆط¹ ط§ظ„ظˆط¯ظٹط¹ط© <span class="text-danger">*</span></label>
                                    <select name="deposit_type_id" id="depositTypeSelect" class="form-select" required>
                                        <option value="">â€” ط§ط®طھط± ط§ظ„ظ†ظˆط¹ â€”</option>
                                        <?php foreach ($depositTypes as $dt): ?>
                                            <option value="<?= $dt['id'] ?>" data-code="<?= $dt['code'] ?>"
                                                <?= (int) ($form['deposit_type_id'] ?? 0) === (int) $dt['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dt['name_ar']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Amount + Currency -->
                                <div class="col-md-4">
                                    <label class="form-label">ط§ظ„ظ…ط¨ظ„ط؛ <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" min="1" step="0.01"
                                        value="<?= htmlspecialchars($form['amount'] ?? '') ?>"
                                        placeholder="0" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">ط§ظ„ط¹ظ…ظ„ط© <span class="text-danger">*</span></label>
                                    <select name="currency" class="form-select">
                                        <option value="IQD" <?= ($form['currency'] ?? 'IQD') === 'IQD' ? 'selected' : '' ?>>ط¯.ط¹ ط¯ظٹظ†ط§ط± ط¹ط±ط§ظ‚ظٹ</option>
                                        <option value="USD" <?= ($form['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>$ ط¯ظˆظ„ط§ط± ط£ظ…ط±ظٹظƒظٹ</option>
                                    </select>
                                </div>

                                <!-- Start Date -->
                                <div class="col-md-6">
                                    <label class="form-label">طھط§ط±ظٹط® ط§ظ„ط¨ط¯ط§ظٹط© <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" class="form-control"
                                        value="<?= htmlspecialchars($form['start_date'] ?? date('Y-m-d')) ?>" required>
                                </div>

                                <!-- Payout Frequency -->
                                <div class="col-md-6">
                                    <label class="form-label">ط¯ظˆط±ظٹط© ط³ط­ط¨ ط§ظ„ط£ط±ط¨ط§ط­ ط§ظ„طھط±ط§ظƒظ…ظٹط© <span class="text-danger">*</span></label>
                                    <select name="profit_payout_frequency" class="form-select" required>
                                        <option value="1" <?= (int)($form['profit_payout_frequency'] ?? 1) === 1 ? 'selected' : '' ?>>ظƒظ„ ط´ظ‡ط±</option>
                                        <option value="2" <?= (int)($form['profit_payout_frequency'] ?? 1) === 2 ? 'selected' : '' ?>>ظƒظ„ ط´ظ‡ط±ظٹظ†</option>
                                        <option value="3" <?= (int)($form['profit_payout_frequency'] ?? 1) === 3 ? 'selected' : '' ?>>ظƒظ„ 3 ط£ط´ظ‡ط±</option>
                                        <option value="6" <?= (int)($form['profit_payout_frequency'] ?? 1) === 6 ? 'selected' : '' ?>>ظƒظ„ 6 ط£ط´ظ‡ط±</option>
                                        <option value="12" <?= (int)($form['profit_payout_frequency'] ?? 1) === 12 ? 'selected' : '' ?>>ظƒظ„ ط³ظ†ط©</option>
                                    </select>
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="deposits.php" class="btn btn-outline-gold">ط¥ظ„ط؛ط§ط،</a>
                                <button type="submit" class="btn btn-gold px-4">
                                    <i class="bi bi-save me-1"></i> <?= $editId ? 'ط¥ط±ط³ط§ظ„ ط·ظ„ط¨ ط§ظ„طھط¹ط¯ظٹظ„ ظ„ظ„ظ…ظˆط§ظپظ‚ط©' : 'ط­ظپط¸ ط§ظ„ظˆط¯ظٹط¹ط©' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>