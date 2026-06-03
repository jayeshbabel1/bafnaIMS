<?php $pageTitle = 'Sign In — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php
$err = $inlineError ?? null;
$presets = [
    ['F5F0E8','E8D5B5','C4A96E'],
    ['1A1A1A','2C2C2C','E8E8E8'],
    ['E8CECE','D4A8A8','B88080'],
    ['2D5A3D','4A8060','8EB89E'],
    ['D4931A','E8B84A','F5D98A'],
];
?>

<div class="auth-page">

  <!-- ── HERO PANEL ──────────────────────────────────────────────────────── -->
  <div class="auth-hero">
    <div class="auth-hero-logo">
      <div class="auth-hero-logo-icon">
        <?php if (!empty($_authLogo)): ?>
          <img width="50" height="50" src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
        <?php else: ?>
          <img width="40" height="40"
               src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
               alt="<?= h(APP_NAME) ?>" decoding="async" style="width:100%;height:100%;object-fit:contain;"/>
        <?php endif; ?>
      </div>
      <span class="auth-hero-brand"><?= APP_NAME ?></span>
    </div>

    <!-- Marble preview strip -->
    <div class="auth-marble-strip">
      <?php foreach ($presets as $pal): ?>
      <div class="auth-marble-item"><?= marbleSVG($pal, 100, 80) ?></div>
      <?php endforeach; ?>
    </div>

    <div class="gold-bar"></div>
    <h1 class="auth-hero-title">Premium Stone<br/>Management Platform</h1>
    <p class="auth-hero-sub">Exclusive access for architects,<br/>interior designers &amp; trade professionals.</p>
  </div>

  <!-- ── FORM CARD ───────────────────────────────────────────────────────── -->
  <div class="auth-card">
    <p class="auth-card-title">Welcome back</p>
    <p class="auth-card-sub">Sign in to access the inventory.</p>

    <?php if ($err): ?>
    <div class="alert alert-error"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login" novalidate>
      <input type="hidden" name="action" value="login"/>

      <div class="input-wrap">
        <label class="input-label">Email Address</label>
        <input type="email" name="email" class="input-field" placeholder="Enter Email Address"
               value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" required/>
      </div>

      <div class="input-wrap" style="margin-bottom:8px;">
        <label class="input-label">Password</label>
        <div class="password-wrap">
          <input type="password" name="password" id="loginPwd" class="input-field"
                 placeholder="" autocomplete="current-password" required/>
          <button type="button" class="pwd-toggle" data-target="loginPwd"><?= icon('eye',16) ?></button>
        </div>
      </div>

      <div style="text-align:right;margin-bottom:20px;">
        <a href="index.php?page=forgot_password" class="auth-link" style="font-size:12.5px;">Forgot password?</a>
      </div>

      <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <p class="auth-footer-text" style="margin-top:22px;">
      New user? <a href="index.php?page=register" class="auth-link">Create an account</a>
    </p>
    <p style="text-align:center;font-size:11px;color:var(--text3);margin-top:10px;line-height:1.6;">
      Exclusive access for verified trade professionals only.
    </p>
  </div><!-- .auth-card -->

</div><!-- .auth-page -->

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>