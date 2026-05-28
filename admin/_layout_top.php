<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= h($adminTitle ?? 'Admin') ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/admin.css"/>
</head>
<body class="admin-body">

<?php $t = getFlash('toast'); $e = getFlash('error'); ?>
<?php if ($t): ?><div class="toast toast-success" id="admin-toast"><?= h($t) ?></div><?php endif; ?>
<?php if ($e): ?><div class="toast toast-error"   id="admin-toast"><?= h($e) ?></div><?php endif; ?>

<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-logo">
      <div class="admin-logo-icon">
                <img width="40" height="40" src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&amp;ssl=1" class="custom-logo" alt="Bafna Marble &amp; Granite" decoding="async" srcset="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?w=317&amp;ssl=1 317w, https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?resize=300%2C237&amp;ssl=1 300w" sizes="100vw">
      </div>
      <div>
        <p class="admin-logo-name">Bafna Marbles Pvt. Ltd.</p>
        <p class="admin-logo-sub">Admin Panel</p>
      </div>
    </div>

    <nav class="admin-nav">
      <?php
      $ap = $_GET['page'] ?? 'dashboard';
      $navItems = [
        ['page'=>'dashboard', 'icon'=>'home',    'label'=>'Dashboard'],
        ['page'=>'products',  'icon'=>'grid',    'label'=>'Products'],
        ['page'=>'sync',      'icon'=>'refresh', 'label'=>'Sync Product Data'],

        ['page'=>'inquiries', 'icon'=>'msg',     'label'=>'Inquiries'],
        ['page'=>'users',     'icon'=>'users',   'label'=>'Users'],
        ['page'=>'colors',    'icon'=>'palette', 'label'=>'Color Scheme'],
      ];
      foreach ($navItems as $n): ?>
      <a href="index.php?page=<?= $n['page'] ?>" class="admin-nav-item <?= $ap===$n['page']?'active':'' ?>">
        <?= icon($n['icon'],18) ?><span><?= $n['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar-footer">
      <a href="../index.php?page=catalog" class="admin-nav-item" target="_blank"><?= icon('eye',16) ?><span>View App</span></a>
      <form method="POST" action="index.php">
        <input type="hidden" name="action" value="admin_logout"/>
        <button type="submit" class="admin-nav-item w-full"><?= icon('logout',16) ?><span>Sign Out</span></button>
      </form>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1 class="admin-page-title serif"><?= h($adminTitle ?? 'Dashboard') ?></h1>
      <div class="admin-topbar-right">
        <span class="badge badge-green">● Live</span>
        <span style="font-size:13px;color:var(--text2);">Welcome, <?= h($_SESSION['admin_name'] ?? 'Admin') ?></span>
      </div>
    </div>
    <div class="admin-content">
