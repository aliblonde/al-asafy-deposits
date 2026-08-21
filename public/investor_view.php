<?php
// public/investor_view.php — View detailed investor information and documents
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: investors.php');
    exit;
}

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reset_password') {
        $newPwd = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';
        
        if (strlen($newPwd) < 6) {
            setFlash('danger', 'كلمة المرور الجديدة يجب أن تكون 6 خانات على الأقل.');
        } elseif ($newPwd !== $confirmPwd) {
            setFlash('danger', 'كلمتا المرور غير متطابقتين.');
        } else {
            try {
                // Fetch user linked to investor
                $stmt = $pdo->prepare("SELECT id, username FROM users WHERE investor_id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $hash = password_hash($newPwd, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hash, $user['id']]);
                    
                    logActivity($pdo, 'RESET_INVESTOR_PASSWORD', 'users', $user['id'], null, [
                        'reset_by' => currentUserId(),
                        'investor_id' => $id,
                        'username' => $user['username']
                    ]);
                    setFlash('success', 'تم إعادة تعيين كلمة المرور بنجاح للمستثمر: ' . $user['username']);
                } else {
                    setFlash('danger', 'المستثمر لا يملك حساب مستخدم مرتبط.');
                }
            } catch (Exception $e) {
                setFlash('danger', 'حدث خطأ أثناء تعديل كلمة المرور: ' . $e->getMessage());
            }
        }
        header("Location: investor_view.php?id=" . $id);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM investors WHERE id = ?");
$stmt->execute([$id]);
$investor = $stmt->fetch();

if (!$investor) {
    setFlash('danger', 'المستثمر غير موجود.');
    header('Location: investors.php');
    exit;
}

// Fetch linked user
$stmt = $pdo->prepare("SELECT username FROM users WHERE investor_id = ?");
$stmt->execute([$id]);
$linkedUser = $stmt->fetch();

