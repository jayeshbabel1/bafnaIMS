<?php $pageTitle = 'Sign In — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php
$err = $inlineError ?? null;
$presets = [
    ['F5F0E8','E8D5B5','C4A96E'],
    ['1A1A1A','2C2C2C','E8E8E8'],
    ['E8CECE','D4A8A8','B88080'],
    ['2D5A3D','4A8060','8EB89E'],
];
?>

<div class="auth-page">
  <!-- Logo -->
  <div class="auth-logo-block fade-up">
    <div class="logo-icon">
      <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
        <polygon points="18,4 32,28 4,28" fill="rgba(255,255,255,0.15)" stroke="white" stroke-width="1.5"/>
        <polygon points="18,10 26,24 10,24" fill="rgba(255,255,255,0.3)" stroke="white" stroke-width="1"/>
      </svg>
    </div>
    <h1 class="logo-title serif"><?= APP_NAME ?></h1>
    <p class="logo-sub">Exclusive Trade Platform</p>
  </div>

  <!-- Marble strip -->
  <div class="marble-strip fade-up" style="animation-delay:.1s">
    <?php foreach ($presets as $pal): ?>
    <div class="marble-strip-item"><?= marbleSVG($pal, 100, 64) ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Form -->
  <div class="auth-form fade-up" style="animation-delay:.15s">
    <h2 class="auth-title">Welcome back</h2>
    <p class="auth-sub">Sign in to access the exclusive stone inventory.</p>

    <?php if ($err): ?>
    <div class="alert alert-error"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login" id="loginForm" novalidate>
      <input type="hidden" name="action" value="login"/>

      <div class="input-wrap">
        <label class="input-label">Email Address</label>
        <input type="email" name="email" class="input-field" placeholder="you@studio.com"
               value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" required/>
      </div>

      <div class="input-wrap">
        <label class="input-label">Password</label>
        <div class="password-wrap">
          <input type="password" name="password" id="loginPwd" class="input-field" placeholder="••••••••" autocomplete="current-password" required/>
          <button type="button" class="pwd-toggle" data-target="loginPwd"><?= icon('eye',16) ?></button>
        </div>
      </div>

      <div class="form-meta">
        <a href="index.php?page=forgot_password" class="auth-link" style="font-size:13px;">Forgot password?</a>
      </div>

      <button type="submit" class="btn-primary" style="margin-top:10px;">Sign In</button>
    </form>

    <p class="auth-footer" style="margin-top:24px;">
      New user? <a href="index.php?page=register" class="auth-link">Create an account</a>
    </p>
    <p class="auth-footer">
      Exclusive access for verified Architects &amp; Interior Designers.
    </p>
  </div>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>
