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
  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <div class="admin-logo">
      <div class="admin-logo-icon">
        <svg width="22" height="22" viewBox="0 0 36 36" fill="none">
          <polygon points="18,4 32,28 4,28" fill="rgba(255,255,255,0.2)" stroke="white" stroke-width="1.5"/>
          <polygon points="18,10 26,24 10,24" fill="rgba(255,255,255,0.35)" stroke="white" stroke-width="1"/>
        </svg>
      </div>
      <div>
        <p class="admin-logo-name">Bafna Marbles</p>
        <p class="admin-logo-sub">Admin Panel</p>
      </div>
    </div>

    <nav class="admin-nav">
      <?php $ap = $_GET['page'] ?? 'dashboard';
      $navItems = [
        ['page'=>'dashboard',    'icon'=>'home',     'label'=>'Dashboard'],
        ['page'=>'products',     'icon'=>'grid',     'label'=>'Products'],
        ['page'=>'inquiries',    'icon'=>'msg',      'label'=>'Inquiries'],
        ['page'=>'users',        'icon'=>'users',    'label'=>'Users'],
        ['page'=>'colors',       'icon'=>'palette',  'label'=>'Color Scheme'],
      ];
      foreach ($navItems as $n): ?>
      <a href="index.php?page=<?= $n['page'] ?>" class="admin-nav-item <?= $ap===$n['page']?'active':'' ?>">
        <?= icon($n['icon'],18) ?>
        <span><?= $n['label'] ?></span>
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

  <!-- Main -->
  <main class="admin-main">
    <div class="admin-topbar">
      <h1 class="admin-page-title serif"><?= h($adminTitle ?? 'Dashboard') ?></h1>
      <div class="admin-topbar-right">
        <span class="badge badge-green">● Live</span>
        <span style="font-size:13px;color:var(--text2);">Welcome, <?= h($_SESSION['admin_name'] ?? 'Admin') ?></span>
      </div>
    </div>
    <div class="admin-content">
