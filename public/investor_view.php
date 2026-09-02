<?php
// public/investor_view.php — View detailed investor information and documents
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/rbac.php';

requirePermission('investors.view');
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
    
        if ($action === 'add_attachment') {
        if (!userCan('investors.edit')) {
            http_response_code(403);
            setFlash('danger', 'لا تملك صلاحية لرفع مرفقات.');
        } else {
            $title = trim($_POST['title'] ?? 'مرفق إضافي');
            $file = $_FILES['attachment_file'] ?? null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                if ($file['size'] > 50 * 1024 * 1024) {
                    setFlash('danger', 'حجم الملف يتجاوز 50 ميجا.');
                } else {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'zip', 'rar'])) {
                        setFlash('danger', 'النوع غير مدعوم. المسموح: PDF, JPG, PNG, ZIP, RAR');
                    } else {
                        $dest = 'uploads/investors/att_' . uniqid() . '_' . basename($file['name']);
                        if (move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $dest)) {
                            try {
                                $stmt = $pdo->prepare("INSERT INTO investor_attachments (investor_id, title, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$id, $title, $dest, currentUserId()]);
                                setFlash('success', 'تم رفع المرفق بنجاح.');
                            } catch (\PDOException $e) {
                                setFlash('danger', 'خطأ في قاعدة البيانات: يرجى التأكد من إنشاء جدول المرفقات (تشغيل update_attachments_db.php). ' . $e->getMessage());
                            }
                        } else {
                            setFlash('danger', 'فشل في حفظ الملف.');
                        }
                    }
                }
            } else {
                setFlash('danger', 'يرجى اختيار ملف صالح.');
            }
        }
        header('Location: investor_view.php?id=' . $id);
        exit;
    }
    
    if ($action === 'delete_attachment') {
        if (!userCan('investors.edit')) {
            http_response_code(403);
            setFlash('danger', 'لا تملك صلاحية للحذف.');
        } else {
            $attId = (int)($_POST['attachment_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT file_path FROM investor_attachments WHERE id = ? AND investor_id = ?");
            $stmt->execute([$attId, $id]);
            $att = $stmt->fetch();
            if ($att) {
                @unlink(__DIR__ . '/../' . $att['file_path']);
                $pdo->prepare("DELETE FROM investor_attachments WHERE id = ?")->execute([$attId]);
                setFlash('success', 'تم حذف المرفق.');
            }
        }
        header('Location: investor_view.php?id=' . $id);
        exit;
    }
    
    if ($action === 'reset_password') {
        // Section 4: Require explicit permission for password reset
        if (!userCan('investor_accounts.reset_password')) {
            http_response_code(403);
            setFlash('danger', 'ليس لديك صلاحية إعادة تعيين كلمة مرور المستثمر.');
            header('Location: investor_view.php?id=' . $id);
            exit;
        }

        $newPwd = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';
        
        $passCheck = validatePasswordPolicy($newPwd);
        if (!$passCheck['valid']) {
            setFlash('danger', $passCheck['error']);
        } elseif ($newPwd !== $confirmPwd) {
            setFlash('danger', 'كلمتا المرور غير متطابقتين.');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username FROM users WHERE investor_id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $hash = password_hash($newPwd, PASSWORD_DEFAULT);
                    // Section 4: Increment session_version to invalidate all active sessions
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?");
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

// Fetch extra attachments
$stmtAtt = $pdo->prepare("SELECT * FROM investor_attachments WHERE investor_id = ? ORDER BY created_at DESC");
$stmtAtt->execute([$id]);
$extraAttachments = $stmtAtt->fetchAll();

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
                                <a href="download_file.php?investor_id=<?= $id ?>&type=contract"
                                    class="btn btn-outline-gold w-100 py-3">
                                    <i class="bi bi-file-pdf fs-3 d-block mb-1"></i>
                                    <span>تحميل/استعراض العقد</span>
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
                                <a href="download_file.php?investor_id=<?= $id ?>&type=id_card"
                                    class="btn btn-outline-gold w-100 py-3">
                                    <i class="bi bi-person-vcard fs-3 d-block mb-1"></i>
                                    <span>تحميل/استعراض الهوية</span>
                                </a>
                            <?php else: ?>
                                <div class="alert alert-secondary py-3 text-center small mb-0">
                                    <i class="bi bi-exclamation-circle me-1"></i> لم يتم رفع الهوية
                                </div>
                                                        <?php endif; ?>
                        </div>
                    </div>

                    <!-- Extra Attachments -->
                    <div class="form-card mb-4">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="section-title mb-0">
                                <i class="bi bi-paperclip me-2"></i>المرفقات الإضافية
                            </h5>
                            <?php if (userCan('investors.edit')): ?>
                                <button type="button" class="btn btn-sm btn-gold" data-bs-toggle="modal" data-bs-target="#uploadAttModal">
                                    <i class="bi bi-plus-lg"></i> إضافة
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($extraAttachments)): ?>
                            <div class="alert alert-secondary text-center small mb-0">
                                <i class="bi bi-info-circle me-1"></i> لا توجد مرفقات إضافية
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush" style="direction: rtl;">
                                <?php foreach ($extraAttachments as $att): ?>
                                    <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center border-secondary">
                                        <div>
                                            <i class="bi bi-file-earmark me-2 text-gold"></i>
                                            <a href="<?= htmlspecialchars($att['file_path']) ?>" target="_blank" class="text-white text-decoration-none" style="color:var(--text-color) !important;">
                                                <?= htmlspecialchars($att['title']) ?>
                                            </a>
                                            <div class="small text-muted" style="font-size:0.75rem;margin-right:24px;">
                                                <?= date('Y-m-d', strtotime($att['created_at'])) ?>
                                            </div>
                                        </div>
                                        <?php if (userCan('investors.edit')): ?>
                                            <form method="POST" action="" class="m-0 p-0" onsubmit="return confirm('هل أنت متأكد من حذف هذا المرفق؟');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete_attachment">
                                                <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
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
                                            <input type="password" name="new_password" class="form-control form-control-sm" required minlength="10" placeholder="10 خانات على الأقل">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small text-muted">تأكيد كلمة المرور</label>
                                            <input type="password" name="confirm_password" class="form-control form-control-sm" required minlength="10" placeholder="أعد إدخال كلمة المرور">
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
        
<?php if (userCan('investors.edit')): ?>
<!-- Upload Attachment Modal -->
<div class="modal fade" id="uploadAttModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" enctype="multipart/form-data" class="modal-content text-start" dir="rtl">
            <div class="modal-header">
                <h5 class="modal-title">إضافة مرفق جديد للمستثمر</h5>
                <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_attachment">
                <div class="mb-3">
                    <label class="form-label text-white" style="color:var(--text-color) !important;">اسم/نوع المرفق <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="مثال: عقد إضافي، إيصال تحويل، الخ...">
                </div>
                <div class="mb-3">
                    <label class="form-label text-white" style="color:var(--text-color) !important;">الملف <span class="text-danger">*</span></label>
                    <input type="file" name="attachment_file" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.zip,.rar">
                    <small class="text-muted d-block mt-1">المسموح: PDF, JPG, PNG, ZIP, RAR (الحد الأقصى 50 ميجا)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-gold">رفع وحفظ</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>