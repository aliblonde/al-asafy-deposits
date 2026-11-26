<?php
// public/investor_view.php — View detailed investor information and documents
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/notifications.php';

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
    
        if ($action === 'credit_wallet') {
        if (!userCan('investors.edit')) {
            http_response_code(403);
            setFlash('danger', 'ليس لديك صلاحية لشحن المحفظة.');
            header('Location: investor_view.php?id=' . $id);
            exit;
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
        $note = trim((string)($_POST['note'] ?? 'شحن رصيد محفظة / أرباح استثنائية من الإدارة'));

        if ($amount <= 0) {
            setFlash('danger', 'يرجى إدخال مبلغ صحيح أكبر من الصفر.');
        } elseif (!in_array($currency, ['USD', 'IQD'], true)) {
            setFlash('danger', 'العملة المختارة غير مدعومة.');
        } else {
            try {
                $pdo->beginTransaction();

                // Ensure table exists
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `wallet_balances` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `investor_id` INT UNSIGNED NOT NULL,
                      `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
                      `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uq_wb_investor_currency` (`investor_id`, `currency`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                    CREATE TABLE IF NOT EXISTS `wallet_transactions` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `investor_id` INT UNSIGNED NOT NULL,
                      `type` ENUM('topup_credit','admin_credit','profit_transfer','withdrawal','investment_out') NOT NULL,
                      `direction` ENUM('credit','debit') NOT NULL DEFAULT 'credit',
                      `amount` DECIMAL(14,2) NOT NULL,
                      `currency` ENUM('IQD','USD') NOT NULL DEFAULT 'USD',
                      `balance_after` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                      `reference_id` INT UNSIGNED NULL,
                      `note` VARCHAR(255) NULL,
                      `created_by` INT UNSIGNED NULL,
                      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Update wallet_balances
                $stmtWb = $pdo->prepare("
                    INSERT INTO wallet_balances (investor_id, currency, balance, updated_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance), updated_at = NOW()
                ");
                $stmtWb->execute([$id, $currency, $amount]);

                // Fetch new balance
                $bStmt = $pdo->prepare("SELECT balance FROM wallet_balances WHERE investor_id = ? AND currency = ?");
                $bStmt->execute([$id, $currency]);
                $newBal = (float)$bStmt->fetchColumn();

                // Insert into wallet_transactions
                $txW = $pdo->prepare("
                    INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, note, created_by, created_at)
                    VALUES (?, 'admin_credit', 'credit', ?, ?, ?, ?, ?, NOW())
                ");
                $txW->execute([$id, $amount, $currency, $newBal, $note, currentUserId()]);

                // Insert into transactions
                $receiptNo = 'WCR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $txMain = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
                    VALUES (?, ?, 'profit_payout', 'credit', ?, ?, NOW(), ?)
                ");
                $txMain->execute([$receiptNo, $id, $amount, $currency, '[شحن محفظة] ' . $note]);

                // Log Activity
                logActivity($pdo, 'ADMIN_CREDIT_WALLET', 'wallet_balances', $id, null, [
                    'admin_id' => currentUserId(),
                    'investor_id' => $id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'new_balance' => $newBal,
                    'note' => $note
                ]);

                $pdo->commit();

                // Send instant Firebase push notification & in-app
                $formattedAmount = number_format($amount, 2);
                notifyInvestor(
                    $pdo,
                    $id,
                    'إيداع رصيد في محفظتك 💳',
                    "تمت إضافة مبلغ {$formattedAmount} {$currency} إلى محفظتك بنجاح من الإدارة. الملاحظة: {$note}",
                    'wallet'
                );

                setFlash('success', "تم شحن محفظة المستثمر بمبلغ {$formattedAmount} {$currency} بنجاح وإرسال إشعار فوري له.");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('danger', 'حدث خطأ أثناء شحن المحفظة: ' . $e->getMessage());
            }
        }
        header('Location: investor_view.php?id=' . $id);
        exit;
    }

    if ($action === 'deduct_wallet') {
        if (!userCan('wallets.adjust') && !userCan('investors.edit') && currentRole() !== 'admin') {
            http_response_code(403);
            setFlash('danger', 'ليس لديك صلاحية لخصم أو سحب رصيد من المحفظة.');
            header('Location: investor_view.php?id=' . $id);
            exit;
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($amount <= 0) {
            setFlash('danger', 'يرجى إدخال مبلغ سحب صحيح أكبر من الصفر.');
        } elseif (empty($note)) {
            setFlash('danger', 'يرجى كتابة سبب الخصم/السحب الإداري لتوثيقه في السجل.');
        } elseif (!in_array($currency, ['USD', 'IQD'], true)) {
            setFlash('danger', 'العملة المختارة غير مدعومة.');
        } else {
            try {
                $pdo->beginTransaction();

                $bStmt = $pdo->prepare("SELECT balance FROM wallet_balances WHERE investor_id = ? AND currency = ? FOR UPDATE");
                $bStmt->execute([$id, $currency]);
                $currBal = (float)($bStmt->fetchColumn() ?: 0.0);

                if ($amount > $currBal) {
                    throw new Exception("المبلغ المراد سحبه (" . formatMoney($amount, $currency) . ") أكبر من رصيد المحفظة المتاح حالياً (" . formatMoney($currBal, $currency) . ").");
                }

                $newBal = max(0.0, $currBal - $amount);
                $upWb = $pdo->prepare("UPDATE wallet_balances SET balance = ?, updated_at = NOW() WHERE investor_id = ? AND currency = ?");
                $upWb->execute([$newBal, $id, $currency]);

                $txW = $pdo->prepare("
                    INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, note, created_by, created_at)
                    VALUES (?, 'admin_debit', 'debit', ?, ?, ?, ?, ?, NOW())
                ");
                $txW->execute([$id, $amount, $currency, $newBal, "[خصم/سحب إداري] {$note}", currentUserId()]);

                $receiptNo = 'WDR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $txMain = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
                    VALUES (?, ?, 'withdrawal', 'debit', ?, ?, NOW(), ?)
                ");
                $txMain->execute([$receiptNo, $id, $amount, $currency, "[خصم محفظة إداري] {$note}"]);

                logActivity($pdo, 'ADMIN_DEDUCT_WALLET', 'wallet_balances', $id, ['old_balance' => $currBal], [
                    'admin_id' => currentUserId(),
                    'investor_id' => $id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'new_balance' => $newBal,
                    'note' => $note
                ]);

                $pdo->commit();

                $fmtAmt = number_format($amount, 2);
                notifyInvestor(
                    $pdo,
                    $id,
                    'إشعار سحب من المحفظة ⚠️',
                    "تم سحب/خصم مبلغ {$fmtAmt} {$currency} من محفظتك من قبل الإدارة. السبب: {$note}",
                    'wallet'
                );

                setFlash('success', "تم سحب مبلغ {$fmtAmt} {$currency} من محفظة المستثمر بنجاح وتوثيق العملية في السجل.");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('danger', 'حدث خطأ أثناء السحب من المحفظة: ' . $e->getMessage());
            }
        }
        header('Location: investor_view.php?id=' . $id);
        exit;
    }

    if ($action === 'reset_wallet') {
        if (!userCan('wallets.adjust') && !userCan('investors.edit') && currentRole() !== 'admin') {
            http_response_code(403);
            setFlash('danger', 'ليس لديك صلاحية لتصفير المحفظة.');
            header('Location: investor_view.php?id=' . $id);
            exit;
        }

        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
        $note = trim((string)($_POST['note'] ?? 'تصفير رصيد المحفظة إدارياً'));

        if (!in_array($currency, ['USD', 'IQD'], true)) {
            setFlash('danger', 'العملة المختارة غير مدعومة.');
        } elseif (empty($note)) {
            setFlash('danger', 'يرجى كتابة سبب التصفير لتوثيقه في السجل والرقابة.');
        } else {
            try {
                $pdo->beginTransaction();

                $bStmt = $pdo->prepare("SELECT balance FROM wallet_balances WHERE investor_id = ? AND currency = ? FOR UPDATE");
                $bStmt->execute([$id, $currency]);
                $currBal = (float)($bStmt->fetchColumn() ?: 0.0);

                if ($currBal <= 0) {
                    throw new Exception("رصيد محفظة المستثمر بالـ {$currency} مصفّر بالفعل (0.00).");
                }

                $upWb = $pdo->prepare("UPDATE wallet_balances SET balance = 0.00, updated_at = NOW() WHERE investor_id = ? AND currency = ?");
                $upWb->execute([$id, $currency]);

                $txW = $pdo->prepare("
                    INSERT INTO wallet_transactions (investor_id, type, direction, amount, currency, balance_after, note, created_by, created_at)
                    VALUES (?, 'admin_reset', 'debit', ?, ?, 0.00, ?, ?, NOW())
                ");
                $txW->execute([$id, $currBal, $currency, "[تصفير رصيد إداري] {$note}", currentUserId()]);

                $receiptNo = 'RST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $txMain = $pdo->prepare("
                    INSERT INTO transactions (receipt_no, investor_id, type, direction, amount, currency, date, note)
                    VALUES (?, ?, 'withdrawal', 'debit', ?, ?, NOW(), ?)
                ");
                $txMain->execute([$receiptNo, $id, $currBal, $currency, "[تصفير محفظة إداري] {$note}"]);

                logActivity($pdo, 'ADMIN_RESET_WALLET', 'wallet_balances', $id, ['old_balance' => $currBal], [
                    'admin_id' => currentUserId(),
                    'investor_id' => $id,
                    'reset_amount' => $currBal,
                    'currency' => $currency,
                    'note' => $note
                ]);

                $pdo->commit();

                $fmtAmt = number_format($currBal, 2);
                notifyInvestor(
                    $pdo,
                    $id,
                    'تصفير رصيد المحفظة ℹ️',
                    "تم تصفير رصيد محفظتك بقيمة {$fmtAmt} {$currency} من قبل الإدارة. البيان: {$note}",
                    'wallet'
                );

                setFlash('success', "تم تصفير رصيد محفظة المستثمر بالـ {$currency} بنجاح (المبلغ المصفّر: {$fmtAmt} {$currency}).");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('danger', 'حدث خطأ أثناء تصفير المحفظة: ' . $e->getMessage());
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

// Fetch wallet balances
$walletBalances = ['IQD' => 0.0, 'USD' => 0.0];
try {
    $wbStmt = $pdo->prepare("SELECT currency, balance FROM wallet_balances WHERE investor_id = ?");
    $wbStmt->execute([$id]);
    while ($wbr = $wbStmt->fetch()) {
        $walletBalances[$wbr['currency']] = (float)$wbr['balance'];
    }
} catch (\Throwable $e) {}

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
                    <?php if (userCan('wallets.adjust') || userCan('investors.edit') || currentRole() === 'admin'): ?>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                        <i class="bi bi-plus-circle me-1"></i> شحن المحفظة
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#deductWalletModal">
                        <i class="bi bi-dash-circle me-1"></i> سحب من المحفظة
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#resetWalletModal">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> تصفير المحفظة
                    </button>
                    <?php endif; ?>
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

                                        <!-- Digital Wallet Card -->
                    <div class="form-card mb-4 border border-warning" style="background: linear-gradient(135deg, rgba(212,175,55,0.08) 0%, rgba(20,24,31,0.95) 100%);">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="section-title mb-0 text-gold">
                                <i class="bi bi-wallet2 me-2"></i>رصيد المحفظة المتاح (الأرباح والرصيد المشحون)
                            </h5>
                            <?php if (userCan('wallets.adjust') || userCan('investors.edit') || currentRole() === 'admin'): ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                                    <i class="bi bi-plus-circle me-1"></i> شحن رصيد
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#deductWalletModal">
                                    <i class="bi bi-dash-circle me-1"></i> سحب رصيد
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#resetWalletModal">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> تصفير المحفظة
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded bg-base border border-gold text-center">
                                    <div class="text-muted small mb-1">رصيد المحفظة الحر (IQD)</div>
                                    <div class="fs-4 fw-bold text-success">
                                        <?= formatMoney($walletBalances['IQD'] ?? 0, 'IQD') ?>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">مستقل عن دورة الأرباح الشهرية</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded bg-base border border-gold text-center">
                                    <div class="text-muted small mb-1">رصيد المحفظة الحر (USD)</div>
                                    <div class="fs-4 fw-bold text-success">
                                        <?= formatMoney($walletBalances['USD'] ?? 0, 'USD') ?>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">مستقل عن دورة الأرباح الشهرية</small>
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
                                            <a href="download_file.php?investor_id=<?= $id ?>&type=extra_attachment&attachment_id=<?= $att['id'] ?>" target="_blank" class="text-white text-decoration-none" style="color:var(--text-color) !important;">
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

                                        <!-- Digital Wallet Card -->
                    <div class="form-card mb-4 border border-warning" style="background: linear-gradient(135deg, rgba(212,175,55,0.08) 0%, rgba(20,24,31,0.95) 100%);">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="section-title mb-0 text-gold">
                                <i class="bi bi-wallet2 me-2"></i>رصيد المحفظة المتاح (الأرباح والرصيد المشحون)
                            </h5>
                            <?php if (userCan('wallets.adjust') || userCan('investors.edit') || currentRole() === 'admin'): ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                                    <i class="bi bi-plus-circle me-1"></i> شحن رصيد
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#deductWalletModal">
                                    <i class="bi bi-dash-circle me-1"></i> سحب رصيد
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#resetWalletModal">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> تصفير المحفظة
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded bg-base border border-gold text-center">
                                    <div class="text-muted small mb-1">رصيد المحفظة الحر (IQD)</div>
                                    <div class="fs-4 fw-bold text-success">
                                        <?= formatMoney($walletBalances['IQD'] ?? 0, 'IQD') ?>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">مستقل عن دورة الأرباح الشهرية</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded bg-base border border-gold text-center">
                                    <div class="text-muted small mb-1">رصيد المحفظة الحر (USD)</div>
                                    <div class="fs-4 fw-bold text-success">
                                        <?= formatMoney($walletBalances['USD'] ?? 0, 'USD') ?>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">مستقل عن دورة الأرباح الشهرية</small>
                                </div>
                            </div>
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
                            <?php if (userCan('wallets.adjust') || userCan('investors.edit') || currentRole() === 'admin'): ?>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#creditWalletModal">
                        <i class="bi bi-plus-circle me-1"></i> شحن المحفظة
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#deductWalletModal">
                        <i class="bi bi-dash-circle me-1"></i> سحب من المحفظة
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#resetWalletModal">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> تصفير المحفظة
                    </button>
                    <?php endif; ?>
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
<!-- Credit Wallet Modal -->
<div class="modal fade" id="creditWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content text-start" dir="rtl">
            <div class="modal-header">
                <h5 class="modal-title text-gold"><i class="bi bi-wallet2 me-2"></i>شحن محفظة المستثمر</h5>
                <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="credit_wallet">
                
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    سيتم إضافة هذا المبلغ فوراً إلى محفظة المستثمر كرصيد أرباح متاح للسحب، وهو <strong>مستقل تماماً عن دورة الأرباح الشهرية</strong> ولن يؤثر على مواعيد استحقاق الودائع.
                </div>

                <div class="mb-3">
                    <label class="form-label">المبلغ المطلوب إيداعه <span class="text-danger">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
                </div>

                <div class="mb-3">
                    <label class="form-label">العملة <span class="text-danger">*</span></label>
                    <select name="currency" class="form-select" required>
                        <option value="USD">دولار أمريكي (USD)</option>
                        <option value="IQD">دينار عراقي (IQD)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">الملاحظة / سبب الشحن <span class="text-danger">*</span></label>
                    <input type="text" name="note" class="form-control" required placeholder="مثال: إيداع نقدي بالفرع، أرباح استثنائية، تسوية حساب...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>تأكيد الشحن الفوري</button>
            </div>
        </form>
    </div>
</div>

<!-- Deduct Wallet Modal -->
<div class="modal fade" id="deductWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content text-start" dir="rtl">
            <div class="modal-header bg-dark border-secondary">
                <h5 class="modal-title text-warning"><i class="bi bi-dash-circle me-2"></i>سحب / خصم رصيد من المحفظة</h5>
                <button type="button" class="btn-close btn-close-white ms-0 me-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-dark text-white">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="deduct_wallet">
                
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    سيتم خصم هذا المبلغ من رصيد المحفظة الحر للمستثمر وتوثيقه في السجل المالي، وإرسال إشعار فوري له.
                </div>

                <div class="mb-3">
                    <label class="form-label">المبلغ المطلوب سحبه / خصمه <span class="text-danger">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
                </div>

                <div class="mb-3">
                    <label class="form-label">العملة <span class="text-danger">*</span></label>
                    <select name="currency" class="form-select" required>
                        <option value="USD">دولار أمريكي (USD) — الحالي: <?= formatMoney($walletBalances['USD'] ?? 0, 'USD') ?></option>
                        <option value="IQD">دينار عراقي (IQD) — الحالي: <?= formatMoney($walletBalances['IQD'] ?? 0, 'IQD') ?></option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">سبب السحب / الخصم <span class="text-danger">*</span></label>
                    <input type="text" name="note" class="form-control" required placeholder="مثال: تسليم نقدي، سحب بناء على طلب المستثمر، تصحيح قيد...">
                </div>
            </div>
            <div class="modal-footer bg-dark border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-warning" onclick="return confirm('هل أنت متأكد من خصم هذا المبلغ من محفظة المستثمر؟')">
                    <i class="bi bi-check-circle me-1"></i>تأكيد السحب والخصم
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Wallet Modal -->
<div class="modal fade" id="resetWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content text-start" dir="rtl">
            <div class="modal-header bg-dark border-danger">
                <h5 class="modal-title text-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>تصفير رصيد المحفظة</h5>
                <button type="button" class="btn-close btn-close-white ms-0 me-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-dark text-white">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reset_wallet">
                
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="bi bi-shield-slash me-1"></i>
                    <strong>تحذير:</strong> هذا الإجراء سيقوم بإعادة رصيد المحفظة للعملة المختارة إلى <strong>0.00</strong> فوراً وتوثيق العملية في سجل التدقيق.
                </div>

                <div class="mb-3">
                    <label class="form-label">اختر محفظة العملة المراد تصفيرها: <span class="text-danger">*</span></label>
                    <select name="currency" class="form-select" required>
                        <option value="USD">محفظة الدولار (USD) — الرصيد: <?= formatMoney($walletBalances['USD'] ?? 0, 'USD') ?></option>
                        <option value="IQD">محفظة الدينار (IQD) — الرصيد: <?= formatMoney($walletBalances['IQD'] ?? 0, 'IQD') ?></option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">سبب تصفير المحفظة <span class="text-danger">*</span></label>
                    <textarea name="note" class="form-control" rows="2" required placeholder="اكتب سبب التصفير بوضوح للتوثيق والرقابة المالية..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-dark border-danger">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger" onclick="return confirm('تأكيد نهائي: هل أنت متأكد من تصفير رصيد محفظة هذا المستثمر؟ لا يمكن التراجع عن هذا الإجراء.')">
                    <i class="bi bi-trash3 me-1"></i>تأكيد تصفير الرصيد
                </button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>