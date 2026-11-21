<?php
// public/api/notifications.php — Mobile API Notifications Center
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../config/notifications.php';

$user = requireApiAuth();
$userId = (int)$user['id'];
$pdo = getPDO();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? 'mark_read';

    if ($action === 'mark_all_read') {
        markAllNotificationsRead($pdo, $userId);
        apiResponse(true, null, 'تم تحديد جميع الإشعارات كمقروءة.');
    } elseif ($action === 'mark_read') {
        $notifId = (int)($input['notif_id'] ?? 0);
        if ($notifId > 0) {
            markNotificationRead($pdo, $notifId, $userId);
        }
        apiResponse(true, null, 'تم تحديد الإشعار كمقروء.');
    }

    apiResponse(false, null, 'إجراء غير معروف.', 400);
}

// GET: Fetch list & unread count
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$notifications = getLatestNotifications($pdo, $userId, $limit);
$unreadCount = getUnreadNotificationsCount($pdo, $userId);

$formatted = [];
foreach ($notifications as $n) {
    $formatted[] = [
        'id' => (int)$n['id'],
        'title' => $n['title'] ?? 'إشعار',
        'message' => $n['message'] ?? '',
        'link' => $n['link'] ?? null,
        'is_read' => (bool)$n['is_read'],
        'created_at' => $n['created_at'] ?? date('Y-m-d H:i:s')
    ];
}

apiResponse(true, [
    'unread_count' => $unreadCount,
    'notifications' => $formatted
], 'تم جلب الإشعارات بنجاح.');
