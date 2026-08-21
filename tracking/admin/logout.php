<?php require_once __DIR__.'/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); die('405 Method Not Allowed'); }
verify_csrf();
if(!empty($_SESSION['user']))audit_log('logout','user',(int)$_SESSION['user']['id']);
end_session();
header('Location: login.php');
exit;
