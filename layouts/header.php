<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,viewport-fit=cover"/>
<meta name="theme-color" content="#0a0a0a"/>
<title><?= h($pageTitle ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="<?= getFontEmbedUrl(false) ?>" rel="stylesheet"/>
<?php $_langFontUrl = getLangFontEmbedUrl(currentLang()); ?>
<?php if ($_langFontUrl): ?>
<link href="<?= h($_langFontUrl) ?>" rel="stylesheet"/>
<?php endif; ?>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="assets/css/style.css"/>
<?php if (!function_exists('renderWatermarkCSS')) 
  require_once BASE_PATH . '/includes/watermark.php'; ?>
<style><?= renderWatermarkCSS(false) ?></style>
<link rel="stylesheet" href="assets/css/clients.css"/>
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
if (!function_exists('getLogo')) require_once BASE_PATH . '/includes/logo.php';
$_authLogo = getLogo(false);
$_trustedDeviceUser = isLoggedIn() ? getCurrentTrustedDevice('user') : null;
?>

<?php if ($_toast || $_success): ?>
<div class="toast" id="app-toast"><?= h($_toast ?: $_success) ?></div>
<?php elseif ($_error): ?>
<div class="toast toast-error" id="app-toast"><?= h($_error) ?></div>
<?php endif; ?>

<?php if (!empty($showNav) && isLoggedIn()):
  $curPage  = $_GET['page'] ?? 'catalog';
  $user     = currentUser();
  $initials = getInitials($user['name'] ?? 'U');
  $sc       = shortlistCount();
  $notifCount = 0;
  try {
    $cutoff20 = time() - (20 * 86400);
    $ns = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND created_at >= ?");
    $ns->execute([$cutoff20]);
    $notifCount = (int)$ns->fetchColumn();
  } catch (Throwable $_e) {}

  // Client count for badge
  $clientCount = 0;
  try {
    if (function_exists('clientCount')) {
      $clientCount = clientCount($_SESSION['user_id']);
    }
  } catch (Throwable $_e) {}
?>

<nav class="navbar">
  <!-- Brand -->
  <a href="index.php?page=catalog" class="navbar-brand">
    <div class="navbar-logo">
      <?php if (!empty($_authLogo)): ?>
        <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
      <?php else: ?>
        <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
             alt="<?= h(APP_NAME) ?>" style="object-fit:contain;"/>
      <?php endif; ?>
    </div>
    <span class="navbar-name"><?= APP_NAME ?></span>
  </a>

  <!-- Desktop nav links -->
  <nav class="navbar-nav">
    <a href="index.php?page=catalog" class="<?= $curPage==='catalog'?'active':'' ?>">
      <?= icon('grid',15) ?> <?= h(ui('nav_catalog','Catalog')) ?>
    </a>
    <a href="index.php?page=shortlist" class="<?= $curPage==='shortlist'?'active':'' ?>" style="position:relative;">
      <?= icon('heart',15) ?> <?= h(ui('nav_shortlist','Shortlist')) ?>
      <?php if ($sc): ?><span class="navbar-badge"><?= $sc ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=clients" class="<?= in_array($curPage,['clients','client_form','client_selections'])?'active':'' ?>" style="position:relative;">
      <?= icon('users',15) ?> <?= h(ui('nav_clients','Clients')) ?>
      <?php if ($clientCount): ?><span class="navbar-badge"><?= $clientCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=notifications" class="<?= $curPage==='notifications'?'active':'' ?>" style="position:relative;">
      <?= icon('bell',15) ?> <?= h(ui('nav_updates','Updates')) ?>
      <?php if ($notifCount): ?><span class="navbar-badge"><?= $notifCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=support" class="<?= $curPage==='support'?'active':'' ?>">
      <?= icon('info',15) ?> <?= h(ui('nav_support','Support')) ?>
    </a>
  </nav>

  <!-- Right actions -->
  <div class="navbar-right">
       
    <!-- Shortlist icon (mobile) -->
    <a href="index.php?page=shortlist" class="navbar-icon-btn" title="Shortlist">
      <?= icon('heart',17) ?>
      <?php if ($sc): ?><span class="navbar-badge"><?= $sc ?></span><?php endif; ?>
    </a>
    <!-- Profile -->
    <a href="index.php?page=profile" class="navbar-user-btn" style="text-decoration:none;">
      <div class="navbar-avatar"><?= h($initials) ?></div>
      <span class="navbar-user-name"><?= h(explode(' ', $user['name'] ?? 'User')[0]) ?></span>
    </a>
    <?php if ($_trustedDeviceUser): ?>
<button type="button" class="navbar-signout" onclick="openForceLogoutConfirm()">
  <?= icon('logout',14) ?> Sign Out
</button>
<?php else: ?>
<form method="POST" action="index.php" class="navbar-signout-form" style="display:contents;">
  <input type="hidden" name="action" value="logout"/>
  <?= csrfField() ?>
  <button type="submit" class="navbar-signout">
    <?= icon('logout',14) ?> <?= h(ui('btn_sign_out','Sign Out')) ?>
  </button>
</form>
<?php endif; ?>
    
    <!-- Language switcher -->
<div class="lang-switch-wrap" id="langSwitchWrap">
  <button class="navbar-icon-btn" id="langSwitchBtn" type="button" title="Language">
    <span style="font-size:11px;font-weight:700;"><?= strtoupper(currentLang()) ?></span>
  </button>
  <div class="lang-switch-dropdown" id="langSwitchDropdown">
    <?php foreach (LANG_LABELS as $code => $label): ?>
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="switch_language"/>
      <input type="hidden" name="lang" value="<?= h($code) ?>"/>
      <input type="hidden" name="return_url" value="index.php?page=<?= h($curPage) ?>"/>
      <?= csrfField() ?>
      <button type="submit" class="lang-switch-item <?= currentLang()===$code?'active':'' ?>">
        <?= h($label) ?>
      </button>
    </form>
    <?php endforeach; ?>
  </div>
</div>
    <!-- Hamburger -->
    <div class="navbar-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()">
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
      <div class="hamburger-line"></div>
    </div>
  </div>
</nav>

<!-- Mobile menu drawer -->
<div class="mobile-menu" id="mobileMenu">
  <div class="mobile-menu-inner">
    <a href="index.php?page=catalog" class="<?= $curPage==='catalog'?'active':'' ?>" onclick="closeMobileMenu()">
      <?= icon('grid',18) ?> <?= h(ui('nav_catalog','Catalog')) ?>
    </a>
    <a href="index.php?page=shortlist" class="<?= $curPage==='shortlist'?'active':'' ?>" onclick="closeMobileMenu()" style="position:relative;">
      <?= icon('heart',18) ?> <?= h(ui('nav_shortlist','Shortlist')) ?>
      <?php if ($sc): ?><span style="margin-left:auto;" class="badge badge-black"><?= $sc ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=clients"
       class="<?= in_array($curPage,['clients','client_form','client_selections'])?'active':'' ?>"
       onclick="closeMobileMenu()">
      <?= icon('users',18) ?> <?= h(ui('nav_clients','Clients')) ?>
      <?php if ($clientCount): ?><span style="margin-left:auto;" class="badge badge-black"><?= $clientCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=notifications" class="<?= $curPage==='notifications'?'active':'' ?>" onclick="closeMobileMenu()">
      <?= icon('bell',18) ?> <?= h(ui('nav_updates','Updates')) ?>
      <?php if ($notifCount): ?><span style="margin-left:auto;" class="badge badge-black"><?= $notifCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=support" class="<?= $curPage==='support'?'active':'' ?>" onclick="closeMobileMenu()">
      <?= icon('info',18) ?> <?= h(ui('nav_support','Support')) ?>
    </a>
    <a href="index.php?page=profile" class="<?= $curPage==='profile'?'active':'' ?>" onclick="closeMobileMenu()">
      <?= icon('user',18) ?> Profile
    </a>
  </div>
  <div class="mobile-menu-footer">
    <?php if ($_trustedDeviceUser): ?>
<button type="button" class="btn btn-danger btn-block" style="border-radius:12px;" onclick="openForceLogoutConfirm()">
  <?= icon('logout',16) ?> <?= h(ui('btn_sign_out','Sign Out')) ?>
</button>
<?php else: ?>
<form method="POST" action="index.php">
  <input type="hidden" name="action" value="logout"/>
  <?= csrfField() ?>
  <button type="submit" class="btn btn-danger btn-block" style="border-radius:12px;">
    <?= icon('logout',16) ?> <?= h(ui('btn_sign_out','Sign Out')) ?>
  </button>
</form>
<?php endif; ?>
  </div>
</div>

<?php endif; ?>
<?php if (!empty($_trustedDeviceUser)): ?>
<div id="forceLogoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9200;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--white);border-radius:var(--radius-xl);padding:26px;max-width:400px;width:100%;box-shadow:var(--shadow-xl);">
    <div id="flStep1">
      <p style="font-size:16px;font-weight:700;margin-bottom:8px;">Sign Out of Trusted Device?</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:20px;">
        This device is trusted for auto sign-in. Signing out here will also remove its trusted status.
      </p>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeForceLogoutConfirm()">Cancel</button>
        <button type="button" class="btn btn-danger btn-block" onclick="flGoStep2()">Continue</button>
      </div>
    </div>
    <div id="flStep2" style="display:none;">
      <p style="font-size:16px;font-weight:700;margin-bottom:8px;">Confirm Forced Logout</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:20px;">
        You'll need your <strong>email and password</strong> to sign back in on this device. Continue?
      </p>
      <form method="POST" action="index.php">
        <input type="hidden" name="action" value="forced_logout"/>
        <?= csrfField() ?>
        <div style="display:flex;gap:10px;">
          <button type="button" class="btn btn-secondary btn-block" onclick="closeForceLogoutConfirm()">Cancel</button>
          <button type="submit" class="btn btn-danger btn-block">Yes, Sign Out</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openForceLogoutConfirm(){document.getElementById('flStep1').style.display='';document.getElementById('flStep2').style.display='none';document.getElementById('forceLogoutModal').style.display='flex';}
function closeForceLogoutConfirm(){document.getElementById('forceLogoutModal').style.display='none';}
function flGoStep2(){document.getElementById('flStep1').style.display='none';document.getElementById('flStep2').style.display='';}
</script>
<?php endif; ?>
<!-- Page wrapper -->
<div class="page-wrapper">