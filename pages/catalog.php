<?php
$pageTitle = 'Catalog — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['catalog.js'];

$cat     = $_GET['cat']   ?? '';
$color   = $_GET['color'] ?? '';
$search  = trim($_GET['q'] ?? '');
$curPage = max(1, (int)($_GET['p'] ?? 1));
$perPage = 16;
$isAjax  = !empty($_GET['ajax']);

$filters = [];
if ($cat)    $filters['category']          = $cat;
if ($color)  $filters['color_subcategory'] = $color;
if ($search) $filters['search']            = $search;

$allProducts = getProducts($filters);
$totalCount  = count($allProducts);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$curPage     = min($curPage, $totalPages);
$products    = array_slice($allProducts, ($curPage - 1) * $perPage, $perPage);
$featured    = array_filter($allProducts, fn($p) => $p['featured']);
$categories  = CATEGORIES;
$colorSubs   = COLOR_SUBCATEGORIES;

/* ── Pagination HTML helper ──────────────────────────────────────────────── */
function renderPagination(int $cur, int $total): string {
    if ($total <= 1) return '';
    $h  = '<div class="pagination" id="paginationWrap" data-total="'.$total.'" data-current="'.$cur.'">';
    $h .= '<button class="pag-btn '.($cur<=1?'disabled':'').'" data-page="'.($cur-1).'">&lsaquo;</button>';
    $range = 2;
    $s = max(1, $cur-$range);
    $e = min($total, $cur+$range);
    if ($s > 1) {
        $h .= '<button class="pag-btn" data-page="1">1</button>';
        if ($s > 2) $h .= '<span class="pag-ellipsis">…</span>';
    }
    for ($i = $s; $i <= $e; $i++)
        $h .= '<button class="pag-btn '.($i===$cur?'active':'').'" data-page="'.$i.'">'.$i.'</button>';
    if ($e < $total) {
        if ($e < $total-1) $h .= '<span class="pag-ellipsis">…</span>';
        $h .= '<button class="pag-btn" data-page="'.$total.'">'.$total.'</button>';
    }
    $h .= '<button class="pag-btn '.($cur>=$total?'disabled':'').'" data-page="'.($cur+1).'">&rsaquo;</button>';
    $h .= '</div>';
    return $h;
}

/* ── Product grid HTML helper ────────────────────────────────────────────── */
function renderProductGrid(array $products): string {
    if (empty($products)) {
        return '<div class="empty-state">
            <div class="empty-icon">'.icon('search',28).'</div>
            <p class="empty-title">No products found</p>
            <p class="empty-sub">Try adjusting your filters or search term.</p>
        </div>';
    }
    $h = '<div class="product-grid" id="productsGrid">';
    foreach ($products as $i => $p) {
        $pal   = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
        $saved = isShortlisted($p['id']);
        $delay = round($i * 0.035, 3);
        $h .= '<div class="product-card-wrap" style="animation:fadeUp .38s '.$delay.'s ease both">';
        $h .= '<a href="index.php?page=product&id='.$p['id'].'" class="product-card">';
        $h .= '<div class="product-thumb">';
        if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo']))
            $h .= '<img src="assets/uploads/photos/'.h($p['primary_photo']).'" alt="'.h($p['name']).'" loading="lazy"/>';
        else
            $h .= marbleSVG($pal, 200, 180, 'pg'.$p['id']);
        $h .= '<div class="product-thumb-overlay"></div>';
        if (!$p['in_stock']) $h .= '<div class="product-out-overlay"><span class="badge badge-gray">Out of Stock</span></div>';
        if ($p['featured'])  $h .= '<div style="position:absolute;top:8px;left:8px;"><span class="badge badge-gold">✦</span></div>';
        $h .= '</div>';
        $h .= '<div class="product-card-body">';
        $h .= '<div class="product-card-cat"><span class="badge badge-amber">'.h($p['category']).'</span></div>';
        $h .= '<p class="product-card-name">'.h($p['name']).'</p>';
        $h .= '<p class="product-card-quarry">Lot: '.h($p['quarry_number']).'</p>';
        $h .= '<div class="product-card-footer"><span class="product-card-qty">'.number_format((float)$p['quantity_available']).' sqft</span>';
        $h .= '<span style="font-size:11px;color:var(--text3);">'.h($p['thickness']).'mm</span></div>';
        $h .= '</div></a>';
        $h .= '<form method="POST" action="index.php" class="shortlist-form" data-id="'.$p['id'].'">';
        $h .= '<input type="hidden" name="action" value="toggle_shortlist"/>';
        $h .= '<input type="hidden" name="product_id" value="'.$p['id'].'"/>';
        $h .= '<input type="hidden" name="return_url" value="index.php?page=catalog"/>';
        $h .= '<button type="submit" class="shortlist-btn '.($saved?'saved':'').'" title="'.($saved?'Remove':'Save').'">';
        $h .= $saved ? icon('heart_fill',14) : icon('heart',14);
        $h .= '</button></form></div>';
    }
    $h .= '</div>';
    return $h;
}

