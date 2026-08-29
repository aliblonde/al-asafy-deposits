<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/notifications.php";
require_once __DIR__ . "/../config/csrf.php";

requireLogin();

$action = $_POST["action"] ?? "";
$userId = currentUserId();
$pdo = getPDO();

header("Content-Type: application/json");

if ($action === "mark_read") {
    $notifId = (int)($_POST["id"] ?? 0);
    if ($notifId > 0) {
        $success = markNotificationRead($pdo, $notifId, $userId);
        echo json_encode(["success" => $success]);
    } else {
        echo json_encode(["success" => false]);
    }
} elseif ($action === "mark_all_read") {
    $success = markAllNotificationsRead($pdo, $userId);
    echo json_encode(["success" => $success]);
} else {
    echo json_encode(["success" => false, "error" => "Invalid action"]);
}
