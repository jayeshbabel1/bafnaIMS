<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= h($adminTitle ?? 'Admin') ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
<style><?= getCSSVariables() ?></style>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="stylesheet" href="../assets/css/admin.css"/>
  <link rel="stylesheet" href="../assets/css/watermark.css"/>
</head>
<body class="admin-body">

<?php $t = getFlash('toast'); $e = getFlash('error'); ?>
<?php if ($t): ?><div class="toast toast-success" id="admin-toast"><?= h($t) ?></div><?php endif; ?>
<?php if ($e): ?><div class="toast toast-error"   id="admin-toast"><?= h($e) ?></div><?php endif; ?>

<?php
// Ensure logo helper is loaded
if (!function_exists('getLogo')) {
    require_once BASE_PATH . '/includes/logo.php';
}

// Notification count for bell badge
$_notifCount = 0;
try {
    $cutoff20 = time() - (20 * 86400);
    $ns = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND created_at >= ?");
    $ns->execute([$cutoff20]);
    $_notifCount = (int)$ns->fetchColumn();
} catch (Throwable $_e) {}

// Recent 5 for dropdown
$_recentNotifs = [];
try {
    $nsr = getDB()->prepare("SELECT * FROM notifications WHERE created_at >= ? ORDER BY created_at DESC LIMIT 5");
    $nsr->execute([$cutoff20]);
    $_recentNotifs = $nsr->fetchAll();
} catch (Throwable $_e) {}

// Site logo for admin panel
$_adminLogo = getLogo(true);
?>

<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-logo">
      <div class="admin-logo-icon">
        <?php if ($_adminLogo): ?>
          <img src="<?= h($_adminLogo) ?>" alt="<?= h(APP_NAME) ?>" width="40" height="40" style="object-fit:contain;border-radius:8px;"/>
        <?php else: ?>
          <img width="40" height="40"
               src="https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1"
               class="custom-logo" alt="<?= h(APP_NAME) ?>" decoding="async"/>
        <?php endif; ?>
      </div>
      <div>
        <p class="admin-logo-name"><?= h(APP_NAME) ?></p>
        <p class="admin-logo-sub">Admin Panel</p>
      </div>
    </div>

    <nav class="admin-nav">
      <?php
      $ap = $_GET['page'] ?? 'dashboard';
      $navItems = [
        ['page'=>'dashboard',     'icon'=>'home',    'label'=>'Dashboard'],
        ['page'=>'products',      'icon'=>'grid',    'label'=>'Products'],
        ['page'=>'sync',          'icon'=>'refresh', 'label'=>'Sync Product Data'],
       // ['page'=>'inquiries',     'icon'=>'msg',     'label'=>'Inquiries'],
        ['page'=>'users',         'icon'=>'users',   'label'=>'Users'],
        ['page'=>'notifications', 'icon'=>'bell',    'label'=>'Notifications', 'badge'=>$_notifCount],
        ['page'=>'colors',        'icon'=>'palette', 'label'=>'Color Scheme'],
        ['page'=>'logo',          'icon'=>'image',   'label'=>'Logo'],
      ];
      foreach ($navItems as $n): ?>
      <a href="index.php?page=<?= $n['page'] ?>" class="admin-nav-item <?= $ap===$n['page']?'active':'' ?>">
        <?= icon($n['icon'], 18) ?>
        <span><?= $n['label'] ?></span>
        <?php if (!empty($n['badge']) && $n['badge'] > 0): ?>
        <span style="margin-left:auto;background:var(--danger);color:#fff;font-size:9px;font-weight:700;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;"><?= $n['badge'] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar-footer">
      <a href="../index.php?page=catalog" class="admin-nav-item" target="_blank"><?= icon('eye',16) ?><span>View App</span></a>
      <form method="POST" action="index.php">
        <input type="hidden" name="action" value="admin_logout"/>
        <button type="submit" class="admin-nav-item w-full"><?= icon('logout',16) ?><span>Sign Out</span></button>
      </form>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1 class="admin-page-title serif"><?= h($adminTitle ?? 'Dashboard') ?></h1>
      <div class="admin-topbar-right">
        <span class="badge badge-green">● Live</span>

        <!-- Notification Bell Dropdown -->
        <div class="notif-bell-wrap" id="notifBellWrap">
          <button class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifDropdown()" type="button">
            <?= icon('bell', 18) ?>
            <?php if ($_notifCount > 0): ?>
            <span class="notif-bell-badge"><?= $_notifCount > 99 ? '99+' : $_notifCount ?></span>
            <?php endif; ?>
          </button>

          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-dropdown-head">
              <span style="font-size:13px;font-weight:700;">Notifications</span>
              <a href="index.php?page=notifications" style="font-size:12px;color:var(--accent);">View all</a>
            </div>
            <div class="notif-dropdown-body">
              <?php if (empty($_recentNotifs)): ?>
              <div style="padding:24px;text-align:center;color:var(--text3);font-size:12px;">
                <?= icon('bell', 20) ?><br/>No new notifications
              </div>
              <?php else: ?>
              <?php foreach ($_recentNotifs as $rn):
                $dot = !$rn['is_read'] ? '<span style="width:7px;height:7px;border-radius:50%;background:var(--accent);display:inline-block;margin-left:6px;vertical-align:middle;flex-shrink:0;"></span>' : '';
              ?>
              <div class="notif-drop-item <?= !$rn['is_read'] ? 'notif-drop-item--unread' : '' ?>">
                <p style="font-size:12px;font-weight:600;color:var(--text);display:flex;align-items:center;">
                  <?= h($rn['title']) ?><?= $dot ?>
                </p>
                <?php if ($rn['message']): ?>
                <p style="font-size:11px;color:var(--text3);margin-top:2px;line-height:1.4;"><?= h(mb_strimwidth($rn['message'], 0, 70, '…')) ?></p>
                <?php endif; ?>
                <p style="font-size:10px;color:var(--text3);margin-top:4px;"><?= timeAgo($rn['created_at']) ?></p>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <?php if (!empty($_recentNotifs)): ?>
            <div style="padding:10px 14px;border-top:1px solid var(--border);">
              <a href="index.php?page=notifications" style="font-size:12px;color:var(--accent);font-weight:600;display:block;text-align:center;">
                See all notifications →
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div><!-- /notif-bell-wrap -->

        <span style="font-size:13px;color:var(--text2);">Welcome, <?= h($_SESSION['admin_name'] ?? 'Admin') ?></span>
      </div>
    </div>
    <div class="admin-content">

