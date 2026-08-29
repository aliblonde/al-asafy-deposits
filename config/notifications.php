<?php
// config/notifications.php

require_once __DIR__ . "/db.php";

function sendNotification(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): bool {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$userId, $title, $message, $link]);
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return false;
    }
}

function getUnreadNotificationsCount(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getLatestNotifications(PDO $pdo, int $userId, int $limit = 5): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function markNotificationRead(PDO $pdo, int $notifId, int $userId): bool {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notifId, $userId]);
    } catch (Exception $e) {
        return false;
    }
}

function markAllNotificationsRead(PDO $pdo, int $userId): bool {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        return false;
    }
}

function sendTelegramAlert(string $message): bool {
    $token = getenv("TELEGRAM_BOT_TOKEN") ?: ($_ENV["TELEGRAM_BOT_TOKEN"] ?? "");
    $chatId = getenv("TELEGRAM_ADMIN_CHAT_ID") ?: ($_ENV["TELEGRAM_ADMIN_CHAT_ID"] ?? "");
    
    if (empty($token) || empty($chatId)) {
        return false; 
    }
    
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        "chat_id" => $chatId,
        "text" => $message,
        "parse_mode" => "HTML"
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Telegram Alert Failed: " . $result);
        return false;
    }
    return true;
}

function notifyInvestor(PDO $pdo, int $investorId, string $title, string $message, ?string $link = null): bool {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE investor_id = ? AND role = 'investor' LIMIT 1");
        $stmt->execute([$investorId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            return sendNotification($pdo, (int)$userId, $title, $message, $link);
        }
    } catch (Exception $e) {
        error_log("Failed to notify investor: " . $e->getMessage());
    }
    return false;
}
