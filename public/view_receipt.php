<?php
// public/view_receipt.php — Secure Receipt Viewer for Admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$id = (int)($_GET['id'] ?? 0);
$fileParam = trim((string)($_GET['file'] ?? ''));
$pdo = getPDO();

$path = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT receipt_image FROM wallet_topup_requests WHERE id = ?");
    $stmt->execute([$id]);
    $path = $stmt->fetchColumn();
} elseif (!empty($fileParam)) {
    // Sanitize filename to prevent directory traversal
    $cleanFilename = basename($fileParam);
    if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $cleanFilename)) {
        $path = 'uploads/receipts/' . $cleanFilename;
    }
}

if (!$path) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>404 — لا يوجد إشعار مرفوع</h2><p>لم يتم إرفاق صورة وصل مع هذا الطلب.</p></div>');
}

// Candidates for file search across cPanel webroot and parent directories
$candidates = [
    __DIR__ . '/' . $path,
    __DIR__ . '/../' . $path,
    __DIR__ . '/../../' . $path,
    __DIR__ . '/uploads/' . basename($path),
    __DIR__ . '/../uploads/receipts/' . basename($path),
    __DIR__ . '/../../uploads/receipts/' . basename($path),
];

$found = null;
foreach ($candidates as $c) {
    if ($c && file_exists($c) && !is_dir($c)) {
        $found = realpath($c);
        break;
    }
}

if (!$found) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>404 — الملف غير موجود</h2><p>تعذر العثور على صورة الوصل على مسار السيرفر.</p></div>');
}

$ext = strtolower(pathinfo($found, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
    'pdf'  => 'application/pdf',
];
$mime = $mimeMap[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($found) : 'image/jpeg');

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($found));
header('Cache-Control: public, max-age=86400');
readfile($found);
exit;