<style>
/* Notification bell — inline here to avoid extra file for small block */
.notif-bell-wrap { position: relative; }
.notif-bell-btn {
  position: relative; width: 36px; height: 36px; border-radius: 8px;
  background: var(--surface2); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--text2); cursor: pointer; transition: background .15s;
}
.notif-bell-btn:hover { background: var(--surface3); }
.notif-bell-badge {
  position: absolute; top: -4px; right: -4px;
  background: var(--danger); color: #fff;
  font-size: 9px; font-weight: 700;
  min-width: 16px; height: 16px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  padding: 0 4px; border: 2px solid var(--surface);
}
.notif-dropdown {
  display: none; position: absolute; top: calc(100% + 8px); right: 0;
  width: 320px; background: var(--surface);
  border: 1px solid var(--border); border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,.12); z-index: 1000;
  overflow: hidden;
}
.notif-dropdown.open { display: block; animation: fadeUp .2s ease; }
.notif-dropdown-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-bottom: 1px solid var(--border);
  background: var(--surface2);
}
.notif-dropdown-body { max-height: 320px; overflow-y: auto; }
.notif-drop-item { padding: 12px 16px; border-bottom: 1px solid var(--border); transition: background .12s; }
.notif-drop-item:last-child { border-bottom: none; }
.notif-drop-item:hover { background: var(--surface2); }
.notif-drop-item--unread { background: var(--accent-light); }
</style>

<script>
function toggleNotifDropdown() {
  var d = document.getElementById('notifDropdown');
  if (d) d.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  var wrap = document.getElementById('notifBellWrap');
  var dd   = document.getElementById('notifDropdown');
  if (wrap && dd && !wrap.contains(e.target)) dd.classList.remove('open');
});
</script>