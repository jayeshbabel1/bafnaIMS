<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,viewport-fit=cover"/>
<meta name="theme-color" content="#0a0a0a"/>
<title><?= h($pageTitle ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="assets/css/style.css"/>
<link rel="stylesheet" href="assets/css/watermark.css"/>
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
      <?= icon('grid',15) ?> Catalog
    </a>
    <a href="index.php?page=shortlist" class="<?= $curPage==='shortlist'?'active':'' ?>" style="position:relative;">
      <?= icon('heart',15) ?> Shortlist
      <?php if ($sc): ?><span class="navbar-badge"><?= $sc ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=clients" class="<?= in_array($curPage,['clients','client_form','client_selections'])?'active':'' ?>" style="position:relative;">
      <?= icon('users',15) ?> Clients
      <?php if ($clientCount): ?><span class="navbar-badge"><?= $clientCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=notifications" class="<?= $curPage==='notifications'?'active':'' ?>" style="position:relative;">
      <?= icon('bell',15) ?> Updates
      <?php if ($notifCount): ?><span class="navbar-badge"><?= $notifCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=support" class="<?= $curPage==='support'?'active':'' ?>">
      <?= icon('info',15) ?> Support
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
    <form method="POST" action="index.php" class="navbar-signout-form" style="display:contents;">
      <input type="hidden" name="action" value="logout"/>
      <button type="submit" class="navbar-signout">
        <?= icon('logout',14) ?> Sign Out
      </button>
    </form>
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
      <?= icon('grid',18) ?> Catalog
    </a>
    <a href="index.php?page=shortlist" class="<?= $curPage==='shortlist'?'active':'' ?>" onclick="closeMobileMenu()" style="position:relative;">
      <?= icon('heart',18) ?> Shortlist
      <?php if ($sc): ?><span style="margin-left:auto;" class="badge badge-black"><?= $sc ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=clients"
       class="<?= in_array($curPage,['clients','client_form','client_selections'])?'active':'' ?>"
       onclick="closeMobileMenu()">
      <?= icon('users',18) ?> Clients
      <?php if ($clientCount): ?><span style="margin-left:auto;" class="badge badge-black"><?= $clientCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=notifications" class="<?= $curPage==='notifications'?'active':'' ?>" onclick="closeMobileMenu()">
      <?= icon('bell',18) ?> Notifications
      <?php if ($notifCount): ?><span style="margin-left:auto;" class="badge badge-black"><?= $notifCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=support" class="<?= $curPage==='support'?'active':'' ?>" onclick="closeMobileMenu()">
      <?= icon('info',18) ?> Support
    </a>
    <a href="index.php?page=profile" class="<?= $curPage==='profile'?'active':'' ?>" onclick="closeMobileMenu()">
      <?= icon('user',18) ?> Profile
    </a>
  </div>
  <div class="mobile-menu-footer">
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="logout"/>
      <button type="submit" class="btn btn-danger btn-block" style="border-radius:12px;">
        <?= icon('logout',16) ?> Sign Out
      </button>
    </form>
  </div>
</div>

<?php endif; ?>

<!-- Page wrapper -->
<div class="page-wrapper">