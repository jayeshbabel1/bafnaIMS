<?php
$pageTitle = 'My Inquiries — ' . APP_NAME;
$showNav   = true;
$db        = getDB();
$st        = $db->prepare("
    SELECT i.*, p.name as product_name, p.quarry_number, p.category, p.palette,
    (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
    FROM inquiries i JOIN products p ON i.product_id=p.id
    WHERE i.user_id=? ORDER BY i.created_at DESC");
$st->execute([$_SESSION['user_id']]);
$inquiries = $st->fetchAll();
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>
<div class="page-content">
  <div class="topbar">
    <div>
      <h1 class="topbar-title serif">Inquiries</h1>
      <p class="topbar-eyebrow"><?= count($inquiries) ?> sent</p>
    </div>
  </div>

  <?php if (empty($inquiries)): ?>
  <div class="empty-state">
    <div class="empty-icon"><?= icon('msg',28) ?></div>
    <p class="empty-title">No inquiries yet</p>
    <p class="empty-sub">Send inquiries from product pages to start a conversation with the Bafna team.</p>
  </div>
  <?php else: ?>
  <div style="padding:12px 16px;">
    <?php foreach ($inquiries as $inq):
      $pal = json_decode($inq['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
      $statusColor = ['pending'=>'badge-gray','sent'=>'badge-blue','replied'=>'badge-green'][$inq['status']] ?? 'badge-gray';
      $statusLabel = ucfirst($inq['status']);
    ?>
    <div class="card inq-card" style="margin-bottom:12px;">
      <div class="inq-header">
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="width:48px;height:48px;border-radius:10px;overflow:hidden;flex-shrink:0;">
            <?php if ($inq['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$inq['primary_photo'])): ?>
            <img src="assets/uploads/photos/<?= h($inq['primary_photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"/>
            <?php else: ?>
            <?= marbleSVG($pal, 48, 48, 'iq'.$inq['id']) ?>
            <?php endif; ?>
          </div>
          <div>
            <p class="inq-name"><?= h($inq['product_name']) ?></p>
            <p class="inq-sub"><?= h($inq['quarry_number']) ?> · <?= timeAgo($inq['created_at']) ?></p>
          </div>
        </div>
        <span class="badge <?= $statusColor ?>"><?= $statusLabel ?></span>
      </div>
      <div class="inq-msg">
        <p style="font-size:12px;color:var(--text3);font-weight:600;margin-bottom:4px;">YOUR MESSAGE</p>
        <?= h($inq['message']) ?>
      </div>
      <?php if ($inq['admin_reply']): ?>
      <div class="inq-msg" style="margin-top:10px;background:var(--accent-light);border-left:3px solid var(--accent);">
        <p style="font-size:12px;color:var(--accent);font-weight:700;margin-bottom:4px;">BAFNA REPLY</p>
        <?= h($inq['admin_reply']) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include BASE_PATH . '/layouts/footer.php'; ?>
