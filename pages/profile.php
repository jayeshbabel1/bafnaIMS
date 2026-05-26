<?php
$pageTitle = 'My Profile — ' . APP_NAME;
$showNav   = true;
$user      = currentUser();
$initials  = getInitials($user['name'] ?? 'U');
$err       = $inlineError ?? null;
$succ      = $inlineSuccess ?? null;
$sec       = $_GET['section'] ?? '';
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>
<div class="page-content">
  <div class="topbar">
    <h1 class="topbar-title serif">My Profile</h1>
    <a href="index.php?page=profile&section=settings" class="topbar-icon-btn"><?= icon('settings',18) ?></a>
  </div>

  <div style="padding:16px 16px 0;">
    <!-- Profile card -->
    <div class="card profile-card" style="margin-bottom:16px;">
      <div class="profile-avatar"><?= h($initials) ?></div>
      <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <p style="font-size:17px;font-weight:700;"><?= h($user['name'] ?? '') ?></p>
          <span class="badge badge-blue"><?= icon('verified',10) ?>&nbsp;Verified</span>
        </div>
        <p style="font-size:13px;color:var(--text2);margin-top:3px;">
          <?= h(ROLES[$user['role'] ?? ''] ?? ($user['role'] ?? '')) ?><?= $user['city'] ? ' · '.h($user['city']) : '' ?>
        </p>
        <p style="font-size:12px;color:var(--text3);margin-top:2px;"><?= h($user['firm'] ?? '') ?></p>
        <?php if ($user['experience']): ?>
        <p style="font-size:11px;color:var(--text3);margin-top:2px;"><?= h($user['experience']) ?> experience</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats -->
    <?php
    $slSt = getDB()->prepare("SELECT COUNT(*) as c FROM shortlist WHERE user_id=?"); $slSt->execute([$user['id']]); $slC = $slSt->fetch()['c'];
    $iqSt = getDB()->prepare("SELECT COUNT(*) as c FROM inquiries WHERE user_id=?");  $iqSt->execute([$user['id']]); $iqC = $iqSt->fetch()['c'];
    $rpSt = getDB()->prepare("SELECT COUNT(*) as c FROM inquiries WHERE user_id=? AND status='replied'"); $rpSt->execute([$user['id']]); $rpC = $rpSt->fetch()['c'];
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:20px;">
      <div class="spec-tile" style="text-align:center;">
        <div style="font-size:24px;font-weight:700;color:var(--accent);font-family:'Cormorant Garamond',serif;"><?= $slC ?></div>
        <div class="spec-tile-label">Saved</div>
      </div>
      <div class="spec-tile" style="text-align:center;">
        <div style="font-size:24px;font-weight:700;color:var(--accent);font-family:'Cormorant Garamond',serif;"><?= $iqC ?></div>
        <div class="spec-tile-label">Inquiries</div>
      </div>
      <div class="spec-tile" style="text-align:center;">
        <div style="font-size:24px;font-weight:700;color:var(--success);font-family:'Cormorant Garamond',serif;"><?= $rpC ?></div>
        <div class="spec-tile-label">Replied</div>
      </div>
    </div>

    <?php if ($sec === 'settings'): ?>
    <!-- Edit Profile Form -->
    <div class="card" style="padding:18px;margin-bottom:16px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
        <a href="index.php?page=profile" class="hero-icon-btn" style="text-decoration:none;"><?= icon('back',16) ?></a>
        <p style="font-weight:700;font-size:15px;">Edit Profile</p>
      </div>
      <?php if ($err):  ?><div class="alert alert-error"   style="margin-bottom:12px;"><?= h($err) ?></div><?php endif; ?>
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
          <div class="password-wrap">
            <input type="password" name="current_password" id="curPwd" class="input-field" placeholder="••••••••"/>
            <button type="button" class="pwd-toggle" data-target="curPwd"><?= icon('eye',16) ?></button>
          </div>
        </div>
        <div class="input-wrap">
          <label class="input-label">New Password</label>
          <div class="password-wrap">
            <input type="password" name="new_password" id="newPwd" class="input-field" placeholder="Min. 8 characters" minlength="8"/>
            <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
          </div>
        </div>
        <button type="submit" class="btn-primary">Update Password</button>
      </form>
    </div>

    <?php else: ?>
    <!-- Menu -->
    <div class="card" style="margin-bottom:12px;overflow:hidden;">
      <?php $menuItems = [
        ['icon'=>'user',  'label'=>'Edit Profile',  'url'=>'index.php?page=profile&section=settings'],
        ['icon'=>'heart', 'label'=>'My Shortlist',  'url'=>'index.php?page=shortlist'],
        ['icon'=>'msg',   'label'=>'My Inquiries',  'url'=>'index.php?page=inquiries'],
        ['icon'=>'bell',  'label'=>'Notifications', 'url'=>'#'],
        ['icon'=>'info',  'label'=>'Help & Support', 'url'=>'#'],
      ];
      foreach ($menuItems as $item): ?>
      <a href="<?= h($item['url']) ?>" class="profile-menu-item">
        <div style="display:flex;align-items:center;gap:12px;">
          <div class="profile-menu-icon"><?= icon($item['icon'],16) ?></div>
          <span style="font-size:14px;font-weight:500;"><?= h($item['label']) ?></span>
        </div>
        <?= icon('forward',16) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="logout"/>
      <button type="submit" class="btn-danger-ghost" style="margin-top:4px;">
        <?= icon('logout',16) ?>&nbsp; Sign Out
      </button>
    </form>

    <p style="text-align:center;font-size:11px;color:var(--text3);margin-top:24px;line-height:1.8;">
      <?= APP_NAME ?> Trade Platform v<?= APP_VERSION ?><br/>
      © <?= date('Y') ?> Bafna Marbles Pvt. Ltd.
    </p>
  </div>
</div>
<?php include BASE_PATH . '/layouts/footer.php'; ?>