// Fetch summary of deposits
$stmt = $pdo->prepare("
    SELECT currency, SUM(amount) as total 
    FROM deposits 
    WHERE investor_id = ? AND status='active' 
    GROUP BY currency
");
$stmt->execute([$id]);
$balances = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'عرض بيانات المستثمر';
include __DIR__ . '/../includes/header.php';
?>

<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-person-lines-fill me-2"></i>عرض بيانات المستثمر</h1>
                <div class="d-flex gap-2">
                    <a href="investor_add.php?edit=<?= $id ?>" class="btn btn-outline-gold btn-sm">
                        <i class="bi bi-pencil me-1"></i> تعديل
                    </a>
                    <a href="investors.php" class="btn btn-gold btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> العودة للقائمة
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Personal Info & Balances -->
                <div class="col-lg-8">
                    <div class="form-card mb-4">
                        <h5 class="section-title border-bottom pb-2 mb-3">
                            <i class="bi bi-person-badge me-2"></i>البيانات الأساسية
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted small d-block">الاسم الكامل</label>
                                <div class="fw-bold fs-5 text-gold">
                                    <?= htmlspecialchars($investor['full_name']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small d-block">رقم الهوية الوطنية</label>
                                <div class="fw-bold fs-5" style="font-family:monospace">
                                    <?= htmlspecialchars($investor['national_id']) ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small d-block">رقم الهاتف</label>
                                <div>
                                    <?= htmlspecialchars($investor['phone'] ?: '—') ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small d-block">المدينة</label>
                                <div>
                                    <?= htmlspecialchars($investor['city'] ?: '—') ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small d-block">تاريخ التسجيل</label>
                                <div>
                                    <?= date('Y/m/d H:i', strtotime($investor['created_at'])) ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small d-block">العنوان</label>
                                <div>
                                    <?= htmlspecialchars($investor['address'] ?: '—') ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small d-block">ملاحظات</label>
                                <div class="p-2 rounded bg-base small text-muted border border-gold"
                                    style="min-height: 50px;">
                                    <?= nl2br(htmlspecialchars($investor['notes'] ?: 'لا توجد ملاحظات.')) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h5 class="section-title border-bottom pb-2 mb-3">
                            <i class="bi bi-wallet2 me-2"></i>ملخص الودائع النشطة
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded bg-base border border-gold text-center">
                                    <div class="text-muted small mb-1">إجمالي الدينار العراقي (IQD)</div>
                                    <div class="fs-4 fw-bold text-gold">
                                        <?= formatMoney($balances['IQD'] ?? 0, 'IQD') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded bg-base border border-gold text-center">
                                    <div class="text-muted small mb-1">إجمالي الدولار الأمريكي (USD)</div>
                                    <div class="fs-4 fw-bold text-gold">
                                        <?= formatMoney($balances['USD'] ?? 0, 'USD') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-start d-flex gap-2">
                            <a href="deposit_add.php?investor_id=<?= $id ?>" class="btn btn-gold btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> إضافة وديعة لهذا المستثمر
                            </a>
                            <a href="reports.php?report=investor_statement&investor_id=<?= $id ?>"
                                class="btn btn-outline-gold btn-sm">
                                <i class="bi bi-file-earmark-text me-1"></i>عرض كشف الحساب المفصل
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Documents & Account -->
                <div class="col-lg-4">
                    <div class="form-card mb-4">
                        <h5 class="section-title border-bottom pb-2 mb-3">
                            <i class="bi bi-files me-2"></i>المستمسكات والوثائق
                        </h5>

                        <div class="mb-4">
                            <label class="form-label d-block small mb-2">عقد المستثمر</label>
                            <?php if ($investor['contract_path']): ?>
                                <a href="/al-asafy-deposits/<?= $investor['contract_path'] ?>" target="_blank"
                                    class="btn btn-outline-gold w-100 py-3">
                                    <i class="bi bi-file-pdf fs-3 d-block mb-1"></i>
                                    <span>استعراض العقد</span>
                                </a>
                            <?php else: ?>
                                <div class="alert alert-secondary py-3 text-center small mb-0">
                                    <i class="bi bi-exclamation-circle me-1"></i> لم يتم رفع العقد
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-2">
                            <label class="form-label d-block small mb-2">هوية المستثمر</label>
                            <?php if ($investor['id_card_path']): ?>
                                <a href="/al-asafy-deposits/<?= $investor['id_card_path'] ?>" target="_blank"
                                    class="btn btn-outline-gold w-100 py-3">
                                    <i class="bi bi-person-vcard fs-3 d-block mb-1"></i>
                                    <span>استعراض الهوية</span>
                                </a>
                            <?php else: ?>
                                <div class="alert alert-secondary py-3 text-center small mb-0">
                                    <i class="bi bi-exclamation-circle me-1"></i> لم يتم رفع الهوية
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-card">
                        <h5 class="section-title border-bottom pb-2 mb-3">
                            <i class="bi bi-shield-lock me-2"></i>حساب الدخول
                        </h5>
                        <?php if ($linkedUser): ?>
                            <div class="d-flex align-items-center p-2 rounded bg-base border border-success">
                                <i class="bi bi-check-circle-fill text-success fs-4 me-2"></i>
                                <div>
                                    <div class="fw-bold">اسم المستخدم: <span class="text-gold">
                                            <?= htmlspecialchars($linkedUser['username']) ?>
                                        </span></div>
                                    <small class="text-muted">الحساب مرتبط ونشط</small>
                                </div>
                            </div>

                            <button class="btn btn-sm btn-outline-warning w-100 mt-3" type="button" data-bs-toggle="collapse" data-bs-target="#resetPasswordForm" aria-expanded="false" aria-controls="resetPasswordForm">
                                <i class="bi bi-key me-1"></i> إعادة تعيين كلمة المرور
                            </button>

                            <div class="collapse mt-3" id="resetPasswordForm">
                                <div class="p-3 rounded bg-base border border-warning">
                                    <form method="POST" action="">
                                        <?= csrfField(); ?>
                                        <input type="hidden" name="action" value="reset_password">
                                        <div class="mb-2">
                                            <label class="form-label small text-muted">كلمة المرور الجديدة</label>
                                            <input type="password" name="new_password" class="form-control form-control-sm" required minlength="6" placeholder="6 خانات على الأقل">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small text-muted">تأكيد كلمة المرور</label>
                                            <input type="password" name="confirm_password" class="form-control form-control-sm" required minlength="6" placeholder="أعد إدخال كلمة المرور">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-warning w-100 mt-2">
                                            <i class="bi bi-check-circle me-1"></i> حفظ كلمة المرور
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center p-2 rounded bg-base border border-warning">
                                <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-2"></i>
                                <div>
                                    <div class="fw-bold">لا يوجد حساب مرتبط</div>
                                    <small class="text-muted">المستثمر لا يملك وصولاً للبوابة</small>
                                </div>
                            </div>
                            <a href="investor_add.php?edit=<?= $id ?>" class="btn btn-gold btn-sm w-100 mt-3">إنشاء حساب
                                الآن</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>