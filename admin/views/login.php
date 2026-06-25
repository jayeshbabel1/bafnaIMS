<?php
if (!function_exists('getLogo')) {
    require_once BASE_PATH . '/includes/logo.php';
}
$_authLogo = getLogo(true); // admin is one directory below root, ../uploads/logo/...
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<meta name="theme-color" content="#0a0a0a"/>
<title>Admin Login — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="<?= getFontEmbedUrl(false) ?>" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/auth.css"/>
</head>
<body class="auth-body">
<div class="app-shell">

<?php if ($adminError ?? null): ?>
<div class="toast toast-error" id="app-toast"><?= h($adminError) ?></div>
<?php endif; ?>

<div class="auth-page">

  <!-- Desktop left panel -->
  <div class="auth-left-panel">
    <?php if (!empty($_authLogo)): ?>
      <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
    <?php else: ?>
      <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
    <?php endif; ?>
    <p class="auth-left-panel-title">Admin<br>Control Panel</p>
    <div class="auth-left-panel-accent"></div>
  </div>

  <!-- Right / mobile area -->
  <div class="auth-right-panel">

    <!-- Logo (mobile/tablet only) -->
    <div class="auth-logo-block">
      <?php if (!empty($_authLogo)): ?>
        <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
      <?php else: ?>
        <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
      <?php endif; ?>
    </div>

    <div class="auth-card">
      <span class="auth-card-accent"></span>
      <p class="auth-card-title">Admin Panel</p>
      <p class="auth-card-sub"><?= h(APP_NAME) ?> — Sign in to manage the catalog.</p>

      <?php if ($adminError ?? null): ?>
      <div class="alert alert-error"><?= h($adminError) ?></div>
      <?php endif; ?>

      <form method="POST" action="index.php" novalidate>
        <input type="hidden" name="action" value="admin_login"/>
        <div class="input-group">
          <label class="input-label">Username</label>
          <input type="text" name="username" class="input-field"
                 required autocomplete="username"/>
        </div>
        <div class="input-group" style="margin-bottom:10px;">
          <label class="input-label">Password</label>
          <div class="password-wrap">
            <input type="password" name="password" id="adminPwd" class="input-field"
                   autocomplete="current-password" required/>
            <button type="button" class="pwd-toggle" data-target="adminPwd"><?= icon('eye',16) ?></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In to Admin</button>
      </form>

      <p class="auth-footer-text">
        <a href="../index.php?page=catalog" class="auth-link">← Back to App</a>
      </p>
    </div>

  </div>
</div>
</div><!-- .app-shell -->
<script src="../assets/js/app.js"></script>
</body>
</html>