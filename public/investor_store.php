<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

$investorId = currentUserId();
$pdo = getPDO();

// Get active deposits and calculate total available profit
$depStmt = $pdo->prepare("SELECT id, amount, currency, accumulated_profit, deposit_type_id FROM deposits WHERE investor_id = ? AND status = 'active'");
$depStmt->execute([$investorId]);
$deposits = $depStmt->fetchAll();

$availableProfitUSD = 0;
$availableProfitIQD = 0;
$depMap = []; // To easily deduct from a specific deposit
foreach ($deposits as $d) {
    $depMap[$d['id']] = $d;
    if ($d['currency'] === 'USD') $availableProfitUSD += (float)$d['accumulated_profit'];
    else $availableProfitIQD += (float)$d['accumulated_profit'];
}

// Subtract pending withdrawal requests
$pStmt = $pdo->prepare("SELECT currency, COALESCE(SUM(amount), 0) AS pending_total FROM withdraw_requests WHERE investor_id = ? AND status = 'pending' GROUP BY currency");
$pStmt->execute([$investorId]);
while ($r = $pStmt->fetch()) {
    if ($r['currency'] === 'USD') $availableProfitUSD -= (float)$r['pending_total'];
    else $availableProfitIQD -= (float)$r['pending_total'];
}

// Subtract pending store orders
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
                    
                    // Deduct from the first deposit that has enough profit in that currency
                    $deducted = false;
                    foreach ($deposits as $d) {
                        if ($d['currency'] === $curr && (float)$d['accumulated_profit'] >= $price) {
                            $updDep = $pdo->prepare("UPDATE deposits SET accumulated_profit = accumulated_profit - ? WHERE id = ?");
                            $updDep->execute([$price, $d['id']]);
                            $deducted = true;
                            break;
                        }
                    }
                    
                    if (!$deducted) {
                        throw new Exception("حدث خطأ تقني في خصم الرصيد. يرجى مراجعة الإدارة.");
                    }
                    
                    // Create Order
                    $insOrd = $pdo->prepare("INSERT INTO store_orders (investor_id, product_id, amount_deducted, currency, status) VALUES (?, ?, ?, ?, 'pending')");
                    $insOrd->execute([$investorId, $productId, $price, $curr]);
                    
                    $pdo->commit();

                    // 1. Notify Investor
                    require_once __DIR__ . '/../config/notifications.php';
                    notifyInvestor($pdo, $investorId, 'طلب شراء من المتجر', "تم استلام طلبك لشراء: {$product['name_ar']}. وهو قيد المعالجة الآن.", 'investor_store.php');
                    
                    // 2. Notify Telegram Group
                    $invNameStmt = $pdo->prepare("SELECT full_name FROM investors WHERE id = ?");
                    $invNameStmt->execute([$investorId]);
                    $invName = $invNameStmt->fetchColumn() ?: 'مستثمر';
                    
                    $tgMessage = "🛒 <b>طلب شراء جديد من المتجر!</b>\n";
                    $tgMessage .= "👤 المستثمر: " . htmlspecialchars($invName) . "\n";
                    $tgMessage .= "🎟️ البطاقة: " . htmlspecialchars($product['name_ar']) . "\n";
                    $tgMessage .= "💵 المبلغ المخصوم: " . formatMoney($price, $curr) . "\n";
                    $tgMessage .= "يرجى الدخول للوحة الإدارة لتسليم الكود.";
                    sendTelegramAlert($tgMessage);
                    
                    // 3. Notify Admins in-app
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
        } else {
            setFlash('danger', 'المنتج غير متوفر حالياً.');
        }
    }
    header('Location: investor_store.php');
    exit;
}

// Fetch categories and products
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

// Fetch user orders
$myOrders = $pdo->prepare("SELECT o.*, p.name_ar as product_name, c.icon FROM store_orders o 
                           JOIN store_products p ON p.id = o.product_id 
                           JOIN store_categories c ON c.id = p.category_id
                           WHERE o.investor_id = ? ORDER BY o.created_at DESC");
$myOrders->execute([$investorId]);
$ordersList = $myOrders->fetchAll();

$pageTitle = 'المتجر الرقمي';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - العسافي</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
        .product-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(212,175,55,0.1);
            border-color: var(--gold);
        }
        .cat-icon {
            font-size: 2rem;
            color: var(--gold);
            margin-bottom: 10px;
        }
        .nav-tabs .nav-link { color: var(--text); border: none; padding: 12px 20px; font-weight: 500; }
        .nav-tabs .nav-link.active { background: transparent; color: var(--gold); border-bottom: 2px solid var(--gold); }
    </style>
