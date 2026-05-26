<?php
$pageTitle = 'Catalog — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['catalog.js'];

$cat    = $_GET['cat']    ?? '';
$subcat = $_GET['subcat'] ?? '';
$color  = $_GET['color']  ?? '';
$search = trim($_GET['q'] ?? '');

$filters = [];
if ($cat)    $filters['category']          = $cat;
if ($subcat) $filters['subcategory']       = $subcat;
if ($color)  $filters['color_subcategory'] = $color;
if ($search) $filters['search']            = $search;

$allProducts  = getProducts($filters);
$featured     = array_filter($allProducts, fn($p) => $p['featured']);
$db           = getDB();
$categories   = CATEGORIES;
$colorSubs    = COLOR_SUBCATEGORIES;
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">

  <!-- ── TOP BAR ── -->
  <div class="topbar">
    <div>
      <p class="topbar-eyebrow">Bafna Marbles</p>
      <h1 class="topbar-title serif">Stone Catalog</h1>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="index.php?page=profile" class="topbar-icon-btn"><?= icon('bell',18) ?></a>
    </div>
  </div>

  <!-- ── SEARCH ── -->
  <div style="padding:14px 16px 0;">
    <form method="GET" action="index.php" id="searchForm">
      <input type="hidden" name="page" value="catalog"/>
      <?php if ($cat):    ?><input type="hidden" name="cat"    value="<?= h($cat) ?>"/><?php endif; ?>
      <?php if ($color):  ?><input type="hidden" name="color"  value="<?= h($color) ?>"/><?php endif; ?>
      <div class="search-wrap">
        <span class="search-icon"><?= icon('search',16) ?></span>
        <input type="search" name="q" class="input-field" placeholder="Search by name or quarry no."
               value="<?= h($search) ?>" id="searchInput" autocomplete="off"/>
        <?php if ($search): ?>
        <a href="index.php?page=catalog<?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="search-clear"><?= icon('close',14) ?></a>
        <?php endif; ?>
      </div>
    </form>

    <!-- ── CATEGORY FILTERS ── -->
    <div class="filter-strip" style="margin-bottom:8px;">
      <a href="index.php?page=catalog<?= $search ? '&q='.urlencode($search) : '' ?><?= $color ? '&color='.urlencode($color) : '' ?>"
         class="tag-pill<?= !$cat ? ' active' : '' ?>">All</a>
      <?php foreach ($categories as $c): ?>
      <a href="index.php?page=catalog?page=catalog&cat=<?= urlencode($c) ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $color ? '&color='.urlencode($color) : '' ?>"
         class="tag-pill<?= $cat === $c ? ' active' : '' ?>"><?= h($c) ?></a>
      <?php endforeach; ?>
    </div>

    <!-- ── COLOR SUB-FILTERS ── -->
    <div class="filter-strip" style="margin-bottom:4px;">
      <a href="index.php?page=catalog<?= $cat ? '&cat='.urlencode($cat) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>"
         class="tag-pill tag-pill-sm<?= !$color ? ' active' : '' ?>">All Colours</a>
      <?php foreach ($colorSubs as $cs): ?>
      <a href="index.php?page=catalog<?= $cat ? '&cat='.urlencode($cat) : '' ?>&color=<?= urlencode($cs) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
         class="tag-pill tag-pill-sm<?= $color === $cs ? ' active' : '' ?>">
        <span class="color-dot color-<?= strtolower($cs) ?>"></span><?= h($cs) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── FEATURED CAROUSEL ── -->
  <?php if (!$search && !$cat && !$color && count($featured)): ?>
  <div style="padding:16px 0 0;">
    <div class="section-header">
      <span class="section-title">✦ Featured Selections</span>
      <span class="badge badge-gold"><?= count($featured) ?> items</span>
    </div>
    <div class="featured-strip" id="featuredStrip">
      <?php foreach ($featured as $fp):
        $pal = json_decode($fp['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
      ?>
      <a href="index.php?page=product&id=<?= $fp['id'] ?>" class="featured-card">
        <div class="featured-thumb">
          <?php if ($fp['primary_photo'] && file_exists(PHOTOS_DIR . '/' . $fp['primary_photo'])): ?>
          <img src="assets/uploads/photos/<?= h($fp['primary_photo']) ?>" alt="<?= h($fp['name']) ?>" style="width:100%;height:100%;object-fit:cover;"/>
          <?php else: ?>
          <?= marbleSVG($pal, 200, 118, 'ft'.$fp['id']) ?>
          <?php endif; ?>
          <div class="featured-overlay"></div>
          <div class="featured-overlay-bottom">
            <span class="badge badge-gold">✦ Featured</span>
            <?= $fp['in_stock'] ? '<span class="badge badge-green">● In Stock</span>' : '' ?>
          </div>
        </div>
        <div class="featured-card-body">
          <p class="featured-card-name"><?= h($fp['name']) ?></p>
          <p class="featured-card-sub"><?= h($fp['quarry_number']) ?> · <?= h($fp['category']) ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── PRODUCT GRID ── -->
  <div class="section-header" style="padding-top:12px;">
    <span class="section-title"><?= count($allProducts) ?> Products</span>
    <span class="badge badge-green">● Live Inventory</span>
  </div>

  <?php if (empty($allProducts)): ?>
  <div class="empty-state">
    <div class="empty-icon"><?= icon('search',28) ?></div>
    <p class="empty-title">No products found</p>
    <p class="empty-sub">Try adjusting your filters or search term.</p>
    <a href="index.php?page=catalog" class="btn-outline" style="margin-top:20px;width:auto;display:inline-flex;">Clear Filters</a>
  </div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($allProducts as $i => $p):
      $pal = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
      $saved = isShortlisted($p['id']);
    ?>
    <div class="product-card-wrap" style="animation:fadeUp .4s <?= $i*0.04 ?>s ease both">
      <a href="index.php?page=product&id=<?= $p['id'] ?>" class="product-card card card-hover">
        <div class="product-thumb">
          <?php if ($p['primary_photo'] && file_exists(PHOTOS_DIR . '/' . $p['primary_photo'])): ?>
          <img src="assets/uploads/photos/<?= h($p['primary_photo']) ?>" alt="<?= h($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;"/>
          <?php else: ?>
          <?= marbleSVG($pal, 200, 180, 'pg'.$p['id']) ?>
          <?php endif; ?>
          <div class="product-thumb-overlay"></div>
          <?php if (!$p['in_stock']): ?>
          <div class="product-out-overlay"><span class="badge badge-gray">Out of Stock</span></div>
          <?php endif; ?>
          <?php if ($p['featured']): ?>
          <div style="position:absolute;bottom:8px;left:8px;"><span class="badge badge-gold" style="font-size:10px;">✦</span></div>
          <?php endif; ?>
        </div>
        <div class="product-card-body">
          <p class="product-card-name"><?= h($p['name']) ?></p>
          <p class="product-card-quarry"><?= h($p['quarry_number']) ?></p>
          <div class="product-card-footer">
            <span class="badge badge-blue" style="font-size:10px;"><?= h($p['category']) ?></span>
            <span style="font-size:11px;color:var(--text2);font-weight:500;"><?= h($p['thickness']) ?>mm</span>
          </div>
        </div>
      </a>
      <!-- Shortlist button (outside <a> to avoid nesting) -->
      <form method="POST" action="index.php" class="shortlist-form" data-id="<?= $p['id'] ?>">
        <input type="hidden" name="action"     value="toggle_shortlist"/>
        <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
        <input type="hidden" name="return_url" value="index.php?page=catalog<?= $cat ? '&cat='.urlencode($cat) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>"/>
        <button type="submit" class="shortlist-btn" title="<?= $saved ? 'Remove from shortlist' : 'Add to shortlist' ?>">
          <?= $saved ? icon('heart_fill',14) : icon('heart',14) ?>
        </button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- .page-content -->

<?php include BASE_PATH . '/layouts/footer.php'; ?>
