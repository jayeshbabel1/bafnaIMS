<?php
$pageTitle = 'Profile — ' . APP_NAME;
$showNav   = true;
$user      = currentUser();
$initials  = getInitials($user['name'] ?? 'U');
$err       = $inlineError ?? null;
$uRole     = ROLES[$user['role'] ?? ''] ?? ($user['role'] ?? 'Trade Professional');

$db   = getDB();
$slSt = $db->prepare("SELECT COUNT(*) as c FROM shortlist WHERE user_id=?");
$slCl = $db->prepare("SELECT COUNT(*) as c FROM clients WHERE user_id=?");
$slCl->execute([$user['id']]);
$slSt->execute([$user['id']]);
$slCt = $slCl->fetch()['c'];
$slC = $slSt->fetch()['c'];

?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="profile-page">

    <!-- Hero card -->
    <div class="profile-hero-card">
      <div class="profile-avatar"><?= h($initials) ?></div>
      <p class="profile-name"><?= h($user['name'] ?? '') ?></p>
      <p class="profile-role"><?= h($uRole) ?><?= $user['city'] ? ' · '.h($user['city']) : '' ?></p>
      <?php if ($user['firm']): ?><p class="profile-firm"><?= h($user['firm']) ?></p><?php endif; ?>
      <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap;">
        <span class="badge" style="background:rgba(255,255,255,.15);color:rgba(255,255,255,.9);">
          <?= icon('verified',10) ?>&nbsp;Verified
        </span>
        <?php if ($user['experience']): ?>
        <span class="badge" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);"><?= h($user['experience']) ?></span>
        <?php endif; ?>
      </div>
      <div class="profile-stats">
        <div class="profile-stat">
          <div class="profile-stat-num"><?= $slC ?></div>
          <div class="profile-stat-label">Saved</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-num"><?= $slCt ?></div>
          <div class="profile-stat-label">Clients</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-num"><?= date('Y') - 2020 ?></div>
          <div class="profile-stat-label">Years</div>
        </div>
      </div>
    </div>

    <!-- Alert -->
    <?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>

    <!-- Edit Profile form (always visible) -->
    <div class="profile-section">
      <div class="profile-section-header">
        <div class="profile-section-icon"><?= icon('user',18) ?></div>
        <p class="profile-section-title">Edit Profile</p>
      </div>
      <div class="profile-section-body">
        <form method="POST" action="index.php?page=profile">
          <input type="hidden" name="action" value="update_profile"/>
          <?= csrfField() ?>
          <div class="profile-form-grid">
            <div class="input-group">
              <label class="input-label">Full Name</label>
              <input type="text" name="name" class="input-field" value="<?= h($user['name'] ?? '') ?>" required/>
            </div>
            <div class="input-group">
              <label class="input-label">Firm / Studio</label>
              <input type="text" name="firm" class="input-field" value="<?= h($user['firm'] ?? '') ?>"/>
            </div>
            <div class="input-group">
              <label class="input-label">City</label>
              <input type="text" name="city" class="input-field" value="<?= h($user['city'] ?? '') ?>"/>
            </div>
            <div class="input-group">
              <label class="input-label">Mobile</label>
              <input type="tel" name="phone" class="input-field" value="<?= h($user['phone'] ?? '') ?>"/>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="profile-section">
      <div class="profile-section-header">
        <div class="profile-section-icon"><?= icon('lock',18) ?></div>
        <p class="profile-section-title">Change Password</p>
      </div>
      <div class="profile-section-body">
        <form method="POST" action="index.php?page=profile">
          <input type="hidden" name="action" value="change_password"/>
          <?= csrfField() ?>
          <div class="profile-form-grid">
            <div class="input-group">
              <label class="input-label">Current Password</label>
              <div class="password-wrap">
                <input type="password" name="current_password" id="curPwd" class="input-field" placeholder="••••••••"/>
                <button type="button" class="pwd-toggle" data-target="curPwd"><?= icon('eye',16) ?></button>
              </div>
            </div>
            <div class="input-group">
              <label class="input-label">New Password</label>
              <div class="password-wrap">
                <input type="password" name="new_password" id="newPwd" class="input-field"
                       placeholder="Min. 8 characters" minlength="8"/>
                <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
      </div>
    </div>

    <!-- Account info -->
    <div class="profile-section">
      <div class="profile-section-header">
        <div class="profile-section-icon"><?= icon('info',18) ?></div>
        <p class="profile-section-title">Account Info</p>
      </div>
      <div class="profile-section-body" style="padding:0;">
        <div style="padding:14px 20px;display:flex;justify-content:space-between;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text3);">Email</span>
          <span style="font-size:13px;font-weight:600;"><?= h($user['email'] ?? '') ?></span>
        </div>
        <div style="padding:14px 20px;display:flex;justify-content:space-between;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text3);">Role</span>
          <span style="font-size:13px;font-weight:600;"><?= h($uRole) ?></span>
        </div>
        <div style="padding:14px 20px;display:flex;justify-content:space-between;">
          <span style="font-size:13px;color:var(--text3);">Member Since</span>
          <span style="font-size:13px;font-weight:600;"><?= date('M Y', $user['created_at'] ?? time()) ?></span>
        </div>
      </div>
    </div>

    <!-- Sign out -->
    <div style="margin-bottom:32px;">
      <form method="POST" action="index.php">
        <input type="hidden" name="action" value="logout"/>
        <?= csrfField() ?>
        <button type="submit" class="btn btn-danger btn-block" style="border-radius:var(--radius-lg);">
          <?= icon('logout',16) ?>&nbsp; Sign Out
        </button>
      </form>
      <p style="text-align:center;font-size:11px;color:var(--text4);margin-top:16px;">
        <?= APP_NAME ?> v<?= APP_VERSION ?> &nbsp;·&nbsp; © <?= date('Y') ?> Bafna Marbles Pvt. Ltd.
      </p>
    </div>

  </div>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>