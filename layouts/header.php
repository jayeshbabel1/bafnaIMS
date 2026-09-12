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
<!-- Bootstrap 5.3.8 (user panel only) - loaded before the theme <style> block so our --bs-* overrides win the cascade. -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous"/>
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

<nav class="navbar navbar-expand-md fixed-top" style="background:var(--navbar-bg);border-bottom:1px solid var(--navbar-border);height:var(--nav-h);z-index:500;">
  <div class="container-fluid h-100 align-items-center px-3 px-md-5">

    <a href="index.php?page=catalog" class="navbar-brand d-flex align-items-center gap-2 me-3">
      <div class="navbar-logo">
        <?php if (!empty($_authLogo)): ?>
          <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
        <?php else: ?>
          <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
               alt="<?= h(APP_NAME) ?>" style="object-fit:contain;"/>
        <?php endif; ?>
      </div>
      <span class="navbar-name d-none d-sm-inline-block"><?= APP_NAME ?></span>
    </a>

    <ul class="navbar-nav flex-row d-none d-md-flex me-auto gap-1">
      <li class="nav-item">
        <a href="index.php?page=catalog" class="nav-link <?= $curPage==='catalog'?'active':'' ?>">
          <?= icon('grid',15) ?> <?= h(ui('nav_catalog','Catalog')) ?>
        </a>
      </li>
      <li class="nav-item position-relative">
        <a href="index.php?page=shortlist" class="nav-link <?= $curPage==='shortlist'?'active':'' ?>">
          <?= icon('heart',15) ?> <?= h(ui('nav_shortlist','Shortlist')) ?>
          <?php if ($sc): ?><span class="navbar-badge"><?= $sc ?></span><?php endif; ?>
        </a>
      </li>
      <li class="nav-item position-relative">
        <a href="index.php?page=clients" class="nav-link <?= in_array($curPage,['clients','client_form','client_selections'])?'active':'' ?>">
          <?= icon('users',15) ?> <?= h(ui('nav_clients','Clients')) ?>
          <?php if ($clientCount): ?><span class="navbar-badge"><?= $clientCount ?></span><?php endif; ?>
        </a>
      </li>
      <li class="nav-item position-relative">
        <a href="index.php?page=notifications" class="nav-link <?= $curPage==='notifications'?'active':'' ?>">
          <?= icon('bell',15) ?> <?= h(ui('nav_updates','Updates')) ?>
          <?php if ($notifCount): ?><span class="navbar-badge"><?= $notifCount ?></span><?php endif; ?>
        </a>
      </li>
      <li class="nav-item">
        <a href="index.php?page=support" class="nav-link <?= $curPage==='support'?'active':'' ?>">
          <?= icon('info',15) ?> <?= h(ui('nav_support','Support')) ?>
        </a>
      </li>
    </ul>

    <div class="d-flex align-items-center gap-2 ms-auto">

      <a href="index.php?page=shortlist" class="navbar-icon-btn d-md-none position-relative" title="Shortlist">
        <?= icon('heart',17) ?>
        <?php if ($sc): ?><span class="navbar-badge"><?= $sc ?></span><?php endif; ?>
      </a>

      <a href="index.php?page=profile" class="navbar-user-btn d-none d-md-flex text-decoration-none">
        <div class="navbar-avatar"><?= h($initials) ?></div>
        <span class="navbar-user-name"><?= h(explode(' ', $user['name'] ?? 'User')[0]) ?></span>
      </a>

      <?php if ($_trustedDeviceUser): ?>
      <button type="button" class="navbar-signout d-none d-md-inline-flex" onclick="openForceLogoutConfirm()">
        <?= icon('logout',14) ?> Sign Out
      </button>
      <?php else: ?>
      <form method="POST" action="index.php" class="d-none d-md-block">
        <input type="hidden" name="action" value="logout"/>
        <?= csrfField() ?>
        <button type="submit" class="navbar-signout d-none d-md-inline-flex">
          <?= icon('logout',14) ?> <?= h(ui('btn_sign_out','Sign Out')) ?>
        </button>
      </form>
      <?php endif; ?>

      <div class="dropdown">
        <button class="navbar-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Language">
          <span style="font-size:11px;font-weight:700;"><?= strtoupper(currentLang()) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-1" style="min-width:140px;">
          <?php foreach (LANG_LABELS as $code => $label): ?>
          <li>
            <form method="POST" action="index.php" class="m-0">
              <input type="hidden" name="action" value="switch_language"/>
              <input type="hidden" name="lang" value="<?= h($code) ?>"/>
              <input type="hidden" name="return_url" value="index.php?page=<?= h($curPage) ?>"/>
              <?= csrfField() ?>
              <button type="submit" class="dropdown-item lang-switch-item <?= currentLang()===$code?'active':'' ?>">
                <?= h($label) ?>
              </button>
            </form>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <button class="navbar-toggler d-md-none border-0 p-1" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu"
              aria-label="Open menu">
        <span class="navbar-toggler-icon"></span>
      </button>

    </div>
  </div>
