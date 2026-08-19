<?php
// includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
function sidebarLink(string $href, string $icon, string $label, string $current): string
{
    $active = (basename($href) === $current) ? ' active' : '';
    return "<a href=\"$href\" class=\"sidebar-link$active\"><i class=\"bi bi-$icon\"></i> <span>$label</span></a>";
}
?>
<nav class="sidebar">
    <div class="sidebar-brand">
        <img src="/assets/img/ag-logo.png" alt="الشعار" onerror="this.style.display='none'">
        <div>
            <div class="sidebar-brand-text">العسافي للاستثمارات</div>
            <div class="sidebar-brand-sub">نظام إدارة الودائع</div>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section-label">القائمة الرئيسية</div>

        <?= sidebarLink('/dashboard.php', 'speedometer2', 'نظرة عامة', $currentPage) ?>
        <?= sidebarLink('/deposits.php', 'bank', 'الودائع', $currentPage) ?>
        <?= sidebarLink('/deposit_add.php', 'plus-circle', 'إضافة وديعة', $currentPage) ?>
        <?= sidebarLink('/declare_rates.php', 'percent', 'إعلان نسب الأرباح', $currentPage) ?>
        <?= sidebarLink('/profit_run.php', 'currency-dollar', 'صرف الأرباح', $currentPage) ?>

        <div class="nav-section-label mt-3">الإدارة</div>
        <?= sidebarLink('/investors.php', 'people', 'المستثمرون', $currentPage) ?>
        <?= sidebarLink('/withdraw_requests.php', 'arrow-up-circle', 'طلبات سحب الأرباح', $currentPage) ?>
        <?= sidebarLink('/reports.php', 'bar-chart-line', 'التقارير', $currentPage) ?>
        <?= sidebarLink('/settings_deposit_types.php', 'gear', 'إعداد نسب الأرباح', $currentPage) ?>

        <?php if (currentRole() === 'admin'): ?>
            <?= sidebarLink('/activity_logs.php', 'clock-history', 'سجل العمليات', $currentPage) ?>
            <?= sidebarLink('/users.php', 'person-badge', 'إدارة الموظفين', $currentPage) ?>
        <?php endif; ?>

        <div class="nav-section-label mt-3">الحساب</div>
        <?= sidebarLink('/change_password.php', 'shield-lock', 'تغيير كلمة المرور', $currentPage) ?>
        <?= sidebarLink('/logout.php', 'box-arrow-left', 'تسجيل الخروج', $currentPage) ?>
    </div>
</nav>