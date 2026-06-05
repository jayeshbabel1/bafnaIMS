<?php $pageTitle = 'Forgot Password — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php
$sent = !empty($_GET['sent']);
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

    <a href="index.php?page=login" class="back-btn"><?= icon('back',16) ?> Back to Login</a>
    <div class="gold-bar"></div>
  </div>

  <!-- Form -->
  <div class="auth-card">

    <?php if ($sent): ?>
    <div style="display:flex;flex-direction:column;align-items:center;padding:8px 0 24px;text-align:center;">
      <div class="auth-success-icon"><?= icon('check',24) ?></div>
      <p class="auth-card-title">Email Sent!</p>
      <p style="font-size:14px;color:var(--text3);line-height:1.6;margin:10px 0 24px;max-width:360px;">
        If that email is registered, a password reset link has been sent. Check your inbox and spam folder.
        The link expires in 1 hour.
      </p>
      <div class="alert alert-success" style="width:100%;justify-content:center;margin-bottom:20px;">
        <?= icon('check',14) ?> Reset email sent successfully.
      </div>
      <a href="index.php?page=login" class="btn btn-primary btn-block btn-lg" style="text-decoration:none;">
        <?= icon('back',15) ?>&nbsp; Back to Login
      </a>
    </div>

    <?php else: ?>
    <p class="auth-card-title">Forgot Password?</p>
    <p class="auth-card-sub">We'll send a reset link to your registered email address.</p>

    <?php if ($inlineError ?? null): ?>
    <div class="alert alert-error"><?= h($inlineError) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=forgot_password">
      <input type="hidden" name="action" value="forgot_password"/>
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
      Remember your password? <a href="index.php?page=login" class="auth-link">Sign in</a>
    </p>
  </div>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>