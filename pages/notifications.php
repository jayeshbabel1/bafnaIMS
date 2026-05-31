<?php
/**
 * pages/notifications.php — Task 6: User Panel Notifications
 */
$pageTitle = 'Notifications — ' . APP_NAME;
$showNav   = true;

require_once BASE_PATH . '/includes/notifications.php';

$db     = getDB();
$cutoff = time() - (20 * 86400);

// Mark all as read
$db->exec("UPDATE notifications SET is_read = 1");

// Fetch
$st = $db->prepare("SELECT * FROM notifications WHERE created_at >= ? ORDER BY created_at DESC");
$st->execute([$cutoff]);
$notifs = $st->fetchAll();

// Clean old ones
$db->prepare("DELETE FROM notifications WHERE created_at < ?")->execute([$cutoff]);
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="topbar">
    <div class="topbar-brand">
      <div>
        <p class="topbar-eyebrow">Updates</p>
        <p class="topbar-title">Notifications</p>
      </div>
    </div>
    <div class="topbar-actions">
      <span class="badge badge-dark" style="font-size:12px;padding:5px 10px;"><?= count($notifs) ?></span>
    </div>
  </div>

  <div style="padding: 14px 16px 80px; max-width: 720px; margin: 0 auto;">

    <?php if (empty($notifs)): ?>
    <div class="empty-state" style="padding-top:60px;">
      <div class="empty-icon"><?= icon('bell', 28) ?></div>
      <p class="empty-title">All caught up!</p>
      <p class="empty-sub">No notifications in the last 20 days.</p>
    </div>

    <?php else: ?>
    <div class="user-notif-list">
      <?php
      $today     = date('Y-m-d');
      $yesterday = date('Y-m-d', strtotime('-1 day'));
      $lastDate  = '';

      foreach ($notifs as $n):
        $nDate = date('Y-m-d', $n['created_at']);
        if ($nDate !== $lastDate):
          $lastDate = $nDate;
          if ($nDate === $today)          $dateLabel = 'Today';
          elseif ($nDate === $yesterday)  $dateLabel = 'Yesterday';
          else                            $dateLabel = date('d M Y', $n['created_at']);
      ?>
      <p class="notif-date-sep"><?= h($dateLabel) ?></p>
      <?php endif; ?>

      <?php
        $typeConf = [
          'product' => ['bg'=>'var(--accent-light)', 'color'=>'var(--accent)',  'icon'=>'grid'],
          'inquiry' => ['bg'=>'var(--gold-bg)',       'color'=>'var(--gold)',    'icon'=>'msg'],
          'user'    => ['bg'=>'var(--success-bg)',    'color'=>'var(--success)', 'icon'=>'users'],
          'info'    => ['bg'=>'var(--surface2)',      'color'=>'var(--text3)',   'icon'=>'info'],
        ];
        $conf = $typeConf[$n['type']] ?? $typeConf['info'];
      ?>
      <div class="user-notif-item">
        <div class="user-notif-icon" style="background:<?= $conf['bg'] ?>;color:<?= $conf['color'] ?>;">
          <?= icon($conf['icon'], 18) ?>
        </div>
        <div class="user-notif-body">
          <p class="user-notif-title"><?= h($n['title']) ?></p>
          <?php if ($n['message']): ?>
          <p class="user-notif-msg"><?= h($n['message']) ?></p>
          <?php endif; ?>
          <p class="user-notif-time"><?= date('g:i A', $n['created_at']) ?> · <?= timeAgo($n['created_at']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <p style="text-align:center;font-size:11px;color:var(--text3);margin-top:20px;padding-bottom:10px;">
      Notifications older than 20 days are automatically removed.
    </p>
    <?php endif; ?>
  </div>
</div>

<style>
.user-notif-list { display: flex; flex-direction: column; gap: 0; }
.notif-date-sep {
  font-size: 10px; font-weight: 700; color: var(--text3);
  text-transform: uppercase; letter-spacing: .6px;
  padding: 16px 4px 8px;
}
.notif-date-sep:first-child { padding-top: 0; }

.user-notif-item {
  display: flex; gap: 14px; align-items: flex-start;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--card-radius); padding: 14px 16px;
  margin-bottom: 8px; transition: box-shadow .15s;
}
.user-notif-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,.06); }

.user-notif-icon {
  width: 42px; height: 42px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.user-notif-body   { flex: 1; min-width: 0; }
.user-notif-title  { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 3px; }
.user-notif-msg    { font-size: 12px; color: var(--text2); line-height: 1.5; margin-bottom: 5px; }
.user-notif-time   { font-size: 11px; color: var(--text3); }
</style>

<?php include BASE_PATH . '/layouts/footer.php'; ?>