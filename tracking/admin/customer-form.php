<?php
$editing=isset($customer);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $data=['name'=>trim($_POST['name']??''),'code'=>trim($_POST['code']??''),'phone'=>trim($_POST['phone']??''),'email'=>trim($_POST['email']??''),'address'=>trim($_POST['address']??''),'notes'=>trim($_POST['notes']??'')];
    if(!$data['name']||!$data['code'])$error='Name and customer code are required.';
    elseif($data['email']&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))$error='Enter a valid email address.';
    else try{
        if($editing){$s=db()->prepare('UPDATE customers SET name=?,code=?,phone=?,email=?,address=?,notes=? WHERE id=?');$s->execute([...array_values($data),$customer['id']]);audit_log('customer_updated','customer',(int)$customer['id'],['code'=>$data['code'],'name'=>$data['name']]);flash('success','Customer updated.');redirect('customer-view.php?id='.$customer['id']);}
        else{$s=db()->prepare('INSERT INTO customers(name,code,phone,email,address,notes) VALUES(?,?,?,?,?,?)');$s->execute(array_values($data));$id=(int)db()->lastInsertId();audit_log('customer_created','customer',$id,['code'=>$data['code'],'name'=>$data['name']]);flash('success','Customer created.');redirect('customer-view.php?id='.$id);}
    }catch(PDOException $e){$error=$e->getCode()==='23000'?'That customer code already exists.':'Could not save customer.';}
}
$v=$data??($customer??[]);?>
<h1 class="h3 mb-4"><?=$editing?'Edit Customer':'Add Customer'?></h1><div class="card p-4" style="max-width:760px"><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><div class="row g-3"><div class="col-md-7"><label class="form-label">Name *</label><input class="form-control" name="name" value="<?=e($v['name']??'')?>" required></div><div class="col-md-5"><label class="form-label">Customer Code *</label><input class="form-control" name="code" value="<?=e($v['code']??'')?>" required></div><div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?=e($v['phone']??'')?>"></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?=e($v['email']??'')?>"></div><div class="col-12"><label class="form-label">Customer Address</label><input class="form-control" name="address" value="<?=e($v['address']??'')?>" placeholder="City, area, street"></div><div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"><?=e($v['notes']??'')?></textarea></div><div class="col-12"><button class="btn btn-primary">Save Customer</button> <a class="btn btn-light" href="customers.php">Cancel</a></div></div></form></div>
