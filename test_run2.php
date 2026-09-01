<?php
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["REQUEST_URI"] = "/declare_rates.php";
try {
    require __DIR__ . "/public/declare_rates.php";
} catch (Throwable $e) {
    echo "CAUGHT: " . $e->getMessage();
}

