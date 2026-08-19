<?php
$page_title='Employees';require_once __DIR__.'/../includes/admin-header.php';require_admin();
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['delete_id'])){
    verify_csrf();$id=(int)$_POST['delete_id'];
    if($id===(int)$_SESSION['user']['id'])flash('danger','You cannot delete your own account.');
    else{$s=db()->prepare('SELECT username,role FROM users WHERE id=?');$s->execute([$id]);$deleted=$s->fetch();$s=db()->prepare('DELETE FROM users WHERE id=?');$s->execute([$id]);if($deleted)audit_log('user_deleted','user',$id,$deleted);flash('success','Employee deleted.');}
    redirect('users.php');
}
$rows=db()->query('SELECT id,name,username,role,created_at FROM users ORDER BY created_at')->fetchAll();
?>
<div class="d-flex justify-content-between mb-4"><h1 class="h3">Employees</h1><a class="btn btn-primary" href="user-add.php">+ Add Employee</a></div><div class="card table-card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['name'])?></td><td><?=e($r['username'])?></td><td><span class="badge text-bg-secondary"><?=e(ucfirst($r['role']))?></span></td><td><?=e($r['created_at'])?></td><td><a class="btn btn-sm btn-outline-secondary" href="user-edit.php?id=<?=$r['id']?>">Edit / Reset Password</a><?php if($r['id']!=$_SESSION['user']['id']):?> <form class="d-inline" method="post" onsubmit="return confirm('Delete this employee?')"><?=csrf_field()?><input type="hidden" name="delete_id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div><?php require __DIR__.'/../includes/admin-footer.php';
