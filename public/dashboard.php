<?php
// public/dashboard.php — Admin/Staff Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/csrf.php';

requireRole(['admin', 'staff']);
$pdo = getPDO();

// Auto-close expired deposits
autoCloseExpiredDeposits($pdo);

// ── Stat Cards ──────────────────────────────────────────────
$stats = [];

// Total active deposit balance by currency
$r = $pdo->query("SELECT currency, COALESCE(SUM(amount),0) AS total FROM deposits WHERE status='active' GROUP BY currency");
$stats['total_balance'] = $r->fetchAll(PDO::FETCH_KEY_PAIR);
if (!isset($stats['total_balance']['IQD']))
    $stats['total_balance']['IQD'] = 0;
if (!isset($stats['total_balance']['USD']))
    $stats['total_balance']['USD'] = 0;

// Investor count
$stats['investors'] = (int) $pdo->query("SELECT COUNT(*) FROM investors")->fetchColumn();

// Profits in last 30 days by currency
$r = $pdo->query("SELECT currency, COALESCE(SUM(amount),0) AS total FROM transactions WHERE type='profit' AND date >= DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY currency");
$stats['profits_30d'] = $r->fetchAll(PDO::FETCH_KEY_PAIR);
if (!isset($stats['profits_30d']['IQD']))
    $stats['profits_30d']['IQD'] = 0;
if (!isset($stats['profits_30d']['USD']))
    $stats['profits_30d']['USD'] = 0;

// Deposits ending within 7 days
$r = $pdo->query("SELECT COUNT(*) FROM deposits WHERE status='active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)");
$stats['ending_7d'] = (int) $r->fetchColumn();

// ── Alerts: Due profits ─────────────────────────────────────
// Build list with next_profit_date computed per deposit
$activeDeposits = $pdo->query(
    "SELECT d.*, i.full_name, dt.name_ar, dt.code
     FROM deposits d
     JOIN investors i  ON i.id = d.investor_id
     JOIN deposit_types dt ON dt.id = d.deposit_type_id
     WHERE d.status='active'
     ORDER BY d.start_date"
)->fetchAll();

$alertsToday = [];
$alerts3Day = [];
$alerts7Day = [];
$today = new DateTimeImmutable(date('Y-m-d'));

foreach ($activeDeposits as $dep) {
    // ── Profits Alert Logic
    $next = calcNextProfitDate($dep);
    if ($next) {
        $dep['next_profit_date'] = $next->format('Y-m-d');
        $diff = (int) $today->diff($next)->format('%r%a'); // negative = overdue
        if ($diff <= 0) {
            $alertsToday[] = $dep;
        } elseif ($diff <= 3) {
            $alerts3Day[] = $dep;
        } elseif ($diff <= 7) {
            $alerts7Day[] = $dep;
        }
    }
}

// ── Alerts: Expiry/Completion ───────────────────────────────
$alertsExpiryEnded = [];
$alertsExpiry3Day = [];
$alertsExpiry7Day = [];

foreach ($activeDeposits as $dep) {
    if (!$dep['end_date'])
        continue;

    $endDate = new DateTimeImmutable($dep['end_date']);
    $diffExpiry = (int) $today->diff($endDate)->format('%r%a'); // negative = passed

    if ($diffExpiry <= 0) {
        $alertsExpiryEnded[] = $dep;
    } elseif ($diffExpiry <= 3) {
        $alertsExpiry3Day[] = $dep;
    } elseif ($diffExpiry <= 7) {
        $alertsExpiry7Day[] = $dep;
    }
}

