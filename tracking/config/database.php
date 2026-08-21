// Helper to load .env file if available
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
loadEnvFile(__DIR__ . '/../../.env');

$trHost = getenv('TRACKING_DB_HOST') ?: ($_ENV['TRACKING_DB_HOST'] ?? ($_SERVER['TRACKING_DB_HOST'] ?? ''));
$trName = getenv('TRACKING_DB_NAME') ?: ($_ENV['TRACKING_DB_NAME'] ?? ($_SERVER['TRACKING_DB_NAME'] ?? ''));
$trUser = getenv('TRACKING_DB_USER') ?: ($_ENV['TRACKING_DB_USER'] ?? ($_SERVER['TRACKING_DB_USER'] ?? ''));
$trPass = getenv('TRACKING_DB_PASSWORD') ?: ($_ENV['TRACKING_DB_PASSWORD'] ?? ($_SERVER['TRACKING_DB_PASSWORD'] ?? ''));
$trCharset = getenv('TRACKING_DB_CHARSET') ?: ($_ENV['TRACKING_DB_CHARSET'] ?? ($_SERVER['TRACKING_DB_CHARSET'] ?? 'utf8mb4'));

$isLocal = (php_sapi_name() === 'cli' || str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost') || str_contains($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1'));
if (empty($trHost) && $isLocal) {
    $trHost = '127.0.0.1';
    $trName = 'alasisfh_tracking';
    $trUser = 'root';
    $trPass = '';
}

define('TRACKING_DB_HOST', $trHost);
define('TRACKING_DB_NAME', $trName);
define('TRACKING_DB_USER', $trUser);
define('TRACKING_DB_PASS', $trPass);
define('TRACKING_DB_CHARSET', $trCharset);

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        if (empty(TRACKING_DB_HOST) || empty(TRACKING_DB_NAME) || empty(TRACKING_DB_USER)) {
            error_log('SECURITY CRITICAL: Tracking database credentials missing from environment variables (TRACKING_DB_*).');
            http_response_code(500);
            die('<div style="font-family:sans-serif;color:#721c24;background-color:#f8d7da;border:1px solid #f5c6cb;padding:25px;margin:50px auto;max-width:600px;border-radius:8px;text-align:center;direction:rtl">
                <h3 style="margin-top:0">عذراً، حدث خطأ داخلي في النظام</h3>
                <p>تعذر الاتصال بقواعد البيانات. يرجى التواصل مع إدارة النظام.</p>
                </div>');
        }

        try {
            $pdo = new PDO('mysql:host='.TRACKING_DB_HOST.';dbname='.TRACKING_DB_NAME.';charset='.TRACKING_DB_CHARSET, TRACKING_DB_USER, TRACKING_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Tracking Database Connection Failure: ' . $e->getMessage());
            http_response_code(500);
            die('<div style="font-family:sans-serif;color:#721c24;background-color:#f8d7da;border:1px solid #f5c6cb;padding:25px;margin:50px auto;max-width:600px;border-radius:8px;text-align:center;direction:rtl">
                <h3 style="margin-top:0">عذراً، تعذر الاتصال بالنظام</h3>
                <p>تم تسجيل هذه المشكلة للفريق الفني.</p>
                </div>');
        }
    }
    return $pdo;
}
