<?php $pageTitle = 'Reset Password — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php
$token      = $_GET['token'] ?? '';
$done       = !empty($_GET['done']);
$validToken = false;
if ($token) {
    $st = getDB()->prepare("SELECT id FROM users WHERE reset_token=? AND reset_expires > ?");
    $st->execute([$token, time()]);
    $validToken = (bool)$st->fetch();
}

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

    <?php if (!$done): ?>
    <a href="index.php?page=login" class="back-btn"><?= icon('back',16) ?> Back to Login</a>
    <?php endif; ?>

 

    <div class="gold-bar"></div>
    <h1 class="auth-hero-title">
      <?php if ($done): ?>Password Updated
      <?php elseif (!$token || !$validToken): ?>Invalid Link
      <?php else: ?>New Password<?php endif; ?>
    </h1>
    <p class="auth-hero-sub">
      <?php if ($done): ?>Your password has been updated successfully.
      <?php elseif (!$token || !$validToken): ?>This reset link is invalid or has expired.
      <?php else: ?>Choose a strong new password for your account.<?php endif; ?>
    </p>
  </div>

  <!-- Form -->
  <div class="auth-card">

    <?php if ($done): ?>
    <div style="display:flex;flex-direction:column;align-items:center;padding:8px 0 24px;text-align:center;">
      <div class="auth-success-icon"><?= icon('check',28) ?></div>
      <p class="auth-card-title">All Done!</p>
      <p style="font-size:14px;color:var(--text3);line-height:1.6;margin:10px 0 24px;max-width:340px;">
        Your password has been successfully updated. You can now sign in with your new password.
      </p>
      <a href="index.php?page=login" class="btn btn-primary btn-block btn-lg" style="text-decoration:none;">
        <?= icon('lock',15) ?>&nbsp; Sign In Now
      </a>
    </div>

    <?php elseif (!$token || !$validToken): ?>
    <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:8px 0 24px;">
      <div style="width:60px;height:60px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
        <?= icon('info',28) ?>
      </div>
      <p class="auth-card-title">Link Expired</p>
      <p style="font-size:14px;color:var(--text3);line-height:1.6;margin:10px 0 24px;max-width:340px;">
        This reset link is invalid or has already expired. Reset links are valid for 1 hour.
      </p>
      <a href="index.php?page=forgot_password" class="btn btn-primary btn-block btn-lg" style="text-decoration:none;">
        <?= icon('mail',15) ?>&nbsp; Request New Link
      </a>
    </div>

    <?php else: ?>
    <p class="auth-card-title">Set New Password</p>
    <p class="auth-card-sub">Choose a strong password with at least 8 characters.</p>

    <?php if ($inlineError ?? null): ?>
    <div class="alert alert-error"><?= h($inlineError) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=reset_password">
      <input type="hidden" name="action" value="reset_password"/>
      <input type="hidden" name="token"  value="<?= h($token) ?>"/>

      <div class="input-group">
        <label class="input-label">New Password</label>
        <div class="password-wrap">
          <input type="password" name="password" id="newPwd" class="input-field"
                 placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password"/>
          <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
        </div>
        <div class="pwd-strength" id="pwdStrength"></div>
      </div>
      <div class="input-group">
        <label class="input-label">Confirm Password</label>
        <input type="password" name="password_confirm" class="input-field"
               placeholder="Re-enter your new password" required autocomplete="new-password"/>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">
        <?= icon('lock',16) ?>&nbsp; Update Password
      </button>
    </form>
    <?php endif; ?>

    <p class="auth-footer-text" style="margin-top:22px;">
      Remembered it? <a href="index.php?page=login" class="auth-link">Sign in</a>
    </p>
  </div>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>