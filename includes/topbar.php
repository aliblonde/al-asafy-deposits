<?php // includes/topbar.php ?>
<div class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" class="btn btn-outline-gold border-0 p-1" title="تصغير/توسيع القائمة">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="topbar-title">
            <i class="bi bi-building me-1"></i> نظام إدارة الودائع الاستثمارية
        </div>
    </div>
    <div class="topbar-user">
        <button id="themeToggle" class="btn btn-sm btn-outline-secondary border-0 p-1 me-2" title="تغيير المظهر">
            <i class="bi bi-brightness-high fs-5 text-warning"></i>
        </button>
        <span class="text-muted small">الفرع الرئيسي</span>
        <span class="divider-v">|</span>
        <i class="bi bi-person-circle" style="color:var(--gold)"></i>
        <span class="user-name">
            <?= htmlspecialchars(currentUsername()) ?>
        </span>
        <span class="badge" style="background:rgba(212,175,55,0.15);color:var(--gold);font-size:0.7rem">
            <?= currentRole() === 'admin' ? 'مشرف' : 'موظف' ?>
        </span>
        <form method="POST" action="logout.php" class="d-inline m-0 p-0">
            <?= csrfField() ?>
            <button type="submit" class="btn-logout border-0 bg-transparent p-0" style="color:inherit;cursor:pointer">
                <i class="bi bi-box-arrow-left"></i> خروج
            </button>
        </form>
    </div>
</div>