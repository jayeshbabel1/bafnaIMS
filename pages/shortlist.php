
<?php
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
  <div class="page-header">
    <div class="page-header-left">
      <p class="page-eyebrow">My Collection</p>
      <h1 class="page-title">Shortlist</h1>
    </div>
    <div class="page-header-right">
      <span class="badge badge-black"><?= count($items) ?></span>
    </div>
  </div>

  <?php if (empty($items)): ?>
  <div class="empty-state" style="padding-top:60px;">
    <div class="empty-icon"><?= icon('heart',28) ?></div>
    <p class="empty-title">Nothing saved yet</p>
    <p class="empty-sub">Tap the heart icon on any product to save it here for quick access.</p>
    <a href="index.php?page=catalog" class="btn btn-primary" style="margin-top:24px;text-decoration:none;">
      <?= icon('grid',15) ?>&nbsp; Browse Catalog
    </a>
  </div>

  <?php else: ?>
  <div class="shortlist-grid">
    <?php foreach ($items as $i => $p):
      $pal = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
    ?>
    <div class="shortlist-card fade-up" style="animation-delay:<?= $i*.04 ?>s">
      <a href="index.php?page=product&id=<?= $p['id'] ?>" class="shortlist-card-link">
        <div class="shortlist-thumb">
          <?php if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])): ?>
          <img src="assets/uploads/photos/<?= h($p['primary_photo']) ?>" alt=""/>
          <?php else: ?><?= marbleSVG($pal, 80, 80, 'sl'.$p['id']) ?><?php endif; ?>
        </div>
        <div class="shortlist-info">
          <div style="display:flex;gap:5px;margin-bottom:5px;">
            <span class="badge badge-amber"><?= h($p['category']) ?></span>
            <?= $p['in_stock'] ? '<span class="badge badge-green">In Stock</span>' : '<span class="badge badge-gray">Out</span>' ?>
          </div>
          <p class="shortlist-name"><?= h($p['name']) ?></p>
          <p class="shortlist-meta">Lot <?= h($p['quarry_number']) ?></p>
          <p class="shortlist-spec"><?= h($p['thickness']) ?> · <?= number_format((float)$p['quantity_available']) ?> sqft</p>
        </div>
      </a>
      <div class="shortlist-actions">
        <form method="POST" action="index.php" style="flex:1">
          <input type="hidden" name="action"     value="toggle_shortlist"/>
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
          <input type="hidden" name="return_url" value="index.php?page=shortlist"/>
          <button type="submit" class="btn btn-danger btn-sm btn-block">
            <?= icon('trash',13) ?>&nbsp; Remove
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>