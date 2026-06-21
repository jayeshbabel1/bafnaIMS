<?php
/**
 * pages/waiting_approval.php
 * Shown after registration and when unverified user tries to login.
 */
$pageTitle = 'Access Pending — ' . APP_NAME;
?>
<?php include BASE_PATH . '/layouts/auth_layout.php'; ?>

<div class="auth-page">

  <!-- Desktop left panel -->
  <div class="auth-left-panel">
    <?php if (!empty($_authLogo)): ?>
      <img src="<?= h($_authLogo) ?>" alt="<?= h(APP_NAME) ?>"/>
    <?php else: ?>
      <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1" alt="<?= h(APP_NAME) ?>"/>
    <?php endif; ?>
    <p class="auth-left-panel-title">Premium Stone<br>Catalog Platform</p>
    <div class="auth-left-panel-accent"></div>
  </div>

  <!-- Right / mobile area -->
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

      <!-- Icon -->
      <div style="display:flex;justify-content:center;margin-bottom:22px;">
        <div style="width:72px;height:72px;border-radius:50%;background:#f4f1ec;border:2px solid #e8ddd0;display:flex;align-items:center;justify-content:center;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
      </div>

      <p class="auth-card-title" style="text-align:center;">Access Pending Verification</p>

      <div style="background:#faf8f5;border:1px solid #ede5d8;border-radius:12px;padding:18px 20px;margin:18px 0 22px;">
        <p style="font-size:14px;color:#444;line-height:1.75;margin:0;text-align:center;">
          Your request to access the <strong>Bafna Marble Catalog Platform</strong> has been received.<br/><br/>
          You will be able to access the catalog once it is verified by the <strong>Bafna Marble Team</strong>.<br/><br/>
          You will receive an <strong>email notification</strong> when your account is approved.
        </p>
      </div>

      <!-- Progress indicator -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
        <div style="flex:1;height:4px;border-radius:2px;background:#0a0a0a;"></div>
        <div style="flex:1;height:4px;border-radius:2px;background:#e0d8ce;"></div>
        <div style="flex:1;height:4px;border-radius:2px;background:#e0d8ce;"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#aaa;margin-top:-18px;margin-bottom:24px;">
        <span style="color:#0a0a0a;">Registered</span>
        <span>Under Review</span>
        <span>Access Granted</span>
      </div>

      <!-- Actions -->
      <a href="index.php?page=login" class="btn btn-primary btn-block"
         style="justify-content:center;margin-bottom:10px;text-decoration:none;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        &nbsp;Back to Login
      </a>

      <!-- Support contact -->
      <div style="background:#f9f7f5;border-radius:10px;padding:16px;margin-top:20px;">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999;margin-bottom:12px;">Need Help?</p>
        <a href="tel:9898074441"
           style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;background:#fff;border:1px solid #e8ddd0;text-decoration:none;color:#333;font-size:13px;margin-bottom:8px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <span><strong>+91 9898074441</strong> &nbsp;·&nbsp; Mon–Sat 9AM–6PM</span>
        </a>
        <a href="mailto:sales@bafnamarbles.com"
           style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;background:#fff;border:1px solid #e8ddd0;text-decoration:none;color:#333;font-size:13px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <span>sales@bafnamarbles.com</span>
        </a>
      </div>

    </div>

  </div>
</div>

<?php include BASE_PATH . '/layouts/auth_footer.php'; ?>