<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * pages/catalog.php
 * Tasks 4 (responsive fix), 5 (sort + view), 7 (search 3-char min)
 */
$pageTitle = 'Catalog — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['catalog.js'];

$cat     = $_GET['cat']   ?? '';
$color   = $_GET['color'] ?? '';
$search  = trim($_GET['q'] ?? '');
$sort    = $_GET['sort']  ?? 'latest';
$curPage = max(1, (int)($_GET['p'] ?? 1));
$perPage = 8;
$isAjax  = !empty($_GET['ajax']);

$filters = [];
if ($cat)    $filters['category']          = $cat;
if ($color)  $filters['color_subcategory'] = $color;
if ($search) $filters['search']            = $search;

// ── Sorting ────────────────────────────────────────────────────────────────
function getSortedProducts(array $filters, string $sort): array {
    $db   = getDB();
    $sql  = "SELECT p.*, (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo FROM products p WHERE 1=1";
    $params = [];
    if (!empty($filters['category']))          { $sql .= " AND p.category=?";          $params[] = $filters['category']; }
    if (!empty($filters['subcategory']))       { $sql .= " AND p.subcategory=?";        $params[] = $filters['subcategory']; }
    if (!empty($filters['color_subcategory'])) { $sql .= " AND p.color_subcategory=?";  $params[] = $filters['color_subcategory']; }
    if (!empty($filters['search']))            { $sql .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)"; $params[] = '%'.$filters['search'].'%'; $params[] = '%'.$filters['search'].'%'; }

    switch ($sort) {
        case 'name_az':  $sql .= " ORDER BY p.name ASC";                                     break;
        case 'qty_asc':  $sql .= " ORDER BY p.quantity_available ASC";                       break;
        case 'qty_desc': $sql .= " ORDER BY p.quantity_available DESC";                      break;
        default:         $sql .= " ORDER BY p.featured DESC, p.sort_order ASC, p.id DESC";   break; // latest
    }
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

$allProducts = getSortedProducts($filters, $sort);
$totalCount  = count($allProducts);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$curPage     = min($curPage, $totalPages);
$products    = array_slice($allProducts, ($curPage - 1) * $perPage, $perPage);
$featured    = array_filter($allProducts, fn($p) => $p['featured']);
$categories  = CATEGORIES;
$colorSubs   = COLOR_SUBCATEGORIES;

/* ── Pagination HTML ─────────────────────────────────────────────────────── */
function renderPagination(int $cur, int $total): string {
    if ($total <= 1) return '';
    $h  = '<div class="pagination" id="paginationWrap">';
    $h .= '<button class="pag-btn '.($cur<=1?'disabled':'').'" data-page="'.($cur-1).'">&lsaquo;</button>';
    $s = max(1,$cur-2); $e = min($total,$cur+2);
    if ($s>1) { $h .= '<button class="pag-btn" data-page="1">1</button>'; if($s>2) $h .= '<span class="pag-ellipsis">…</span>'; }
    for ($i=$s;$i<=$e;$i++) $h .= '<button class="pag-btn '.($i===$cur?'active':'').'" data-page="'.$i.'">'.$i.'</button>';
    if ($e<$total) { if($e<$total-1) $h .= '<span class="pag-ellipsis">…</span>'; $h .= '<button class="pag-btn" data-page="'.$total.'">'.$total.'</button>'; }
    $h .= '<button class="pag-btn '.($cur>=$total?'disabled':'').'" data-page="'.($cur+1).'">&rsaquo;</button>';
    $h .= '</div>';
    return $h;
}

/* ── Product Grid HTML ───────────────────────────────────────────────────── */
function renderProductGrid(array $products, string $view = 'grid'): string {
    if (empty($products)) {
        return '<div class="empty-state" style="padding:48px 20px;">
            <div class="empty-icon">'.icon('search',28).'</div>
            <p class="empty-title">No products found</p>
            <p class="empty-sub">Try adjusting your filters or search term.</p>
        </div>';
    }

    if ($view === 'list') {
        $h = '<div class="catalog-list-view" id="productsGrid">';
        foreach ($products as $i => $p) {
            $pal   = json_decode($p['palette']??'[]',true)?:['F2F0EC','D8CFC4','BFB0A0'];
            $saved = isShortlisted($p['id']);
            $h .= '<div class="catalog-list-item fade-up" style="animation-delay:'.round($i*.04,3).'s">';
            $h .= '<a href="index.php?page=product&id='.$p['id'].'" class="catalog-list-link">';
            $h .= '<div class="catalog-list-thumb">';
            if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo']))
                $h .= '<img src="assets/uploads/photos/'.h($p['primary_photo']).'" alt="'.h($p['name']).'" loading="lazy"/>';
            else $h .= marbleSVG($pal,80,80,'lv'.$p['id']);
            $h .= '</div>';
            $h .= '<div class="catalog-list-info">';
            $h .= '<div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:4px;"><span class="badge badge-amber">'.h($p['category']).'</span>';
            if (!$p['in_stock']) $h .= '<span class="badge badge-gray">Out of Stock</span>';
            if ($p['featured'])  $h .= '<span class="badge badge-gold">✦</span>';
            $h .= '</div>';
            $h .= '<p class="catalog-list-name">'.h($p['name']).'</p>';
            $h .= '<p class="catalog-list-meta">Lot: '.h($p['quarry_number']).' · '.h($p['thickness']).'mm · '.number_format((float)$p['quantity_available']).' sqft</p>';
            $h .= '</div>';
            $h .= '<div class="catalog-list-actions">';
            $h .= '<a href="index.php?page=inquiry_form&product_id='.$p['id'].'" class="btn-outline btn-sm" style="white-space:nowrap;text-decoration:none;">'.icon('msg',13).' Inquire</a>';
            $h .= '</div>';
            $h .= '</a>';
            $h .= '<form method="POST" action="index.php" class="shortlist-form" data-id="'.$p['id'].'">';
            $h .= '<input type="hidden" name="action" value="toggle_shortlist"/>';
            $h .= '<input type="hidden" name="product_id" value="'.$p['id'].'"/>';
            $h .= '<input type="hidden" name="return_url" value="index.php?page=catalog"/>';
            $h .= '<button type="submit" class="shortlist-btn-list '.($saved?'saved':'').'" title="'.($saved?'Remove':'Save').'">';
            $h .= $saved ? icon('heart_fill',16) : icon('heart',16);
            $h .= '</button></form>';
            $h .= '</div>';
        }
        $h .= '</div>';
        return $h;
    }

    // Grid view
    $h = '<div class="product-grid" id="productsGrid">';
    foreach ($products as $i => $p) {
        $pal   = json_decode($p['palette']??'[]',true)?:['F2F0EC','D8CFC4','BFB0A0'];
        $saved = isShortlisted($p['id']);
        $delay = round($i*.035,3);
        $h .= '<div class="product-card-wrap fade-up" style="animation-delay:'.$delay.'s">';
        $h .= '<a href="index.php?page=product&id='.$p['id'].'" class="product-card">';
        $h .= '<div class="product-thumb">';
        if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo']))
            $h .= '<img src="assets/uploads/photos/'.h($p['primary_photo']).'" alt="'.h($p['name']).'" loading="lazy"/>';
        else $h .= marbleSVG($pal,200,180,'pg'.$p['id']);
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
    $view = $_GET['view'] ?? 'grid';
    $html = renderProductGrid($products, $view) . renderPagination($curPage, $totalPages);
    header('Content-Type: application/json');
    echo json_encode(['html'=>$html,'total'=>$totalCount,'pages'=>$totalPages,'current'=>$curPage]);
    exit;
}

$view = 'grid'; // default — JS overrides from localStorage
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">

  <!-- TOPBAR -->
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
      <a href="index.php?page=profile" class="topbar-icon-btn"><?= icon('settings',17) ?></a>
    </div>
  </div>

  <!-- SEARCH + FILTERS -->
  <div class="catalog-topbar">
    <!-- Search -->
    <div class="search-wrap" style="position:relative;margin-bottom:12px;">
      <span class="search-icon"><?= icon('search',15) ?></span>
      <input type="search" id="searchInput" class="input-field search-input"
             placeholder="Search by name or lot number…"
             value="<?= h($search) ?>" autocomplete="off"
             data-min="3"/>
      <?php if ($search): ?>
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?>" class="search-clear"><?= icon('close',13) ?></a>
      <?php endif; ?>
      <!-- helper text -->
      <p id="searchHint" class="search-hint" style="display:<?= strlen($search) > 0 && strlen($search) < 3 ? 'block' : 'none' ?>;">
        <?= icon('info',12) ?> Type at least 3 characters to search
      </p>
    </div>

    <!-- Category pills -->
    <div class="filter-strip" style="margin-bottom:8px;">
      <a href="index.php?page=catalog<?= $search?'&q='.urlencode($search):'' ?><?= $color?'&color='.urlencode($color):'' ?><?= $sort&&$sort!='latest'?'&sort='.urlencode($sort):'' ?>"
         class="tag-pill<?= !$cat?' active':'' ?>">All</a>
      <?php foreach ($categories as $c): ?>
      <a href="index.php?page=catalog&cat=<?= urlencode($c) ?><?= $search?'&q='.urlencode($search):'' ?><?= $color?'&color='.urlencode($color):'' ?><?= $sort&&$sort!='latest'?'&sort='.urlencode($sort):'' ?>"
         class="tag-pill<?= $cat===$c?' active':'' ?>"><?= h($c) ?></a>
      <?php endforeach; ?>
    </div>

    <!-- Colour pills -->
    <div class="filter-strip" style="margin-bottom:4px;">
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?><?= $search?'&q='.urlencode($search):'' ?><?= $sort&&$sort!='latest'?'&sort='.urlencode($sort):'' ?>"
         class="tag-pill tag-pill-sm tag-pill-color<?= !$color?' active':'' ?>">All Colours</a>
      <?php foreach ($colorSubs as $cs): ?>
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?>&color=<?= urlencode($cs) ?><?= $search?'&q='.urlencode($search):'' ?><?= $sort&&$sort!='latest'?'&sort='.urlencode($sort):'' ?>"
         class="tag-pill tag-pill-sm tag-pill-color<?= $color===$cs?' active':'' ?>">
        <span class="color-dot color-<?= strtolower($cs) ?>"></span><?= h($cs) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- FEATURED CAROUSEL -->
  <?php if (!$search && !$cat && !$color && count($featured) && $curPage === 1): ?>
  <div style="padding-top:14px;">
    <div class="section-header">
      <span class="section-title">✦ Featured</span>
      <span class="badge badge-gold"><?= count($featured) ?></span>
    </div>
    <div class="featured-strip">
      <?php foreach ($featured as $fp):
        $pal = json_decode($fp['palette']??'[]',true)?:['F2F0EC','D8CFC4','BFB0A0'];
      ?>
      <a href="index.php?page=product&id=<?= $fp['id'] ?>" class="featured-card">
        <div class="featured-thumb">
          <?php if ($fp['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$fp['primary_photo'])): ?>
          <img src="assets/uploads/photos/<?= h($fp['primary_photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"/>
          <?php else: ?><?= marbleSVG($pal,220,120,'ft'.$fp['id']) ?><?php endif; ?>
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

  <!-- COUNT + SORT + VIEW BAR -->
  <div class="catalog-controls-bar">
    <div class="catalog-count">
      <span class="catalog-count-num" id="totalCount"><?= $totalCount ?></span>
      <span>products</span>
      <?php if ($search || $cat || $color): ?>
      · <a href="index.php?page=catalog" style="color:var(--gold);font-weight:600;font-size:12px;">Clear</a>
      <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <!-- Sort dropdown -->
      <select id="sortSelect" class="catalog-sort-select" data-current="<?= h($sort) ?>">
        <option value="latest"   <?= $sort==='latest'  ?'selected':'' ?>>Latest</option>
        <option value="qty_desc" <?= $sort==='qty_desc'?'selected':'' ?>>Qty High→Low</option>
        <option value="qty_asc"  <?= $sort==='qty_asc' ?'selected':'' ?>>Qty Low→High</option>
        <option value="name_az"  <?= $sort==='name_az' ?'selected':'' ?>>Name A→Z</option>
      </select>
      <!-- View toggle -->
      <div class="view-toggle">
        <button class="view-btn" id="viewGrid" title="Grid view"><?= icon('grid',15) ?></button>
        <button class="view-btn" id="viewList" title="List view"><?= icon('filter',15) ?></button>
      </div>
    </div>
  </div>

  <!-- AJAX Loader -->
  <div id="ajaxLoader" class="ajax-loader">
    <div class="loader-spinner"></div>
  </div>

  <!-- PRODUCTS AREA -->
  <div id="catalogContent"
       data-cat="<?= h($cat) ?>"
       data-color="<?= h($color) ?>"
       data-q="<?= h($search) ?>"
       data-sort="<?= h($sort) ?>"
       data-view="grid">
    <?= renderProductGrid($products, 'grid') ?>
    <? $curPage = max(1, (int)($_GET['p'] ?? 1)); ?>
    <?= renderPagination($curPage, $totalPages) ?>
  </div>

</div><!-- .page-content -->

<style>
/* ── Catalog controls bar ─────────────────────────────────────────────── */
.catalog-controls-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 16px 0; flex-wrap: wrap; gap: 8px;
}
.catalog-sort-select {
  padding: 7px 28px 7px 10px; border: 1.5px solid var(--border);
  border-radius: 8px; font-size: 12px; font-weight: 500;
  color: var(--text); background: var(--surface);
  cursor: pointer; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%238FA3B1' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 8px center;
  outline: none; transition: border-color .15s;
}
.catalog-sort-select:focus { border-color: var(--accent); }

.view-toggle { display: flex; border: 1.5px solid var(--border); border-radius: 8px; overflow: hidden; }
.view-btn {
  width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
  background: var(--surface); color: var(--text3); cursor: pointer;
  border: none; transition: all .15s;
}
.view-btn:first-child { border-right: 1px solid var(--border); }
.view-btn.active { background: var(--nav-bg); color: #fff; }

/* ── Search hint ──────────────────────────────────────────────────────── */
.search-hint {
  font-size: 11px; color: var(--text3); margin-top: 5px;
  display: flex; align-items: center; gap: 4px; padding: 0 2px;
}

/* ── List view ────────────────────────────────────────────────────────── */
.catalog-list-view {
  display: flex; flex-direction: column; gap: 0;
  padding: 8px 12px 16px;
}
.catalog-list-item {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--card-radius); margin-bottom: 8px;
  display: flex; align-items: center; overflow: hidden;
  position: relative; transition: box-shadow .2s;
}
.catalog-list-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }
.catalog-list-link { display: flex; align-items: center; gap: 12px; flex: 1; padding: 12px 14px; min-width: 0; text-decoration: none; }
.catalog-list-thumb { width: 72px; height: 72px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: var(--surface2); }
.catalog-list-thumb img { width: 100%; height: 100%; object-fit: cover; }
.catalog-list-info { flex: 1; min-width: 0; }
.catalog-list-name { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.catalog-list-meta { font-size: 11px; color: var(--text3); margin-top: 2px; }
.catalog-list-actions { padding-right: 14px; flex-shrink: 0; display: flex; align-items: center; gap: 8px; }
.shortlist-btn-list {
  width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  background: var(--surface); color: var(--text3); cursor: pointer;
  transition: all .15s; flex-shrink: 0;
}
.shortlist-btn-list.saved { color: var(--danger); border-color: var(--danger); background: var(--danger-bg); }

/* ── Fix: product grid equal heights ──────────────────────────────────── */
.product-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  padding: 8px 12px 16px;
  width: 100%;
  box-sizing: border-box;
}
.product-card-wrap {
  display: flex;
  flex-direction: column;
  min-width: 0; /* prevent overflow */
}
.product-card {
  display: flex;
  flex-direction: column;
  height: 100%;
}
.product-card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.product-card-footer { margin-top: auto; }

/* ── Prevent horizontal overflow ──────────────────────────────────────── */
.page-content { overflow-x: hidden; }
.filter-strip  { max-width: 100%; }

/* ── Loader ────────────────────────────────────────────────────────────── */
.ajax-loader {
  position: fixed; inset: 0;
  background: rgba(255,255,255,.72); backdrop-filter: blur(2px);
  display: none; align-items: center; justify-content: center; z-index: 99999;
}
.loader-spinner {
  width: 46px; height: 46px; border: 3px solid var(--border);
  border-top-color: var(--accent); border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

@media (min-width: 768px) {
  .product-grid { grid-template-columns: repeat(3,1fr); gap: 14px; padding: 10px 20px 20px; }
  .catalog-list-view { padding: 10px 20px 20px; }
  .catalog-controls-bar { padding: 12px 20px 0; }
}
@media (min-width: 1024px) {
  .product-grid { grid-template-columns: repeat(4,1fr); gap: 16px; padding: 12px 24px 24px; }
  .catalog-list-view { padding: 12px 24px 24px; }
  .catalog-controls-bar { padding: 14px 24px 0; }
}
</style>

<?php include BASE_PATH . '/layouts/footer.php'; ?>