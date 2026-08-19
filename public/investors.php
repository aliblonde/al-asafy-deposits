<?php
// public/investors.php — List & manage investors
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

$search = trim($_GET['search'] ?? '');
$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = '(i.full_name LIKE ? OR i.phone LIKE ? OR i.national_id LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$stmt = $pdo->prepare(
    "SELECT i.*,
            COUNT(d.id)                                        AS total_deposits,
            COALESCE(SUM(CASE WHEN d.status='active' THEN d.amount END), 0) AS active_balance
     FROM investors i
     LEFT JOIN deposits d ON d.investor_id = i.id
     WHERE " . implode(' AND ', $where) . "
     GROUP BY i.id
     ORDER BY i.created_at DESC"
);
$stmt->execute($params);
$investors = $stmt->fetchAll();

$pageTitle = 'إدارة المستثمرين';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <div class="page-header">
                <h1 class="page-title"><i class="bi bi-people me-2"></i>إدارة المستثمرين</h1>
                <a href="investor_add.php" class="btn btn-gold">
                    <i class="bi bi-person-plus me-1"></i> إضافة مستثمر
                </a>
            </div>

            <!-- Search -->
            <form method="get" class="filter-bar mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>"
                            placeholder="ابحث بالاسم أو الهاتف أو الهوية...">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-gold btn-sm"><i class="bi bi-search"></i> بحث</button>
                        <a href="investors.php" class="btn btn-outline-gold btn-sm ms-1">مسح</a>
                    </div>
                </div>
            </form>

            <div class="table-wrapper">
                <div class="p-3 border-bottom border-gold">
                    <span class="section-title mb-0">المستثمرون (
                        <?= count($investors) ?>)
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم الكامل</th>
                                <th>رقم الهوية</th>
                                <th>الهاتف</th>
                                <th>عدد الودائع</th>
                                <th>الرصيد النشط (ر.س)</th>
                                <th>تاريخ التسجيل</th>
                                <?php if (currentRole() === 'admin'): ?>
                                    <th>إجراءات</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($investors as $inv): ?>
                                <tr>
                                    <td>
                                        <?= $inv['id'] ?>
                                    </td>
                                    <td><strong>
                                            <?= htmlspecialchars($inv['full_name']) ?>
                                        </strong></td>
                                    <td style="font-family:monospace">
                                        <?= htmlspecialchars($inv['national_id'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($inv['phone'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-type-medium">
                                            <?= (int) $inv['total_deposits'] ?>
                                        </span>
                                    </td>
                                    <td class="text-gold fw-bold">
                                        <?= formatMoney($inv['active_balance']) ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($inv['created_at'])) ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="investor_view.php?id=<?= $inv['id'] ?>"
                                            class="btn btn-outline-info btn-sm me-1" title="عرض التفاصيل">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="investor_add.php?edit=<?= $inv['id'] ?>"
                                            class="btn btn-outline-gold btn-sm" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($investors)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">لا يوجد مستثمرون</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>