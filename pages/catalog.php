<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * pages/catalog.php
 * Range filters added: sqft_min/sqft_max (quantity_available)
 *                      size_min/size_max  (sizes_l × sizes_h)
 */
$pageTitle = 'Catalog — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['catalog.js'];

// ── Read all GET params ────────────────────────────────────────────────────
$cat      = $_GET['cat']      ?? '';
$color    = $_GET['color']    ?? '';
$search   = trim($_GET['q']   ?? '');
$sort     = $_GET['sort']     ?? 'latest';
$currentPage  = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 8;
$isAjax   = !empty($_GET['ajax']);

// Range filter params
$sqftMin  = isset($_GET['sqft_min']) && $_GET['sqft_min'] !== '' ? (float)$_GET['sqft_min'] : null;
$sqftMax  = isset($_GET['sqft_max']) && $_GET['sqft_max'] !== '' ? (float)$_GET['sqft_max'] : null;
$sizeMin  = isset($_GET['size_min']) && $_GET['size_min'] !== '' ? (float)$_GET['size_min'] : null;
$sizeMax  = isset($_GET['size_max']) && $_GET['size_max'] !== '' ? (float)$_GET['size_max'] : null;

// ── Build $filters array (passed to query builder) ─────────────────────────
$filters = [];
if ($cat)            $filters['category']          = $cat;
if ($color)          $filters['color_subcategory'] = $color;
if ($search)         $filters['search']            = $search;
if ($sqftMin !== null) $filters['sqft_min']         = $sqftMin;
if ($sqftMax !== null) $filters['sqft_max']         = $sqftMax;
if ($sizeMin !== null) $filters['size_min']         = $sizeMin;
if ($sizeMax !== null) $filters['size_max']         = $sizeMax;

