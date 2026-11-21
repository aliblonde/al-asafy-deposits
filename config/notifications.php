<?php
// config/notifications.php — Internal & Firebase Cloud Messaging (FCM) Push Notifications

require_once __DIR__ . "/db.php";

/**
 * Base64 URL-safe encode
 */
function fcmBase64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Retrieve cached or fresh Google OAuth2 access token for Firebase HTTP v1
 */
function getFcmGoogleAccessToken(): ?string {
    $cacheFile = sys_get_temp_dir() . '/alasafy_fcm_token.json';
    if (file_exists($cacheFile)) {
        $cached = @json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['access_token']) && isset($cached['expires_at']) && $cached['expires_at'] > time() + 90) {
            return $cached['access_token'];
        }
    }

    $credsPath = __DIR__ . '/firebase_credentials.json';
    if (!file_exists($credsPath)) {
        error_log("Firebase credentials missing: $credsPath");
        return null;
    }

    $creds = @json_decode(file_get_contents($credsPath), true);
    if (!$creds || empty($creds['private_key']) || empty($creds['client_email'])) {
        error_log("Invalid Firebase credentials JSON at $credsPath");
        return null;
    }

    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $claims = json_encode([
        'iss' => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $dataToSign = fcmBase64UrlEncode($header) . '.' . fcmBase64UrlEncode($claims);
    $privateKey = openssl_pkey_get_private($creds['private_key']);
    if (!$privateKey) {
        error_log("Failed to parse Firebase private key");
        return null;
    }

    $signature = '';
    if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log("Failed to sign Firebase OAuth2 JWT");
        return null;
    }

    $jwt = $dataToSign . '.' . fcmBase64UrlEncode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = @json_decode($res, true);
    if ($code !== 200 || empty($json['access_token'])) {
        error_log("FCM OAuth2 token exchange failed ($code): $res");
        return null;
    }

    @file_put_contents($cacheFile, json_encode([
        'access_token' => $json['access_token'],
        'expires_at' => $now + ($json['expires_in'] ?? 3600)
    ]));

    return $json['access_token'];
}

/**
 * Send real-time Push Notification via Firebase Cloud Messaging (HTTP v1)
 *
 * @param string $target Topic name (e.g. 'investors', 'investor_20', 'all') or device registration token
 * @param string $title Notification title
 * @param string $body Notification message
 * @param array $data Optional custom key-value payload
 * @return bool
 */
function sendFirebasePushNotification(string $target, string $title, string $body, array $data = []): bool {
    try {
        $token = getFcmGoogleAccessToken();
        if (!$token) {
            return false;
        }

        $credsPath = __DIR__ . '/firebase_credentials.json';
        if (!file_exists($credsPath)) return false;
        $creds = @json_decode(file_get_contents($credsPath), true);
        $projectId = $creds['project_id'] ?? 'alasafy-investor';

        $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

        $message = [
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'data' => array_merge([
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ], array_map('strval', $data))
        ];

        // If target looks like a token, use token target; otherwise use topic
        if (str_starts_with($target, 'token:')) {
            $message['token'] = substr($target, 6);
        } elseif (strlen($target) > 100) {
            $message['token'] = $target;
        } else {
            $cleanTopic = ltrim($target, '/topics/');
            $message['topic'] = $cleanTopic;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $message]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            'Content-Type: application/json; UTF-8'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("FCM send failed ($httpCode): $res");
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("FCM exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Broadcast an announcement push notification to all investors
 */
function broadcastFirebasePush(string $title, string $body, array $data = []): bool {
    return sendFirebasePushNotification('investors', $title, $body, $data);
}

/**
 * In-app Notification + Automatic Firebase Push Dispatch
 */
function sendNotification(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): bool {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ok = $stmt->execute([$userId, $title, $message, $link]);

        // Push to mobile app if this user is linked to an investor
        try {
            $stmtUser = $pdo->prepare("SELECT investor_id FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $invId = $stmtUser->fetchColumn();
            if ($invId) {
                sendFirebasePushNotification("investor_{$invId}", $title, $message, ['link' => (string)($link ?? '')]);
            }
        } catch (Exception $ex) {
            error_log("FCM dispatch error: " . $ex->getMessage());
        }

        return $ok;
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

function getLatestNotifications(PDO $pdo, int $userId, int $limit = 10): array {
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
        } else {
            // Even if no user account exists, push directly to mobile app topic
            sendFirebasePushNotification("investor_{$investorId}", $title, $message, ['link' => (string)($link ?? '')]);
        }
    } catch (Exception $e) {
        error_log("Failed to notify investor: " . $e->getMessage());
    }
    return false;
}
