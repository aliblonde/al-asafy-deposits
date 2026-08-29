<?php
// public/download_file.php — Authenticated File Download Controller with IDOR Protection
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/logger.php';

requirePermission('investors.view');

$pdo = getPDO();
$investorId = (int)($_GET['investor_id'] ?? 0);
$fileType = $_GET['type'] ?? '';

if (!$investorId || !in_array($fileType, ['contract', 'id_card'], true)) {
    http_response_code(400);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>400 — طلب غير صالح</h2><p>معلمات الملف المطلوبة غير صحيحة.</p></div>');
}

// IDOR Guard: Verify User Authorization
$userRole = currentRole();
$currentInvId = currentInvestorId();

if ($userRole === 'investor' && $investorId !== $currentInvId) {
    http_response_code(403);
    logActivity($pdo, 'IDOR_BLOCKED', 'investors', $investorId, null, ['attempted_by' => currentUserId()]);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>403 — غير مصرح</h2><p>عفواً، لا يمكنك الوصول لملفات مستثمر آخر.</p></div>');
}

if (!in_array($userRole, ['admin', 'staff', 'investor'], true)) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>403 — غير مصرح</h2></div>');
}

// Fetch Investor Record
$stmt = $pdo->prepare("SELECT contract_path, id_card_path, full_name FROM investors WHERE id = ?");
$stmt->execute([$investorId]);
$investor = $stmt->fetch();

if (!$investor) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>404 — غير موجود</h2><p>سجل المستثمر غير موجود.</p></div>');
}

$relativePath = ($fileType === 'contract') ? $investor['contract_path'] : $investor['id_card_path'];

if (empty($relativePath)) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>404 — الملف غير موجود</h2><p>لم يتم رفع هذا الملف للمستثمر بعد.</p></div>');
}

// Path Traversal Security Verification
$baseUploadDir = realpath(__DIR__ . '/../uploads/investors');
$fullFilePath = realpath(__DIR__ . '/../' . $relativePath);

if (!$fullFilePath || !file_exists($fullFilePath) || !$baseUploadDir || !str_starts_with($fullFilePath, $baseUploadDir)) {
    error_log("Path Traversal or Missing File Blocked: $relativePath");
    http_response_code(404);
    die('<div style="font-family:sans-serif;color:#721c24;padding:30px;direction:rtl"><h2>404 — الملف غير موجود</h2><p>تعذر العثور على الملف المطلوب في السيرفر.</p></div>');
}

// Determine Content-Type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullFilePath);
finfo_close($finfo);

$allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/pjpeg'];
if (!in_array($mimeType, $allowedMimes, true)) {
    $mimeType = 'application/octet-stream';
}

$ext = pathinfo($fullFilePath, PATHINFO_EXTENSION);
$safeFilename = ($fileType === 'contract' ? 'Contract_' : 'ID_Card_') . $investorId . '.' . $ext;

// Security & Download Headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($fullFilePath));
header('Cache-Control: private, no-transform, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($fullFilePath);
exit;
