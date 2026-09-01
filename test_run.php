
<?php
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["REQUEST_URI"] = "/approval_requests.php";
try {
    require __DIR__ . "/public/approval_requests.php";
} catch (Throwable $e) {
    echo "CAUGHT: " . $e->getMessage();
}

