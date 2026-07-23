<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= h($adminTitle ?? 'Admin') ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="<?= getFontEmbedUrl(true) ?>" rel="stylesheet"/>
<style><?= getCSSVariables(true) ?></style>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/admin.css"/>
<!--<link rel="stylesheet" href="../assets/css/watermark.css"/>-->
  <link rel="stylesheet" href="../assets/css/clients.css"/>
</head>
<body class="admin-body">

<?php
/*  Prerequisites  */
if (!function_exists('getLogo')) {
    require_once BASE_PATH . '/includes/logo.php';
}

$_notifCount  = 0;
$_recentNotifs = [];
try {
    $cutoff20 = time() - (20 * 86400);
    $ns = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND created_at >= ?");
    $ns->execute([$cutoff20]);
    $_notifCount = (int)$ns->fetchColumn();

    $nsr = getDB()->prepare("SELECT * FROM notifications WHERE created_at >= ? ORDER BY created_at DESC LIMIT 5");
    $nsr->execute([$cutoff20]);
    $_recentNotifs = $nsr->fetchAll();
} catch (Throwable $_e) {}

$_adminLogo = getLogo(true);
$ap = $_GET['page'] ?? 'dashboard';

$settingsPages    = ['colors', 'logo', 'smtp','license'];
$isSettingsActive = in_array($ap, $settingsPages);

$t = getFlash('toast');
$e = getFlash('error');
?>

<?php if ($t): ?><div class="toast toast-success" id="admin-toast"><?= h($t) ?></div><?php endif; ?>
<?php if ($e): ?><div class="toast toast-error"   id="admin-toast"><?= h($e) ?></div><?php endif; ?>

<header class="admin-mobile-topbar" id="adminMobileTopbar">
 
  <!-- Left: logo + brand -->
  <div class="amt-brand">
    <?php if ($_adminLogo): ?>
      <img src="<?= h($_adminLogo) ?>" alt="<?= h(APP_NAME) ?>"
           class="amt-logo" style="filter:brightness(0) invert(0);"/>
    <?php else: ?>
      <img src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
           alt="<?= h(APP_NAME) ?>" class="amt-logo"/>
    <?php endif; ?>
    <div>
      <p class="amt-name"><?= h(APP_NAME) ?></p>
      <p class="amt-sub">Admin Panel</p>
    </div>
  </div>
 
  <!-- Right: logout (always visible on mobile) + hamburger -->
  <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
    <form method="POST" action="index.php" style="display:contents;">
      <input type="hidden" name="action" value="admin_logout"/>
      <?= csrfField() ?>
      <button type="submit"
              class="amt-logout-btn"
              title="Sign Out">
        <?= icon('logout', 16) ?>
        <span>Logout</span>
      </button>
    </form>
    <button class="admin-hamburger-btn" id="adminHamburgerBtn"
            aria-label="Open menu" aria-expanded="false" aria-controls="adminSidebar"
            type="button">
      <span class="admin-hamburger-line"></span>
      <span class="admin-hamburger-line"></span>
      <span class="admin-hamburger-line"></span>
    </button>
  </div>
 
</header>

<!-- Overlay (mobile/tablet) -->
<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

