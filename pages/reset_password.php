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
  <div class="auth-hero" style="padding-bottom:28px;">
    <div class="auth-hero-logo">
      <div class="auth-hero-logo-icon">
        <svg width="22" height="22" viewBox="0 0 36 36" fill="none">
          <polygon points="18,4 32,28 4,28" fill="rgba(255,255,255,.18)" stroke="white" stroke-width="1.5"/>
          <polygon points="18,10 26,24 10,24" fill="rgba(255,255,255,.35)" stroke="white" stroke-width="1"/>
        </svg>
      </div>
      <span class="auth-hero-brand"><?= APP_NAME ?></span>
    </div>
    <div class="gold-bar" style="margin-bottom:6px;"></div>
    <h1 class="auth-hero-title" style="font-size:26px;">
      <?= $done ? 'Password Updated' : (!$token || !$validToken ? 'Invalid Link' : 'New Password') ?>
    </h1>
    <p class="auth-hero-sub">
      <?= $done ? 'You can now sign in with your new password.' : (!$token || !$validToken ? 'This reset link is invalid or has expired.' : 'Choose a strong new password.') ?>
    </p>
  </div>

  <div class="auth-card" style="flex:1;">
    <?php if ($done): ?>
    <div style="display:flex;flex-direction:column;align-items:center;padding:8px 0 24px;text-align:center;">
      <div class="auth-success-icon"><?= icon('check',24) ?></div>
      <p class="auth-card-title">All done!</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin:8px 0 24px;">Your password has been updated. You can now sign in.</p>
      <a href="index.php?page=login" class="btn-primary" style="text-decoration:none;width:100%;justify-content:center;">Sign In Now</a>
    </div>

    <?php elseif (!$token || !$validToken): ?>
    <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:8px 0 24px;">
      <div class="empty-icon" style="margin-bottom:16px;"><?= icon('info',28) ?></div>
      <p class="auth-card-title">Link expired</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin:10px 0 24px;">This reset link is invalid or has expired. Please request a new one.</p>
      <a href="index.php?page=forgot_password" class="btn-primary" style="text-decoration:none;width:100%;justify-content:center;">Request New Link</a>
    </div>

    <?php else: ?>
    <p class="auth-card-title">Set new password</p>
    <p class="auth-card-sub">Choose a strong password for your account.</p>

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
                 placeholder="Min. 8 characters" required minlength="8"/>
          <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
        </div>
        <div class="pwd-strength" id="pwdStrength"></div>
      </div>
      <div class="input-wrap">
        <label class="input-label">Confirm Password</label>
        <input type="password" name="password_confirm" class="input-field"
               placeholder="Re-enter password" required/>
      </div>
      <button type="submit" class="btn-primary">Update Password</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>
