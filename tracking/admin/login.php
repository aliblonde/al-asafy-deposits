<?php
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
$company = require __DIR__.'/../config/company.php';
$error = '';
if(isset($_GET['expired']))$error='Your session expired after 30 minutes of inactivity. Please sign in again.';
if (!empty($_SESSION['user'])) redirect('index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('SELECT id,name,username,password,role FROM users WHERE username=?');
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        unset($user['password']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity']=time();
        audit_log('login_success','user',(int)$user['id'],['role'=>$user['role']]);
        redirect('index.php');
    }
    audit_log('login_failed','user',null,['username'=>trim($_POST['username']??'')]);$error = 'Invalid username or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Team Login - <?=e($company['company_name'])?></title><link rel="icon" type="image/png" href="../assets/images/treandy-logo.png?v=2"><link rel="apple-touch-icon" href="../assets/images/treandy-logo.png?v=2">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/css/style.css?v=2" rel="stylesheet"></head>
<body class="login-page"><div class="login-accent"></div><div class="container min-vh-100 d-flex align-items-center py-5"><div class="card login-card p-4 p-md-5 mx-auto"><div class="text-center">
<img src="../<?=e($company['logo'])?>" alt="<?=e($company['company_name'])?>" class="login-logo"><p class="eyebrow mb-2">Shipment operations</p><h1 class="h3 fw-bold">Welcome back</h1><p class="text-muted mb-4">Sign in to manage Treandy shipments.</p></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form method="post"><?=csrf_field()?><label class="form-label">Username</label><input class="form-control form-control-lg mb-3" name="username" autocomplete="username" required autofocus><label class="form-label">Password</label><input class="form-control form-control-lg mb-4" type="password" name="password" autocomplete="current-password" required><button class="btn btn-brand btn-lg w-100">Sign In</button></form>
</div></div></body></html>
