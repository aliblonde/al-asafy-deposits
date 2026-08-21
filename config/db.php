<?php
// config/db.php — PDO connection

date_default_timezone_set('Asia/Baghdad');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Helper to load .env file if available locally or on server
if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Load .env from project root if present
loadEnvFile(__DIR__ . '/../.env');

// Retrieve DB credentials from Environment Variables
$dbHost = getenv('ASAFY_DB_HOST') ?: ($_ENV['ASAFY_DB_HOST'] ?? ($_SERVER['ASAFY_DB_HOST'] ?? ''));
$dbName = getenv('ASAFY_DB_NAME') ?: ($_ENV['ASAFY_DB_NAME'] ?? ($_SERVER['ASAFY_DB_NAME'] ?? ''));
$dbUser = getenv('ASAFY_DB_USER') ?: ($_ENV['ASAFY_DB_USER'] ?? ($_SERVER['ASAFY_DB_USER'] ?? ''));
$dbPass = getenv('ASAFY_DB_PASSWORD') ?: ($_ENV['ASAFY_DB_PASSWORD'] ?? ($_SERVER['ASAFY_DB_PASSWORD'] ?? ''));
$dbCharset = getenv('ASAFY_DB_CHARSET') ?: ($_ENV['ASAFY_DB_CHARSET'] ?? ($_SERVER['ASAFY_DB_CHARSET'] ?? 'utf8mb4'));

// Fallback for local development environment only
$isLocal = (php_sapi_name() === 'cli' || str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost') || str_contains($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1'));
if (empty($dbHost) && $isLocal) {
    $dbHost = '127.0.0.1';
    $dbName = 'al_asafy_deposits';
    $dbUser = 'root';
    $dbPass = '';
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', $dbCharset);

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (empty(DB_HOST) || empty(DB_NAME) || empty(DB_USER)) {
            error_log('SECURITY CRITICAL: Production database credentials missing from environment variables (ASAFY_DB_*).');
            http_response_code(500);
            die('<div style="font-family:sans-serif;color:#721c24;background-color:#f8d7da;border:1px solid #f5c6cb;padding:25px;margin:50px auto;max-width:600px;border-radius:8px;text-align:center;direction:rtl">
                <h3 style="margin-top:0">عذراً، حدث خطأ داخلي في النظام</h3>
                <p>تعذر الاتصال بقواعد البيانات. يرجى التواصل مع إدارة النظام.</p>
                </div>');
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database Connection Failure: ' . $e->getMessage());
            http_response_code(500);
            die('<div style="font-family:sans-serif;color:#721c24;background-color:#f8d7da;border:1px solid #f5c6cb;padding:25px;margin:50px auto;max-width:600px;border-radius:8px;text-align:center;direction:rtl">
                <h3 style="margin-top:0">عذراً، حدث خطأ في الاتصال بالحاسوب المركزية</h3>
                <p>تعذر إكمال الطلب حالياً. تم تسجيل المشكلة للفريق الفني.</p>
                </div>');
        }
    }
    return $pdo;
}
