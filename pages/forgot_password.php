<?php $pageTitle = 'Forgot Password — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php $sent = !empty($_GET['sent']); ?>
<?php
$err = $inlineError ?? null;
$presets = [
    ['F5F0E8','E8D5B5','C4A96E'],
    ['1A1A1A','2C2C2C','E8E8E8'],
    ['E8CECE','D4A8A8','B88080'],
    ['2D5A3D','4A8060','8EB89E'],
    ['D4931A','E8B84A','F5D98A'],
];
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

    <a href="index.php?page=login" class="back-btn"><?= icon('back',16) ?> Back to Login</a>
     
 <!-- Marble preview strip -->
    <div class="auth-marble-strip">
      <?php foreach ($presets as $pal): ?>
      <div class="auth-marble-item"><?= marbleSVG($pal, 100, 80) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="gold-bar" style="margin-bottom:8px;"></div>
    <h1 class="auth-hero-title">
      <?= $sent ? 'Check Your Inbox' : 'Reset Password' ?>
    </h1>
    <p class="auth-hero-sub">
      <?= $sent
        ? 'A password reset link has been sent to your registered email.'
        : 'Enter your email address and we\'ll send you a secure link to reset your password.' ?>
    </p>

   
  </div>

  <!-- ── FORM CARD ───────────────────────────────────────────────────────── -->
  <div class="auth-card">

    <?php if ($sent): ?>

    <div style="display:flex;flex-direction:column;align-items:center;padding:8px 0 24px;text-align:center;">
      <div class="auth-success-icon"><?= icon('check',24) ?></div>
      <p class="auth-card-title">Email Sent!</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin:10px 0 24px;max-width:360px;">
        If that email is registered, a password reset link has been sent. Check your inbox and spam folder.
        The link expires in 1 hour.
      </p>
      <div class="alert alert-success" style="width:100%;justify-content:center;margin-bottom:20px;">
        <?= icon('check',14) ?> Reset email sent successfully.
      </div>
      <a href="index.php?page=login" class="btn-primary" style="text-decoration:none;max-width:320px;">
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

      <div class="input-wrap">
        <label class="input-label">Email Address</label>
        <input type="email" name="email" class="input-field" placeholder="you@studio.com"
               required autocomplete="email"/>
      </div>

      <button type="submit" class="btn-primary">
        <?= icon('mail',16) ?>&nbsp; Send Reset Link
      </button>
    </form>

    <?php endif; ?>

    <p class="auth-footer-text" style="margin-top:22px;">
      Remember your password? <a href="index.php?page=login" class="auth-link">Sign in</a>
    </p>
  </div><!-- .auth-card -->

</div><!-- .auth-page -->

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>