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

  <div class="auth-left-panel">
    <?php if (!empty($_authLogo)): ?>
      <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
    <?php else: ?>
      <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
    <?php endif; ?>
    <p class="auth-left-panel-title"><?= h(ui('title_secure_password_reset', 'Secure Password Reset')) ?></p>
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

      <?php if ($done): ?>
      <div style="text-align:center;padding:8px 0 16px;">
        <div class="auth-success-icon" style="margin:0 auto 16px;"><?= icon('check',28) ?></div>
        <p class="auth-card-title"><?= h(ui('title_password_updated', 'Password Updated!')) ?></p>
        <p style="font-size:13px;color:#888;margin:10px 0 24px;line-height:1.6;"><?= h(ui('msg_password_changed', 'Your password has been changed. You can now sign in.')) ?></p>
        <a href="index.php?page=login" class="btn btn-primary btn-block btn-lg" style="text-decoration:none;"><?= h(ui('btn_sign_in_now', 'Sign In Now')) ?></a>
      </div>

      <?php elseif (!$token || !$validToken): ?>
      <div style="text-align:center;padding:8px 0 16px;">
        <div style="width:56px;height:56px;border-radius:50%;background:#FEF2F2;color:#B91C1C;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <?= icon('info',26) ?>
        </div>
        <p class="auth-card-title"><?= h(ui('title_link_expired', 'Link Expired')) ?></p>
        <p style="font-size:13px;color:#888;margin:10px 0 24px;line-height:1.6;"><?= h(ui('msg_link_expired', 'This reset link is invalid or expired. Reset links are valid for 1 hour.')) ?></p>
        <a href="index.php?page=forgot_password" class="btn btn-primary btn-block btn-lg" style="text-decoration:none;"><?= h(ui('btn_request_new_link', 'Request New Link')) ?></a>
      </div>

      <?php else: ?>
      <p class="auth-card-title"><?= h(ui('title_set_new_password', 'Set New Password')) ?></p>
      <p class="auth-card-sub"><?= h(ui('subtitle_choose_strong_password', 'Choose a strong password with at least 8 characters.')) ?></p>

      <?php if ($inlineError ?? null): ?>
      <div class="alert alert-error"><?= h($inlineError) ?></div>
      <?php endif; ?>

      <form method="POST" action="index.php?page=reset_password">
        <input type="hidden" name="action" value="reset_password"/>
        <input type="hidden" name="token"  value="<?= h($token) ?>"/>
         <?= csrfField() ?>
        <div class="input-group">
          <label class="input-label"><?= h(ui('form_new_password', 'New Password')) ?></label>
          <div class="password-wrap">
            <input type="password" name="password" id="newPwd" class="input-field"
                   placeholder="<?= h(ui('hint_min_8_chars', 'Min. 8 characters')) ?>" required minlength="8" autocomplete="new-password"/>
            <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
          </div>
          <div class="pwd-strength" id="pwdStrength"></div>
        </div>
        <div class="input-group">
          <label class="input-label"><?= h(ui('form_confirm_password', 'Confirm Password')) ?></label>
          <input type="password" name="password_confirm" class="input-field"
                 placeholder="<?= h(ui('form_reenter_new_password_placeholder', 'Re-enter your new password')) ?>" required autocomplete="new-password"/>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg"><?= h(ui('btn_update_password', 'Update Password')) ?></button>
      </form>
      <?php endif; ?>

      <p class="auth-footer-text">
        <a href="index.php?page=login" class="auth-link">← <?= h(ui('btn_back_to_login', 'Back to Login')) ?></a>
      </p>
    </div>

  </div>
</div>
<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>