<style>
/*  Settings submenu  */
 .amt-logout-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 7px 10px;
  border-radius: 8px;
  background: var(--danger-bg, #FFF0F0);
  color: var(--danger, #E84040);
  border: 1px solid var(--danger-bg, #FFF0F0);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  white-space: nowrap;
  transition: background .15s;
  flex-shrink: 0;
}
.amt-logout-btn:hover {
  background: var(--danger, #E84040);
  color: #fff;
}
/* Hide logout label on very small screens, keep icon */
@media (max-width: 380px) {
  .amt-logout-btn span { display: none; }
  .amt-logout-btn { padding: 7px 9px; }
}
/* Show the logout button only on mobile/tablet */
.amt-logout-btn { display: flex; }
@media (min-width: 1025px) {
  .amt-logout-btn { display: none; }
}
 
/* Sidebar logout button emphasis  */
.admin-logout-btn {
  color: rgba(255,255,255,0.65) !important;
  transition: color .15s, background .15s !important;
}
.admin-logout-btn:hover {
  background: rgba(232,64,64,.25) !important;
  color: #fff !important;
} 
.admin-nav-group          { width:100%; }
.admin-nav-group-header   {
  display:flex;align-items:center;gap:10px;padding:10px 12px;
  border-radius:10px;color:rgba(255,255,255,.65);font-size:13px;
  font-weight:500;cursor:pointer;transition:all .15s;
  background:none;border:none;width:100%;text-align:left;font-family:inherit;
}
.admin-nav-group-header:hover,
.admin-nav-group-header.active { background:rgba(255,255,255,.12);color:#fff; }
.admin-nav-group-header svg    { flex-shrink:0; }
.admin-nav-group-chevron {
  margin-left:auto;transition:transform .2s;flex-shrink:0;opacity:.6;
}
.admin-nav-group-chevron.open  { transform:rotate(90deg); }
.admin-nav-submenu  { display:none;flex-direction:column;gap:1px;padding:2px 0 4px 28px; }
.admin-nav-submenu.open { display:flex; }
.admin-nav-subitem  {
  display:flex;align-items:center;gap:8px;padding:7px 12px;
  border-radius:8px;color:rgba(255,255,255,.55);font-size:12px;
  font-weight:500;cursor:pointer;transition:all .15s;
  text-decoration:none;border:none;width:100%;text-align:left;
  background:none;font-family:inherit;
}
.admin-nav-subitem:hover          { background:rgba(255,255,255,.10);color:rgba(255,255,255,.9); }
.admin-nav-subitem.active         { background:rgba(255,255,255,.15);color:#fff; }
.admin-nav-subitem-dot            { width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.4);flex-shrink:0; }
.admin-nav-subitem.active .admin-nav-subitem-dot { background:var(--gold,#B8975A); }

/*  Notification bell dropdown  */
.notif-bell-wrap { position:relative; }
.notif-bell-btn  {
  position:relative;width:36px;height:36px;border-radius:8px;
  background:var(--surface2);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  color:var(--text2);cursor:pointer;transition:background .15s;
}
.notif-bell-btn:hover { background:var(--surface3); }
.notif-bell-badge {
  position:absolute;top:-4px;right:-4px;
  background:var(--danger);color:#fff;
  font-size:9px;font-weight:700;
  min-width:16px;height:16px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  padding:0 4px;border:2px solid var(--surface);
}
.notif-dropdown {
  display:none;position:absolute;top:calc(100% + 8px);right:0;
  width:320px;background:var(--surface);
  border:1px solid var(--border);border-radius:12px;
  box-shadow:0 8px 32px rgba(0,0,0,.12);z-index:1000;overflow:hidden;
}
.notif-dropdown.open        { display:block;animation:fadeUp .2s ease; }
.notif-dropdown-head        {
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 16px;border-bottom:1px solid var(--border);background:var(--surface2);
}
.notif-dropdown-body        { max-height:320px;overflow-y:auto; }
.notif-drop-item            { padding:12px 16px;border-bottom:1px solid var(--border);transition:background .12s; }
.notif-drop-item:last-child { border-bottom:none; }
.notif-drop-item:hover      { background:var(--surface2); }
.notif-drop-item--unread    { background:var(--accent-light); }
</style>

<div class="admin-shell">

  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-logo">
      <div class="admin-logo-icon">
        <?php if ($_adminLogo): ?>
          <img src="<?= h($_adminLogo) ?>" alt="<?= h(APP_NAME) ?>"
               width="40" height="40" style="object-fit:contain;border-radius:8px;"/>
        <?php else: ?>
          <img width="40" height="40"
               src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
               alt="<?= h(APP_NAME) ?>" decoding="async"/>
        <?php endif; ?>
      </div>
      <div>
        <p class="admin-logo-name"><?= h(APP_NAME) ?></p>
        <p class="admin-logo-sub">Admin Panel</p>
      </div>
    </div>

    <nav class="admin-nav">
      <?php
      $navItems = [];
 
// Dashboard — always visible (every role can see something)
$navItems[] = ['page'=>'dashboard', 'icon'=>'home', 'label'=>'Dashboard'];
 
// Products
if (adminCan('products.view')) {
    $navItems[] = ['page'=>'products', 'icon'=>'grid', 'label'=>'Products'];
}
 
// Sync
if (adminCan('sync.run')) {
    $navItems[] = ['page'=>'sync', 'icon'=>'refresh', 'label'=>'Sync Product Data'];
}
 
// Users
if (adminCan('users.view')) {
    $navItems[] = ['page'=>'users', 'icon'=>'users', 'label'=>'Users'];
}
 
// Client Selections
if (adminCan('clients.view')) {
    $navItems[] = ['page'=>'admin_clients', 'icon'=>'verified', 'label'=>'Client Selections'];
}
 
// Notifications
if (adminCan('notifications.view')) {
    $navItems[] = ['page'=>'notifications', 'icon'=>'bell', 'label'=>'Notifications', 'badge'=>$_notifCount];
}
// Trusted Devices
if (adminCan('devices.view')) {
    $navItems[] = ['page'=>'devices', 'icon'=>'lock', 'label'=>'Trusted Devices'];
}
      foreach ($navItems as $n):
  $isClientsGroup = $n['page'] === 'admin_clients'
      && in_array($ap, ['admin_clients','admin_client_form','admin_client_selections']);
?>
   <a href="index.php?page=<?= $n['page'] ?>"
   class="admin-nav-item <?= ($ap === $n['page'] || $isClientsGroup) ? 'active' : '' ?>">
        <?= icon($n['icon'], 18) ?>
        <span><?= $n['label'] ?></span>
        <?php if (!empty($n['badge']) && $n['badge'] > 0): ?>
        <span style="margin-left:auto;background:var(--danger);color:#fff;font-size:9px;
                     font-weight:700;min-width:18px;height:18px;border-radius:9px;
                     display:flex;align-items:center;justify-content:center;padding:0 4px;">
          <?= $n['badge'] ?>
        </span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>

      <?php
// Build list of visible settings sub-items
$settingsSubItems = [];
if (adminCan('settings.colors')) {
    $settingsSubItems[] = ['page'=>'colors',         'label'=>'Color Scheme'];
}
if (adminCan('settings.logo')) {
    $settingsSubItems[] = ['page'=>'logo',           'label'=>'Company Profile'];
}
if (adminCan('settings.product_views')) {
    $settingsSubItems[] = ['page'=>'product_view_settings', 'label'=>'Product Views'];
}
if (adminCan('settings.smtp')) {
    $settingsSubItems[] = ['page'=>'smtp',           'label'=>'Mail Settings'];
}
    $settingsSubItems[] = ['page'=>'room_templates',           'label'=>'Room Templates'];
//if (adminCan('license.manage')) {
  //  $settingsSubItems[] = ['page'=>'license', 'label'=>'License & Activation'];
//}
 
// Roles & Permissions sub-items
$rolesSubItems = [];
if (adminCan('roles.view')) {
    $rolesSubItems[] = ['page'=>'roles',             'label'=>'Roles & Permissions'];
}
if (adminCan('admins.view')) {
    $rolesSubItems[] = ['page'=>'admin_accounts',    'label'=>'Admin Accounts'];
}
?>
 
<?php if (!empty($settingsSubItems)): ?>
<!-- Settings group -->
<div class="admin-nav-group">
  <button class="admin-nav-group-header <?= $isSettingsActive ? 'active' : '' ?>"
          onclick="toggleSettingsMenu()" id="settingsMenuBtn" type="button">
    <?= icon('settings', 18) ?>
    <span>Settings</span>
    <svg class="admin-nav-group-chevron <?= $isSettingsActive ? 'open' : '' ?>"
         id="settingsChevron" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </button>
  <div class="admin-nav-submenu <?= $isSettingsActive ? 'open' : '' ?>" id="settingsSubmenu">
    <?php foreach ($settingsSubItems as $si): ?>
    <a href="index.php?page=<?= $si['page'] ?>"
       class="admin-nav-subitem <?= $ap === $si['page'] ? 'active' : '' ?>">
      <span class="admin-nav-subitem-dot"></span> <?= h($si['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
 
<?php if (!empty($rolesSubItems)): ?>
<!-- Roles & Permissions group -->
<?php
$isRolesActive = in_array($ap, ['roles','admin_accounts']);
?>
<div class="admin-nav-group">
  <button class="admin-nav-group-header <?= $isRolesActive ? 'active' : '' ?>"
          onclick="toggleRolesMenu()" id="rolesMenuBtn" type="button">
    <?= icon('lock', 18) ?>
    <span>Access Control</span>
    <svg class="admin-nav-group-chevron <?= $isRolesActive ? 'open' : '' ?>"
         id="rolesChevron" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </button>
  <div class="admin-nav-submenu <?= $isRolesActive ? 'open' : '' ?>" id="rolesSubmenu">
    <?php foreach ($rolesSubItems as $si): ?>
    <a href="index.php?page=<?= $si['page'] ?>"
       class="admin-nav-subitem <?= $ap === $si['page'] ? 'active' : '' ?>">
      <span class="admin-nav-subitem-dot"></span> <?= h($si['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
    </nav>

    <div class="admin-sidebar-footer">
       <?php if (adminCan('devices.view')): ?>
      <a href="index.php?page=devices" class="admin-nav-item">
        <?= icon('lock', 16) ?><span>Trusted Devices</span>
      </a>
      <?php endif; ?>
      <a href="../index.php?page=catalog" class="admin-nav-item" target="_blank">
        <?= icon('eye', 16) ?><span>View App</span>
      </a>
      <form method="POST" action="index.php">
        <input type="hidden" name="action" value="admin_logout"/>
        <?= csrfField() ?>
        <button type="submit" class="admin-nav-item w-full admin-logout-btn">
          <?= icon('logout', 16) ?><span>Sign Out</span>
        </button>
      </form>
    </div>
  </aside>

  <!-- ════════════════════════════════════════════════════════════
       MAIN CONTENT
       ════════════════════════════════════════════════════════════ -->
  <main class="admin-main">
    <div class="admin-topbar">
      <h1 class="admin-page-title serif"><?= h($adminTitle ?? 'Dashboard') ?></h1>
      <div class="admin-topbar-right">
        <span class="badge badge-green">● Live</span>

        <!-- Notification Bell -->
        <div class="notif-bell-wrap" id="notifBellWrap">
          <button class="notif-bell-btn" id="notifBellBtn"
                  onclick="toggleNotifDropdown()" type="button">
            <?= icon('bell', 18) ?>
            <?php if ($_notifCount > 0): ?>
            <span class="notif-bell-badge">
              <?= $_notifCount > 99 ? '99+' : $_notifCount ?>
            </span>
            <?php endif; ?>
          </button>

          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-dropdown-head">
              <span style="font-size:13px;font-weight:700;">Notifications</span>
              <a href="index.php?page=notifications"
                 style="font-size:12px;color:var(--accent);">View all</a>
            </div>
            <div class="notif-dropdown-body">
              <?php if (empty($_recentNotifs)): ?>
              <div style="padding:24px;text-align:center;color:var(--text3);font-size:12px;">
                <?= icon('bell', 20) ?><br/>No new notifications
              </div>
              <?php else: ?>
              <?php foreach ($_recentNotifs as $rn):
                $dot = !$rn['is_read']
                  ? '<span style="width:7px;height:7px;border-radius:50%;background:var(--accent);
                                  display:inline-block;margin-left:6px;vertical-align:middle;
                                  flex-shrink:0;"></span>'
                  : '';
              ?>
              <div class="notif-drop-item <?= !$rn['is_read'] ? 'notif-drop-item--unread' : '' ?>">
                <p style="font-size:12px;font-weight:600;color:var(--text);
                          display:flex;align-items:center;">
                  <?= h($rn['title']) ?><?= $dot ?>
                </p>
                <?php if ($rn['message']): ?>
                <p style="font-size:11px;color:var(--text3);margin-top:2px;line-height:1.4;">
                  <?= h(mb_strimwidth($rn['message'], 0, 70, '…')) ?>
                </p>
                <?php endif; ?>
                <p style="font-size:10px;color:var(--text3);margin-top:4px;">
                  <?= timeAgo($rn['created_at']) ?>
                </p>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if (!empty($_recentNotifs)): ?>
            <div style="padding:10px 14px;border-top:1px solid var(--border);">
              <a href="index.php?page=notifications"
                 style="font-size:12px;color:var(--accent);font-weight:600;
                        display:block;text-align:center;">
                See all notifications →
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <span style="font-size:13px;color:var(--text2);">
          Welcome, <?= h($_SESSION['admin_name'] ?? 'Admin') ?>
        </span>
      </div>
    </div>

    <div class="admin-content">

<!--  JS: sidebar drawer + notification dropdown + settings submenu ── -->
<script>
/*  Settings submenu  */
function toggleSettingsMenu() {
  var menu = document.getElementById('settingsSubmenu');
  var chev = document.getElementById('settingsChevron');
  var btn  = document.getElementById('settingsMenuBtn');
  var isOpen = menu.classList.contains('open');
  menu.classList.toggle('open', !isOpen);
  chev.classList.toggle('open', !isOpen);
  btn.classList.toggle('active', !isOpen || <?= json_encode($isSettingsActive) ?>);
}
function toggleRolesMenu() {
  var menu = document.getElementById('rolesSubmenu');
  var chev = document.getElementById('rolesChevron');
  var btn  = document.getElementById('rolesMenuBtn');
  if (!menu) return;
  var isOpen = menu.classList.contains('open');
  menu.classList.toggle('open', !isOpen);
  chev && chev.classList.toggle('open', !isOpen);
  btn  && btn.classList.toggle('active', !isOpen);
}

/*  Notification dropdown  */
function toggleNotifDropdown() {
  document.getElementById('notifDropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  var wrap = document.getElementById('notifBellWrap');
  var dd   = document.getElementById('notifDropdown');
  if (wrap && dd && !wrap.contains(e.target)) dd.classList.remove('open');
});

/*  Sidebar drawer  */
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('adminSidebar');
  var overlay = document.getElementById('adminSidebarOverlay');
  var hamBtn  = document.getElementById('adminHamburgerBtn');

  if (!sidebar || !overlay || !hamBtn) return;

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
    hamBtn.classList.add('open');
    hamBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    hamBtn.classList.remove('open');
    hamBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  hamBtn.addEventListener('click', function () {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });
  /* Auto-close drawer when a nav link is tapped on mobile/tablet */
  sidebar.addEventListener('click', function (e) {
    if (e.target.closest('a, button[type="submit"]') && window.innerWidth <= 1024) {
      closeSidebar();
    }
  });
  /* Restore body scroll if viewport grows past breakpoint */
  window.addEventListener('resize', function () {
    if (window.innerWidth > 1024) {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
      hamBtn.classList.remove('open');
      document.body.style.overflow = '';
    }
  });
});
</script>