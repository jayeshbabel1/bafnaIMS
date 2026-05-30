<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,viewport-fit=cover"/>
<meta name="theme-color" content="#0F0D0A"/>
<title><?= h($pageTitle ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="assets/css/style.css"/>
<?php if (!empty($extraCSS)) foreach ($extraCSS as $f): ?>
<link rel="stylesheet" href="assets/css/<?= h($f) ?>"/>
<?php endforeach; ?>
</head>
<body>
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

<?php if (!empty($showNav) && isLoggedIn()):
  $curPage = $_GET['page'] ?? 'catalog';
  $user    = currentUser();
  $initials= getInitials($user['name'] ?? 'U');
  $sc      = shortlistCount();
  $ic      = inquiryCount();
?>
<!-- ── DESKTOP SIDEBAR ───────────────────────────────────────────────────── -->
<aside class="desktop-sidebar">
  <div class="ds-logo">
    <div class="ds-logo-icon">
      <svg width="22" height="22" viewBox="0 0 36 36" fill="none">
        <polygon points="18,4 32,28 4,28" fill="rgba(255,255,255,.15)" stroke="white" stroke-width="1.5"/>
        <polygon points="18,10 26,24 10,24" fill="rgba(255,255,255,.3)" stroke="white" stroke-width="1"/>
      </svg>
    </div>
    <div>
      <p class="ds-brand"><?= APP_NAME ?></p>
      <p class="ds-sub">Trade Platform</p>
    </div>
  </div>

  <nav class="ds-nav">
    <a href="index.php?page=catalog"
       class="ds-nav-item <?= $curPage==='catalog' ? 'active' : '' ?>">
      <?= icon('grid',18) ?><span>Catalog</span>
    </a>
    <a href="index.php?page=shortlist"
       class="ds-nav-item <?= $curPage==='shortlist' ? 'active' : '' ?>">
      <?= icon('heart',18) ?><span>Shortlist</span>
      <?php if ($sc): ?><span class="ds-nav-badge"><?= $sc ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=inquiries"
       class="ds-nav-item <?= $curPage==='inquiries' ? 'active' : '' ?>">
      <?= icon('msg',18) ?><span>Inquiries</span>
      <?php if ($ic): ?><span class="ds-nav-badge"><?= $ic ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=profile"
       class="ds-nav-item <?= $curPage==='profile' ? 'active' : '' ?>">
      <?= icon('user',18) ?><span>Profile</span>
    </a>
  </nav>

  <div class="ds-footer">
    <div class="ds-footer-user">
      <div class="ds-footer-avatar"><?= h($initials) ?></div>
      <div style="min-width:0;">
        <p class="ds-footer-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($user['name'] ?? '') ?></p>
        <p class="ds-footer-role" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h(ROLES[$user['role']??''] ?? 'Professional') ?></p>
      </div>
    </div>
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="logout"/>
      <button type="submit" class="ds-nav-item" style="width:100%;text-align:left;color:rgba(255,255,255,.4);">
        <?= icon('logout',16) ?><span>Sign Out</span>
      </button>
    </form>
  </div>
</aside>
<?php endif; ?>

<!-- ── PAGE WRAPPER ──────────────────────────────────────────────────────── -->
<div class="page-wrapper">