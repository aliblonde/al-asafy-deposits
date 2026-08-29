<?php
// public/notifications.php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/notifications.php";
require_once __DIR__ . "/../config/helpers.php";

requireLogin();

$pdo = getPDO();
$userId = currentUserId();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$pageTitle = "الإشعارات";
include __DIR__ . "/../includes/header.php";
?>
<div class="layout-wrapper">
    <?php include __DIR__ . "/../includes/sidebar.php"; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . "/../includes/topbar.php"; ?>
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0"><i class="bi bi-bell text-gold me-2"></i> كل الإشعارات</h2>
                <button class="btn btn-outline-secondary btn-sm" onclick="markAllRead(event)">تحديد الكل كمقروء</button>
            </div>
            
            <div class="card shadow-sm border-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($notifications)): ?>
                        <div class="list-group-item text-center p-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            لا توجد إشعارات حالياً
                        </div>
                    <?php else: foreach ($notifications as $n): ?>
                        <a href="<?= htmlspecialchars($n["link"] ?? "#") ?>" onclick="markRead(<?= $n["id"] ?>)" class="list-group-item list-group-item-action p-3 <?= $n["is_read"] ? "text-muted bg-light" : "" ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h6 class="mb-1 fw-bold <?= $n["is_read"] ? "text-muted" : "text-gold" ?>"><?= htmlspecialchars($n["title"]) ?></h6>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date("Y-m-d H:i", strtotime($n["created_at"])) ?></small>
                            </div>
                            <p class="mb-1" style="font-size:0.9rem;"><?= htmlspecialchars($n["message"]) ?></p>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <?php include __DIR__ . "/../includes/footer.php"; ?>
