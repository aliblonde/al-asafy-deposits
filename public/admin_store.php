<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';

requirePermission('deposits.view'); // Basic admin access for MVP
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($orderId) {
        $stmt = $pdo->prepare("SELECT o.*, p.name_ar as product_name, d.id as dep_id, d.investor_id 
                               FROM store_orders o 
                               JOIN store_products p ON p.id = o.product_id 
                               JOIN (SELECT id, investor_id FROM deposits LIMIT 1) d ON d.investor_id = o.investor_id 
                               WHERE o.id = ? AND o.status = 'pending'");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order) {
            try {
                $pdo->beginTransaction();
                
                if ($action === 'approve') {
                    $pinCode = trim($_POST['pin_code'] ?? '');
                    if (empty($pinCode)) throw new Exception('يجب إدخال الرقم السري للبطاقة.');
                    
                    // Update order
                    $upd = $pdo->prepare("UPDATE store_orders SET status = 'completed', pin_code = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
                    $upd->execute([$pinCode, currentUserId(), $orderId]);
                    
                    // Add transaction record
                    $insTx = $pdo->prepare("INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, date, note)
                                            VALUES (?, ?, ?, 'service_purchase', 'debit', ?, ?, NOW(), ?)");
                    $insTx->execute([generateReceiptNo($pdo), $order['investor_id'], $order['dep_id'], $order['amount_deducted'], $order['currency'], 'شراء بطاقة: ' . $order['product_name']]);
                    
                    // Notify Investor & Telegram
                    require_once __DIR__ . '/../config/notifications.php';
                    notifyInvestor($pdo, (int)$order['investor_id'], 'استلام بطاقة المتجر', "تم تنفيذ طلبك وتسليم كود بطاقة: {$order['product_name']}. ادخل لقسم (مشترياتي) لرؤية الكود.", 'investor_store.php');
                    
                    $invNameStmt = $pdo->prepare("SELECT full_name FROM investors WHERE id = ?");
                    $invNameStmt->execute([$order['investor_id']]);
                    $invName = $invNameStmt->fetchColumn() ?: 'مستثمر';
                    sendTelegramAlert("✅ <b>تم تسليم البطاقة</b>\nالمستثمر: {$invName}\nالبطاقة: {$order['product_name']}\nتم التسليم بواسطة الإدارة.");

                    setFlash('success', 'تم تنفيذ الطلب وإرسال الكود للمستثمر.');
                } elseif ($action === 'reject') {
                    $reason = trim($_POST['admin_note'] ?? '');
                    
                    // Update order
                    $upd = $pdo->prepare("UPDATE store_orders SET status = 'rejected', admin_note = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
                    $upd->execute([$reason, currentUserId(), $orderId]);
                    
                    // Refund to ANY active deposit of the investor (just pick one for now)
                    $refund = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ? WHERE investor_id = ? AND status = 'active' LIMIT 1");
                    $refund->execute([$order['amount_deducted'], $order['investor_id']]);
                    
                    // Notify Investor & Telegram
                    require_once __DIR__ . '/../config/notifications.php';
                    notifyInvestor($pdo, (int)$order['investor_id'], 'إلغاء طلب المتجر', "تم إلغاء طلبك لبطاقة: {$order['product_name']} واسترجاع الرصيد. السبب: {$reason}", 'investor_store.php');
                    
                    $invNameStmt = $pdo->prepare("SELECT full_name FROM investors WHERE id = ?");
                    $invNameStmt->execute([$order['investor_id']]);
                    $invName = $invNameStmt->fetchColumn() ?: 'مستثمر';
                    sendTelegramAlert("❌ <b>تم إلغاء طلب متجر</b>\nالمستثمر: {$invName}\nالبطاقة: {$order['product_name']}\nالسبب: {$reason}");

                    setFlash('warning', 'تم رفض الطلب وإعادة المبلغ لحساب المستثمر.');
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', $e->getMessage());
            }
        }
    }
    header('Location: admin_store.php');
    exit;
}

$ordersStmt = $pdo->query("SELECT o.*, p.name_ar as product_name, i.full_name as investor_name 
                           FROM store_orders o 
                           JOIN store_products p ON p.id = o.product_id 
                           JOIN investors i ON i.id = o.investor_id 
                           ORDER BY o.created_at DESC");
$orders = $ordersStmt->fetchAll();
$pageTitle = 'إدارة المتجر الرقمي';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-white"><i class="bi bi-shop me-2 text-gold"></i>إدارة طلبات المتجر الرقمي</h4>
    </div>
    
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>المستثمر</th>
                    <th>المنتج</th>
                    <th>المبلغ المخصوم</th>
                    <th>تاريخ الطلب</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['investor_name']) ?></td>
                    <td class="text-info"><?= htmlspecialchars($o['product_name']) ?></td>
                    <td class="text-gold fw-bold"><?= formatMoney($o['amount_deducted'], $o['currency']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                    <td>
                        <?php if ($o['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">قيد الانتظار ⏳</span>
                        <?php elseif ($o['status'] === 'completed'): ?>
                            <span class="badge bg-success">مكتمل ✅</span>
                        <?php else: ?>
                            <span class="badge bg-danger">مرفوض ❌</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($o['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $o['id'] ?>">تنفيذ وإرسال الكود</button>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $o['id'] ?>">رفض (استرجاع)</button>
                            
                            <!-- Approve Modal -->
                            <div class="modal fade" id="approveModal<?= $o['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form class="modal-content bg-dark text-white" method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <div class="modal-header border-secondary">
                                            <h5 class="modal-title">تسليم البطاقة: <?= htmlspecialchars($o['product_name']) ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-end">
                                            <label class="form-label text-warning">أدخل الرقم السري للبطاقة (أو رابط التحويل) <span class="text-danger">*</span></label>
                                            <input type="text" name="pin_code" class="form-control" required placeholder="مثال: 1234-5678-9012">
                                            <small class="text-muted mt-2 d-block">سيظهر هذا الكود فوراً للمستثمر في حسابه.</small>
                                        </div>
                                        <div class="modal-footer border-secondary">
                                            <button type="submit" class="btn btn-success">تسليم الطلب</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal<?= $o['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form class="modal-content bg-dark text-white" method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <div class="modal-header border-secondary">
                                            <h5 class="modal-title text-danger">رفض وإلغاء الطلب</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-end">
                                            <label class="form-label">سبب الرفض (سيظهر للمستثمر)</label>
                                            <input type="text" name="admin_note" class="form-control" placeholder="مثال: البطاقة غير متوفرة حالياً...">
                                            <small class="text-warning mt-2 d-block">سيتم إرجاع مبلغ <?= formatMoney($o['amount_deducted'], $o['currency']) ?> إلى رصيد المستثمر فوراً.</small>
                                        </div>
                                        <div class="modal-footer border-secondary">
                                            <button type="submit" class="btn btn-danger">تأكيد الإلغاء</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php elseif ($o['status'] === 'completed'): ?>
                            <small class="text-muted">الكود المُسلَّم: <br><span class="user-select-all text-white font-monospace"><?= htmlspecialchars($o['pin_code']) ?></span></small>
                        <?php elseif ($o['status'] === 'rejected'): ?>
                            <small class="text-danger">السبب: <?= htmlspecialchars($o['admin_note']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($orders)): ?>
                    <tr><td colspan="7" class="text-center py-4">لا توجد طلبات حتى الآن</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
