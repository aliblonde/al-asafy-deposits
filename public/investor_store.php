<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';

$investorId = currentUserId();
$pdo = getPDO();

// Get active deposits and calculate total available profit
$depStmt = $pdo->prepare("SELECT id, amount, currency, accumulated_profit, deposit_type_id FROM deposits WHERE investor_id = ? AND status = 'active'");
$depStmt->execute([$investorId]);
$deposits = $depStmt->fetchAll();

$availableProfitUSD = 0;
$availableProfitIQD = 0;
$depMap = []; 
foreach ($deposits as $d) {
    $depMap[$d['id']] = $d;
    if ($d['currency'] === 'USD') $availableProfitUSD += (float)$d['accumulated_profit'];
    else $availableProfitIQD += (float)$d['accumulated_profit'];
}

// Subtract pending withdraws & orders
$pStmt = $pdo->prepare("SELECT currency, COALESCE(SUM(amount), 0) AS pending_total FROM withdraw_requests WHERE investor_id = ? AND status = 'pending' GROUP BY currency");
$pStmt->execute([$investorId]);
while ($r = $pStmt->fetch()) {
    if ($r['currency'] === 'USD') $availableProfitUSD -= (float)$r['pending_total'];
    else $availableProfitIQD -= (float)$r['pending_total'];
}

$soStmt = $pdo->prepare("SELECT currency, COALESCE(SUM(amount_deducted), 0) AS pending_store FROM store_orders WHERE investor_id = ? AND status = 'pending' GROUP BY currency");
$soStmt->execute([$investorId]);
while ($r = $soStmt->fetch()) {
    if ($r['currency'] === 'USD') $availableProfitUSD -= (float)$r['pending_store'];
    else $availableProfitIQD -= (float)$r['pending_store'];
}

$availableProfitUSD = max(0, $availableProfitUSD);
$availableProfitIQD = max(0, $availableProfitIQD);

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if ($productId) {
        $pStmt = $pdo->prepare("SELECT * FROM store_products WHERE id = ? AND is_active = 1");
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch();
        
        if ($product) {
            $price = (float)$product['price'];
            $curr = $product['currency'];
            
            $hasEnough = false;
            if ($curr === 'USD' && $availableProfitUSD >= $price) $hasEnough = true;
            if ($curr === 'IQD' && $availableProfitIQD >= $price) $hasEnough = true;
            
            if (!$hasEnough) {
                setFlash('danger', 'عذراً، رصيد أرباحك (' . currencySymbol($curr) . ') لا يكفي لشراء هذه البطاقة.');
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    $deducted = false;
                    foreach ($deposits as $d) {
                        if ($d['currency'] === $curr && (float)$d['accumulated_profit'] >= $price) {
                            $updDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit - ? WHERE id = ?");
                            $updDep->execute([$price, $d['id']]);
                            $deducted = true;
                            break;
                        }
                    }
                    
                    if (!$deducted) throw new Exception("حدث خطأ تقني في خصم الرصيد.");
                    
                    $insOrd = $pdo->prepare("INSERT INTO store_orders (investor_id, product_id, amount_deducted, currency, status) VALUES (?, ?, ?, ?, 'pending')");
                    $insOrd->execute([$investorId, $productId, $price, $curr]);
                    
                    $pdo->commit();

                    require_once __DIR__ . '/../config/notifications.php';
                    notifyInvestor($pdo, $investorId, 'طلب شراء من المتجر', "تم استلام طلبك لشراء: {$product['name_ar']}. وهو قيد المعالجة الآن.", 'investor_store.php');
                    
                    $invNameStmt = $pdo->prepare("SELECT full_name FROM investors WHERE id = ?");
                    $invNameStmt->execute([$investorId]);
                    $invName = $invNameStmt->fetchColumn() ?: 'مستثمر';
                    
                    $tgMessage = "🛒 <b>طلب شراء جديد من المتجر!</b>\n👤 المستثمر: " . htmlspecialchars($invName) . "\n🎟️ البطاقة: " . htmlspecialchars($product['name_ar']) . "\n💵 المبلغ: " . formatMoney($price, $curr);
                    sendTelegramAlert($tgMessage);
                    
                    $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'superadmin')")->fetchAll();
                    foreach ($admins as $ad) {
                        sendNotification($pdo, $ad['id'], 'طلب متجر جديد', "طلب المستثمر {$invName} بطاقة {$product['name_ar']}", 'admin_store.php');
                    }

                    setFlash('success', 'تم استلام طلبك بنجاح! سيتم إرسال كود البطاقة لك قريباً في قسم (مشترياتي).');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('danger', $e->getMessage());
                }
            }
        }
    }
    header('Location: investor_store.php');
    exit;
}