// ── Sorting + Query builder ────────────────────────────────────────────────
function getSortedProducts(array $filters, string $sort): array {
    $db     = getDB();
    $sql    = "SELECT p.*,
                 (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
               FROM products p
               WHERE 1=1";
    $params = [];

    // Standard filters
    if (!empty($filters['category'])) {
        $sql     .= " AND p.category = ?";
        $params[] = $filters['category'];
    }
    if (!empty($filters['subcategory'])) {
        $sql     .= " AND p.subcategory = ?";
        $params[] = $filters['subcategory'];
    }
    if (!empty($filters['color_subcategory'])) {
        $sql     .= " AND p.color_subcategory = ?";
        $params[] = $filters['color_subcategory'];
    }
    if (!empty($filters['search'])) {
        $sql     .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)";
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }

    // ── Available Sqft range filter ────────────────────────────────────────
    if (isset($filters['sqft_min'])) {
        $sql     .= " AND p.quantity_available >= ?";
        $params[] = $filters['sqft_min'];
    }
    if (isset($filters['sqft_max'])) {
        $sql     .= " AND p.quantity_available <= ?";
        $params[] = $filters['sqft_max'];
    }

    // ── Usable Slab Size range filter ──────────────────────────────────────
    // Filters on the PRODUCT of L × H (area), casting both columns safely
    if (isset($filters['size_min'])) {
        $sql     .= " AND (CAST(p.sizes_l AS DECIMAL(10,2)) * CAST(p.sizes_h AS DECIMAL(10,2))) >= ?";
        $params[] = $filters['size_min'];
    }
    if (isset($filters['size_max'])) {
        $sql     .= " AND (CAST(p.sizes_l AS DECIMAL(10,2)) * CAST(p.sizes_h AS DECIMAL(10,2))) <= ?";
        $params[] = $filters['size_max'];
    }

    // Sorting
    switch ($sort) {
        case 'name_az':  $sql .= " ORDER BY p.name ASC";                                    break;
        case 'qty_asc':  $sql .= " ORDER BY p.quantity_available ASC";                      break;
        case 'qty_desc': $sql .= " ORDER BY p.quantity_available DESC";                     break;
        default:         $sql .= " ORDER BY p.featured DESC, p.sort_order ASC, p.id DESC";  break;
    }

    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

$allProducts = getSortedProducts($filters, $sort);
$totalCount  = count($allProducts);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$currentPage     = min($currentPage, $totalPages);
$products    = array_slice($allProducts, ($currentPage - 1) * $perPage, $perPage);
$featured    = array_filter($allProducts, fn($p) => $p['featured']);
$categories  = CATEGORIES;
$colorSubs   = COLOR_SUBCATEGORIES;

// ── Pagination HTML ────────────────────────────────────────────────────────
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

// ── Product Grid HTML ──────────────────────────────────────────────────────
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
            $slabDisplay = '';
            if (!empty($p['sizes_l']) || !empty($p['sizes_h'])) {
                $slabDisplay = trim($p['sizes_l']??'') . ' x ' . trim($p['sizes_h']??'');
            }
            $metaParts = ['Quarry No.: ' . h($p['quarry_number'])];
            if ($p['thickness']) $metaParts[] = ' Thickness: ' . h($p['thickness']);
          if ($slabDisplay)    $metaParts[] = ' Useable Size: ' . h($slabDisplay);
            $metaParts[] =' Quantity Available: ' . number_format((float)$p['quantity_available']) . ' sqft';

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
            $h .= '<p class="catalog-list-meta">' . implode(' · ', $metaParts) . '</p>';
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
        $h .= '<p class="product-card-quarry">Quarry No.: '.h($p['quarry_number']).'</p>';
        $h .= '<div class="product-card-footer"><span class="product-card-qty">'.number_format((float)$p['quantity_available']).' sqft</span>';
        $h .= '<span style="font-size:11px;color:var(--text3);">'.h($p['thickness']).'</span></div>';
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

// ── AJAX response ──────────────────────────────────────────────────────────
if ($isAjax) {
    $view = $_GET['view'] ?? 'grid';
    $html = renderProductGrid($products, $view) . renderPagination($currentPage, $totalPages);
    header('Content-Type: application/json');
    echo json_encode(['html'=>$html,'total'=>$totalCount,'pages'=>$totalPages,'current'=>$currentPage]);
    exit;
}

$view = 'grid'; // JS overrides from localStorage

// ── Active range-filter flag (for "Clear" link) ───────────────────────────
$hasRangeFilter = ($sqftMin !== null || $sqftMax !== null || $sizeMin !== null || $sizeMax !== null);
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
<!-- FILTER TOGGLE BUTTON -->
<div class="catalog-filter-toggle-wrap">
  <button type="button" class="catalog-filter-toggle-btn" id="filterToggleBtn">
    <span class="filter-toggle-icon"><?= icon('filter',15) ?></span>
    <span>Filters</span>

    <?php if ($search || $cat || $color || $hasRangeFilter): ?>
      <span class="filter-active-dot"></span>
    <?php endif; ?>

    <span class="filter-toggle-arrow" id="filterToggleArrow">
      <?= icon('chevron_down',14) ?>
    </span>
  </button>
</div>
  <!-- SEARCH + FILTERS -->
<div class="catalog-topbar" id="catalogFiltersWrap">
  <div id="catalogFiltersInner" class="catalog-filters-inner">
    <!-- Search -->
    <div class="search-wrap" style="position:relative;margin-bottom:12px;">
      <span class="search-icon"><?= icon('search',15) ?></span>
      <input type="search" id="searchInput" class="input-field search-input"
             placeholder="Search by name or lot number…"
             value="<?= h($search) ?>" autocomplete="off" data-min="3"/>
      <?php if ($search): ?>
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?>" class="search-clear"><?= icon('close',13) ?></a>
      <?php endif; ?>
      <p id="searchHint" class="search-hint" style="display:<?= strlen($search)>0&&strlen($search)<3?'block':'none' ?>;">
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
    <div class="filter-strip" style="margin-bottom:12px;">
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?><?= $search?'&q='.urlencode($search):'' ?><?= $sort&&$sort!='latest'?'&sort='.urlencode($sort):'' ?>"
         class="tag-pill tag-pill-sm tag-pill-color<?= !$color?' active':'' ?>">All Colours</a>
      <?php foreach ($colorSubs as $cs): ?>
      <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?>&color=<?= urlencode($cs) ?><?= $search?'&q='.urlencode($search):'' ?><?= $sort&&$sort!='latest'?'&sort='.urlencode($sort):'' ?>"
         class="tag-pill tag-pill-sm tag-pill-color<?= $color===$cs?' active':'' ?>">
        <span class="color-dot color-<?= strtolower($cs) ?>"></span><?= h($cs) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- ── Range filter bar (single line) ───────────────────────────────── -->
    <div class="range-bar" id="rangeFiltersRow">

      <!-- Sqft group -->
      <div class="range-bar-group <?= ($sqftMin!==null||$sqftMax!==null)?'range-bar-group--active':'' ?>" id="rfCardSqft">
        <span class="range-bar-label"><?= icon('grid',12) ?> Available Sqft</span>
        <input type="number" class="tag-pill" id="sqftMin"
               min="0" step="1" placeholder="Min"
               value="<?= $sqftMin !== null ? h((string)$sqftMin) : '' ?>"/>
        <span class="range-bar-sep">–</span>
        <input type="number" class="tag-pill" id="sqftMax"
               min="0" step="1" placeholder="Max"
               value="<?= $sqftMax !== null ? h((string)$sqftMax) : '' ?>"/>
        <button class="range-bar-clear" id="sqftClear" title="Clear"
                style="display:<?= ($sqftMin!==null||$sqftMax!==null)?'flex':'none' ?>">
          <?= icon('close',10) ?>
        </button>
      </div>

      <div class="range-bar-divider"></div>

      <!-- Slab size group -->
      <div class="range-bar-group <?= ($sizeMin!==null||$sizeMax!==null)?'range-bar-group--active':'' ?>" id="rfCardSize">
        <span class="range-bar-label"><?= icon('eye',12) ?> Useable Size L×H</span>
        <input type="number" class="tag-pill" id="sizeMin"
               min="0" step="0.01" placeholder="Min"
               value="<?= $sizeMin !== null ? h((string)$sizeMin) : '' ?>"/>
        <span class="range-bar-sep">–</span>
        <input type="number" class="tag-pill" id="sizeMax"
               min="0" step="0.01" placeholder="Max"
               value="<?= $sizeMax !== null ? h((string)$sizeMax) : '' ?>"/>
        <button class="range-bar-clear" id="sizeClear" title="Clear"
                style="display:<?= ($sizeMin!==null||$sizeMax!==null)?'flex':'none' ?>">
          <?= icon('close',10) ?>
        </button>
      </div>

    </div><!-- .range-bar -->
</div>
  </div><!-- .catalog-topbar -->

  <!-- FEATURED CAROUSEL -->
  <?php if (!$search && !$cat && !$color && !$hasRangeFilter && count($featured) && $currentPage===1): ?>
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
      <?php if ($search || $cat || $color || $hasRangeFilter): ?>
      · <a href="index.php?page=catalog" style="color:var(--gold);font-weight:600;font-size:12px;">Clear all</a>
      <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <select id="sortSelect" class="catalog-sort-select" data-current="<?= h($sort) ?>">
        <option value="latest"   <?= $sort==='latest'  ?'selected':'' ?>>Latest</option>
        <option value="qty_desc" <?= $sort==='qty_desc'?'selected':'' ?>>Qty High→Low</option>
        <option value="qty_asc"  <?= $sort==='qty_asc' ?'selected':'' ?>>Qty Low→High</option>
        <option value="name_az"  <?= $sort==='name_az' ?'selected':'' ?>>Name A→Z</option>
      </select>
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
       data-sqft-min="<?= $sqftMin !== null ? h((string)$sqftMin) : '' ?>"
       data-sqft-max="<?= $sqftMax !== null ? h((string)$sqftMax) : '' ?>"
       data-size-min="<?= $sizeMin !== null ? h((string)$sizeMin) : '' ?>"
       data-size-max="<?= $sizeMax !== null ? h((string)$sizeMax) : '' ?>"
       data-view="grid">
    <?= renderProductGrid($products, 'grid') ?>
    <?= renderPagination($currentPage, $totalPages) ?>
  </div>

</div><!-- .page-content -->
<script>
document.addEventListener('DOMContentLoaded', () => {

  const btn   = document.getElementById('filterToggleBtn');
  const panel = document.getElementById('catalogFiltersInner');

  if (!btn || !panel) return;

  // Auto-open if filters already active
  const hasActiveFilters =
      <?= ($search || $cat || $color || $hasRangeFilter) ? 'true' : 'false' ?>;

  if (hasActiveFilters) {
    panel.classList.add('open');
    btn.classList.add('active');
  }

  btn.addEventListener('click', () => {
    panel.classList.toggle('open');
    btn.classList.toggle('active');
  });

});</script>
<?php include BASE_PATH . '/layouts/footer.php'; ?>