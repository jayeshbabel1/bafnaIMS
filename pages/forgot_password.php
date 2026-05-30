<?php $pageTitle = 'Forgot Password — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php $sent = !empty($_GET['sent']); ?>

<div class="auth-page" style="padding-top:48px;">
  <a href="index.php?page=login" class="back-btn"><?= icon('back',18) ?> Back</a>

  <div class="auth-logo-block fade-up" style="margin-bottom:28px;">
    <div class="logo-icon" style="background:linear-gradient(135deg,var(--accent),var(--accent2));">
      <?= icon('mail', 28, 'icon-white') ?>
    </div>
  </div>

  <?php if ($sent): ?>
  <div class="auth-form fade-up">
    <h2 class="auth-title">Check your inbox</h2>
    <p class="auth-sub">If that email is registered, we've sent a reset link. Check your inbox (and spam folder).</p>
    <div class="alert alert-success" style="margin-bottom:24px;">
      <?= icon('check',16) ?> Reset email sent successfully.
    </div>
    <a href="index.php?page=login" class="btn-primary" style="text-decoration:none;display:flex;justify-content:center;">Back to Login</a>
  </div>

  <?php else: ?>
  <div class="auth-form fade-up">
    <h2 class="auth-title">Reset password</h2>
    <p class="auth-sub">Enter your registered email and we'll send you a reset link.</p>

    <?php if ($inlineError ?? null): ?>
    <div class="alert alert-error"><?= h($inlineError) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=forgot_password">
      <input type="hidden" name="action" value="forgot_password"/>
      <div class="input-wrap">
        <label class="input-label">Email Address</label>
        <input type="email" name="email" class="input-field" placeholder="you@studio.com" required autocomplete="email"/>
      </div>
      <button type="submit" class="btn-primary">Send Reset Link</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>
