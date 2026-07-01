<?php
/**
 * admin/views/notifications.php — with RBAC guards
 */
requireAdminPermission('notifications.view');

$adminTitle = 'Notifications';
include __DIR__ . '/../_layout_top.php';

$db = getDB();

$db->exec("UPDATE notifications SET is_read = 1");

$cutoff = time() - (20 * 86400);
$st = $db->prepare("SELECT * FROM notifications WHERE created_at >= ? ORDER BY created_at DESC");
$st->execute([$cutoff]);
$notifications = $st->fetchAll();

$db->prepare("DELETE FROM notifications WHERE created_at < ?")->execute([$cutoff]);
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <p style="font-size:13px;color:var(--text3);"><?= count($notifications) ?> notification<?= count($notifications) !== 1 ? 's' : '' ?> in the last 20 days</p>
   <?php if (!empty($notifications) && adminCan('notifications.clear')): ?>
  <form method="POST" action="index.php">
    <input type="hidden" name="action" value="clear_notifications"/>
    <?= csrfField() ?>
    <button type="submit" class="btn-admin-secondary btn-admin-sm"
            data-confirm="Clear all notifications?"><?= icon('trash', 13) ?> Clear All</button>
  </form>
  <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<div style="text-align:center;padding:60px 20px;">
  <div style="width:64px;height:64px;border-radius:16px;background:var(--surface2);display:flex;align-items:center;justify-content:center;color:var(--text3);margin:0 auto 16px;">
    <?= icon('bell', 28) ?>
  </div>
  <p style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px;">All caught up!</p>
  <p style="font-size:13px;color:var(--text3);">No notifications in the last 20 days.</p>
</div>

<?php else: ?>
<div class="notif-list">
  <?php foreach ($notifications as $n):
    $typeConf = [
      'product' => ['bg' => 'var(--accent-light)', 'color' => 'var(--accent)', 'icon' => 'grid'],
      'inquiry' => ['bg' => 'var(--gold-bg)',      'color' => 'var(--gold)',   'icon' => 'msg'],
      'user'    => ['bg' => 'var(--success-bg)',   'color' => 'var(--success)','icon' => 'users'],
      'info'    => ['bg' => 'var(--surface2)',     'color' => 'var(--text3)',  'icon' => 'info'],
    ];
    $conf = $typeConf[$n['type']] ?? $typeConf['info'];
  ?>
  <div class="notif-item <?= !$n['is_read'] ? 'notif-item--unread' : '' ?>">
    <div class="notif-icon" style="background:<?= $conf['bg'] ?>;color:<?= $conf['color'] ?>;">
      <?= icon($conf['icon'], 16) ?>
    </div>
    <div class="notif-body">
      <p class="notif-title"><?= h($n['title']) ?></p>
      <?php if ($n['message']): ?>
      <p class="notif-msg"><?= h($n['message']) ?></p>
      <?php endif; ?>
      <p class="notif-time"><?= timeAgo($n['created_at']) ?> · <?= date('d M Y, g:i A', $n['created_at']) ?></p>
    </div>
    <?php if (!$n['is_read']): ?>
    <div class="notif-dot"></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.notif-list { display: flex; flex-direction: column; gap: 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--card-radius); overflow: hidden; }
.notif-item { display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px; border-bottom: 1px solid var(--border); position: relative; transition: background .15s; }
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--surface2); }
.notif-item--unread { background: var(--accent-light); }
.notif-item--unread:hover { background: #d4e8f5; }
.notif-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.notif-body { flex: 1; min-width: 0; }
.notif-title { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 3px; }
.notif-msg   { font-size: 12px; color: var(--text2); line-height: 1.5; margin-bottom: 5px; }
.notif-time  { font-size: 11px; color: var(--text3); }
.notif-dot   { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); flex-shrink: 0; margin-top: 5px; }
</style>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>