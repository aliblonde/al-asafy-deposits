<?php
// Edit these four values after creating the database in cPanel.
const DB_HOST = 'localhost';
const DB_NAME = 'alasisfh_tracking';
const DB_USER = 'alasisfh_tracking_user';
const DB_PASS = 'EkzH[~.HAiL$Yy]6';

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
