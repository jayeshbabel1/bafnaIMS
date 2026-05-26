<?php
// shortlist.php
$pageTitle = 'Shortlist — ' . APP_NAME;
$showNav   = true;
$db        = getDB();
$st        = $db->prepare("
    SELECT p.*, s.created_at as saved_at,
    (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
    FROM shortlist s JOIN products p ON s.product_id=p.id
    WHERE s.user_id=? ORDER BY s.created_at DESC");
$st->execute([$_SESSION['user_id']]);
$items = $st->fetchAll();
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>
<div class="page-content">
  <div class="topbar">
    <div>
      <h1 class="topbar-title serif">My Shortlist</h1>
      <p class="topbar-eyebrow"><?= count($items) ?> saved product<?= count($items)!==1?'s':'' ?></p>
    </div>
  </div>

  <?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="empty-icon"><?= icon('heart',28) ?></div>
    <p class="empty-title">No saved products</p>
    <p class="empty-sub">Tap the heart icon on any product to shortlist it for quick access.</p>
    <a href="index.php?page=catalog" class="btn-primary" style="margin-top:24px;width:auto;text-decoration:none;padding:12px 28px;">Browse Catalog</a>
  </div>
  <?php else: ?>
  <div style="padding:12px 0;">
    <?php foreach ($items as $p):
      $pal = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
    ?>
    <div class="card" style="margin:0 16px 12px;overflow:visible;">
      <a href="index.php?page=product&id=<?= $p['id'] ?>" class="list-card">
        <div class="list-thumb">
          <?php if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])): ?>
          <img src="assets/uploads/photos/<?= h($p['primary_photo']) ?>" alt="<?= h($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;"/>
          <?php else: ?>
          <?= marbleSVG($pal, 78, 78, 'sl'.$p['id']) ?>
          <?php endif; ?>
        </div>
        <div class="list-info">
          <p class="list-name"><?= h($p['name']) ?></p>
          <p class="list-meta"><?= h($p['quarry_number']) ?> · <?= h($p['category']) ?></p>
          <p class="list-spec"><?= h($p['thickness']) ?>mm · <?= h($p['sizes']) ?></p>
          <?= $p['in_stock'] ? '<span class="badge badge-green" style="margin-top:6px;">● In Stock</span>' : '<span class="badge badge-gray" style="margin-top:6px;">Out of Stock</span>' ?>
        </div>
      </a>
      <div class="list-actions" style="padding:0 16px 12px;">
        <a href="index.php?page=inquiry_form&product_id=<?= $p['id'] ?>" class="btn-outline btn-sm" style="text-decoration:none;flex:1;"><?= icon('msg',14) ?> Inquire</a>
        <form method="POST" action="index.php" style="flex:1">
          <input type="hidden" name="action"     value="toggle_shortlist"/>
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
          <input type="hidden" name="return_url" value="index.php?page=shortlist"/>
          <button type="submit" class="btn-ghost btn-sm" style="width:100%;color:var(--danger);"><?= icon('trash',14) ?> Remove</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include BASE_PATH . '/layouts/footer.php'; ?>
