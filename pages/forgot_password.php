<?php $pageTitle = 'Forgot Password — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>
<?php $sent = !empty($_GET['sent']); ?>

<div class="auth-page">

  <div class="auth-left-panel">
    <?php if (!empty($_authLogo)): ?>
      <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
    <?php else: ?>
      <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
    <?php endif; ?>
    <p class="auth-left-panel-title">Secure Account<br>Recovery</p>
    <div class="auth-left-panel-accent"></div>
  </div>

  <div class="auth-right-panel">

    <div class="auth-logo-block">
      <?php if (!empty($_authLogo)): ?>
        <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
      <?php else: ?>
        <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
      <?php endif; ?>
    </div>

    <div class="auth-card">
      <span class="auth-card-accent"></span>

      <?php if ($sent): ?>
      <div style="text-align:center;padding:8px 0 16px;">
        <div class="auth-success-icon" style="margin:0 auto 16px;"><?= icon('check',28) ?></div>
        <p class="auth-card-title">Email Sent!</p>
        <p style="font-size:13px;color:#888;line-height:1.6;margin:10px 0 24px;">
          If that email is registered, a reset link has been sent. Check your inbox. Link expires in 1 hour.
        </p>
        <a href="index.php?page=login" class="btn btn-primary btn-block btn-lg" style="text-decoration:none;">
          <?= icon('back',15) ?>&nbsp; Back to Login
        </a>
      </div>

      <?php else: ?>
      <p class="auth-card-title">Forgot Password?</p>
      <p class="auth-card-sub">Enter your email and we'll send a reset link.</p>

      <?php if ($inlineError ?? null): ?>
      <div class="alert alert-error"><?= h($inlineError) ?></div>
      <?php endif; ?>

      <form method="POST" action="index.php?page=forgot_password">
        <input type="hidden" name="action" value="forgot_password"/>
         <?= csrfField() ?>
        <div class="input-group">
          <label class="input-label">Email Address</label>
          <input type="email" name="email" class="input-field"
                 placeholder="you@studio.com" required autocomplete="email"/>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <?= icon('mail',16) ?>&nbsp; Send Reset Link
        </button>
      </form>
      <?php endif; ?>

      <p class="auth-footer-text">
        <a href="index.php?page=login" class="auth-link">← Back to Login</a>
      </p>
    </div>

  </div>
</div>
<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>