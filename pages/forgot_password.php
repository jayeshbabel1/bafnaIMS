<?php $pageTitle = 'Forgot Password — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php $sent = !empty($_GET['sent']); ?>

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
    <a href="index.php?page=login" class="back-btn"><?= icon('back',16) ?> Back to Login</a>
    <div class="gold-bar" style="margin-bottom:6px;"></div>
    <h1 class="auth-hero-title" style="font-size:26px;">
      <?= $sent ? 'Check your inbox' : 'Reset Password' ?>
    </h1>
    <p class="auth-hero-sub">
      <?= $sent ? 'A reset link has been sent to your email.' : 'Enter your email to receive a reset link.' ?>
    </p>
  </div>

  <div class="auth-card" style="flex:1;">
    <?php if ($sent): ?>
    <div style="display:flex;flex-direction:column;align-items:center;padding:8px 0 24px;text-align:center;">
      <div class="auth-success-icon"><?= icon('check',24) ?></div>
      <p class="auth-card-title">Email Sent!</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin:8px 0 24px;">
        If that email is registered, a password reset link has been sent. Check your inbox and spam folder.
      </p>
      <div class="alert alert-success" style="width:100%;justify-content:center;"><?= icon('check',14) ?> Reset email sent successfully.</div>
      <a href="index.php?page=login" class="btn-primary" style="margin-top:4px;text-decoration:none;">Back to Login</a>
    </div>

    <?php else: ?>
    <p class="auth-card-title">Forgot password?</p>
    <p class="auth-card-sub">We'll send a reset link to your registered email.</p>

    <?php if ($inlineError ?? null): ?>
    <div class="alert alert-error"><?= h($inlineError) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=forgot_password">
      <input type="hidden" name="action" value="forgot_password"/>
      <div class="input-wrap">
        <label class="input-label">Email Address</label>
        <input type="email" name="email" class="input-field" placeholder="you@studio.com"
               required autocomplete="email"/>
      </div>
      <button type="submit" class="btn-primary"><?= icon('mail',16) ?>&nbsp; Send Reset Link</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>
