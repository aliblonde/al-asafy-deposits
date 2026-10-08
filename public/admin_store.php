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
        $stmt = $pdo->prepare("SELECT o.*, p.name_ar as product_name 
                               FROM store_orders o 
                               JOIN store_products p ON p.id = o.product_id 
                               WHERE o.id = ? AND o.status = 'pending'");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order) {
            try {
                $pdo->beginTransaction();
                
                if ($action === 'approve') {
                    $pinCode = trim($_POST['pin_code'] ?? '');
                    if (empty($pinCode)) throw new Exception("يجب إدخال الرقم السري للبطاقة.");
                    
                    $upd = $pdo->prepare("UPDATE store_orders SET status = 'completed', pin_code = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
                    $upd->execute([$pinCode, currentUserId(), $orderId]);
                    
                    require_once __DIR__ . '/../config/notifications.php';
                    notifyInvestor($pdo, $order['investor_id'], 'تم تسليم بطاقتك!', "تم تسليم بطاقة {$order['product_name']} بنجاح. الكود موجود في قسم مشترياتي.", 'investor_store.php');
                    
                    setFlash('success', 'تم تسليم البطاقة للمستثمر بنجاح.');
                    
                } elseif ($action === 'reject') {
                    // Find active deposit for this investor in matching currency to refund
                    $depStmt = $pdo->prepare("SELECT id FROM deposits WHERE investor_id = ? AND currency = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
                    $depStmt->execute([$order['investor_id'], $order['currency']]);
                    $depId = $depStmt->fetchColumn();
                    
                    if (!$depId) {
                        $depStmt = $pdo->prepare("SELECT id FROM deposits WHERE investor_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
                        $depStmt->execute([$order['investor_id']]);
                        $depId = $depStmt->fetchColumn();
                    }
                    if (!$depId) {
                        $depStmt = $pdo->prepare("SELECT id FROM deposits WHERE investor_id = ? ORDER BY id DESC LIMIT 1");
                        $depStmt->execute([$order['investor_id']]);
                        $depId = $depStmt->fetchColumn();
                    }
                    
                    if ($depId) {
                        $updDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit + ? WHERE id = ?");
                        $updDep->execute([$order['amount_deducted'], $depId]);
                    }
                    
                    $adminNote = trim($_POST['admin_note'] ?? '');
                    $noteStr = $adminNote ?: 'مرفوض';
                    $updOrd = $pdo->prepare("UPDATE store_orders SET status = 'rejected', admin_note = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
                    $updOrd->execute([$noteStr, currentUserId(), $orderId]);
                    
                    require_once __DIR__ . '/../config/notifications.php';
                    notifyInvestor($pdo, $order['investor_id'], 'تم رفض طلب المتجر واسترجاع المبلغ', "تم رفض طلب بطاقة {$order['product_name']}. تم إرجاع مبلغ " . formatMoney($order['amount_deducted'], $order['currency']) . " إلى رصيد أرباحك. السبب: {$noteStr}", 'investor_store.php');
                    
                    setFlash('success', 'تم رفض الطلب وإرجاع المبلغ للمستثمر بنجاح.');
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', $e->getMessage());
            }
        } else {
            setFlash('danger', 'لم يتم العثور على الطلب أو تمت معالجته مسبقاً.');
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
$pageTitle = 'إدارة الطلبات الرقمية';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/flash_messages.php'; ?>
            
            <div class="page-header mb-4">
                <div>
                    <h1 class="page-title"><i class="bi bi-shop me-2 text-gold"></i>إدارة طلبات المتجر الرقمي</h1>
                    <p class="page-subtitle">متابعة وتنفيذ طلبات شراء البطاقات</p>
                </div>
            </div>
            
            <div class="card bg-dark border-secondary">
                <div class="table-responsive">
                    <table class="table table-dark-custom mb-0">
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
                                        <span class="badge bg-warning text-dark">بانتظار التنفيذ ⏳</span>
                                    <?php elseif ($o['status'] === 'completed'): ?>
                                        <span class="badge bg-success">مكتمل ✅</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">مرفوض ❌</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($o['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $o['id'] ?>">تسليم البطاقة (موافقة)</button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $o['id'] ?>">رفض (استرجاع)</button>
                                        
                                        <!-- Approve Modal -->
                                        <div class="modal fade" id="approveModal<?= $o['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <form class="modal-content bg-dark text-white" method="POST">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                    <div class="modal-header border-secondary">
                                                        <h5 class="modal-title">تسليم بطاقة: <?= htmlspecialchars($o['product_name']) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-end">
                                                        <label class="form-label text-warning">أدخل الرقم السري للبطاقة (أو رابط التفعيل) <span class="text-danger">*</span></label>
                                                        <input type="text" name="pin_code" class="form-control" required placeholder="مثال: 1234-5678-9012">
                                                        <small class="text-muted mt-2 d-block">سيظهر هذا الرقم للمستثمر مباشرة في حسابه.</small>
                                                    </div>
                                                    <div class="modal-footer border-secondary">
                                                        <button type="submit" class="btn btn-success">تأكيد التسليم</button>
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
                                                        <h5 class="modal-title text-danger">رفض طلب البطاقة</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-end">
                                                        <label class="form-label">سبب الرفض (يظهر للمستثمر)</label>
                                                        <input type="text" name="admin_note" class="form-control" placeholder="مثال: البطاقة غير متوفرة حالياً...">
                                                        <small class="text-warning mt-2 d-block">سيتم إرجاع مبلغ <?= formatMoney($o['amount_deducted'], $o['currency']) ?> إلى رصيد أرباح المستثمر تلقائياً.</small>
                                                    </div>
                                                    <div class="modal-footer border-secondary">
                                                        <button type="submit" class="btn btn-danger">تأكيد الرفض والإرجاع</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php elseif ($o['status'] === 'completed'): ?>
                                        <small class="text-muted">الرقم السري المُسلم: <br><span class="user-select-all text-white font-monospace"><?= htmlspecialchars($o['pin_code']) ?></span></small>
                                    <?php elseif ($o['status'] === 'rejected'): ?>
                                        <small class="text-danger">السبب: <?= htmlspecialchars($o['admin_note']) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($orders)): ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3"></i>لا توجد طلبات حتى الآن</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
