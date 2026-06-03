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

  <!-- ── HERO PANEL ──────────────────────────────────────────────────────── -->
  <div class="auth-hero">
    <div class="auth-hero-logo">
      <div class="auth-hero-logo-icon">
        <?php if (!empty($_authLogo)): ?>
          <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
        <?php else: ?>
          <svg width="26" height="26" viewBox="0 0 36 36" fill="none">
            <polygon points="18,4 32,28 4,28" fill="rgba(255,255,255,.18)" stroke="white" stroke-width="1.5"/>
            <polygon points="18,10 26,24 10,24" fill="rgba(255,255,255,.35)" stroke="white" stroke-width="1"/>
          </svg>
        <?php endif; ?>
      </div>
      <span class="auth-hero-brand"><?= APP_NAME ?></span>
    </div>

    <?php if (!$done): ?>
    <a href="index.php?page=login" class="back-btn"><?= icon('back',16) ?> Back to Login</a>
    <?php endif; ?>
       <!-- Decorative marble strip -->
    <div class="auth-marble-strip" style="margin-top:auto;">
      <?php
      $presets = [
        ['1A1A1A','2C2C2C','E8E8E8'],
        ['F5F0E8','E8D5B5','C4A96E'],
        ['2D5A3D','4A8060','8EB89E'],
        ['606060','787878','D0D0D0'],
      ];
      foreach ($presets as $pal): ?>
      <div class="auth-marble-item"><?= marbleSVG($pal, 100, 110) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="gold-bar" style="margin-bottom:8px;"></div>
    <h1 class="auth-hero-title">
      <?php if ($done): ?>
        Password Updated
      <?php elseif (!$token || !$validToken): ?>
        Invalid Link
      <?php else: ?>
        New Password
      <?php endif; ?>
    </h1>
    <p class="auth-hero-sub">
      <?php if ($done): ?>
        Your password has been updated. You can now sign in with your new credentials.
      <?php elseif (!$token || !$validToken): ?>
        This reset link is invalid or has expired. Please request a new one.
      <?php else: ?>
        Choose a strong new password for your account.
      <?php endif; ?>
    </p>

 
  </div>

  <!-- ── FORM CARD ───────────────────────────────────────────────────────── -->
  <div class="auth-card">

    <?php if ($done): ?>

    <div style="display:flex;flex-direction:column;align-items:center;padding:8px 0 24px;text-align:center;">
      <div class="auth-success-icon" style="width:68px;height:68px;">
        <?= icon('check',28) ?>
      </div>
      <p class="auth-card-title">All Done!</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin:10px 0 24px;max-width:340px;">
        Your password has been successfully updated. You can now sign in with your new password.
      </p>
      <a href="index.php?page=login" class="btn-primary" style="text-decoration:none;max-width:320px;">
        <?= icon('lock',15) ?>&nbsp; Sign In Now
      </a>
    </div>

    <?php elseif (!$token || !$validToken): ?>

    <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:8px 0 24px;">
      <div class="empty-icon" style="margin-bottom:16px;width:64px;height:64px;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;border-radius:16px;">
        <?= icon('info',28) ?>
      </div>
      <p class="auth-card-title">Link Expired</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin:10px 0 24px;max-width:340px;">
        This password reset link is invalid or has already expired. Reset links are valid for 1 hour.
      </p>
      <a href="index.php?page=forgot_password" class="btn-primary" style="text-decoration:none;max-width:320px;">
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

      <div class="input-wrap">
        <label class="input-label">New Password</label>
        <div class="password-wrap">
          <input type="password" name="password" id="newPwd" class="input-field"
                 placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password"/>
          <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
        </div>
        <div class="pwd-strength" id="pwdStrength"></div>
      </div>

      <div class="input-wrap">
        <label class="input-label">Confirm Password</label>
        <input type="password" name="password_confirm" class="input-field"
               placeholder="Re-enter your new password" required autocomplete="new-password"/>
      </div>

      <button type="submit" class="btn-primary">
        <?= icon('lock',16) ?>&nbsp; Update Password
      </button>
    </form>

    <?php endif; ?>

    <p class="auth-footer-text" style="margin-top:22px;">
      Remembered it? <a href="index.php?page=login" class="auth-link">Sign in</a>
    </p>
  </div><!-- .auth-card -->

</div><!-- .auth-page -->

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>