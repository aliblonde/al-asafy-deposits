<?php // includes/topbar.php
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/notifications.php';

$unreadNotifCount = getUnreadNotificationsCount(getPDO(), currentUserId());
?>
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
        <!-- Notifications Bell -->
        <div class="dropdown me-3">
            <button class="btn btn-sm btn-outline-secondary border-0 p-1 position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($unreadNotifCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;">
                    <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
                </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifDropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
                <li><h6 class="dropdown-header d-flex justify-content-between align-items-center">
                    الإشعارات
                    <?php if ($unreadNotifCount > 0): ?>
                    <button class="btn btn-sm btn-link p-0 text-decoration-none" onclick="markAllRead(event)">تحديد الكل كمقروء</button>
                    <?php endif; ?>
                </h6></li>
                <li><hr class="dropdown-divider"></li>
                <?php
                $latestNotifs = getLatestNotifications(getPDO(), currentUserId(), 5);
                if (empty($latestNotifs)):
                ?>
                    <li><span class="dropdown-item text-center text-muted small">لا توجد إشعارات جديدة</span></li>
                <?php else: foreach ($latestNotifs as $n): ?>
                    <li>
                        <a class="dropdown-item <?= $n['is_read'] ? 'text-muted' : 'fw-bold' ?>" href="<?= htmlspecialchars($n['link'] ?? '#') ?>" onclick="markRead(<?= $n['id'] ?>)">
                            <div class="d-flex justify-content-between">
                                <small class="text-gold"><?= htmlspecialchars($n['title']) ?></small>
                                <small class="text-muted" style="font-size: 0.65rem;"><?= date('m/d H:i', strtotime($n['created_at'])) ?></small>
                            </div>
                            <div style="font-size: 0.8rem; white-space: normal; line-height: 1.4;"><?= htmlspecialchars($n['message']) ?></div>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                <?php endforeach; endif; ?>
                <li><a class="dropdown-item text-center small text-primary" href="notifications.php">عرض كل الإشعارات</a></li>
            </ul>
        </div>
        
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
            <?= currentRole() === 'admin' ? 'مشرف' : (currentRole() === 'investor' ? 'مستثمر' : 'موظف') ?>
        </span>
        <form method="POST" action="logout.php" class="d-inline m-0 p-0">
            <?= csrfField() ?>
            <button type="submit" class="btn-logout border-0 bg-transparent p-0" style="color:inherit;cursor:pointer">
                <i class="bi bi-box-arrow-left"></i> خروج
            </button>
        </form>
    </div>
</div>