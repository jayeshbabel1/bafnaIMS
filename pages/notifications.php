<?php
$pageTitle = 'Notifications — ' . APP_NAME;
$showNav   = true;

require_once BASE_PATH . '/includes/notifications.php';

$db     = getDB();
$cutoff = time() - (20 * 86400);

$db->prepare("UPDATE notifications SET is_read = 1")->execute();

$st = $db->prepare("SELECT * FROM notifications WHERE created_at >= ? ORDER BY created_at DESC");
$st->execute([$cutoff]);
$notifs = $st->fetchAll();

$db->prepare("DELETE FROM notifications WHERE created_at < ?")->execute([$cutoff]);
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="page-header">
    <div class="page-header-left">
      <p class="page-eyebrow">Updates</p>
      <h1 class="page-title">Notifications</h1>
    </div>
    <div class="page-header-right">
      <span class="badge badge-black"><?= count($notifs) ?></span>
    </div>
  </div>

  <?php if (empty($notifs)): ?>
  <div class="empty-state" style="padding-top:60px;">
    <div class="empty-icon"><?= icon('bell',28) ?></div>
    <p class="empty-title">All caught up</p>
    <p class="empty-sub">No notifications in the last 20 days.</p>
  </div>

  <?php else: ?>
  <div class="notif-list">
    <?php
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $lastDate  = '';
    foreach ($notifs as $n):
      $nDate = date('Y-m-d', $n['created_at']);
      if ($nDate !== $lastDate):
        $lastDate = $nDate;
        if ($nDate === $today)         $dateLabel = 'Today';
        elseif ($nDate === $yesterday) $dateLabel = 'Yesterday';
        else                           $dateLabel = date('d M Y', $n['created_at']);
    ?>
    <p class="notif-date-sep"><?= h($dateLabel) ?></p>
    <?php endif;

      $typeConf = [
        'product' => ['bg'=>'var(--gray-100)', 'color'=>'var(--text3)',    'icon'=>'grid'],
        'inquiry' => ['bg'=>'var(--gold-light)','color'=>'var(--gold-dark)','icon'=>'msg'],
        'user'    => ['bg'=>'var(--success-bg)','color'=>'var(--success)',  'icon'=>'users'],
        'info'    => ['bg'=>'var(--gray-100)',  'color'=>'var(--text3)',    'icon'=>'info'],
      ];
      $conf = $typeConf[$n['type']] ?? $typeConf['info'];
    ?>
    <div class="notif-card fade-up">
      <div class="notif-icon" style="background:<?= $conf['bg'] ?>;color:<?= $conf['color'] ?>;">
        <?= icon($conf['icon'],18) ?>
      </div>
      <div class="notif-body">
        <p class="notif-title"><?= h($n['title']) ?></p>
        <?php if ($n['message']): ?>
        <p class="notif-msg"><?= h($n['message']) ?></p>
        <?php endif; ?>
        <p class="notif-time"><?= date('g:i A', $n['created_at']) ?> · <?= timeAgo($n['created_at']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <p style="text-align:center;font-size:11px;color:var(--text4);margin-top:20px;padding-bottom:10px;">
    Notifications older than 20 days are automatically removed.
  </p>
  <?php endif; ?>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>