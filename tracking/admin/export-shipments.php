<?php
require_once __DIR__.'/../includes/auth.php'; require_login();
audit_log('shipments_exported','export');
header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="shipments-'.date('Y-m-d').'.csv"');
$o=fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF"); fputcsv($o,['Customer Name','Customer Code','Address','Brand','JTR','Weight','Price','Date','Status','Notes']);
$sql='SELECT c.name,c.code,c.address,s.brand_name,s.jtr_number,s.weight,s.price,s.shipment_date,s.status,s.notes FROM shipments s JOIN customers c ON c.id=s.customer_id ORDER BY s.id';
foreach(db()->query($sql) as $r){$r['status']=status_labels()[$r['status']]??$r['status'];fputcsv($o,$r);} fclose($o);
