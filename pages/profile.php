<?php
$pageTitle = 'My Profile — ' . APP_NAME;
$showNav   = true;
$user      = currentUser();
$initials  = getInitials($user['name'] ?? 'U');
$err       = $inlineError ?? null;
$succ      = $inlineSuccess ?? null;
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="topbar">
    <h1 class="topbar-title serif">My Profile</h1>
    <a href="index.php?page=profile&section=settings" class="topbar-icon-btn"><?= icon('settings',18) ?></a>
  </div>

  <!-- Profile card -->
  <div style="padding:16px 16px 0;">
    <div class="card" style="padding:20px;display:flex;gap:16px;align-items:center;margin-bottom:16px;">
      <div class="profile-avatar"><?= h($initials) ?></div>
      <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <p style="font-size:17px;font-weight:700;"><?= h($user['name'] ?? '') ?></p>
          <span class="badge badge-blue"><?= icon('verified',10) ?>&nbsp;Verified</span>
        </div>
        <p style="font-size:13px;color:var(--text2);margin-top:3px;"><?= h(ROLES[$user['role'] ?? ''] ?? ($user['role'] ?? '')) ?><?= $user['city'] ? ' · '.h($user['city']) : '' ?></p>
        <p style="font-size:12px;color:var(--text3);margin-top:2px;"><?= h($user['firm'] ?? '') ?></p>
      </div>
    </div>

    <!-- Stats strip -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
      <?php
        $slSt = getDB()->prepare("SELECT COUNT(*) as c FROM shortlist WHERE user_id=?"); $slSt->execute([$user['id']]); $slC = $slSt->fetch()['c'];
        $iqSt = getDB()->prepare("SELECT COUNT(*) as c FROM inquiries WHERE user_id=?"); $iqSt->execute([$user['id']]); $iqC = $iqSt->fetch()['c'];
      ?>
      <div class="spec-tile" style="text-align:center;">
        <div style="font-size:26px;font-weight:700;color:var(--accent);font-family:'Cormorant Garamond',serif;"><?= $slC ?></div>
        <div class="spec-tile-label">Saved Products</div>
      </div>
      <div class="spec-tile" style="text-align:center;">
        <div style="font-size:26px;font-weight:700;color:var(--accent);font-family:'Cormorant Garamond',serif;"><?= $iqC ?></div>
        <div class="spec-tile-label">Inquiries Sent</div>
      </div>
    </div>

    <!-- Edit Profile -->
    <?php $sec = $_GET['section'] ?? ''; ?>
    <?php if ($sec === 'settings'): ?>
    <div class="card" style="padding:18px;margin-bottom:16px;">
      <p style="font-weight:700;font-size:15px;margin-bottom:16px;"><?= icon('edit',16) ?> Edit Profile</p>
      <?php if ($err):  ?><div class="alert alert-error"  style="margin-bottom:12px;"><?= h($err) ?></div><?php endif; ?>
      <?php if ($succ): ?><div class="alert alert-success" style="margin-bottom:12px;"><?= h($succ) ?></div><?php endif; ?>
      <form method="POST" action="index.php?page=profile">
        <input type="hidden" name="action" value="update_profile"/>
        <div class="input-wrap">
          <label class="input-label">Full Name</label>
          <input type="text" name="name" class="input-field" value="<?= h($user['name'] ?? '') ?>" required/>
        </div>
        <div class="input-wrap">
          <label class="input-label">Firm / Studio</label>
          <input type="text" name="firm" class="input-field" value="<?= h($user['firm'] ?? '') ?>"/>
        </div>
        <div class="input-wrap">
          <label class="input-label">City</label>
          <input type="text" name="city" class="input-field" value="<?= h($user['city'] ?? '') ?>"/>
        </div>
        <div class="input-wrap">
          <label class="input-label">Mobile</label>
          <input type="tel" name="phone" class="input-field" value="<?= h($user['phone'] ?? '') ?>"/>
        </div>
        <button type="submit" class="btn-primary">Save Changes</button>
      </form>

      <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;"/>
      <p style="font-weight:700;font-size:15px;margin-bottom:14px;"><?= icon('lock',16) ?> Change Password</p>
      <form method="POST" action="index.php?page=profile">
        <input type="hidden" name="action" value="change_password"/>
        <div class="input-wrap">
          <label class="input-label">Current Password</label>
          <input type="password" name="current_password" class="input-field" placeholder="••••••••"/>
        </div>
        <div class="input-wrap">
          <label class="input-label">New Password</label>
          <input type="password" name="new_password" class="input-field" placeholder="Min. 8 characters" minlength="8"/>
        </div>
        <button type="submit" class="btn-primary">Update Password</button>
      </form>
    </div>

    <?php else: ?>
    <!-- Menu items -->
    <div class="card" style="margin-bottom:12px;overflow:hidden;">
      <?php $menuItems = [
        ['icon'=>'user',    'label'=>'Edit Profile',   'url'=>'index.php?page=profile&section=settings'],
        ['icon'=>'heart',   'label'=>'My Shortlist',   'url'=>'index.php?page=shortlist'],
        ['icon'=>'msg',     'label'=>'My Inquiries',   'url'=>'index.php?page=inquiries'],
        ['icon'=>'bell',    'label'=>'Notifications',  'url'=>'#'],
        ['icon'=>'info',    'label'=>'Help & Support',  'url'=>'#'],
      ];
      foreach ($menuItems as $item): ?>
      <a href="<?= h($item['url']) ?>" class="profile-menu-item">
        <div style="display:flex;align-items:center;gap:12px;">
          <div class="profile-menu-icon"><?= icon($item['icon'],16) ?></div>
          <span style="font-size:14px;font-weight:500;"><?= h($item['label']) ?></span>
        </div>
        <?= icon('forward',16,'') ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Sign out -->
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="logout"/>
      <button type="submit" class="btn-danger-ghost" style="margin-top:4px;">
        <?= icon('logout',16) ?>&nbsp; Sign Out
      </button>
    </form>

    <p style="text-align:center;font-size:11px;color:var(--text3);margin-top:20px;line-height:1.7;">
      <?= APP_NAME ?> Trade Platform v<?= APP_VERSION ?><br/>
      © <?= date('Y') ?> Bafna Marbles Pvt. Ltd.
    </p>
  </div>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
