<?php require_once __DIR__.'/../includes/auth.php';if(!empty($_SESSION['user']))audit_log('logout','user',(int)$_SESSION['user']['id']);end_session();header('Location: login.php');exit;
