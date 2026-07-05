<?php
/**
 * pages/catalog.php — Left filter panel, separate L and H slab size ranges
 */
$pageTitle = 'Catalog — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['catalog.js'];

// ── Read GET params ─────────────────────────────────────────────────────────
$cat      = $_GET['cat']    ?? '';
$color    = $_GET['color']  ?? '';
$search   = trim($_GET['q'] ?? '');
$sort     = $_GET['sort']   ?? 'latest';
$currentPage  = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 9;
$isAjax   = !empty($_GET['ajax']);

// Available sqft range
$sqftMin = isset($_GET['sqft_min']) && $_GET['sqft_min'] !== '' ? (float)$_GET['sqft_min'] : null;
$sqftMax = isset($_GET['sqft_max']) && $_GET['sqft_max'] !== '' ? (float)$_GET['sqft_max'] : null;

// Slab Length (sizes_l) range
$slMin = isset($_GET['sl_min']) && $_GET['sl_min'] !== '' ? (float)$_GET['sl_min'] : null;
$slMax = isset($_GET['sl_max']) && $_GET['sl_max'] !== '' ? (float)$_GET['sl_max'] : null;

// Slab Height (sizes_h) range
$shMin = isset($_GET['sh_min']) && $_GET['sh_min'] !== '' ? (float)$_GET['sh_min'] : null;
$shMax = isset($_GET['sh_max']) && $_GET['sh_max'] !== '' ? (float)$_GET['sh_max'] : null;

// ── Build filters array ─────────────────────────────────────────────────────
$filters = [];
if ($cat)              $filters['category']          = $cat;
if ($color)            $filters['color_subcategory'] = $color;
if ($search)           $filters['search']            = $search;
if ($sqftMin !== null) $filters['sqft_min']           = $sqftMin;
if ($sqftMax !== null) $filters['sqft_max']           = $sqftMax;
if ($slMin   !== null) $filters['sl_min']             = $slMin;
if ($slMax   !== null) $filters['sl_max']             = $slMax;
if ($shMin   !== null) $filters['sh_min']             = $shMin;
if ($shMax   !== null) $filters['sh_max']             = $shMax;

// Query Builder 
// Query Builder — shared WHERE clause builder so count + rows never drift apart
function buildProductFilterSQL(array $filters): array {
    $where  = " WHERE 1=1";
    $params = [];

    if (!empty($filters['category']))          { $where .= " AND p.category=?";            $params[] = $filters['category']; }
    if (!empty($filters['subcategory']))       { $where .= " AND p.subcategory=?";          $params[] = $filters['subcategory']; }
    if (!empty($filters['color_subcategory'])) { $where .= " AND p.color_subcategory=?";    $params[] = $filters['color_subcategory']; }
    if (!empty($filters['search']))            { $where .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)"; $params[] = '%'.$filters['search'].'%'; $params[] = '%'.$filters['search'].'%'; }

    // Available sqft — BETWEEN / >= / <=
    if (isset($filters['sqft_min']) && isset($filters['sqft_max'])) {
        $where .= " AND p.quantity_available BETWEEN ? AND ?";
        $params[] = $filters['sqft_min']; $params[] = $filters['sqft_max'];
    } elseif (isset($filters['sqft_min'])) {
        $where .= " AND p.quantity_available >= ?"; $params[] = $filters['sqft_min'];
    } elseif (isset($filters['sqft_max'])) {
        $where .= " AND p.quantity_available <= ?"; $params[] = $filters['sqft_max'];
    }

   // Slab Length (sizes_l_num) — pre-cast generated column, index-friendly
    if (isset($filters['sl_min']) && isset($filters['sl_max'])) {
        $where .= " AND p.sizes_l_num BETWEEN ? AND ?";
        $params[] = $filters['sl_min']; $params[] = $filters['sl_max'];
    } elseif (isset($filters['sl_min'])) {
        $where .= " AND p.sizes_l_num >= ?"; $params[] = $filters['sl_min'];
    } elseif (isset($filters['sl_max'])) {
        $where .= " AND p.sizes_l_num <= ?"; $params[] = $filters['sl_max'];
    }

    // Slab Height (sizes_h_num) — pre-cast generated column, index-friendly
    if (isset($filters['sh_min']) && isset($filters['sh_max'])) {
        $where .= " AND p.sizes_h_num BETWEEN ? AND ?";
        $params[] = $filters['sh_min']; $params[] = $filters['sh_max'];
    } elseif (isset($filters['sh_min'])) {
        $where .= " AND p.sizes_h_num >= ?"; $params[] = $filters['sh_min'];
    } elseif (isset($filters['sh_max'])) {
        $where .= " AND p.sizes_h_num <= ?"; $params[] = $filters['sh_max'];
    }

    return [$where, $params];
}

