<?php $pageTitle = 'Register — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<?php
$step = (int)($_GET['step'] ?? 1);
$err  = $inlineError ?? null;
$old  = $_SESSION['reg_data'] ?? [];
?>
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

    <div class="gold-bar" style="margin-bottom:8px;"></div>
    <h1 class="auth-hero-title">
      <?= $step === 1 ? 'Create Account' : 'Your Profession' ?>
    </h1>
    <p class="auth-hero-sub">
      <?= $step === 1 ? 'Join the exclusive trade network.' : 'Help us personalise your experience.' ?>
    </p>

    <!-- Desktop: show marble strip below text -->
    <div class="auth-marble-strip" style="margin-top:auto;">
      <?php
           foreach ($presets as $pal): ?>
      <div class="auth-marble-item"><?= marbleSVG($pal, 100, 110) ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── FORM CARD ───────────────────────────────────────────────────────── -->
  <div class="auth-card">

    <!-- Step indicator -->
    <div class="step-wrap">
      <p class="step-label">Step <?= $step ?> of 2</p>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $step * 50 ?>%"></div>
      </div>
    </div>

    <?php if ($err): ?>
    <div class="alert alert-error"><?= h($err) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>

    <p class="auth-card-title">Create Account</p>
    <p class="auth-card-sub">Sign up for exclusive trade access.</p>

    <form method="POST" action="index.php?page=register&step=1" novalidate>
      <input type="hidden" name="action" value="register_step1"/>

      <div class="input-wrap">
        <label class="input-label">Full Name <span class="req">*</span></label>
        <input type="text" name="name" class="input-field" placeholder="Rahul Sharma"
               value="<?= h($old['name'] ?? '') ?>" required autocomplete="name"/>
      </div>
      <div class="input-wrap">
        <label class="input-label">Email Address <span class="req">*</span></label>
        <input type="email" name="email" class="input-field" placeholder="you@studio.com"
               value="<?= h($old['email'] ?? '') ?>" autocomplete="email" required/>
      </div>
      <div class="input-wrap">
        <label class="input-label">Password <span class="req">*</span></label>
        <div class="password-wrap">
          <input type="password" name="password" id="regPwd" class="input-field"
                 placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password"/>
          <button type="button" class="pwd-toggle" data-target="regPwd"><?= icon('eye',16) ?></button>
        </div>
        <div class="pwd-strength" id="pwdStrength"></div>
      </div>
      <div class="input-wrap">
        <label class="input-label">Confirm Password <span class="req">*</span></label>
        <input type="password" name="password_confirm" class="input-field"
               placeholder="Re-enter password" required autocomplete="new-password"/>
      </div>
      <div class="input-wrap">
        <label class="input-label">Mobile Number</label>
        <div class="input-prefix-group">
          <span class="input-prefix">+91</span>
          <input type="tel" name="phone" class="input-field" placeholder="98765 43210"
                 value="<?= h($old['phone'] ?? '') ?>" maxlength="10" autocomplete="tel"/>
        </div>
      </div>
      <button type="submit" class="btn-primary">Continue →</button>
    </form>

    <?php else: ?>

    <p class="auth-card-title">Your Profession</p>
    <p class="auth-card-sub">Help us personalise your experience.</p>

    <form method="POST" action="index.php?page=register&step=2" novalidate>
      <input type="hidden" name="action" value="register_step2"/>

      <div class="input-wrap">
        <label class="input-label">Firm / Studio Name <span class="req">*</span></label>
        <input type="text" name="firm" class="input-field" placeholder="RS Architecture Studio"
               value="<?= h($old['firm'] ?? '') ?>" required/>
      </div>
      <div class="input-wrap">
        <label class="input-label">City <span class="req">*</span></label>
        <input type="text" name="city" class="input-field" placeholder="Mumbai"
               value="<?= h($old['city'] ?? '') ?>" required/>
      </div>

      <div class="input-wrap">
        <label class="input-label">Professional Role <span class="req">*</span></label>
        <div class="roles-grid">
          <?php foreach (ROLES as $val => $label): ?>
          <label class="role-option <?= ($old['role'] ?? '') === $val ? 'selected' : '' ?>">
            <input type="radio" name="role" value="<?= h($val) ?>"
                   <?= ($old['role'] ?? '') === $val ? 'checked' : '' ?> required/>
            <span class="role-check"><?= icon('check',11) ?></span>
            <span><?= h($label) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="input-wrap" style="margin-top:18px;">
        <label class="input-label">Years of Experience</label>
        <div class="chip-row">
          <?php foreach (EXPERIENCE_OPTIONS as $exp): ?>
          <label class="exp-chip <?= ($old['experience'] ?? '') === $exp ? 'active' : '' ?>">
            <input type="radio" name="experience" value="<?= h($exp) ?>" style="display:none"
                   <?= ($old['experience'] ?? '') === $exp ? 'checked' : '' ?>/>
            <?= h($exp) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn-primary" style="margin-top:22px;">Complete Registration →</button>
      <a href="index.php?page=register&step=1" class="btn-ghost"
         style="width:100%;justify-content:center;margin-top:10px;">← Back</a>
    </form>

    <?php endif; ?>

    <p class="auth-footer-text">
      Already registered? <a href="index.php?page=login" class="auth-link">Sign in</a>
    </p>
  </div><!-- .auth-card -->

</div><!-- .auth-page -->

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>