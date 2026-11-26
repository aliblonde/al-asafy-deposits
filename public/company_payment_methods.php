<?php
// public/company_payment_methods.php — Admin Management for Company Payment Channels
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireLogin();
$pdo = getPDO();

// Ensure company_payment_methods table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `company_payment_methods` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(100) NOT NULL,
      `type` ENUM('bank','wallet','exchange','cash') NOT NULL DEFAULT 'bank',
      `account_title` VARCHAR(150) NOT NULL,
      `account_number` VARCHAR(150) NOT NULL,
      `currency` VARCHAR(20) NOT NULL DEFAULT 'ALL',
      `instructions` TEXT NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      `display_order` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Seed defaults if table is empty
$count = (int)$pdo->query("SELECT COUNT(*) FROM company_payment_methods")->fetchColumn();
if ($count === 0) {
    $seedStmt = $pdo->prepare("
        INSERT INTO company_payment_methods (name, type, account_title, account_number, currency, instructions, is_active, display_order)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $seedStmt->execute(['مصرف العراق الأول (FIB)', 'bank', 'مجموعة العسافي للاستثمارات المالية', 'IQ78FIBK00010000000001234567', 'ALL', 'يرجى كتابة اسم المستثمر في حقل الملاحظات عند التحويل وإرفاق وصل التحويل هنا.', 1]);
    $seedStmt->execute(['محفظة زين كاش (ZainCash)', 'wallet', 'محفظة الأعمال المعتمدة - مجموعة العسافي', '07731111861', 'IQD', 'التحويل المالي المباشر عبر تطبيق زين كاش لرقم المحفظة وإرفاق لقطة شاشة الإشعار.', 2]);
    $seedStmt->execute(['محفظة كي كارد (Qi Card / SuperQi)', 'wallet', 'حساب الدفع المعتمد - مجموعة العسافي', '07731111861', 'IQD', 'تحويل من محفظة SuperQi أو بطاقة الماستر كارد التابعة لـ Qi.', 3]);
    $seedStmt->execute(['الحوالات المعتمدة ومكاتب الصيرفة', 'exchange', 'شركة الطيف / الروابي للصرافة', 'فرع بغداد - باسم مجموعة العسافي للاستثمارات', 'ALL', 'تسليم الحوالة بالفرع أو استلام وصل الإيداع وإرفاق صورته في التطبيق.', 4]);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!userCan('deposits.create') && currentRole() !== 'admin') {
        setFlash('danger', 'ليس لديك الصلاحية لإدارة حسابات وقنوات التحويل.');
        header('Location: company_payment_methods.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $type = trim((string)($_POST['type'] ?? 'bank'));
        $accountTitle = trim((string)($_POST['account_title'] ?? ''));
        $accountNumber = trim((string)($_POST['account_number'] ?? ''));
        $currency = trim((string)($_POST['currency'] ?? 'ALL'));
        $instructions = trim((string)($_POST['instructions'] ?? ''));
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($accountNumber)) {
            setFlash('danger', 'يرجى ملء اسم الحساب/البنك ورقم الحساب أو الآيبان.');
        } else {
            if ($action === 'create') {
                $ins = $pdo->prepare("
                    INSERT INTO company_payment_methods (name, type, account_title, account_number, currency, instructions, is_active, display_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$name, $type, $accountTitle, $accountNumber, $currency, $instructions, $isActive, $displayOrder]);
                logActivity($pdo, 'CREATE_PAYMENT_METHOD', 'company_payment_methods', (int)$pdo->lastInsertId(), null, ['name' => $name, 'account_number' => $accountNumber]);
                setFlash('success', 'تمت إضافة حساب التحويل بنجاح وسيظهر فوراً في تطبيق المستثمرين.');
            } else {
                $editId = (int)($_POST['id'] ?? 0);
                $up = $pdo->prepare("
                    UPDATE company_payment_methods 
                    SET name = ?, type = ?, account_title = ?, account_number = ?, currency = ?, instructions = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $up->execute([$name, $type, $accountTitle, $accountNumber, $currency, $instructions, $isActive, $displayOrder, $editId]);
                logActivity($pdo, 'UPDATE_PAYMENT_METHOD', 'company_payment_methods', $editId, null, ['name' => $name, 'account_number' => $accountNumber]);
                setFlash('success', 'تم تحديث بيانات الحساب بنجاح.');
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $up = $pdo->prepare("UPDATE company_payment_methods SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
        $up->execute([$id]);
        setFlash('success', 'تم تغيير حالة تفعيل الحساب.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM company_payment_methods WHERE id = ?");
        $del->execute([$id]);
        logActivity($pdo, 'DELETE_PAYMENT_METHOD', 'company_payment_methods', $id, null, []);
        setFlash('success', 'تم حذف الحساب بنجاح.');
    }

    header('Location: company_payment_methods.php');
    exit;
}

// Fetch all payment methods
$stmt = $pdo->query("SELECT * FROM company_payment_methods ORDER BY display_order ASC, id ASC");
$methods = $stmt->fetchAll();

$pageTitle = 'حسابات وقنوات التحويل للشركة';
include __DIR__ . '/../includes/header.php';
?>

<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title"><i class="bi bi-bank me-2"></i>حسابات وقنوات التحويل للشركة</h1>
                    <p class="text-muted small mb-0">إدارة أرقام الحسابات البنكية ومحافظ الكاش التي تظهر للمستثمرين في تطبيق الموبايل لشحن المحافظ</p>
                </div>
                <div>
                    <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#createMethodModal">
                        <i class="bi bi-plus-circle me-1"></i> إضافة حساب جديد
                    </button>
                </div>
            </div>

            <!-- List of Channels -->
            <div class="row g-3">
                <?php if (empty($methods)): ?>
                    <div class="col-12">
                        <div class="form-card text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            لا توجد قنوات تحويل مضافة حالياً.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($methods as $m): ?>
                        <div class="col-md-6 col-lg-6">
                            <div class="form-card h-100 border <?= $m['is_active'] ? 'border-gold' : 'border-secondary opacity-75' ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-2 rounded bg-base border border-gold text-gold fs-5">
                                            <?php if ($m['type'] === 'bank'): ?>
                                                <i class="bi bi-bank2"></i>
                                            <?php elseif ($m['type'] === 'wallet'): ?>
                                                <i class="bi bi-phone"></i>
                                            <?php elseif ($m['type'] === 'exchange'): ?>
                                                <i class="bi bi-currency-exchange"></i>
                                            <?php else: ?>
                                                <i class="bi bi-cash-stack"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($m['name']) ?></h5>
                                            <span class="badge bg-secondary small"><?= htmlspecialchars($m['type']) ?></span>
                                            <span class="badge bg-info text-dark small"><?= htmlspecialchars($m['currency']) ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($m['is_active']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>مفعل للمستثمر</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-pause-circle me-1"></i>معطل مؤقتاً</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="bg-base p-3 rounded border border-secondary mb-3">
                                    <div class="small text-muted mb-1">اسم صاحب الحساب / الجهة المستلمة:</div>
                                    <div class="fw-bold mb-2"><?= htmlspecialchars($m['account_title']) ?></div>
                                    <div class="small text-muted mb-1">رقم الحساب / الآيبان IBAN / رقم الهاتف:</div>
                                    <div class="fs-6 fw-bold text-gold" style="font-family:monospace;"><?= htmlspecialchars($m['account_number']) ?></div>
                                </div>

                                <?php if (!empty($m['instructions'])): ?>
                                    <div class="small text-muted mb-3">
                                        <i class="bi bi-info-circle me-1 text-gold"></i>
                                        <?= htmlspecialchars($m['instructions']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary mt-auto">
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $m['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                            <i class="bi bi-power me-1"></i><?= $m['is_active'] ? 'تعطيل' : 'تفعيل' ?>
                                        </button>
                                    </form>

                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-gold" data-bs-toggle="modal" data-bs-target="#editModal<?= $m['id'] ?>">
                                            <i class="bi bi-pencil me-1"></i> تعديل
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب؟');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $m['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" class="modal-content text-start" dir="rtl">
                                    <div class="modal-header">
                                        <h5 class="modal-title text-gold"><i class="bi bi-pencil me-2"></i>تعديل بيانات الحساب</h5>
                                        <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">

                                        <div class="mb-3">
                                            <label class="form-label">اسم البنك / المحفظة <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($m['name']) ?>">
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">النوع</label>
                                                <select name="type" class="form-select">
                                                    <option value="bank" <?= $m['type'] === 'bank' ? 'selected' : '' ?>>حساب مصرفي (Bank)</option>
                                                    <option value="wallet" <?= $m['type'] === 'wallet' ? 'selected' : '' ?>>محفظة كاش (Wallet)</option>
                                                    <option value="exchange" <?= $m['type'] === 'exchange' ? 'selected' : '' ?>>مكتب صيرفة (Exchange)</option>
                                                    <option value="cash" <?= $m['type'] === 'cash' ? 'selected' : '' ?>>تسليم نقدي (Cash)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">العملة المقبولة</label>
                                                <select name="currency" class="form-select">
                                                    <option value="ALL" <?= $m['currency'] === 'ALL' ? 'selected' : '' ?>>كافة العملات (USD / IQD)</option>
                                                    <option value="USD" <?= $m['currency'] === 'USD' ? 'selected' : '' ?>>دولار فقط (USD)</option>
                                                    <option value="IQD" <?= $m['currency'] === 'IQD' ? 'selected' : '' ?>>دينار فقط (IQD)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">اسم صاحب الحساب</label>
                                            <input type="text" name="account_title" class="form-control" value="<?= htmlspecialchars($m['account_title']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">رقم الحساب / IBAN / رقم المحفظة <span class="text-danger">*</span></label>
                                            <input type="text" name="account_number" class="form-control" required style="font-family:monospace;" value="<?= htmlspecialchars($m['account_number']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">تعليمات الإيداع للمستثمر</label>
                                            <textarea name="instructions" class="form-control" rows="2"><?= htmlspecialchars($m['instructions'] ?: '') ?></textarea>
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ترتيب الظهور</label>
                                                <input type="number" name="display_order" class="form-control" value="<?= (int)$m['display_order'] ?>">
                                            </div>
                                            <div class="col-md-6 d-flex align-items-center pt-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_active" id="editActive<?= $m['id'] ?>" <?= $m['is_active'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="editActive<?= $m['id'] ?>">تفعيل الحساب في التطبيق</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" class="btn btn-gold">حفظ التعديلات</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Create Modal -->
            <div class="modal fade" id="createMethodModal" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" class="modal-content text-start" dir="rtl">
                        <div class="modal-header">
                            <h5 class="modal-title text-gold"><i class="bi bi-plus-circle me-2"></i>إضافة حساب تحويل رسمي جديد</h5>
                            <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="create">

                            <div class="mb-3">
                                <label class="form-label">اسم البنك / المحفظة <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="مثال: مصرف الرافدين، زين كاش، مصرف FIB...">
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">النوع</label>
                                    <select name="type" class="form-select">
                                        <option value="bank">حساب مصرفي (Bank)</option>
                                        <option value="wallet">محفظة كاش (Wallet)</option>
                                        <option value="exchange">مكتب صيرفة (Exchange)</option>
                                        <option value="cash">تسليم نقدي (Cash)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">العملة المقبولة</label>
                                    <select name="currency" class="form-select">
                                        <option value="ALL">كافة العملات (USD / IQD)</option>
                                        <option value="USD">دولار فقط (USD)</option>
                                        <option value="IQD">دينار فقط (IQD)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">اسم صاحب الحساب / الجهة المستلمة</label>
                                <input type="text" name="account_title" class="form-control" placeholder="مثال: شركة مجموعة العسافي للاستثمارات">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">رقم الحساب / IBAN / رقم المحفظة <span class="text-danger">*</span></label>
                                <input type="text" name="account_number" class="form-control" required style="font-family:monospace;" placeholder="IQ... أو 077...">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">تعليمات الإيداع للمستثمر</label>
                                <textarea name="instructions" class="form-control" rows="2" placeholder="مثال: يرجى كتابة اسمك في الملاحظات ورفع لقطة شاشة للإشعار..."></textarea>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ترتيب الظهور</label>
                                    <input type="number" name="display_order" class="form-control" value="0">
                                </div>
                                <div class="col-md-6 d-flex align-items-center pt-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="newActive" checked>
                                        <label class="form-check-label" for="newActive">تفعيل الحساب فوراً</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-gold">إضافة واعتماد</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