</nav>

<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
  <div class="offcanvas-header border-bottom">
    <span id="mobileMenuLabel" class="fw-bold"><?= APP_NAME ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column p-0">
    <div class="mobile-menu-inner flex-grow-1">
      <a href="index.php?page=catalog" class="<?= $curPage==='catalog'?'active':'' ?>" data-bs-dismiss="offcanvas">
        <?= icon('grid',18) ?> <?= h(ui('nav_catalog','Catalog')) ?>
      </a>
      <a href="index.php?page=shortlist" class="<?= $curPage==='shortlist'?'active':'' ?>" data-bs-dismiss="offcanvas" style="position:relative;">
        <?= icon('heart',18) ?> <?= h(ui('nav_shortlist','Shortlist')) ?>
        <?php if ($sc): ?><span style="margin-left:auto;" class="badge badge-black"><?= $sc ?></span><?php endif; ?>
      </a>
      <a href="index.php?page=clients"
         class="<?= in_array($curPage,['clients','client_form','client_selections'])?'active':'' ?>"
         data-bs-dismiss="offcanvas">
        <?= icon('users',18) ?> <?= h(ui('nav_clients','Clients')) ?>
        <?php if ($clientCount): ?><span style="margin-left:auto;" class="badge badge-black"><?= $clientCount ?></span><?php endif; ?>
      </a>
      <a href="index.php?page=notifications" class="<?= $curPage==='notifications'?'active':'' ?>" data-bs-dismiss="offcanvas">
        <?= icon('bell',18) ?> <?= h(ui('nav_updates','Updates')) ?>
        <?php if ($notifCount): ?><span style="margin-left:auto;" class="badge badge-black"><?= $notifCount ?></span><?php endif; ?>
      </a>
      <a href="index.php?page=support" class="<?= $curPage==='support'?'active':'' ?>" data-bs-dismiss="offcanvas">
        <?= icon('info',18) ?> <?= h(ui('nav_support','Support')) ?>
      </a>
      <a href="index.php?page=profile" class="<?= $curPage==='profile'?'active':'' ?>" data-bs-dismiss="offcanvas">
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
</div>

<?php endif; ?>
<?php if (!empty($_trustedDeviceUser)): ?>
<div class="modal fade" id="forceLogoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:var(--radius-xl);border:none;padding:6px;">
      <div class="modal-body">
        <div id="flStep1">
          <p style="font-size:16px;font-weight:700;margin-bottom:8px;">Sign Out of Trusted Device?</p>
          <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:20px;">
            This device is trusted for auto sign-in. Signing out here will also remove its trusted status.
          </p>
          <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-secondary btn-block" data-bs-dismiss="modal">Cancel</button>
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
              <button type="button" class="btn btn-secondary btn-block" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger btn-block">Yes, Sign Out</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function openForceLogoutConfirm(){
  document.getElementById('flStep1').style.display='';
  document.getElementById('flStep2').style.display='none';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('forceLogoutModal')).show();
}
function flGoStep2(){document.getElementById('flStep1').style.display='none';document.getElementById('flStep2').style.display='';}
</script>
<?php endif; ?>
<!-- Page wrapper -->
<div class="page-wrapper">