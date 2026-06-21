<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin Login — <?= APP_NAME ?></title>
<link href="<?= getFontEmbedUrl(true) ?>" rel="stylesheet"/>
<style><?= getCSSVariables(true) ?></style>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/admin.css"/>
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);">
<div style="width:100%;max-width:380px;padding:24px;">
  <div style="text-align:center;margin-bottom:32px;">
    <?php
    if (!function_exists('getLogo')) {
        require_once BASE_PATH . '/includes/logo.php';
    }
    $_loginLogo = getLogo(false);  // relative path from admin/ dir uses admin=false but we fix prefix below
    // getLogo(true) gives ../uploads/logo/... which works from admin/views/
    $_loginLogo = getLogo(true);
    ?>
    <div style="width:64px;height:64px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <?php if ($_loginLogo): ?>
        <img src="<?= h($_loginLogo) ?>" alt="<?= h(APP_NAME) ?>"
             style="max-width:100%;max-height:100%;object-fit:contain;filter:brightness(0) invert(1);"/>
      <?php else: ?>
        <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
             alt="<?= h(APP_NAME) ?>"
             style="max-width:100%;max-height:100%;object-fit:contain;filter:brightness(0) invert(1);"/>
      <?php endif; ?>
    </div>
    <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:600;color:var(--text);">Admin Panel</h1>
    <p style="font-size:13px;color:var(--text3);margin-top:4px;"><?= APP_NAME ?></p>
  </div>

  <?php if ($adminError ?? null): ?>
  <div class="alert alert-error" style="margin-bottom:16px;"><?= h($adminError) ?></div>
  <?php endif; ?>

  <div class="admin-form-section">
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="admin_login"/>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Username</label>
        <input type="text" name="username" class="admin-input" placeholder="admin" required autocomplete="username"/>
      </div>
      <div style="margin-bottom:20px;">
        <label class="admin-label">Password</label>
        <input type="password" name="password" class="admin-input" placeholder="••••••••" required autocomplete="current-password"/>
      </div>
      <button type="submit" class="btn-admin-primary" style="width:100%;justify-content:center;padding:11px;">Sign In to Admin</button>
    </form>
  </div>
  <p style="text-align:center;margin-top:16px;font-size:12px;color:var(--text3);">
    <a href="../index.php?page=catalog" style="color:var(--accent);">← Back to App</a>
  </p>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
