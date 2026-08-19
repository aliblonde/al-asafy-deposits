<?php
// public/withdraw_requests.php — Withdrawal Requests Management
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

// Handle approve / reject / pay
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';
    $reqId = (int) ($_POST['req_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    $req = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id=?");
    $req->execute([$reqId]);
    $req = $req->fetch();

    if (!$req) {
        setFlash('danger', 'الطلب غير موجود.');
        header('Location: withdraw_requests.php');
        exit;
    }

    if ($action === 'approve' && $req['status'] === 'pending') {
        $pdo->prepare("UPDATE withdraw_requests SET status='approved', staff_user_id=?, decision_date=NOW(), note=? WHERE id=?")
            ->execute([currentUserId(), $note, $reqId]);
        logActivity(
            $pdo,
            'APPROVE_WITHDRAW',
            'withdraw_requests',
            $reqId,
            ['status' => 'pending'],
            ['status' => 'approved', 'note' => $note]
        );
        setFlash('success', 'تمت الموافقة على طلب السحب.');

    } elseif ($action === 'reject' && $req['status'] === 'pending') {
        $pdo->prepare("UPDATE withdraw_requests SET status='rejected', staff_user_id=?, decision_date=NOW(), note=? WHERE id=?")
            ->execute([currentUserId(), $note, $reqId]);
        logActivity(
            $pdo,
            'REJECT_WITHDRAW',
            'withdraw_requests',
            $reqId,
            ['status' => 'pending'],
            ['status' => 'rejected', 'note' => $note]
        );
        setFlash('warning', 'تم رفض طلب السحب.');

    } elseif ($action === 'pay' && $req['status'] === 'approved') {
        try {
            $pdo->beginTransaction();
            $receiptNo = generateReceiptNo($pdo);
            $pdo->prepare(
                "INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, amount, currency, date, note)
                 VALUES (?, ?, NULL, 'withdraw', ?, ?, NOW(), ?)"
            )->execute([$receiptNo, $req['investor_id'], $req['amount'], $req['currency'] ?? 'IQD', 'صرف طلب سحب #' . $reqId]);
            $pdo->prepare("UPDATE withdraw_requests SET status='paid', staff_user_id=?, decision_date=NOW() WHERE id=?")
                ->execute([currentUserId(), $reqId]);
            $pdo->commit();
            logActivity(
                $pdo,
                'PAY_WITHDRAW',
                'withdraw_requests',
                $reqId,
                ['status' => 'approved'],
                ['status' => 'paid', 'receipt_no' => $receiptNo]
            );
            setFlash('success', "تم صرف المبلغ. رقم الإيصال: $receiptNo");
        } catch (PDOException $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            setFlash('danger', 'خطأ في الصرف: ' . $e->getMessage());
        }
    }

    header('Location: withdraw_requests.php');
    exit;
}

$requests = $pdo->query(
    "SELECT wr.*, i.full_name,
            u.username AS staff_name
     FROM withdraw_requests wr
     JOIN investors i ON i.id = wr.investor_id
     LEFT JOIN users u ON u.id = wr.staff_user_id
     ORDER BY wr.request_date DESC"
)->fetchAll();

$pageTitle = 'طلبات سحب الأرباح';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-arrow-up-circle me-2"></i>طلبات سحب الأرباح</h1>
            </div>

            <div class="table-wrapper">
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستثمر</th>
                                <th>العملة</th>
                                <th>المبلغ</th>
                                <th>تاريخ الطلب</th>
                                <th>الحالة</th>
                                <th>موظف القرار</th>
                                <th>تاريخ القرار</th>
                                <th>ملاحظة</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td class="text-muted"><?= $r['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($r['full_name']) ?></td>
                                    <td><?= currencyBadge($r['currency'] ?? 'IQD') ?></td>
                                    <td class="text-gold fw-bold"><?= formatMoney($r['amount'], $r['currency'] ?? 'IQD') ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($r['request_date'])) ?>
                                    </td>
                                    <td><span class="badge <?= statusBadge($r['status']) ?>">
                                            <?= arabicStatus($r['status']) ?>
                                        </span></td>
                                    <td>
                                        <?= htmlspecialchars($r['staff_name'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <?= $r['decision_date'] ? date('d/m/Y', strtotime($r['decision_date'])) : '—' ?>
                                    </td>
                                    <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis">
                                        <?= htmlspecialchars($r['note'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <!-- Approve -->
                                            <form method="post" class="d-inline" onsubmit="return confirmAction('الموافقة')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button class="btn btn-sm"
                                                    style="background:rgba(39,174,96,0.2);color:#2ecc71;border:1px solid rgba(39,174,96,0.4)">موافقة</button>
                                            </form>
                                            <!-- Reject -->
                                            <form method="post" class="d-inline" onsubmit="return confirmAction('الرفض')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button class="btn btn-sm"
                                                    style="background:rgba(231,76,60,0.2);color:#e74c3c;border:1px solid rgba(231,76,60,0.4)">رفض</button>
                                            </form>
                                        <?php elseif ($r['status'] === 'approved'): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirmAction('الصرف')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="pay">
                                                <button class="btn btn-sm btn-gold">صرف</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">لا توجد طلبات سحب</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php
        $extraScript = '<script>function confirmAction(a){return confirm("هل أنت متأكد من " + a + "؟");}</script>';
        include __DIR__ . '/../includes/footer.php'; ?>