// Count only — cheap, no row fetch, used for pagination math
function countFilteredProducts(array $filters): int {
    $db = getDB();
    [$where, $params] = buildProductFilterSQL($filters);
    $st = $db->prepare("SELECT COUNT(*) FROM products p" . $where);
    $st->execute($params);
    return (int)$st->fetchColumn();
}

// Row fetch — SQL does LIMIT/OFFSET now, no more full-table slurp into PHP
function getSortedProducts(array $filters, string $sort, int $limit = 0, int $offset = 0): array {
    $db = getDB();
    [$where, $params] = buildProductFilterSQL($filters);

    $sql = "SELECT p.*,
              (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
            FROM products p" . $where;

    switch ($sort) {
        case 'name_az':  $sql .= " ORDER BY p.name ASC"; break;
        case 'qty_asc':  $sql .= " ORDER BY p.quantity_available ASC"; break;
        case 'qty_desc': $sql .= " ORDER BY p.quantity_available DESC"; break;
        default:         $sql .= " ORDER BY p.featured DESC, p.sort_order ASC, p.id DESC"; break;
    }

    if ($limit > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
    }

    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

// Featured strip — separate cheap query, small LIMIT, not part of main slurp
function getFeaturedProducts(int $limit = 8): array {
    $db = getDB();
    $st = $db->prepare("SELECT p.*,
              (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
            FROM products p WHERE p.featured=1 ORDER BY p.sort_order ASC, p.id DESC LIMIT ?");
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

$totalCount  = countFilteredProducts($filters);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);
$products    = getSortedProducts($filters, $sort, $perPage, ($currentPage - 1) * $perPage);
$featured    = getFeaturedProducts(8);
$categories  = CATEGORIES;
$colorSubs   = COLOR_SUBCATEGORIES;
$hasFilter   = $cat || $color || $search
               || $sqftMin !== null || $sqftMax !== null
               || $slMin   !== null || $slMax   !== null
               || $shMin   !== null || $shMax   !== null;

// ── Pagination HTML ─────────────────────────────────────────────────────────
function renderPagination(int $cur, int $total): string {
    if ($total <= 1) return '';
    $h  = '<div class="pagination" id="paginationWrap">';
    $h .= '<button class="pag-btn '.($cur<=1?'disabled':'').'" data-page="'.($cur-1).'">&lsaquo;</button>';
    $s = max(1,$cur-2); $e = min($total,$cur+2);
    if ($s > 1) { $h .= '<button class="pag-btn" data-page="1">1</button>'; if ($s>2) $h .= '<span class="pag-ellipsis">…</span>'; }
    for ($i=$s;$i<=$e;$i++) $h .= '<button class="pag-btn '.($i===$cur?'active':'').'" data-page="'.$i.'">'.$i.'</button>';
    if ($e < $total) { if ($e < $total-1) $h .= '<span class="pag-ellipsis">…</span>'; $h .= '<button class="pag-btn" data-page="'.$total.'">'.$total.'</button>'; }
    $h .= '<button class="pag-btn '.($cur>=$total?'disabled':'').'" data-page="'.($cur+1).'">&rsaquo;</button>';
    $h .= '</div>';
    return $h;
}

// ── Product grid / list HTML ────────────────────────────────────────────────
function renderProductGrid(array $products, string $view = 'grid'): string {
    if (empty($products)) {
        return '<div class="empty-state">'.icon('search',28).'<p class="empty-title" style="margin-top:16px;">No products found</p><p class="empty-sub">Try adjusting your filters or search term.</p></div>';
    }
    if ($view === 'list') {
        $h = '<div class="catalog-list-view" id="productsGrid">';
        foreach ($products as $i => $p) {
            $pal   = json_decode($p['palette']??'[]',true)?:['F2F0EC','D8CFC4','BFB0A0'];
            $saved = isShortlisted($p['id']);
            $parts = [];
            if ($p['quarry_number'])        $parts[] = 'Quarry No. : '.h($p['quarry_number']);
            if ($p['thickness'])            $parts[] = 'Thickness : '.h($p['thickness']);
            if ($p['quantity_available'])   $parts[] = 'Available : '.number_format((float)$p['quantity_available']).' sqft';
            $h .= '<div class="list-card fade-up" style="animation-delay:'.round($i*.04,3).'s">';
            $h .= '<a href="index.php?page=product&id='.$p['id'].'" style="display:flex;align-items:center;flex:1;text-decoration:none;color:inherit;">';
            $h .= '<div class="list-thumb">';
            if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo']))
                $h .= '<img src="assets/uploads/photos/'.h($p['primary_photo']).'" alt="'.h($p['name']).'" loading="lazy"/>';
            else $h .= marbleSVG($pal,90,90,'lv'.$p['id']);
            $h .= '</div>';
            $h .= '<div class="list-info">';
            $h .= '<div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:4px;"><span class="badge badge-amber">'.h($p['category']).'</span>';
           if (!$p['in_stock'] || (float)$p['quantity_available'] <= 0) $h .= '<span class="badge badge-gray">Out of Stock</span>';
            if ($p['featured'])  $h .= '<span class="badge badge-gold">★</span>';
            $h .= '</div>';
            $h .= '<p class="list-name">'.h($p['name']).'</p>';
            $h .= '<p class="list-meta">'.implode(' · ',$parts).'</p>';
            $h .= '</div></a>';
            $h .= '<div class="list-actions">';
            $h .= '<form method="POST" action="index.php" class="shortlist-form" data-id="'.$p['id'].'">';
            $h .= '<input type="hidden" name="action" value="toggle_shortlist"/>';
            $h .= '<input type="hidden" name="product_id" value="'.$p['id'].'"/>';
            $h .= '<input type="hidden" name="return_url" value="index.php?page=catalog"/>';
          $h .= '<input type="hidden" name="csrf_token" value="'.h(csrfToken()).'"/>';
            $h .= '<button type="submit" class="shortlist-btn-list '.($saved?'saved':'').'">';
            $h .= $saved ? icon('heart_fill',16) : icon('heart',16);
            $h .= '</button></form></div></div>';
        }
        $h .= '</div>';
        return $h;
    }
    // Grid view
    $h = '<div class="product-grid" id="productsGrid">';
    foreach ($products as $i => $p) {
        $pal   = json_decode($p['palette']??'[]',true)?:['F2F0EC','D8CFC4','BFB0A0'];
        $saved = isShortlisted($p['id']);
        $h .= '<div class="fade-up" style="animation-delay:'.round($i*.035,3).'s">';
        $h .= '<a href="index.php?page=product&id='.$p['id'].'" class="product-card">';
        $h .= '<div class="product-thumb">';
        if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo']))
            $h .= '<img src="assets/uploads/photos/'.h($p['primary_photo']).'" alt="'.h($p['name']).'" loading="lazy"/>';
        else $h .= marbleSVG($pal,200,160,'pg'.$p['id']);
        if (!$p['in_stock'] || (float)$p['quantity_available'] <= 0) $h .= '<div class="product-out-overlay"><span class="badge badge-gray">Out of Stock</span></div>';
        if ($p['featured'])  $h .= '<div style="position:absolute;top:10px;left:10px;"><span class="badge badge-gold">★ Featured</span></div>';
        $h .= '</div>';
        $h .= '<div class="product-body">';
        $h .= '<p class="product-cat">'.h($p['category']).'</p>';
        $h .= '<p class="product-name">'.h($p['name']).'</p>';
        $h .= '<p class="product-quarry">Quarry No. : '.h($p['quarry_number']).'</p>';
        $h .= '<div class="product-footer">';
        $h .= '<span class="product-qty">Available Qty : '.number_format((float)$p['quantity_available']).' sqft</span>';
        if ($p['thickness']) $h .= '<span style="font-size:11px;color:var(--text4);">Thickness : '.h($p['thickness']).'</span>';
        $h .= '</div></div></a>';
        $h .= '<form method="POST" action="index.php" class="shortlist-form" data-id="'.$p['id'].'">';
        $h .= '<input type="hidden" name="action" value="toggle_shortlist"/>';
        $h .= '<input type="hidden" name="product_id" value="'.$p['id'].'"/>';
        $h .= '<input type="hidden" name="return_url" value="index.php?page=catalog"/>';
      $h .= '<input type="hidden" name="csrf_token" value="'.h(csrfToken()).'"/>';
        $h .= '<button type="submit" class="shortlist-btn '.($saved?'saved':'').'">';
        $h .= $saved ? icon('heart_fill',14) : icon('heart',14);
        $h .= '</button></form></div>';
    }
    $h .= '</div>';
    return $h;
}

// ── AJAX response ───────────────────────────────────────────────────────────
if ($isAjax) {
    $view = $_GET['view'] ?? 'grid';
    $html = renderProductGrid($products, $view) . renderPagination($currentPage, $totalPages);
    header('Content-Type: application/json');
    echo json_encode(['html'=>$html,'total'=>$totalCount,'pages'=>$totalPages,'current'=>$currentPage]);
    exit;
}

// Helper: input value fallback
function fv($v): string { return $v !== null ? h((string)$v) : ''; }
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="catalog-layout" style="padding-top:24px;">


   <?php
/**
 * PATCH B — pages/catalog.php
 *
 * FIND the entire <aside> block:
 *   <aside class="catalog-filters-panel filter-sidebar">
 *     ...all content...
 *   </aside><!-- /filter-sidebar -->
 *
 * REPLACE WITH the block below.
 *
 * Changes:
 *  - Wraps all filter sections inside <div class="filter-sidebar-content">
 *  - Adds <div class="filter-sidebar-footer"> with Apply + Clear buttons
 *  - filter-chip <a> tags changed to <button type="button"> so they don't navigate
 *  - Range inputs lose their oninput/onchange wiring (handled by new JS)
 */
?>

    <!-- ══════════════════ LEFT SIDEBAR (desktop only) ═══════════════════ -->
    <aside class="catalog-filters-panel filter-sidebar">

      <!-- ── Scrollable content ───────────────────────────────────────── -->
      <div class="filter-sidebar-content">

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <p style="font-family:var(--font-display);font-size:15px;font-weight:700;">Filters</p>
          <?php if ($hasFilter): ?>
          <a href="index.php?page=catalog" style="font-size:12px;font-weight:600;color:var(--text3);text-decoration:underline;" id="sidebarClearAllLink">
            Clear all
          </a>
          <?php endif; ?>
        </div>

        <!-- Stone Type -->
        <div class="filter-sidebar-section">
          <p class="filter-sidebar-title">Stone Type</p>
          <div class="filter-option-list" id="sidebarCatList">
            <button type="button" class="filter-chip<?= !$cat?' active':'' ?>"
                    data-filter="cat" data-value=""
                    onclick="sidebarPendingChip(this,'cat')">All</button>
            <?php foreach ($categories as $c): ?>
            <button type="button" class="filter-chip<?= $cat===$c?' active':'' ?>"
                    data-filter="cat" data-value="<?= h($c) ?>"
                    onclick="sidebarPendingChip(this,'cat')"><?= h($c) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Colour -->
        <div class="filter-sidebar-section">
          <p class="filter-sidebar-title">Color</p>
          <div class="filter-option-list" id="sidebarColorList">
            <button type="button" class="filter-chip<?= !$color?' active':'' ?>"
                    data-filter="color" data-value=""
                    onclick="sidebarPendingChip(this,'color')">All</button>
            <?php foreach ($colorSubs as $cs): ?>
            <button type="button" class="filter-chip<?= $color===$cs?' active':'' ?>"
                    data-filter="color" data-value="<?= h($cs) ?>"
                    onclick="sidebarPendingChip(this,'color')">
              <span class="color-dot color-<?= strtolower($cs) ?>"></span><?= h($cs) ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Available Sqft -->
        <div class="filter-sidebar-section">
          <div class="filter-sidebar-title">
            Available Quantity (sqft)
          </div>
          <div class="range-filter">
            <div>
              <p class="range-label">Min</p>
              <input type="number" class="range-input" id="sidebarSqftMin"
                     min="0" step="1" placeholder="0" value="<?= fv($sqftMin) ?>"/>
            </div>
            <div>
              <p class="range-label">Max</p>
              <input type="number" class="range-input" id="sidebarSqftMax"
                     min="0" step="1" placeholder="∞" value="<?= fv($sqftMax) ?>"/>
            </div>
          </div>
        </div>

        <!-- Slab Length (L) -->
        <div class="filter-sidebar-section">
          <div class="filter-sidebar-title">Useable Length (L)</div>
          <div class="range-filter">
            <div>
              <p class="range-label">Min</p>
              <input type="number" class="range-input" id="sidebarSlMin"
                     min="0" step="0.01" placeholder="0" value="<?= fv($slMin) ?>"/>
            </div>
            <div>
              <p class="range-label">Max</p>
              <input type="number" class="range-input" id="sidebarSlMax"
                     min="0" step="0.01" placeholder="∞" value="<?= fv($slMax) ?>"/>
            </div>
          </div>
        </div>

        <!-- Slab Height (H) -->
        <div class="filter-sidebar-section">
          <div class="filter-sidebar-title">Useable Height (H)</div>
          <div class="range-filter">
            <div>
              <p class="range-label">Min</p>
              <input type="number" class="range-input" id="sidebarShMin"
                     min="0" step="0.01" placeholder="0" value="<?= fv($shMin) ?>"/>
            </div>
            <div>
              <p class="range-label">Max</p>
              <input type="number" class="range-input" id="sidebarShMax"
                     min="0" step="0.01" placeholder="∞" value="<?= fv($shMax) ?>"/>
            </div>
          </div>
        </div>

      </div><!-- /filter-sidebar-content -->

      <!-- ── Sticky footer ────────────────────────────────────────────── -->
      <div class="filter-sidebar-footer">
        <button type="button" id="sidebarApplyBtn"
                class="btn btn-primary btn-block sidebar-apply-btn">
          <span class="pending-dot"></span>
          Apply Filters
        </button>
        <a href="index.php?page=catalog"
           class="btn btn-secondary btn-block"
           style="text-align:center;">
          Clear All
        </a>
      </div>

    </aside><!-- /filter-sidebar -->
    
    <!-- ══════════════════ MAIN CONTENT ══════════════════════════════════ -->
    <div class="catalog-main">

      <!-- Search -->
      <div class="catalog-search-wrap">
        <span class="catalog-search-icon"><?= icon('search',16) ?></span>
        <input type="search" id="searchInput" class="catalog-search-input"
               placeholder="Search by name or lot number…"
               value="<?= h($search) ?>" autocomplete="off"/>
        <?php if ($search): ?>
        <span class="catalog-search-clear" id="searchClear"><?= icon('close',13) ?></span>
        <?php endif; ?>
      </div>

     
      <!-- Controls bar -->
      <div class="catalog-controls">
        <div class="catalog-count">
          <strong id="totalCount"><?= $totalCount ?></strong>
          <span>products</span>
          <?php if ($hasFilter): ?>
          <span>·</span>
          <a href="index.php?page=catalog"
             style="font-size:12px;font-weight:600;color:var(--text3);text-decoration:underline;">
            Clear all
          </a>
          <?php endif; ?>
        </div>
        <div class="catalog-controls-right">
          <button class="filter-toggle-btn<?= $hasFilter?' has-filter':'' ?>" id="filterToggleBtn">
            <?= icon('filter',15) ?> Filters
            <?php if ($hasFilter): ?><span class="filter-active-dot"></span><?php endif; ?>
          </button>
          <select id="sortSelect" class="sort-select">
            <option value="latest"   <?= $sort==='latest'  ?'selected':'' ?>>Latest</option>
            <option value="qty_desc" <?= $sort==='qty_desc'?'selected':'' ?>>Qty: High→Low</option>
            <option value="qty_asc"  <?= $sort==='qty_asc' ?'selected':'' ?>>Qty: Low→High</option>
            <option value="name_az"  <?= $sort==='name_az' ?'selected':'' ?>>Name A→Z</option>
          </select>
          <div class="view-toggle">
            <button class="view-btn active" id="viewGrid" title="Grid"><?= icon('grid',15) ?></button>
            <button class="view-btn" id="viewList" title="List"><?= icon('filter',15) ?></button>
          </div>
        </div>
      </div>

      <!-- AJAX Loader -->
      <div id="ajaxLoader" class="ajax-loader">
        <div class="loader-spinner"></div>
      </div>

      <!-- Products output -->
      <div id="catalogContent"
           data-cat="<?= h($cat) ?>"
           data-color="<?= h($color) ?>"
           data-q="<?= h($search) ?>"
           data-sort="<?= h($sort) ?>"
           data-sqft-min="<?= fv($sqftMin) ?>"
           data-sqft-max="<?= fv($sqftMax) ?>"
           data-sl-min="<?= fv($slMin) ?>"
           data-sl-max="<?= fv($slMax) ?>"
           data-sh-min="<?= fv($shMin) ?>"
           data-sh-max="<?= fv($shMax) ?>"
           data-view="grid">
        <?= renderProductGrid($products, 'grid') ?>
        <?= renderPagination($currentPage, $totalPages) ?>
      </div>

    </div><!-- /catalog-main -->
  </div><!-- /catalog-layout -->
</div><!-- /page-content -->

<!-- ══════════════════ MOBILE FILTER DRAWER ════════════════════════════════ -->
<div class="filter-drawer-overlay" id="filterOverlay" onclick="closeFilterDrawer()"></div>
<div class="filter-drawer" id="filterDrawer">
  <div class="filter-drawer-handle"></div>
  <div class="filter-drawer-header">
    <p class="filter-drawer-title">Filters</p>
    <button onclick="closeFilterDrawer()" class="btn btn-ghost btn-icon"><?= icon('close',18) ?></button>
  </div>
  <div class="filter-drawer-body">

    <!-- Stone Type -->
    <div class="filter-section">
      <p class="filter-section-title">Stone Type</p>
      <div class="filter-option-list">
        <a href="index.php?page=catalog<?= $color?'&color='.urlencode($color):'' ?><?= $search?'&q='.urlencode($search):'' ?>"
           class="filter-chip<?= !$cat?' active':'' ?>" onclick="closeFilterDrawer()">All</a>
        <?php foreach ($categories as $c): ?>
        <a href="index.php?page=catalog&cat=<?= urlencode($c) ?><?= $color?'&color='.urlencode($color):'' ?><?= $search?'&q='.urlencode($search):'' ?>"
           class="filter-chip<?= $cat===$c?' active':'' ?>" onclick="closeFilterDrawer()"><?= h($c) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Colour -->
    <div class="filter-section">
      <p class="filter-section-title">Colour</p>
      <div class="filter-option-list">
        <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?><?= $search?'&q='.urlencode($search):'' ?>"
           class="filter-chip<?= !$color?' active':'' ?>" onclick="closeFilterDrawer()">All</a>
        <?php foreach ($colorSubs as $cs): ?>
        <a href="index.php?page=catalog<?= $cat?'&cat='.urlencode($cat):'' ?>&color=<?= urlencode($cs) ?><?= $search?'&q='.urlencode($search):'' ?>"
           class="filter-chip<?= $color===$cs?' active':'' ?>" onclick="closeFilterDrawer()">
          <span class="color-dot color-<?= strtolower($cs) ?>"></span><?= h($cs) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Available Sqft -->
    <div class="filter-section">
      <p class="filter-section-title">Available Sqft</p>
      <div class="range-filter">
        <div>
          <p class="range-label">Min</p>
          <input type="number" class="range-input" id="drawerSqftMin"
                 min="0" step="1" placeholder="0" value="<?= fv($sqftMin) ?>"/>
        </div>
        <div>
          <p class="range-label">Max</p>
          <input type="number" class="range-input" id="drawerSqftMax"
                 min="0" step="1" placeholder="∞" value="<?= fv($sqftMax) ?>"/>
        </div>
      </div>
    </div>

    <!-- Slab Length (L) -->
    <div class="filter-section">
      <p class="filter-section-title">Slab Length (L)</p>
      <div class="range-filter">
        <div>
          <p class="range-label">Min</p>
          <input type="number" class="range-input" id="drawerSlMin"
                 min="0" step="0.01" placeholder="0" value="<?= fv($slMin) ?>"/>
        </div>
        <div>
          <p class="range-label">Max</p>
          <input type="number" class="range-input" id="drawerSlMax"
                 min="0" step="0.01" placeholder="∞" value="<?= fv($slMax) ?>"/>
        </div>
      </div>
    </div>

    <!-- Slab Height (H) -->
    <div class="filter-section">
      <p class="filter-section-title">Slab Height (H)</p>
      <div class="range-filter">
        <div>
          <p class="range-label">Min</p>
          <input type="number" class="range-input" id="drawerShMin"
                 min="0" step="0.01" placeholder="0" value="<?= fv($shMin) ?>"/>
        </div>
        <div>
          <p class="range-label">Max</p>
          <input type="number" class="range-input" id="drawerShMax"
                 min="0" step="0.01" placeholder="∞" value="<?= fv($shMax) ?>"/>
        </div>
      </div>
    </div>

  </div><!-- /filter-drawer-body -->
  <div class="filter-drawer-footer">
    <a href="index.php?page=catalog" class="btn btn-secondary btn-block">Clear All</a>
    <button id="drawerApplyBtn" class="btn btn-primary btn-block">Apply Filters</button>
  </div>
</div>

<script>

// Mirrors the currently *applied* catalog state from data attributes
var _applied = {
  cat:      document.getElementById('catalogContent')?.dataset.cat      || '',
  color:    document.getElementById('catalogContent')?.dataset.color    || '',
  sqft_min: document.getElementById('catalogContent')?.dataset.sqftMin  || '',
  sqft_max: document.getElementById('catalogContent')?.dataset.sqftMax  || '',
  sl_min:   document.getElementById('catalogContent')?.dataset.slMin    || '',
  sl_max:   document.getElementById('catalogContent')?.dataset.slMax    || '',
  sh_min:   document.getElementById('catalogContent')?.dataset.shMin    || '',
  sh_max:   document.getElementById('catalogContent')?.dataset.shMax    || '',
};

// Pending state — updated by chip clicks / range inputs but NOT applied yet
var _pending = Object.assign({}, _applied);

//  Chip click handler (sidebar only) 
  function sidebarPendingChip(btn, filterKey) {
  // Update active state within the group
  var list = btn.closest('.filter-option-list');
  list.querySelectorAll('.filter-chip').forEach(function(b) {
    b.classList.remove('active');
  });
  btn.classList.add('active');

  // Store in pending
  _pending[filterKey] = btn.dataset.value;
  updateApplyBtnState();
}

//  Show/hide pending dot on Apply button 
function updateApplyBtnState() {
  var btn = document.getElementById('sidebarApplyBtn');
  if (!btn) return;
  var dirty = Object.keys(_pending).some(function(k) {
    return (_pending[k] || '') !== (_applied[k] || '');
  });
  btn.classList.toggle('has-pending', dirty);
}

//  Apply sidebar filters 
document.getElementById('sidebarApplyBtn')?.addEventListener('click', function() {
  // Read range inputs into pending
  _pending.sqft_min = (document.getElementById('sidebarSqftMin')?.value || '').trim();
  _pending.sqft_max = (document.getElementById('sidebarSqftMax')?.value || '').trim();
  _pending.sl_min   = (document.getElementById('sidebarSlMin')?.value   || '').trim();
  _pending.sl_max   = (document.getElementById('sidebarSlMax')?.value   || '').trim();
  _pending.sh_min   = (document.getElementById('sidebarShMin')?.value   || '').trim();
  _pending.sh_max   = (document.getElementById('sidebarShMax')?.value   || '').trim();

  // Push to catalog.js state and reload via AJAX
  if (window.catalogApplyAllFilters) {
    window.catalogApplyAllFilters(_pending);
  }

  // Update applied snapshot
  _applied = Object.assign({}, _pending);
  updateApplyBtnState();
});

// Range inputs: update pending state silently (no AJAX, no auto-apply)
['sidebarSqftMin','sidebarSqftMax','sidebarSlMin','sidebarSlMax','sidebarShMin','sidebarShMax'].forEach(function(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('input', function() { updateApplyBtnState(); });
});

// ══════════════════════════════════════════════════════════════════════════════
// MOBILE DRAWER — open/close
// ══════════════════════════════════════════════════════════════════════════════
function openFilterDrawer() {
  // Sync drawer inputs from current applied state before opening
  document.getElementById('drawerSqftMin').value = _applied.sqft_min || '';
  document.getElementById('drawerSqftMax').value = _applied.sqft_max || '';
  document.getElementById('drawerSlMin').value   = _applied.sl_min   || '';
  document.getElementById('drawerSlMax').value   = _applied.sl_max   || '';
  document.getElementById('drawerShMin').value   = _applied.sh_min   || '';
  document.getElementById('drawerShMax').value   = _applied.sh_max   || '';

  document.getElementById('filterDrawer').classList.add('open');
  document.getElementById('filterOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeFilterDrawer() {
  document.getElementById('filterDrawer').classList.remove('open');
  document.getElementById('filterOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

document.getElementById('filterToggleBtn')?.addEventListener('click', openFilterDrawer);

// ── Drawer Apply button ────────────────────────────────────────────────────
document.getElementById('drawerApplyBtn')?.addEventListener('click', function() {
  var drawerState = {
    sqft_min: (document.getElementById('drawerSqftMin')?.value || '').trim(),
    sqft_max: (document.getElementById('drawerSqftMax')?.value || '').trim(),
    sl_min:   (document.getElementById('drawerSlMin')?.value   || '').trim(),
    sl_max:   (document.getElementById('drawerSlMax')?.value   || '').trim(),
    sh_min:   (document.getElementById('drawerShMin')?.value   || '').trim(),
    sh_max:   (document.getElementById('drawerShMax')?.value   || '').trim(),
  };

  // Merge with current cat/color state
  var full = Object.assign({}, _applied, drawerState);

  if (window.catalogApplyAllFilters) {
    window.catalogApplyAllFilters(full);
  }

  _applied = Object.assign({}, full);
  _pending = Object.assign({}, full);
  updateApplyBtnState();
  closeFilterDrawer();
});

// Init pending dot state on page load
updateApplyBtnState();
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>