<?php
require_once __DIR__.'/../includes/auth.php'; require_login();
audit_log('customers_exported','export');
header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="customers-'.date('Y-m-d').'.csv"');
$o=fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF"); fputcsv($o,['Name','Customer Code','Phone','Email','Notes','Created At']);
foreach(db()->query('SELECT name,code,phone,email,notes,created_at FROM customers ORDER BY id') as $r) fputcsv($o,$r);
fclose($o);
