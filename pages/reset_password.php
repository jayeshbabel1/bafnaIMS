<?php $pageTitle = 'Reset Password — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php
$token = $_GET['token'] ?? '';
$done  = !empty($_GET['done']);
// Validate token
$validToken = false;
if ($token) {
    $st = getDB()->prepare("SELECT id FROM users WHERE reset_token=? AND reset_expires > ?");
    $st->execute([$token, time()]);
    $validToken = (bool)$st->fetch();
}
?>

<div class="auth-page" style="padding-top:48px;">
  <div class="auth-logo-block fade-up" style="margin-bottom:28px;">
    <div class="logo-icon"><?= icon('lock',28,'icon-white') ?></div>
  </div>

  <?php if ($done): ?>
  <div class="auth-form fade-up">
    <h2 class="auth-title">Password updated!</h2>
    <p class="auth-sub">Your password has been reset successfully.</p>
    <a href="index.php?page=login" class="btn-primary" style="text-decoration:none;display:flex;justify-content:center;">Sign In Now</a>
  </div>

  <?php elseif (!$token || !$validToken): ?>
  <div class="auth-form fade-up">
    <h2 class="auth-title">Invalid link</h2>
    <p class="auth-sub">This reset link is invalid or has expired.</p>
    <a href="index.php?page=forgot_password" class="btn-primary" style="text-decoration:none;display:flex;justify-content:center;">Request New Link</a>
  </div>

  <?php else: ?>
  <div class="auth-form fade-up">
    <h2 class="auth-title">Set new password</h2>
    <p class="auth-sub">Choose a strong new password for your account.</p>

    <?php if ($inlineError ?? null): ?>
    <div class="alert alert-error"><?= h($inlineError) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=reset_password">
      <input type="hidden" name="action" value="reset_password"/>
      <input type="hidden" name="token"  value="<?= h($token) ?>"/>
      <div class="input-wrap">
        <label class="input-label">New Password</label>
        <div class="password-wrap">
          <input type="password" name="password" id="newPwd" class="input-field" placeholder="Min. 8 characters" required minlength="8"/>
          <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
        </div>
        <div class="pwd-strength" id="pwdStrength"></div>
      </div>
      <div class="input-wrap">
        <label class="input-label">Confirm Password</label>
        <input type="password" name="password_confirm" class="input-field" placeholder="Re-enter password" required/>
      </div>
      <button type="submit" class="btn-primary">Update Password</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>
