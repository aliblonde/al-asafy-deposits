<?php
// config/db.php — PDO connection

$isLocal = (php_sapi_name() === 'cli' || str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost') || str_contains($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1'));

if ($isLocal) {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'al_asafy_deposits');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'alasisfh_al_asafy_deposits');
    define('DB_USER', 'alasisfh_alasafy');
    define('DB_PASS', 'Alasafy@Treandy2026');
    define('DB_CHARSET', 'utf8mb4');
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:monospace;color:red;padding:20px">
                خطأ في الاتصال بقاعدة البيانات: ' . htmlspecialchars($e->getMessage()) . '
                </div>');
        }
    }
    return $pdo;
}
