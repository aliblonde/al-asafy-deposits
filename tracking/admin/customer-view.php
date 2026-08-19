<?php
$page_title='Customer Details';require_once __DIR__.'/../includes/admin-header.php';
$s=db()->prepare('SELECT * FROM customers WHERE id=?');$s->execute([(int)($_GET['id']??0)]);$c=$s->fetch();if(!$c){http_response_code(404);exit('Customer not found.');}
$s=db()->prepare('SELECT * FROM shipments WHERE customer_id=? ORDER BY shipment_date DESC,id DESC');$s->execute([$c['id']]);$shipments=$s->fetchAll();$link=base_url().'/onlineview.php?code='.rawurlencode($c['code']);
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-4"><h1 class="h3 mb-0"><?=e($c['name'])?></h1><div><a class="btn btn-primary" href="shipment-add.php?customer_id=<?=$c['id']?>">+ Add Shipment</a> <a class="btn btn-outline-secondary" href="customer-edit.php?id=<?=$c['id']?>">Edit</a></div></div>
<div class="card p-4 mb-4"><div class="row g-3"><div class="col-md-3"><small class="text-muted">Code</small><div><?=e($c['code'])?></div></div><div class="col-md-3"><small class="text-muted">Phone</small><div><?=e($c['phone']?:'-')?></div></div><div class="col-md-3"><small class="text-muted">Email</small><div><?=e($c['email']?:'-')?></div></div><div class="col-12"><small class="text-muted">Address</small><div><?=e($c['address']?:'-')?></div></div><div class="col-12"><small class="text-muted">Notes</small><div><?=nl2br(e($c['notes']?:'-'))?></div></div><div class="col-12"><label class="form-label text-muted" for="trackingLink">Tracking Link</label><div class="input-group"><input id="trackingLink" class="form-control" readonly value="<?=e($link)?>" onclick="this.select()"><button type="button" id="copyTrackingLink" class="btn btn-outline-primary">Copy Link</button><a class="btn btn-outline-secondary" target="_blank" href="../onlineview.php?code=<?=urlencode($c['code'])?>">Open</a></div><div id="copyMessage" class="small mt-2 text-muted">You can also click the link field and press Ctrl+C.</div></div></div></div>
<div class="card table-card"><div class="p-3 border-bottom"><h2 class="h5 mb-0">Shipments</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Brand / JTR</th><th>Weight / Price</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($shipments as $r):?><tr><td><small class="text-muted d-block"><?=e($r['brand_name']?:'-')?></small><strong><?=e($r['jtr_number'])?></strong></td><td><?=e($r['weight'])?> KG<br><small><?=$r['price']!==null?'$ '.e(number_format((float)$r['price'],2)):'-'?></small></td><td><?=e($r['shipment_date'])?></td><td><span class="status-badge <?=e(status_class($r['status']))?>"><?=e(status_labels()[$r['status']])?></span></td><td><a class="btn btn-sm btn-outline-primary" href="shipment-receipt.php?id=<?=$r['id']?>">Print</a> <a class="btn btn-sm btn-outline-secondary" href="shipment-edit.php?id=<?=$r['id']?>">Edit</a></td></tr><?php endforeach;?><?php if(!$shipments):?><tr><td colspan="5" class="text-center text-muted py-4">No shipments yet.</td></tr><?php endif;?></tbody></table></div></div>
<script>
document.getElementById('copyTrackingLink').addEventListener('click', async function () {
    const input=document.getElementById('trackingLink'), message=document.getElementById('copyMessage');
    input.focus();input.select();input.setSelectionRange(0,input.value.length);
    let ok=false;
    try { if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(input.value);ok=true;} } catch(e) {}
    if(!ok){try{ok=document.execCommand('copy');}catch(e){}}
    this.textContent=ok?'Copied!':'Selected';
    message.textContent=ok?'Tracking link copied successfully.':'Link selected — press Ctrl+C to copy it.';
    message.className='small mt-2 '+(ok?'text-success':'text-danger');
    setTimeout(()=>this.textContent='Copy Link',1800);
});
</script>
<?php require __DIR__.'/../includes/admin-footer.php';