/* ── AJAX response ───────────────────────────────────────────────────────── */
if ($isAjax) {
    $html = renderProductGrid($products) . renderPagination($curPage, $totalPages);
    header('Content-Type: application/json');
    echo json_encode(['html'=>$html,'total'=>$totalCount,'pages'=>$totalPages,'current'=>$curPage]);
    exit;
}
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">

  <!-- ── TOPBAR ─────────────────────────────────────────────────────────── -->
  <div class="topbar">
    <div class="topbar-brand">
      <div class="topbar-logo">
        <svg width="20" height="20" viewBox="0 0 36 36" fill="none">
          <polygon points="18,4 32,28 4,28" fill="rgba(184,151,90,.18)" stroke="rgba(184,151,90,.9)" stroke-width="1.5"/>
          <polygon points="18,10 26,24 10,24" fill="rgba(184,151,90,.35)" stroke="rgba(184,151,90,.7)" stroke-width="1"/>
        </svg>
      </div>
      <div>
        <p class="topbar-eyebrow"><?= APP_NAME ?></p>
        <p class="topbar-title">Stone Catalog</p>
      </div>
    </div>
    <div class="topbar-actions">
      <a href="index.php?page=profile" class="topbar-icon-btn"><?= icon('bell',17) ?></a>
    </div>
  </div>

  <!-- ── SEARCH + FILTERS ───────────────────────────────────────────────── -->
  <div class="catalog-topbar">
    <form method="GET" action="index.php" id="searchForm">
      <input type="hidden" name="page" value="catalog"/>
      <?php if ($cat):   ?><input type="hidden" name="cat"   value="<?= h($cat) ?>"/><?php endif; ?>
      <?php if ($color): ?><input type="hidden" name="color" value="<?= h($color) ?>"/><?php endif; ?>
      <div class="search-wrap">
        <span class="search-icon"><?= icon('search',15) ?></span>
        <input type="search" name="q" class="input-field search-input"
               placeholder="Search by name or lot number…"
               value="<?= h($search) ?>" id="searchInput" autocomplete="off"/>
        <?php if ($search): ?>
        <a href="index.php?page=catalog<?= $cat ? '&cat='.urlencode($cat) : '' ?>" class="search-clear"><?= icon('close',13) ?></a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Category pills -->
    <div class="filter-strip" style="margin-bottom:8px;" id="catStrip">
      <a href="index.php?page=catalog<?= $search?'&q='.urlencode($search):'' ?><?= $color?'&color='.urlencode($color):'' ?>"
         class="tag-pill<?= !$cat?' active':'' ?>">All</a>
      <?php foreach ($categories as $c): ?>
      <a href="index.php?page=catalog&cat=<?= urlencode($c) ?><?= $search?'&q='.urlencode($search):'' ?><?= $color?'&color='.urlencode($color):'' ?>"
         class="tag-pill<?= $cat===$c?' active':'' ?>"><?= h($c) ?></a>
      <?php endforeach; ?>
    </div>

    <!-- Color pills -->
    <div class="filter-strip" style="margin-bottom:4px;" id="colorStrip">
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?><?= $search?'&q='.urlencode($search):'' ?>"
         class="tag-pill tag-pill-sm tag-pill-color<?= !$color?' active':'' ?>">All Colours</a>
      <?php foreach ($colorSubs as $cs): ?>
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?>&color=<?= urlencode($cs) ?><?= $search?'&q='.urlencode($search):'' ?>"
         class="tag-pill tag-pill-sm tag-pill-color<?= $color===$cs?' active':'' ?>">
        <span class="color-dot color-<?= strtolower($cs) ?>"></span><?= h($cs) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── FEATURED CAROUSEL ─────────────────────────────────────────────── -->
  <?php if (!$search && !$cat && !$color && count($featured) && $curPage === 1): ?>
  <div style="padding-top:14px;">
    <div class="section-header">
      <span class="section-title">✦ Featured</span>
      <span class="badge badge-gold"><?= count($featured) ?></span>
    </div>
    <div class="featured-strip">
      <?php foreach ($featured as $fp):
        $pal = json_decode($fp['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
      ?>
      <a href="index.php?page=product&id=<?= $fp['id'] ?>" class="featured-card">
        <div class="featured-thumb">
          <?php if ($fp['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$fp['primary_photo'])): ?>
          <img src="assets/uploads/photos/<?= h($fp['primary_photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"/>
          <?php else: ?>
          <?= marbleSVG($pal, 220, 120, 'ft'.$fp['id']) ?>
          <?php endif; ?>
          <div class="featured-overlay"></div>
          <div class="featured-overlay-bottom"><span class="badge badge-gold" style="font-size:9px;">✦ Featured</span></div>
        </div>
        <div class="featured-card-body">
          <p class="featured-card-name"><?= h($fp['name']) ?></p>
          <p class="featured-card-sub">Lot <?= h($fp['quarry_number']) ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── COUNT BAR ──────────────────────────────────────────────────────── -->
  <div class="catalog-topbar" style="padding-top:14px;">
    <div class="catalog-count">
      <span class="catalog-count-num"><?= $totalCount ?></span>
      <span>products</span>
      <?php if ($search || $cat || $color): ?>
      · <a href="index.php?page=catalog" style="color:var(--gold);font-weight:600;font-size:12px;">Clear filters</a>
      <?php endif; ?>
      <span style="margin-left:auto;" class="badge badge-green">● Live</span>
    </div>
  </div>

  <!-- ── PRODUCTS AREA ──────────────────────────────────────────────────── -->
  <div id="catalogContent"
       data-cat="<?= h($cat) ?>"
       data-color="<?= h($color) ?>"
       data-q="<?= h($search) ?>">
    <?= renderProductGrid($products) ?>
    <?= renderPagination($curPage, $totalPages) ?>
  </div>

</div><!-- .page-content -->

<?php include BASE_PATH . '/layouts/footer.php'; ?>