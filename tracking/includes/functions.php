<?php
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = preg_replace('#/admin$#', '', rtrim($script, '/'));
    return ($https ? 'https' : 'http').'://'.$host.$script;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    if (!isset($_POST['csrf']) || !hash_equals(csrf_token(), (string)$_POST['csrf'])) {
        http_response_code(419); exit('Invalid or expired request. Please go back and try again.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function redirect(string $url): never { header('Location: '.$url); exit; }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR']??''),0,45); }
function audit_log(string $action,?string $entityType=null,?int $entityId=null,array $details=[],?array $actor=null): void {
    try{$actor=$actor??($_SESSION['user']??[]);$s=db()->prepare('INSERT INTO audit_logs(user_id,username,action,entity_type,entity_id,details,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?)');$s->execute([isset($actor['id'])?(int)$actor['id']:null,$actor['username']??($details['username']??null),$action,$entityType,$entityId,$details?json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,client_ip(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255)]);}catch(Throwable $e){}
}
function status_labels(): array { return [
    'turkey_branch'=>'فرع تركيا', 'dubai_branch'=>'فرع دبي', 'shipping_to_erbil'=>'شحن أربيل',
    'erbil_warehouse'=>'في مخزن أربيل', 'delivered'=>'تم التسليم'
]; }
function valid_status(string $status): bool { return array_key_exists($status, status_labels()); }
function status_class(string $status): string { return [
    'turkey_branch'=>'status-turkey', 'dubai_branch'=>'status-dubai', 'shipping_to_erbil'=>'status-way',
    'erbil_warehouse'=>'status-turkey', 'delivered'=>'status-delivered'
][$status] ?? 'bg-secondary'; }
