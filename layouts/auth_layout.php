<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<meta name="theme-color" content="#F2F5F9"/>
<title><?= h($pageTitle ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="assets/css/style.css"/>
<link rel="stylesheet" href="assets/css/auth.css"/>
</head>
<body class="auth-body">
<div class="app-shell">

<?php
$_toast   = getFlash('toast');
$_error   = getFlash('error');
$_success = getFlash('success');
?>
<?php if ($_toast || $_success): ?>
<div class="toast toast-success" id="app-toast"><?= h($_toast ?: $_success) ?></div>
<?php elseif ($_error): ?>
<div class="toast toast-error" id="app-toast"><?= h($_error) ?></div>
<?php endif; ?>

<!-- AUTH PAGE CONTENT STARTS BELOW -->