</head>
<body class="bg-dark text-white pb-5">
    <?php include __DIR__ . '/../includes/flash_messages.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-gold"><i class="bi bi-shop me-2"></i>العسافي ستور</h3>
            <a href="investor_portal.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-right me-1"></i>العودة للمحفظة</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-6 mb-2">
                <div class="p-3 rounded bg-surface border border-secondary text-center">
                    <div class="text-muted small">رصيد الأرباح المتاح (IQD)</div>
                    <div class="fs-4 text-gold fw-bold"><?= formatMoney($availableProfitIQD, 'IQD') ?></div>
                </div>
            </div>
            <div class="col-md-6 mb-2">
                <div class="p-3 rounded bg-surface border border-secondary text-center">
                    <div class="text-muted small">رصيد الأرباح المتاح (USD)</div>
                    <div class="fs-4 text-gold fw-bold"><?= formatMoney($availableProfitUSD, 'USD') ?></div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="storeTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-store">🛒 المنتجات</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-orders">🧾 مشترياتي وبطاقاتي</button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Store Tab -->
            <div class="tab-pane fade show active" id="tab-store">
                <?php foreach ($store as $section): if(empty($section['products'])) continue; ?>
                    <h5 class="mt-4 mb-3 text-light border-bottom border-secondary pb-2">
                        <i class="bi <?= $section['cat']['icon'] ?> text-gold me-2"></i><?= htmlspecialchars($section['cat']['name_ar']) ?>
                    </h5>
                    <div class="row g-3 mb-4">
                        <?php foreach ($section['products'] as $p): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="product-card p-3 text-center d-flex flex-column">
                                <?php if (!empty($p['image_url'])): ?>
    <div class="mb-3 d-flex align-items-center justify-content-center" style="height: 100px; background: rgba(255,255,255,0.05); border-radius: 8px; padding: 10px;">
        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="Logo" style="max-height: 100%; max-width: 100%; object-fit: contain;">
    </div>
<?php else: ?>
    <i class="bi <?= $section['cat']['icon'] ?> cat-icon"></i>
<?php endif; ?>
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($p['name_ar']) ?></h6>
                                <p class="small text-muted mb-3 flex-grow-1"><?= htmlspecialchars($p['description']) ?></p>
                                <div class="fs-5 fw-bold text-gold mb-3"><?= formatMoney($p['price'], $p['currency']) ?></div>
                                <button class="btn btn-sm btn-gold w-100 mt-auto" data-bs-toggle="modal" data-bs-target="#buyModal<?= $p['id'] ?>">شراء الآن</button>
                            </div>
                        </div>

                        <!-- Buy Modal -->
                        <div class="modal fade" id="buyModal<?= $p['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-sm modal-dialog-centered">
                                <form class="modal-content bg-dark text-white" method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <div class="modal-header border-secondary">
                                        <h6 class="modal-title text-gold">تأكيد الشراء</h6>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <p class="mb-1">هل تريد تأكيد شراء:</p>
                                        <h5 class="fw-bold mb-3"><?= htmlspecialchars($p['name_ar']) ?></h5>
                                        <div class="p-2 bg-surface rounded text-warning mb-3">
                                            سيتم خصم <strong><?= formatMoney($p['price'], $p['currency']) ?></strong> من رصيد أرباحك المتراكمة.
                                        </div>
                                    </div>
                                    <div class="modal-footer border-secondary justify-content-center gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">تراجع</button>
                                        <button type="submit" class="btn btn-gold btn-sm">تأكيد الدفع 💳</button>
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
                        <div class="p-3 bg-surface border <?= $o['status'] === 'completed' ? 'border-success' : 'border-secondary' ?> rounded position-relative">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <i class="bi <?= $o['icon'] ?> text-gold me-1"></i>
                                    <span class="fw-bold"><?= htmlspecialchars($o['product_name']) ?></span>
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
                                <div class="p-3 bg-dark border border-success rounded text-center">
                                    <div class="small text-success mb-1">الرقم السري / الكود الخاص بك:</div>
                                    <div class="fs-5 fw-bold font-monospace user-select-all text-white"><?= htmlspecialchars($o['pin_code']) ?></div>
                                </div>
                            <?php elseif ($o['status'] === 'pending'): ?>
                                <div class="p-2 bg-dark rounded text-center text-warning small border border-warning border-opacity-50">
                                    جاري معالجة طلبك... يرجى تحديث الصفحة بعد قليل.
                                </div>
                            <?php elseif ($o['status'] === 'rejected'): ?>
                                <div class="p-2 bg-dark rounded text-center text-danger small">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
