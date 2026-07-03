<?php $pageTitle = 'Sign In — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>
<?php $err = $inlineError ?? null; 
$tagline = getSetting('company_tagline', 'Premium Stone Catalog Platform');
?>

<div class="auth-page">

  <!-- Desktop left panel (hidden on mobile/tablet) -->
  <div class="auth-left-panel">
    <?php if (!empty($_authLogo)): ?>
      <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
    <?php else: ?>
      <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
    <?php endif; ?>
    <p class="auth-left-panel-title"><?= h($tagline) ?></p>
    <div class="auth-left-panel-accent"></div>
  </div>

  <!-- Right / Mobile form area -->
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
      <p class="auth-card-title">Welcome back</p>
      <p class="auth-card-sub">Sign in to access the inventory.</p>

      <?php if ($err): ?>
      <div class="alert alert-error"><?= h($err) ?></div>
      <?php endif; ?>

      <form method="POST" action="index.php?page=login" novalidate>
        <input type="hidden" name="action" value="login"/>
         <?= csrfField() ?>
        <div class="input-group">
          <label class="input-label">Email Address</label>
          <input type="email" name="email" class="input-field"
                 value="<?= h($_POST['email'] ?? '') ?>"
                 autocomplete="email" required/>
        </div>
        <div class="input-group" style="margin-bottom:10px;">
          <label class="input-label">Password</label>
          <div class="password-wrap">
            <input type="password" name="password" id="loginPwd" class="input-field"
                   autocomplete="current-password" required/>
            <button type="button" class="pwd-toggle" data-target="loginPwd"><?= icon('eye',16) ?></button>
          </div>
        </div>
        <div class="auth-forgot">
          <a href="index.php?page=forgot_password">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
      </form>

      <div class="gold-divider">OR</div>
      <p class="auth-footer-text">
        New user? <a href="index.php?page=register" class="auth-link">Create an account</a>
      </p>
    </div>

  </div>
</div>
<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>