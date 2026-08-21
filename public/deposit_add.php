<?php
// public/deposit_add.php — Add New Deposit
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

$investors = $pdo->query("SELECT id, full_name FROM investors ORDER BY full_name")->fetchAll();
$depositTypes = $pdo->query("SELECT * FROM deposit_types ORDER BY min_days")->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$getInvestorId = (int)($_GET['investor_id'] ?? 0);
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
    $form['currency']                = in_array($_POST['currency'] ?? '', ['IQD','USD']) ? $_POST['currency'] : 'IQD';
    $form['start_date']              = trim($_POST['start_date']               ?? '');
    $payoutFrequency = (int) ($_POST['profit_payout_frequency'] ?? 1);

    // Validate
    if (!$form['investor_id'])
        $errors[] = 'يجب اختيار المستثمر.';
    if (!$form['deposit_type_id'])
        $errors[] = 'يجب اختيار نوع الوديعة.';

    $amount = (float) $form['amount'];
    if ($amount <= 0 || $amount < 100)
        $errors[] = 'المبلغ يجب أن يكون 100 ريال أو أكثر.';

    if (!$form['start_date']) {
        $errors[] = 'يجب تحديد تاريخ البداية.';
    }

    // Fetch the selected deposit type
    $selType = null;
    foreach ($depositTypes as $dt) {
        if ((int) $dt['id'] === $form['deposit_type_id']) {
            $selType = $dt;
            break;
        }
    }
    if (!$selType)
        $errors[] = 'نوع الوديعة غير صالح.';
    
    // Validate payout frequency
    if ($payoutFrequency < 1 || $payoutFrequency > 12) {
        $errors[] = 'دورية صرف الأرباح يجب أن تكون بين شهر و 12 شهراً.';
    }

    // Calculate end_date based on max_days
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
            $pdo->beginTransaction();

            if ($editId) {
                // Update existing deposit
                $stmt = $pdo->prepare(
                    "UPDATE deposits SET investor_id=?, deposit_type_id=?, amount=?, currency=?, start_date=?, end_date=?, profit_payout_frequency=? WHERE id=?"
                );
                $stmt->execute([
                    $form['investor_id'],
                    $form['deposit_type_id'],
                    $amount,
                    $form['currency'],
                    $startDateStr,
                    $endDateStr,
                    $payoutFrequency,
                    $editId
                ]);

                $pdo->commit();

                // Log
                logActivity($pdo, 'UPDATE_DEPOSIT', 'deposits', $editId, $deposit, [
                    'investor_id' => $form['investor_id'],
                    'type_id' => $form['deposit_type_id'],
                    'amount' => $amount,
                    'start_date' => $startDateStr,
                    'end_date' => $endDateStr,
                    'payout_frequency' => $payoutFrequency
                ]);

                setFlash('success', "تم تعديل بيانات الوديعة بنجاح!");
                header('Location: deposits.php');
                exit;

            } else {
                // Insert new deposit
                $stmt = $pdo->prepare(
                    "INSERT INTO deposits (investor_id, deposit_type_id, amount, currency, start_date, end_date, profit_payout_frequency, accumulated_profit, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 'active')"
                );
                $stmt->execute([
                    $form['investor_id'],
                    $form['deposit_type_id'],
                    $amount,
                    $form['currency'],
                    $startDateStr,
                    $endDateStr,
                    $payoutFrequency
                ]);
                $depositId = (int)$pdo->lastInsertId();

                // Generate receipt_no and insert transaction
                $receiptNo = generateReceiptNo($pdo);
                $pdo->prepare(
                    "INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                     VALUES (?, ?, ?, 'deposit', ?, ?, NOW(), ?)"
                )->execute([
                    $receiptNo,
                    $form['investor_id'],
                    $depositId,
                    $amount,
                    $form['currency'],
                    'إيداع جديد — وديعة ' . $selType['name_ar']
                ]);

                $pdo->commit();

                // Log
                logActivity($pdo, 'CREATE_DEPOSIT', 'deposits', $depositId, null, [
                    'investor_id' => $form['investor_id'],
                    'type' => $selType['code'],
                    'amount' => $amount,
                    'start_date' => $startDateStr,
                    'end_date' => $endDateStr,
                    'payout_frequency' => $payoutFrequency,
                    'receipt_no' => $receiptNo,
                ]);

                setFlash('success', "تمت إضافة الوديعة بنجاح! رقم الإيصال: $receiptNo");
                header('Location: deposits.php');
                exit;
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            $errors[] = 'حدث خطأ أثناء الحفظ: ' . $e->getMessage();
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
                <a href="/deposits.php" class="btn btn-outline-gold">
                    <i class="bi bi-arrow-right me-1"></i> عودة للودائع
                </a>
            </div>

            <?php if ($errors): ?>
                <div class="alert flash-danger border mb-3" style="border-radius:8px">
                    <ul class="mb-0 pe-3">
                        <?php foreach ($errors as $e): ?>
                            <li>
                                <?= htmlspecialchars($e) ?>
                            </li>
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
                                            <option value="<?= $dt['id'] ?>" data-code="<?= $dt['code'] ?>"
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
                                    <select name="profit_payout_frequency" class="form-select" required>
                                        <option value="1" <?= (int)($form['profit_payout_frequency'] ?? 1) === 1 ? 'selected' : '' ?>>كل شهر</option>
                                        <option value="2" <?= (int)($form['profit_payout_frequency'] ?? 1) === 2 ? 'selected' : '' ?>>كل شهرين</option>
                                        <option value="3" <?= (int)($form['profit_payout_frequency'] ?? 1) === 3 ? 'selected' : '' ?>>كل 3 أشهر</option>
                                        <option value="6" <?= (int)($form['profit_payout_frequency'] ?? 1) === 6 ? 'selected' : '' ?>>كل 6 أشهر</option>
                                        <option value="12" <?= (int)($form['profit_payout_frequency'] ?? 1) === 12 ? 'selected' : '' ?>>كل سنة</option>
                                    </select>
                                </div>

                                <!-- Long Days (only for long type) -->
                                <div class="col-md-6" id="longDaysRow" style="display:none">
                                    <label class="form-label">عدد الأيام (180–360) <span
                                            class="text-danger">*</span></label>
                                    <input type="number" name="long_days" id="longDaysInput" class="form-control"
                                        min="180" max="360" placeholder="مثال: 270"
                                        value="<?= htmlspecialchars($form['long_days'] ?? '') ?>">
                                </div>
                            </div>

                            <hr class="divider my-4">

                            <div class="d-flex gap-2 justify-content-end">
                                <a href="/deposits.php" class="btn btn-outline-gold">إلغاء</a>
                                <button type="submit" class="btn btn-gold px-4">
                                    <i class="bi bi-save me-1"></i> <?= $editId ? 'حفظ التعديلات' : 'حفظ الوديعة' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
        <?php
        $extraScript = <<<JS
<script>
        document.addEventListener('DOMContentLoaded', () => {
            // Any specific dynamic ui interactions
        });
</script>
JS;
        include __DIR__ . '/../includes/footer.php'; ?>