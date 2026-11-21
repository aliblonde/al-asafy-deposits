<?php
// public/api/config.php — Mobile API Base Configuration & JWT Security
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/approval.php';
require_once __DIR__ . '/../../config/logger.php';

// Set Headers for Mobile Application (JSON & CORS)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle CORS Preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Secret key for HMAC-SHA256 Token Signing
define('API_SECRET_KEY', getenv('ASAFY_API_SECRET') ?: 'ASAFY_MOBILE_API_SECRET_KEY_98a7sd6f5g4h3j2k1_2026');

/**
 * Standard JSON Response Helper
 */
function apiResponse(bool $success, mixed $data = null, string $message = '', int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    $response = array_merge([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'timestamp' => time()
    ], $extra);

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Read incoming JSON body
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Base64Url Encode
 */
function base64UrlEncode(string $data): string
{
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Base64Url Decode
 */
function base64UrlDecode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data)) ?: '';
}

/**
 * Generate Secure Signed JWT Token
 */
function generateApiToken(int $userId, int $investorId, string $role, int $sessionVersion): string
{
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'iss' => 'alasafygroup.xyz',
        'sub' => $userId,
        'investor_id' => $investorId,
        'role' => $role,
        'session_version' => $sessionVersion,
        'iat' => time(),
        'exp' => time() + (86400 * 90) // Valid for 90 days on mobile devices
    ]);

    $base64Header = base64UrlEncode($header);
    $base64Payload = base64UrlEncode($payload);

    $signature = hash_hmac('sha256', "$base64Header.$base64Payload", API_SECRET_KEY, true);
    $base64Signature = base64UrlEncode($signature);

    return "$base64Header.$base64Payload.$base64Signature";
}

/**
 * Verify JWT Token
 */
function verifyApiToken(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$base64Header, $base64Payload, $base64Signature] = $parts;

    $expectedSignature = base64UrlEncode(hash_hmac('sha256', "$base64Header.$base64Payload", API_SECRET_KEY, true));
    if (!hash_equals($expectedSignature, $base64Signature)) {
        return null;
    }

    $payload = json_decode(base64UrlDecode($base64Payload), true);
    if (!is_array($payload) || !isset($payload['sub'], $payload['exp'])) {
        return null;
    }

    // Check expiration
    if (time() > $payload['exp']) {
        return null;
    }

    return $payload;
}

/**
 * Require Investor Authentication for Protected Endpoints
 */
function requireApiAuth(): array
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        apiResponse(false, null, 'يرجى تسجيل الدخول للوصول إلى هذه الخدمة (Bearer Token Missing).', 401);
    }

    $token = trim($matches[1]);
    $payload = verifyApiToken($token);

    if (!$payload) {
        apiResponse(false, null, 'جلسة الدخول منتهية أو غير صالحة. يرجى تسجيل الدخول مجدداً.', 401);
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.role, u.session_version, u.investor_id,
               i.full_name, i.phone, i.city, i.national_id
        FROM users u
        JOIN investors i ON i.id = u.investor_id
        WHERE u.id = ?
    ");
    $stmt->execute([(int)$payload['sub']]);
    $user = $stmt->fetch();

    if (!$user) {
        apiResponse(false, null, 'الحساب غير موجود أو تم إيقافه.', 401);
    }

    // Invalidate if password or session_version changed
    if ((int)$user['session_version'] !== (int)$payload['session_version']) {
        apiResponse(false, null, 'تم تحديث بيانات أمان الحساب. يرجى إعادة تسجيل الدخول.', 401);
    }

    return $user;
}
