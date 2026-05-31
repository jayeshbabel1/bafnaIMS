<?php
$pageTitle = 'Profile — ' . APP_NAME;
$showNav   = true;
$user      = currentUser();
$initials  = getInitials($user['name'] ?? 'U');
$err       = $inlineError ?? null;
$succ      = $inlineSuccess ?? null;
$sec       = $_GET['section'] ?? '';
$uRole     = ROLES[$user['role'] ?? ''] ?? ($user['role'] ?? 'Trade Professional');

$db   = getDB();
$slSt = $db->prepare("SELECT COUNT(*) as c FROM shortlist WHERE user_id=?");  $slSt->execute([$user['id']]); $slC = $slSt->fetch()['c'];
$iqSt = $db->prepare("SELECT COUNT(*) as c FROM inquiries WHERE user_id=?");   $iqSt->execute([$user['id']]); $iqC = $iqSt->fetch()['c'];
$rpSt = $db->prepare("SELECT COUNT(*) as c FROM inquiries WHERE user_id=? AND status='replied'"); $rpSt->execute([$user['id']]); $rpC = $rpSt->fetch()['c'];
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">

  <!-- ── TOPBAR ─────────────────────────────────────────────────────────── -->
  <div class="topbar">
    <div class="topbar-brand">
      <div class="topbar-logo" style="display:flex;">
        <svg width="20" height="20" viewBox="0 0 36 36" fill="none">
          <polygon points="18,4 32,28 4,28" fill="rgba(184,151,90,.18)" stroke="rgba(184,151,90,.9)" stroke-width="1.5"/>
          <polygon points="18,10 26,24 10,24" fill="rgba(184,151,90,.35)" stroke="rgba(184,151,90,.7)" stroke-width="1"/>
        </svg>
      </div>
      <div>
        <p class="topbar-eyebrow">Account</p>
        <p class="topbar-title">My Profile</p>
      </div>
    </div>
    <div class="topbar-actions">
      <a href="index.php?page=profile&section=settings" class="topbar-icon-btn"><?= icon('settings',17) ?></a>
    </div>
  </div>

  <!-- ── TWO-PANEL LAYOUT on desktop ────────────────────────────────────── -->
  <div class="profile-layout">

    <!-- LEFT: Hero panel ─────────────────────────────────────────────── -->
    <div class="profile-sidebar-panel">

      <!-- Profile hero card -->
      <div class="profile-hero" style="border-radius:12px;margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:14px;">
          <div class="profile-avatar"><?= h($initials) ?></div>
          <div style="flex:1;min-width:0;">
            <p class="profile-name" style="margin-top:0;font-size:20px;"><?= h($user['name'] ?? '') ?></p>
            <p class="profile-role"><?= h($uRole) ?><?= $user['city'] ? ' · '.h($user['city']) : '' ?></p>
            <?php if ($user['firm']): ?>
            <p class="profile-firm"><?= h($user['firm']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap;">
          <span class="badge badge-gold"><?= icon('verified',10) ?>&nbsp;Verified</span>
          <?php if ($user['experience']): ?>
          <span class="badge badge-dark"><?= h($user['experience']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats row -->
      <div class="stat-tiles-row" style="margin-top:0;margin-bottom:12px;">
        <div class="stat-tile">
          <div class="stat-tile-num"><?= $slC ?></div>
          <div class="stat-tile-label">Saved</div>
        </div>
        <div class="stat-tile">
          <div class="stat-tile-num"><?= $iqC ?></div>
          <div class="stat-tile-label">Inquiries</div>
        </div>
        <div class="stat-tile">
          <div class="stat-tile-num" style="color:var(--success);"><?= $rpC ?></div>
          <div class="stat-tile-label">Replied</div>
        </div>
      </div>

      <!-- Quick menu (desktop left panel) -->
      <div class="card" style="overflow:hidden;">
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
          <?= icon('forward',15) ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Sign out -->
      <form method="POST" action="index.php" style="margin-top:12px;">
        <input type="hidden" name="action" value="logout"/>
        <button type="submit" class="btn-danger-ghost"><?= icon('logout',16) ?>&nbsp; Sign Out</button>
      </form>

      <p style="text-align:center;font-size:10.5px;color:var(--text3);margin-top:20px;line-height:1.9;">
        <?= APP_NAME ?> v<?= APP_VERSION ?><br/>
        © <?= date('Y') ?> Bafna Marbles Pvt. Ltd.
      </p>
    </div>

    <!-- RIGHT: Settings panel ────────────────────────────────────────── -->
    <div>
      <?php if ($sec === 'settings'): ?>
      <!-- Edit Profile Form -->
      <div class="card" style="padding:22px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
          <a href="index.php?page=profile" class="hero-icon-btn"
             style="width:32px;height:32px;background:var(--surface2);text-decoration:none;"><?= icon('back',15) ?></a>
          <p style="font-weight:700;font-size:15px;">Edit Profile</p>
        </div>
        <?php if ($err):  ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>
        <?php if ($succ): ?><div class="alert alert-success"><?= h($succ) ?></div><?php endif; ?>

        <form method="POST" action="index.php?page=profile">
          <input type="hidden" name="action" value="update_profile"/>
          <!-- 2-col grid on wider screens -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
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
          </div>
          <button type="submit" class="btn-primary btn-gold" style="max-width:220px;">Save Changes</button>
        </form>

        <hr style="border:none;border-top:1px solid var(--border);margin:24px 0;"/>

        <p style="font-weight:700;font-size:14px;margin-bottom:16px;"><?= icon('lock',15) ?>&nbsp; Change Password</p>
        <form method="POST" action="index.php?page=profile">
          <input type="hidden" name="action" value="change_password"/>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;">
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
                <input type="password" name="new_password" id="newPwd" class="input-field"
                       placeholder="Min. 8 characters" minlength="8"/>
                <button type="button" class="pwd-toggle" data-target="newPwd"><?= icon('eye',16) ?></button>
              </div>
            </div>
          </div>
          <button type="submit" class="btn-primary btn-gold" style="max-width:220px;">Update Password</button>
        </form>
      </div>

      <?php else: ?>
      <!-- Default: show a welcome card on desktop right panel -->
      <div class="card" style="padding:28px;text-align:center;background:linear-gradient(135deg,var(--nav-bg),#2a2420);">
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(184,151,90,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <?= icon('verified',24) ?>
        </div>
        <p style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;color:#fff;margin-bottom:6px;">
          Welcome back, <?= h(explode(' ', $user['name'] ?? '')[0]) ?>
        </p>
        <p style="font-size:13px;color:rgba(255,255,255,.45);line-height:1.6;margin-bottom:20px;">
          You have <?= $iqC ?> active inquiry<?= $iqC !== 1 ? 'ies' : '' ?> and <?= $slC ?> saved product<?= $slC !== 1 ? 's' : '' ?>.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
          <a href="index.php?page=catalog" class="btn-primary btn-gold" style="width:auto;padding:12px 24px;text-decoration:none;"><?= icon('grid',14) ?>&nbsp; Browse Catalog</a>
          <a href="index.php?page=inquiries" class="btn-outline" style="width:auto;padding:12px 24px;border-color:rgba(255,255,255,.2);color:rgba(255,255,255,.7);text-decoration:none;"><?= icon('msg',14) ?>&nbsp; My Inquiries</a>
        </div>
      </div>

      <!-- Recent shortlist preview -->
      <?php
      $slPv = $db->prepare("SELECT p.name,p.quarry_number,p.category,
        (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
        FROM shortlist s JOIN products p ON s.product_id=p.id
        WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 3");
      $slPv->execute([$user['id']]);
      $slPreview = $slPv->fetchAll();
      if ($slPreview): ?>
      <div style="margin-top:14px;">
        <p style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px;">Recently Saved</p>
        <div class="card" style="overflow:hidden;">
          <?php foreach ($slPreview as $sp): ?>
          <a href="index.php?page=product&id=<?= /* need product id */ '' ?>" class="profile-menu-item">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;border-radius:6px;overflow:hidden;background:var(--surface2);flex-shrink:0;">
                <?php if ($sp['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$sp['primary_photo'])): ?>
                <img src="assets/uploads/photos/<?= h($sp['primary_photo']) ?>" style="width:100%;height:100%;object-fit:cover;" alt=""/>
                <?php endif; ?>
              </div>
              <div>
                <p style="font-size:13px;font-weight:600;color:var(--text);"><?= h($sp['name']) ?></p>
                <p style="font-size:11px;color:var(--text3);">Lot <?= h($sp['quarry_number']) ?></p>
              </div>
            </div>
            <?= icon('forward',14) ?>
          </a>
          <?php endforeach; ?>
          <a href="index.php?page=shortlist" class="profile-menu-item"
             style="color:var(--gold);font-size:13px;font-weight:600;justify-content:center;">
            View All Saved →
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div><!-- right panel -->

  </div><!-- .profile-layout -->
</div><!-- .page-content -->

<style>
/* Profile layout responsive overrides */
.profile-layout{
  display:block;
  padding:0;
}
.profile-sidebar-panel{
  padding:14px 14px 0;
}
.profile-sidebar-panel > div:last-of-type{
  padding:0 14px;
}
.stat-tiles-row{
  display:grid;
  grid-template-columns:1fr 1fr 1fr;
  gap:8px;
  margin-bottom:0;
}
/* On desktop: switch to two-column */
@media(min-width:1024px){
  .profile-layout{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:20px;
    padding:24px 28px;
    align-items:start;
  }
  .profile-sidebar-panel{
    padding:0;
    position:sticky;
    top:80px;
  }
  .profile-sidebar-panel > div:last-of-type{
    padding:0;
  }
}
@media(min-width:768px) and (max-width:1023px){
  .profile-layout{
    padding:0 20px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
    align-items:start;
    padding:14px 20px;
  }
  .profile-sidebar-panel{padding:0;}
  .profile-sidebar-panel > div:last-of-type{padding:0;}
}
</style>

<?php include BASE_PATH . '/layouts/footer.php'; ?>