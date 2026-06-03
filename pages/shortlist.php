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

  <!-- ── TOPBAR ─────────────────────────────────────────────────────────── -->
  <div class="topbar">
    <div class="topbar-brand">
      
      <div>
        <p class="topbar-eyebrow">My Collection</p>
        <p class="topbar-title">Shortlist</p>
      </div>
    </div>
    <span class="badge badge-dark" style="font-size:12px;padding:5px 12px;"><?= count($items) ?></span>
  </div>

  <?php if (empty($items)): ?>
  <div class="empty-state" style="padding-top:72px;">
    <div class="empty-icon"><?= icon('heart',28) ?></div>
    <p class="empty-title">Nothing saved yet</p>
    <p class="empty-sub">Tap the heart icon on any product to shortlist it for quick access.</p>
    <a href="index.php?page=catalog" class="btn-primary btn-gold"
       style="margin-top:28px;width:auto;padding:13px 32px;text-decoration:none;"><?= icon('grid',15) ?>&nbsp; Browse Catalog</a>
  </div>

  <?php else: ?>
  <!-- Responsive grid: 1 col mobile, 2 col tablet, 3 col desktop -->
  <div class="shortlist-grid">
    <?php foreach ($items as $i => $p):
      $pal = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
    ?>
    <div class="card fade-up" style="animation-delay:<?= $i*0.04 ?>s;overflow:visible;">
      <!-- Product info -->
      <a href="index.php?page=product&id=<?= $p['id'] ?>" class="list-card">
        <div class="list-thumb">
          <?php if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])): ?>
          <img src="assets/uploads/photos/<?= h($p['primary_photo']) ?>" alt=""
               style="width:100%;height:100%;object-fit:cover;"/>
          <?php else: ?>
          <?= marbleSVG($pal, 80, 80, 'sl'.$p['id']) ?>
          <?php endif; ?>
        </div>
        <div class="list-info">
          <p class="list-name"><?= h($p['name']) ?></p>
          <p class="list-meta">Lot <?= h($p['quarry_number']) ?> · <?= h($p['category']) ?></p>
          <p class="list-spec"><?= h($p['thickness']) ?> · <?//= h($p['sizes']) ?></p>
          <div style="margin-top:6px;display:flex;gap:5px;flex-wrap:wrap;">
            <?= $p['in_stock']
              ? '<span class="badge badge-green">● In Stock</span>'
              : '<span class="badge badge-gray">Out of Stock</span>' ?>
            <span class="badge badge-amber"><?= number_format((float)$p['quantity_available']) ?> sqft</span>
          </div>
        </div>
      </a>
      <!-- Actions -->
      <div class="list-actions">
        <a href="index.php?page=inquiry_form&product_id=<?= $p['id'] ?>"
           class="btn-primary btn-sm" style="text-decoration:none;flex:1;">
          <?= icon('msg',13) ?>&nbsp; Inquire
        </a>
        <form method="POST" action="index.php" style="flex:1">
          <input type="hidden" name="action"     value="toggle_shortlist"/>
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
          <input type="hidden" name="return_url" value="index.php?page=shortlist"/>
          <button type="submit" class="btn-ghost btn-sm"
                  style="width:100%;color:var(--danger);background:var(--danger-bg);border-radius:var(--btn-radius);">
            <?= icon('trash',13) ?>&nbsp; Remove
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<style>
/* Shortlist responsive grid */
.shortlist-grid{
  display:block;
  padding:10px 14px 10px;
}
.shortlist-grid > .card{ margin-bottom:10px; }

@media(min-width:768px){
  .shortlist-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    padding:14px 20px;
  }
  .shortlist-grid > .card{ margin-bottom:0; }
  .list-thumb{ width:96px; height:96px; }
}
@media(min-width:1024px){
  .shortlist-grid{
    grid-template-columns:repeat(3,1fr);
    padding:16px 28px;
    gap:16px;
  }
}
</style>

<?php include BASE_PATH . '/layouts/footer.php'; ?>