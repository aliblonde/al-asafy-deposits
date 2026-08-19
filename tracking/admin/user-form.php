<?php
$editing=isset($account); $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf(); $name=trim($_POST['name']??''); $username=trim($_POST['username']??''); $password=$_POST['password']??''; $role=$_POST['role']??'';
    if(!$name||!$username||!in_array($role,['admin','employee'],true)||(!$editing&&strlen($password)<8)||($password&&strlen($password)<8)) $error='Complete all fields; new passwords must be at least 8 characters.';
    else try {
        if($editing){
            if($password){$s=db()->prepare('UPDATE users SET name=?,username=?,role=?,password=? WHERE id=?');$s->execute([$name,$username,$role,password_hash($password,PASSWORD_DEFAULT),$account['id']]);}
            else{$s=db()->prepare('UPDATE users SET name=?,username=?,role=? WHERE id=?');$s->execute([$name,$username,$role,$account['id']]);}
            if($account['id']==$_SESSION['user']['id']){$_SESSION['user']['name']=$name;$_SESSION['user']['username']=$username;$_SESSION['user']['role']=$role;}
            audit_log('user_updated','user',(int)$account['id'],['username'=>$username,'role'=>$role,'password_reset'=>(bool)$password]); flash('success','Employee updated.');
        } else {
            $s=db()->prepare('INSERT INTO users(name,username,password,role) VALUES(?,?,?,?)');$s->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT),$role]);
            audit_log('user_created','user',(int)db()->lastInsertId(),['username'=>$username,'role'=>$role]); flash('success','Employee added.');
        }
        redirect('users.php');
    } catch(PDOException $e){$error=$e->getCode()==='23000'?'That username already exists.':'Could not save employee.';}
}
$v=$_POST?:($account??[]);
?>
<h1 class="h3 mb-4"><?=$editing?'Edit Employee':'Add Employee'?></h1><div class="card p-4" style="max-width:650px"><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><label class="form-label">Name *</label><input class="form-control mb-3" name="name" value="<?=e($v['name']??'')?>" required><label class="form-label">Username *</label><input class="form-control mb-3" name="username" value="<?=e($v['username']??'')?>" required><label class="form-label">Password <?=$editing?'(leave blank to keep current)':'*'?></label><input class="form-control mb-3" type="password" name="password" minlength="8" <?=$editing?'':'required'?>><label class="form-label">Role *</label><select class="form-select mb-4" name="role"><option value="employee" <?=($v['role']??'employee')==='employee'?'selected':''?>>Employee</option><option value="admin" <?=($v['role']??'')==='admin'?'selected':''?>>Admin</option></select><button class="btn btn-primary">Save Employee</button> <a class="btn btn-light" href="users.php">Cancel</a></form></div>
