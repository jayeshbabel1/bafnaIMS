<?php $pageTitle = 'Register — ' . APP_NAME; ?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>
<?php
$step = (int)($_GET['step'] ?? 1);
$err  = $inlineError ?? null;
$old  = $_SESSION['reg_data'] ?? [];
?>

<div class="auth-page">

  <div class="auth-left-panel">
    <?php if (!empty($_authLogo)): ?>
      <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
    <?php else: ?>
      <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
    <?php endif; ?>
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

      <div class="step-wrap">
        <p class="step-label">Step <?= $step ?> of 2</p>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $step * 50 ?>%"></div></div>
      </div>

      <?php if ($err): ?>
      <div class="alert alert-error"><?= h($err) ?></div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
      <p class="auth-card-title">Create Account</p>
      <p class="auth-card-sub">Sign up for exclusive trade access.</p>

      <form method="POST" action="index.php?page=register&step=1" novalidate>
        <input type="hidden" name="action" value="register_step1"/>
         <?= csrfField() ?>
        <div class="input-group">
          <label class="input-label">Full Name *</label>
          <input type="text" name="name" class="input-field" placeholder="Rahul Sharma"
                 value="<?= h($old['name'] ?? '') ?>" required autocomplete="name"/>
        </div>
        <div class="input-group">
          <label class="input-label">Email Address *</label>
          <input type="email" name="email" class="input-field" placeholder="you@studio.com"
                 value="<?= h($old['email'] ?? '') ?>" autocomplete="email" required/>
        </div>
        <div class="input-group">
          <label class="input-label">Password *</label>
          <div class="password-wrap">
            <input type="password" name="password" id="regPwd" class="input-field"
                   placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password"/>
            <button type="button" class="pwd-toggle" data-target="regPwd"><?= icon('eye',16) ?></button>
          </div>
          <div class="pwd-strength" id="pwdStrength"></div>
        </div>
        <div class="input-group">
          <label class="input-label">Confirm Password *</label>
          <input type="password" name="password_confirm" class="input-field"
                 placeholder="Re-enter password" required autocomplete="new-password"/>
        </div>
        <div class="input-group">
          <label class="input-label">Mobile Number</label>
          <div class="input-prefix-group">
            <span class="input-prefix">+91</span>
            <input type="tel" name="phone" class="input-field" placeholder="98765 43210"
                   value="<?= h($old['phone'] ?? '') ?>" maxlength="10" autocomplete="tel"/>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Continue →</button>
      </form>

      <?php else: ?>
      <p class="auth-card-title">Your Profession</p>
      <p class="auth-card-sub">Help us personalise your experience.</p>

      <form method="POST" action="index.php?page=register&step=2" novalidate>
        <input type="hidden" name="action" value="register_step2"/>
         <?= csrfField() ?>
        <div class="input-group">
          <label class="input-label">Firm / Studio Name *</label>
          <input type="text" name="firm" class="input-field" placeholder="RS Architecture Studio"
                 value="<?= h($old['firm'] ?? '') ?>" required/>
        </div>
        <div class="input-group">
          <label class="input-label">City *</label>
          <input type="text" name="city" class="input-field" placeholder="Mumbai"
                 value="<?= h($old['city'] ?? '') ?>" required/>
        </div>
        <div class="input-group">
          <label class="input-label">Professional Role *</label>
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
        <div class="input-group" style="margin-top:18px;">
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
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:22px;">Complete Registration →</button>
        <a href="index.php?page=register&step=1" class="btn btn-ghost btn-block" style="margin-top:10px;justify-content:center;">← Back</a>
      </form>
      <?php endif; ?>

      <p class="auth-footer-text">
        Already registered? <a href="index.php?page=login" class="auth-link">Sign in</a>
      </p>
    </div>

  </div>
</div>
<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>