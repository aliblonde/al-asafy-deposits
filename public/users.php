<?php
// public/users.php — Staff/Admin Management Listing
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/rbac.php';

requirePermission('users.manage');

$pdo = getPDO();
$pageTitle = 'إدارة الموظفين';

// Fetch all staff and admins (exclude investors from this management list)
$stmt = $pdo->query("SELECT id, username, role, last_login_at, created_at FROM users WHERE role IN ('admin', 'staff') ORDER BY role ASC, username ASC");
$users = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= $pageTitle ?>
                </h1>
                <p class="page-subtitle">إدارة حسابات المشرفين والموظفين في النظام</p>
            </div>
            <a href="user_add.php" class="btn btn-gold">
                <i class="bi bi-person-plus me-1"></i> إضافة موظف جديد
            </a>
        </div>

        <?php include __DIR__ . '/../includes/alerts.php'; ?>

        <div class="table-wrapper">
            <table class="table table-dark-custom mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم المستخدم</th>
                        <th>الصلاحية</th>
                        <th>آخر ظهور</th>
                        <th>تاريخ الإنشاء</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <?= $u['id'] ?>
                            </td>
                            <td class="fw-bold text-gold">
                                <?= htmlspecialchars($u['username']) ?>
                            </td>
                            <td>
                                <span class="badge" style="background:rgba(212,175,55,0.15); color:var(--gold)">
                                    <?= $u['role'] === 'admin' ? 'مشرف (Admin)' : 'موظف (Staff)' ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted">
                                    <?= $u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—' ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="text-center">
                                <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-gold"
                                    title="تعديل">
                                    <i class="bi bi-pencil-square"></i> تعديل
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">لا يوجد موظفين حالياً</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>