$cats = $pdo->query("SELECT * FROM store_categories ORDER BY sort_order")->fetchAll();
$prods = $pdo->query("SELECT * FROM store_products WHERE is_active = 1")->fetchAll();

$store = [];
foreach ($cats as $c) {
    $store[$c['id']] = ['cat' => $c, 'products' => []];
}
foreach ($prods as $p) {
    if (isset($store[$p['category_id']])) {
        $store[$p['category_id']]['products'][] = $p;
    }
}

$myOrders = $pdo->prepare("SELECT o.*, p.name_ar as product_name, c.icon FROM store_orders o 
                           JOIN store_products p ON p.id = o.product_id 
                           JOIN store_categories c ON c.id = p.category_id
                           WHERE o.investor_id = ? ORDER BY o.created_at DESC");
$myOrders->execute([$investorId]);
$ordersList = $myOrders->fetchAll();

$pageTitle = 'المتجر الرقمي';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <?php include __DIR__ . '/../includes/flash_messages.php'; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-white"><i class="bi bi-shop me-2 text-gold"></i>متجر العسافي الرقمي</h4>
        <a href="investor_portal.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-right me-1"></i>العودة للمحفظة</a>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="stat-card p-3 rounded text-center" style="background: var(--surface); border: 1px solid var(--border);">
                <div class="text-muted small mb-1">رصيد الأرباح المتاح (IQD)</div>
                <div class="fs-4 text-gold fw-bold"><?= formatMoney($availableProfitIQD, 'IQD') ?></div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stat-card p-3 rounded text-center" style="background: var(--surface); border: 1px solid var(--border);">
                <div class="text-muted small mb-1">رصيد الأرباح المتاح (USD)</div>
                <div class="fs-4 text-gold fw-bold"><?= formatMoney($availableProfitUSD, 'USD') ?></div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs custom-tabs mb-4" id="storeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tab-store">🛒 المنتجات المتوفرة</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-orders">🧾 مشترياتي وبطاقاتي</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Store Tab -->
        <div class="tab-pane fade show active" id="tab-store">
            <?php foreach ($store as $section): if(empty($section['products'])) continue; ?>
                <h5 class="mt-4 mb-3 text-white border-bottom border-secondary pb-2">
                    <i class="bi <?= $section['cat']['icon'] ?> text-gold me-2"></i><?= htmlspecialchars($section['cat']['name_ar']) ?>
                </h5>
                <div class="row g-3 mb-4">
                    <?php foreach ($section['products'] as $p): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100 product-card text-center p-3" style="background: var(--surface); border: 1px solid var(--border); transition: 0.3s;">
                            <!-- Icon / Image Area -->
                            <div class="mb-3 d-flex align-items-center justify-content-center mx-auto" style="height: 70px; width: 70px; background: rgba(212,175,55,0.1); border-radius: 50%;">
                                <?php if (!empty($p['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="Logo" style="max-height: 40px; max-width: 40px; object-fit: contain;" onerror="this.onerror=null; this.src=''; this.parentElement.innerHTML='<i class=\'bi <?= $section['cat']['icon'] ?> fs-2 text-gold\'></i>';">
                                <?php else: ?>
                                    <i class="bi <?= $section['cat']['icon'] ?> fs-2 text-gold"></i>
                                <?php endif; ?>
                            </div>
                            
                            <h6 class="fw-bold text-white mb-1"><?= htmlspecialchars($p['name_ar']) ?></h6>
                            <p class="small text-muted mb-3 flex-grow-1" style="font-size: 0.8rem;"><?= htmlspecialchars($p['description']) ?></p>
                            
                            <div class="fs-5 fw-bold text-gold mb-3"><?= formatMoney($p['price'], $p['currency']) ?></div>
                            <button class="btn btn-sm btn-gold w-100 mt-auto fw-bold" data-bs-toggle="modal" data-bs-target="#buyModal<?= $p['id'] ?>">شراء الآن</button>
                        </div>
                    </div>

                    <!-- Buy Modal -->
                    <div class="modal fade" id="buyModal<?= $p['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm modal-dialog-centered">
                            <form class="modal-content" style="background: var(--surface); border: 1px solid var(--border);" method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <div class="modal-header border-secondary">
                                    <h6 class="modal-title text-gold fw-bold">تأكيد الشراء</h6>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center text-white">
                                    <p class="mb-1 text-muted small">هل تريد تأكيد شراء:</p>
                                    <h5 class="fw-bold mb-3"><?= htmlspecialchars($p['name_ar']) ?></h5>
                                    <div class="p-2 rounded text-warning mb-3" style="background: rgba(255,193,7,0.1); border: 1px dashed var(--gold);">
                                        سيتم خصم <strong class="fs-5"><?= formatMoney($p['price'], $p['currency']) ?></strong><br>من رصيد أرباحك المتراكمة.
                                    </div>
                                </div>
                                <div class="modal-footer border-secondary justify-content-center gap-2">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">تراجع</button>
                                    <button type="submit" class="btn btn-gold btn-sm fw-bold">تأكيد الدفع 💳</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Orders Tab -->
        <div class="tab-pane fade" id="tab-orders">
            <div class="row g-3">
                <?php foreach ($ordersList as $o): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="p-3 border rounded position-relative" style="background: var(--surface); border-color: <?= $o['status'] === 'completed' ? 'var(--bs-success)' : 'var(--border)' ?> !important;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <i class="bi <?= $o['icon'] ?> text-gold me-1"></i>
                                <span class="fw-bold text-white"><?= htmlspecialchars($o['product_name']) ?></span>
                            </div>
                            <?php if ($o['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">قيد التنفيذ ⏳</span>
                            <?php elseif ($o['status'] === 'completed'): ?>
                                <span class="badge bg-success">تم التسليم ✅</span>
                            <?php else: ?>
                                <span class="badge bg-danger">مرفوض ❌</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small mb-3">التاريخ: <?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
                        
                        <?php if ($o['status'] === 'completed'): ?>
                            <div class="p-3 rounded text-center mt-2" style="background: rgba(25,135,84,0.1); border: 1px dashed var(--bs-success);">
                                <div class="small text-success mb-1">الرقم السري / الكود الخاص بك:</div>
                                <div class="fs-4 fw-bold font-monospace user-select-all text-white"><?= htmlspecialchars($o['pin_code']) ?></div>
                            </div>
                        <?php elseif ($o['status'] === 'pending'): ?>
                            <div class="p-2 rounded text-center text-warning small mt-2" style="background: rgba(255,193,7,0.1); border: 1px dashed var(--bs-warning);">
                                جاري معالجة طلبك... يرجى تحديث الصفحة بعد قليل.
                            </div>
                        <?php elseif ($o['status'] === 'rejected'): ?>
                            <div class="p-2 rounded text-center text-danger small mt-2" style="background: rgba(220,53,69,0.1); border: 1px dashed var(--bs-danger);">
                                السبب: <?= htmlspecialchars($o['admin_note']) ?><br>
                                (تم إرجاع المبلغ لرصيدك)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($ordersList)): ?>
                    <div class="col-12 text-center py-5 text-muted">لم تقم بشراء أي منتج حتى الآن.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(212,175,55,0.15);
        border-color: var(--gold) !important;
    }
    .custom-tabs .nav-link { color: var(--text); border: none; padding: 12px 20px; }
    .custom-tabs .nav-link.active { background: transparent; color: var(--gold); border-bottom: 2px solid var(--gold); }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
