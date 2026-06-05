<?php $pageTitle = 'Sign In — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php
$err = $inlineError ?? null;
?>

<div class="auth-page">
  <!-- Hero -->
  <div class="auth-hero">
    <div class="auth-hero-logo">
      <div class="auth-hero-logo-icon">
        <?php if (!empty($_authLogo)): ?>
          <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
        <?php else: ?>
          <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
               alt="<?= h(APP_NAME) ?>" style="width:100%;height:100%;object-fit:contain;filter:brightness(0) invert(1);"/>
        <?php endif; ?>
      </div>
      <span class="auth-hero-brand"><?= APP_NAME ?></span>
    </div>
  </div>
  <!-- Form -->
  <div class="auth-card">
    

    <p class="auth-card-title">Welcome back</p>
    <p class="auth-card-sub">Sign in to access the inventory.</p>

    <?php if ($err): ?>
    <div class="alert alert-error"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login" novalidate>
      <input type="hidden" name="action" value="login"/>

      <div class="input-group">
        <label class="input-label">Email Address</label>
        <input type="email" name="email" class="input-field"
               placeholder=""
               value="<?= h($_POST['email'] ?? '') ?>"
               autocomplete="email" required/>
      </div>

      <div class="input-group" style="margin-bottom:8px;">
        <label class="input-label">Password</label>
        <div class="password-wrap">
          <input type="password" name="password" id="loginPwd" class="input-field"
                 placeholder="" autocomplete="current-password" required/>
          <button type="button" class="pwd-toggle" data-target="loginPwd"><?= icon('eye',16) ?></button>
        </div>
      </div>

      <div style="text-align:right;margin-bottom:22px;">
        <a href="index.php?page=forgot_password" class="auth-link" style="font-size:13px;font-weight:600;">
          Forgot password?
        </a>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
    </form>

    <p class="auth-footer-text">
      New user? <a href="index.php?page=register" class="auth-link">Create an account</a>
    </p>
    <p style="text-align:center;font-size:11px;color:var(--text4);margin-top:10px;">
      Exclusive access for verified professionals only.
    </p>
  </div>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>