// ── Latest 10 deposits ──────────────────────────────────────
$latestDeposits = $pdo->query(
    "SELECT d.id, d.amount, d.start_date, d.end_date, dt.min_rate AS profit_rate_monthly, d.status,
            d.last_profit_date,
            i.full_name,
            dt.name_ar, dt.code
     FROM deposits d
     JOIN investors i   ON i.id = d.investor_id
     JOIN deposit_types dt ON dt.id = d.deposit_type_id
     ORDER BY d.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'لوحة التحكم';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="page-content">
            <?php include __DIR__ . '/../includes/alerts.php'; ?>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-speedometer2 me-2"></i>لوحة التحكم</h1>
                    <p class="page-subtitle">نظرة عامة على الودائع والأرباح —
                        <?= date('d/m/Y') ?>
                    </p>
                </div>
                <a href="deposit_add.php" class="btn btn-gold">
                    <i class="bi bi-plus-lg me-1"></i> إضافة وديعة
                </a>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="bi bi-bank"></i></div>
                        <div class="stat-card-value" style="font-size:1.1rem">
                            <div><?= formatMoney($stats['total_balance']['IQD'], 'IQD') ?></div>
                            <div class="mt-1" style="color:var(--gold-light)">
                                <?= formatMoney($stats['total_balance']['USD'], 'USD') ?>
                            </div>
                        </div>
                        <div class="stat-card-label">إجمالي أرصدة الودائع النشطة</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="bi bi-people"></i></div>
                        <div class="stat-card-value">
                            <?= $stats['investors'] ?>
                        </div>
                        <div class="stat-card-label">عدد المستثمرين</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="stat-card-value" style="font-size:1.1rem">
                            <div><?= formatMoney($stats['profits_30d']['IQD'], 'IQD') ?></div>
                            <div class="mt-1" style="color:var(--gold-light)">
                                <?= formatMoney($stats['profits_30d']['USD'], 'USD') ?>
                            </div>
                        </div>
                        <div class="stat-card-label">أرباح مصروفة آخر 30 يوم</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="bi bi-calendar-x"></i></div>
                        <div class="stat-card-value">
                            <?= $stats['ending_7d'] ?>
                        </div>
                        <div class="stat-card-label">ودائع تنتهي خلال 7 أيام</div>
                    </div>
                </div>
            </div>

            <!-- Alerts Panel -->
            <?php $totalAlerts = count($alertsToday) + count($alerts3Day) + count($alerts7Day); ?>
            <?php if ($totalAlerts > 0): ?>
                <div class="alert-panel mb-4">
                    <div class="alert-panel-header">
                        <i class="bi bi-bell-fill"></i> تنبيهات الأرباح المستحقة
                        <span class="badge ms-auto" style="background:rgba(231,76,60,0.3);color:#e74c3c">
                            <?= $totalAlerts ?>
                        </span>
                    </div>

                    <?php if ($alertsToday): ?>
                        <div class="px-3 py-2"
                            style="background:rgba(231,76,60,0.05);font-size:0.78rem;font-weight:700;color:var(--danger)">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> مستحقة اليوم (
                            <?= count($alertsToday) ?>)
                        </div>
                        <?php foreach ($alertsToday as $a): ?>
                            <div class="alert-row alert-today">
                                <div>
                                    <span class="text-gold fw-bold">
                                        <?= htmlspecialchars($a['full_name']) ?>
                                    </span>
                                    <span class="badge <?= typeBadge($a['code']) ?> ms-2">
                                        <?= arabicType($a['code']) ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <span class="d-block" style="font-size:0.82rem">
                                        <?= formatMoney($a['amount'], $a['currency'] ?? 'IQD') ?>
                                    </span>
                                    <span style="font-size:0.75rem;color:var(--text-muted)">ربح:
                                        <?= formatMoney($a['amount'] * $a['profit_rate_monthly'], $a['currency'] ?? 'IQD') ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($alerts3Day): ?>
                        <div class="px-3 py-2"
                            style="background:rgba(230,126,34,0.05);font-size:0.78rem;font-weight:700;color:var(--warning)">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> خلال 3 أيام (
                            <?= count($alerts3Day) ?>)
                        </div>
                        <?php foreach ($alerts3Day as $a): ?>
                            <div class="alert-row alert-3day">
                                <div>
                                    <span class="fw-bold">
                                        <?= htmlspecialchars($a['full_name']) ?>
                                    </span>
                                    <span class="badge <?= typeBadge($a['code']) ?> ms-2">
                                        <?= arabicType($a['code']) ?>
                                    </span>
                                </div>
                                <div class="text-end" style="font-size:0.82rem">
                                    <?= formatMoney($a['amount'], $a['currency'] ?? 'IQD') ?> &nbsp;
                                    <span style="color:var(--text-muted);font-size:0.75rem">
                                        <?= formatDate($a['next_profit_date']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($alerts7Day): ?>
                        <div class="px-3 py-2"
                            style="background:rgba(41,128,185,0.05);font-size:0.78rem;font-weight:700;color:var(--info)">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> خلال 7 أيام (
                            <?= count($alerts7Day) ?>)
                        </div>
                        <?php foreach ($alerts7Day as $a): ?>
                            <div class="alert-row alert-7day">
                                <div>
                                    <span class="fw-bold">
                                        <?= htmlspecialchars($a['full_name']) ?>
                                    </span>
                                    <span class="badge <?= typeBadge($a['code']) ?> ms-2">
                                        <?= arabicType($a['code']) ?>
                                    </span>
                                </div>
                                <div class="text-end" style="font-size:0.82rem">
                                    <?= formatMoney($a['amount'], $a['currency'] ?? 'IQD') ?> &nbsp;
                                    <span style="color:var(--text-muted);font-size:0.75rem">
                                        <?= formatDate($a['next_profit_date']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert flash-success border mb-4 d-flex align-items-center gap-2" style="border-radius:8px">
                    <i class="bi bi-check-circle-fill"></i> لا توجد أرباح مستحقة حالياً
                </div>
            <?php endif; ?>

            <!-- Expiry Alerts Panel -->
            <?php $totalExpiryAlerts = count($alertsExpiryEnded) + count($alertsExpiry3Day) + count($alertsExpiry7Day); ?>
            <?php if ($totalExpiryAlerts > 0): ?>
                <div class="alert-panel mb-4" style="border-right-color: var(--danger)">
                    <div class="alert-panel-header">
                        <i class="bi bi-exclamation-triangle-fill"></i> تنبيهات انتهاء الودائع (إرجاع رأس المال)
                        <span class="badge ms-auto" style="background:rgba(231,76,60,0.3);color:#e74c3c">
                            <?= $totalExpiryAlerts ?>
                        </span>
                    </div>

                    <?php if ($alertsExpiryEnded): ?>
                        <div class="px-3 py-2"
                            style="background:rgba(231,76,60,0.05);font-size:0.78rem;font-weight:700;color:var(--danger)">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> منتهية الصلاحية بانتظار الإغلاق
                            (<?= count($alertsExpiryEnded) ?>)
                        </div>
                        <?php foreach ($alertsExpiryEnded as $a): ?>
                            <div class="alert-row alert-today">
                                <div>
                                    <span class="text-danger fw-bold">
                                        <?= htmlspecialchars($a['full_name']) ?>
                                    </span>
                                    <span class="badge <?= typeBadge($a['code']) ?> ms-2">
                                        <?= arabicType($a['code']) ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <span class="d-block text-gold fw-bold" style="font-size:0.82rem">
                                        <?= formatMoney($a['amount'], $a['currency'] ?? 'IQD') ?>
                                    </span>
                                    <span style="font-size:0.75rem;color:var(--danger)">انتهت في:
                                        <?= formatDate($a['end_date']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($alertsExpiry3Day): ?>
                        <div class="px-3 py-2"
                            style="background:rgba(230,126,34,0.05);font-size:0.78rem;font-weight:700;color:var(--warning)">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> تنتهي خلال 3 أيام
                            (<?= count($alertsExpiry3Day) ?>)
                        </div>
                        <?php foreach ($alertsExpiry3Day as $a): ?>
                            <div class="alert-row alert-3day">
                                <div>
                                    <span class="fw-bold">
                                        <?= htmlspecialchars($a['full_name']) ?>
                                    </span>
                                    <span class="badge <?= typeBadge($a['code']) ?> ms-2">
                                        <?= arabicType($a['code']) ?>
                                    </span>
                                </div>
                                <div class="text-end text-gold fw-bold" style="font-size:0.82rem">
                                    <?= formatMoney($a['amount'], $a['currency'] ?? 'IQD') ?> &nbsp;
                                    <span style="color:var(--text-muted);font-size:0.75rem">
                                        <?= formatDate($a['end_date']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($alertsExpiry7Day): ?>
                        <div class="px-3 py-2"
                            style="background:rgba(41,128,185,0.05);font-size:0.78rem;font-weight:700;color:var(--info)">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> تنتهي خلال 7 أيام
                            (<?= count($alertsExpiry7Day) ?>)
                        </div>
                        <?php foreach ($alertsExpiry7Day as $a): ?>
                            <div class="alert-row alert-7day">
                                <div>
                                    <span class="fw-bold">
                                        <?= htmlspecialchars($a['full_name']) ?>
                                    </span>
                                    <span class="badge <?= typeBadge($a['code']) ?> ms-2">
                                        <?= arabicType($a['code']) ?>
                                    </span>
                                </div>
                                <div class="text-end text-gold fw-bold" style="font-size:0.82rem">
                                    <?= formatMoney($a['amount'], $a['currency'] ?? 'IQD') ?> &nbsp;
                                    <span style="color:var(--text-muted);font-size:0.75rem">
                                        <?= formatDate($a['end_date']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Latest Deposits Table -->
            <div class="table-wrapper">
                <div class="p-3 border-bottom border-gold d-flex justify-content-between align-items-center">
                    <span class="section-title mb-0"><i class="bi bi-clock-history me-1"></i>آخر الودائع المضافة</span>
                    <a href="/deposits.php" class="btn btn-outline-gold btn-sm">عرض الكل</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستثمر</th>
                                <th>النوع</th>
                                <th>المبلغ</th>
                                <th>العملة</th>
                                <th>بداية</th>
                                <th>نهاية</th>
                                <th>النسبة المقفلة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestDeposits as $d): ?>
                                <tr>
                                    <td><span class="text-muted">
                                            <?= $d['id'] ?>
                                        </span></td>
                                    <td class="fw-bold">
                                        <?= htmlspecialchars($d['full_name']) ?>
                                    </td>
                                    <td><span class="badge <?= typeBadge($d['code']) ?>">
                                            <?= htmlspecialchars($d['name_ar']) ?>
                                        </span></td>
                                    <td class="text-gold fw-bold">
                                        <?= formatMoney($d['amount'], $d['currency'] ?? 'IQD') ?>
                                    </td>
                                    <td><?= currencyBadge($d['currency'] ?? 'IQD') ?></td>
                                    <td>
                                        <?= formatDate($d['start_date']) ?>
                                    </td>
                                    <td>
                                        <?= formatDate($d['end_date']) ?>
                                    </td>
                                    <td><code
                                            style="color:var(--gold-light)"><?= number_format($d['profit_rate_monthly'] * 100, 3) ?>%</code>
                                    </td>
                                    <td><span class="badge <?= statusBadge($d['status']) ?>">
                                            <?= arabicStatus($d['status']) ?>
                                        </span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($latestDeposits)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">لا توجد ودائع بعد</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.page-content -->
        <?php include __DIR__ . '/../includes/footer.php'; ?>