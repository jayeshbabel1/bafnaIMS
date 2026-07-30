<?php
/**
 * pages/catalog.php — Left filter panel, separate L and H slab size ranges
 * Fire 4: Grid / List / Table views, field visibility & order driven by
 * Settings → Product Views (user panel).
 */
require_once BASE_PATH . '/includes/categories.php';
require_once BASE_PATH . '/includes/product_views.php';
ensureProductViewTables();
require_once BASE_PATH . '/includes/search_history.php';
ensureSearchHistoryTable();


$pageTitle = 'Catalog — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['catalog.js'];

//  AJAX: search suggestions (recent history + product matches) 
if (!empty($_GET['ajax_suggest'])) {
    header('Content-Type: application/json');
    $q   = trim($_GET['q'] ?? '');
    $uid = (int)$_SESSION['user_id'];
    if ($q === '') {
        $recent   = getRecentSearches($uid, 8);
        $products = [];
    } else {
        $recent   = getRecentSearchesFiltered($uid, $q, 5);
        $products = getSearchProductSuggestions($q, 8);
    }
    echo json_encode(['recent' => $recent, 'products' => $products]);
    exit;
}

//  AJAX: save a search query into the current user's history 
if (!empty($_GET['ajax_search_save']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    csrfVerify(true);
    saveSearchQuery((int)$_SESSION['user_id'], $_POST['q'] ?? '');
    echo json_encode(['success' => true]);
    exit;
}

//  AJAX: delete one history item 
if (!empty($_GET['ajax_search_delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    csrfVerify(true);
    deleteSearchHistoryItem((int)$_SESSION['user_id'], (int)($_POST['id'] ?? 0));
    echo json_encode(['success' => true]);
    exit;
}

//  AJAX: clear all history for current user 
if (!empty($_GET['ajax_search_clear']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    csrfVerify(true);
    clearSearchHistory((int)$_SESSION['user_id']);
    echo json_encode(['success' => true]);
    exit;
}

//  Read GET params 
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

//  Build filters array 
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

// Query Builder — shared WHERE clause builder so count + rows never drift apart
function buildProductFilterSQL(array $filters): array {
    $where  = " WHERE 1=1";
    $params = [];

    if (!empty($filters['category']))          { $where .= " AND p.category=?";            $params[] = $filters['category']; }
    if (!empty($filters['subcategory']))       { $where .= " AND p.subcategory=?";          $params[] = $filters['subcategory']; }
    if (!empty($filters['color_subcategory'])) { $where .= " AND p.color_subcategory=?";    $params[] = $filters['color_subcategory']; }
    if (!empty($filters['search']))            { $where .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)"; $params[] = '%'.$filters['search'].'%'; $params[] = '%'.$filters['search'].'%'; }

    if (isset($filters['sqft_min']) && isset($filters['sqft_max'])) {
        $where .= " AND p.quantity_available BETWEEN ? AND ?";
        $params[] = $filters['sqft_min']; $params[] = $filters['sqft_max'];
    } elseif (isset($filters['sqft_min'])) {
        $where .= " AND p.quantity_available >= ?"; $params[] = $filters['sqft_min'];
    } elseif (isset($filters['sqft_max'])) {
        $where .= " AND p.quantity_available <= ?"; $params[] = $filters['sqft_max'];
    }

    if (isset($filters['sl_min']) && isset($filters['sl_max'])) {
        $where .= " AND p.sizes_l_num BETWEEN ? AND ?";
        $params[] = $filters['sl_min']; $params[] = $filters['sl_max'];
    } elseif (isset($filters['sl_min'])) {
        $where .= " AND p.sizes_l_num >= ?"; $params[] = $filters['sl_min'];
    } elseif (isset($filters['sl_max'])) {
        $where .= " AND p.sizes_l_num <= ?"; $params[] = $filters['sl_max'];
    }

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

function countFilteredProducts(array $filters): int {
    $db = getDB();
    [$where, $params] = buildProductFilterSQL($filters);
    $st = $db->prepare("SELECT COUNT(*) FROM products p" . $where);
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function getSortedProducts(array $filters, string $sort, int $limit = 0, int $offset = 0): array {
    $db = getDB();
    [$where, $params] = buildProductFilterSQL($filters);

   $sql = "SELECT p.*, EXISTS(SELECT 1 FROM product_photos pp WHERE pp.product_id=p.id) AS has_photo
        FROM products p" . $where;

switch ($sort) {
    case 'name_az':  $sql .= " ORDER BY has_photo DESC, p.name ASC"; break;
    case 'qty_asc':  $sql .= " ORDER BY has_photo DESC, p.quantity_available ASC"; break;
    case 'qty_desc': $sql .= " ORDER BY has_photo DESC, p.quantity_available DESC"; break;
    default:         $sql .= " ORDER BY has_photo DESC, p.featured DESC, p.sort_order ASC, p.id DESC"; break;
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

function getFeaturedProducts(int $limit = 8): array {
    $db = getDB();
    $st = $db->prepare("SELECT p.*
            FROM products p WHERE p.featured=1 ORDER BY p.sort_order ASC, p.id DESC LIMIT ?");
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

//  Fire 4: user-panel field renderer (shared across grid/list/table) 
function pvUserShortLabel(string $key, string $fallback): string {
    $short = [
        'photo' => '', 'quantity_available' => 'Available', 'color_subcategory' => 'Color',
        'quarry_number' => 'Lot #', 'cutter_size' => 'Italian Size', 'sizes' => 'Useable Size',
        'in_stock' => 'Stock', 'actions' => '',
    ];
    return array_key_exists($key, $short) ? $short[$key] : $fallback;
}

function pvUserShortlistBtn(array $p, string $btnClass, int $iconSize): string {
    $saved = isShortlisted($p['id']);
    return '<form method="POST" action="index.php" class="shortlist-form" data-id="'.$p['id'].'">
        <input type="hidden" name="action" value="toggle_shortlist"/>
        <input type="hidden" name="product_id" value="'.$p['id'].'"/>
        <input type="hidden" name="return_url" value="index.php?page=catalog"/>
        <input type="hidden" name="csrf_token" value="'.h(csrfToken()).'"/>
        <button type="submit" class="'.$btnClass.' '.($saved?'saved':'').'">'.($saved ? icon('heart_fill',$iconSize) : icon('heart',$iconSize)).'</button>
    </form>';
}

function pvUserFieldHtml(array $p, string $key, array $pal): string {
    switch ($key) {
        case 'photo':
            if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])) {
                $thumbSrc = getPhotoThumbUrl($p['primary_photo']);
                return '<img src="'.h($thumbSrc).'" alt="'.h($p['name']).'" loading="lazy" decoding="async"/>';
            }
            return marbleSVG($pal, 200, 160, 'pv'.$p['id']);
        case 'name':        return h(tr('product', $p['id'], 'name', $p['name']));
case 'category':    return h(tr('product', $p['id'], 'category', $p['category'] ?: '—'));
case 'color_subcategory': return h(tr('product', $p['id'], 'color_subcategory', $p['color_subcategory'] ?: '—'));;
        case 'quarry_number':      return h($p['quarry_number'] ?: '—');
       
        case 'thickness':          return h($p['thickness'] ?: '—');
        case 'sizes':              return h(formatDimension($p['sizes_l'] ?? '', $p['sizes_h'] ?? '') ?: '—');
        case 'cutter_size':        return h(formatDimension($p['cutter_size_l'] ?? '', $p['cutter_size_h'] ?? '') ?: '—');
        case 'quantity_available': return number_format((float)$p['quantity_available']) . ' sqft';
        case 'in_stock':
            return (!$p['in_stock'] || (float)$p['quantity_available'] <= 0)
                ? '<span class="badge badge-gray">Out of Stock</span>' : '<span class="badge badge-green">In Stock</span>';
        case 'featured':
            return $p['featured'] ? '<span class="badge badge-gold">★ Featured</span>' : '';
        case 'actions':
            return pvUserShortlistBtn($p, 'shortlist-btn-list', 15); // overridden per-view where needed
        default: return '';
    }
}

//  Product grid / list / table HTML 
function renderProductGrid(array $products, string $view = 'grid'): string {
    if (empty($products)) {
        return '<div class="empty-state">'.icon('search',28).'<p class="empty-title" style="margin-top:16px;">No products found</p><p class="empty-sub">Try adjusting your filters or search term.</p></div>';
    }

    $fields = array_values(array_filter(getViewFieldConfig('user', $view), fn($f) => $f['visible']));
    if (empty($fields)) $fields = [['key'=>'photo'],['key'=>'name'],['key'=>'quantity_available'],['key'=>'actions']];

    if ($view === 'table') {
        $h = '<div class="catalog-table-scroll"><table class="catalog-table" id="productsGrid"><thead><tr>';
        foreach ($fields as $f) {
            $label = pvUserShortLabel($f['key'], $f['label'] ?? $f['key']);
            $h .= $label !== '' ? '<th>'.h($label).'</th>' : '<th></th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach ($products as $p) {
            $pal = json_decode($p['palette']??'[]',true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
            $h .= '<tr>';
            foreach ($fields as $f) {
                $key = $f['key'];
                if ($key === 'photo') {
                    $h .= '<td><a href="index.php?page=product&id='.$p['id'].'" class="catalog-table-thumb">'.pvUserFieldHtml($p,'photo',$pal).'</a></td>';
                } elseif ($key === 'name') {
                    $h .= '<td><a href="index.php?page=product&id='.$p['id'].'" class="catalog-table-name">'.pvUserFieldHtml($p,'name',$pal).'</a></td>';
                } else {
                    $h .= '<td>'.pvUserFieldHtml($p, $key, $pal).'</td>';
                }
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';
        return $h;
    }

    if ($view === 'list') {
        $keys = array_column($fields, 'key');
        $showActions = in_array('actions', $keys, true);
        $specKeys = array_values(array_intersect(['thickness','sizes','cutter_size','color_subcategory'], $keys));

        $h = '<div class="catalog-list-view" id="productsGrid">';
        foreach ($products as $i => $p) {
            $pal = json_decode($p['palette']??'[]',true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
            $h .= '<div class="list-card fade-up" style="animation-delay:'.round($i*.04,3).'s">';
            $h .= '<a href="index.php?page=product&id='.$p['id'].'" style="display:flex;align-items:center;flex:1;text-decoration:none;color:inherit;min-width:0;">';

            if (in_array('photo', $keys, true)) {
                $h .= '<div class="list-thumb">'.pvUserFieldHtml($p,'photo',$pal).'</div>';
            }

            $h .= '<div class="list-info">';
            $badges = [];
            if (in_array('category', $keys, true) && $p['category']) $badges[] = '<span class="badge badge-amber">'.h($p['category']).'</span>';
            if (in_array('in_stock', $keys, true)) $badges[] = pvUserFieldHtml($p,'in_stock',$pal);
            if (in_array('featured', $keys, true) && $p['featured']) $badges[] = pvUserFieldHtml($p,'featured',$pal);
            if ($badges) $h .= '<div style="display:flex;gap:5px;margin-bottom:4px;flex-wrap:wrap;">'.implode('', $badges).'</div>';

            if (in_array('name', $keys, true)) $h .= '<p class="list-name">'.pvUserFieldHtml($p,'name',$pal).'</p>';

            $metaParts = [];
            if (in_array('quarry_number', $keys, true)) $metaParts[] = 'Lot '.pvUserFieldHtml($p,'quarry_number',$pal);
            foreach ($specKeys as $sk) $metaParts[] = pvUserFieldHtml($p, $sk, $pal);
            if (in_array('quantity_available', $keys, true)) $metaParts[] = pvUserFieldHtml($p,'quantity_available',$pal);
            if ($metaParts) $h .= '<p class="list-meta">'.implode(' · ', $metaParts).'</p>';

            $h .= '</div></a>';
            if ($showActions) {
                $h .= '<div class="list-actions">'.pvUserShortlistBtn($p, 'shortlist-btn-list', 16).'</div>';
            }
            $h .= '</div>';
        }
        $h .= '</div>';
        return $h;
    }

    // Grid view (default / "classic")
    $keys = array_column($fields, 'key');
    $showActions = in_array('actions', $keys, true);
    $specKeys = array_values(array_intersect(['thickness','sizes','cutter_size','color_subcategory'], $keys));

    $h = '<div class="product-grid" id="productsGrid">';
    foreach ($products as $i => $p) {
        $pal = json_decode($p['palette']??'[]',true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
        $h .= '<div class="fade-up" style="animation-delay:'.round($i*.035,3).'s">';
        $h .= '<a href="index.php?page=product&id='.$p['id'].'" class="product-card">';

        if (in_array('photo', $keys, true)) {
            $h .= '<div class="product-thumb">'.pvUserFieldHtml($p,'photo',$pal);
            if (in_array('in_stock', $keys, true) && (!$p['in_stock'] || (float)$p['quantity_available'] <= 0)) {
                $h .= '<div class="product-out-overlay"><span class="badge badge-gray">Out of Stock</span></div>';
            }
            if (in_array('featured', $keys, true) && $p['featured']) {
                $h .= '<div style="position:absolute;top:10px;left:10px;">'.pvUserFieldHtml($p,'featured',$pal).'</div>';
            }
            $h .= '</div>';
        }

        $h .= '<div class="product-body">';
        if (in_array('category', $keys, true)) $h .= '<p class="product-cat">'.pvUserFieldHtml($p,'category',$pal).'</p>';
        if (in_array('name', $keys, true))     $h .= '<p class="product-name">'.pvUserFieldHtml($p,'name',$pal).'</p>';
        if (in_array('quarry_number', $keys, true)) $h .= '<p class="product-quarry">Quarry No. : '.pvUserFieldHtml($p,'quarry_number',$pal).'</p>';

        $specLine = implode(' · ', array_map(fn($sk) => pvUserFieldHtml($p, $sk, $pal), $specKeys));
        if (in_array('quantity_available', $keys, true) || $specLine !== '') {
            $h .= '<div class="product-footer">';
            if (in_array('quantity_available', $keys, true)) $h .= '<span class="product-qty">Available Qty : '.pvUserFieldHtml($p,'quantity_available',$pal).'</span>';
            if ($specLine !== '') $h .= '<span style="font-size:11px;color:var(--text4);">'.$specLine.'</span>';
            $h .= '</div>';
        }
        $h .= '</div></a>';

        if ($showActions) $h .= pvUserShortlistBtn($p, 'shortlist-btn', 14);
        $h .= '</div>';
    }
    $h .= '</div>';
    return $h;
}

$totalCount  = countFilteredProducts($filters);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);
$products    = getSortedProducts($filters, $sort, $perPage, ($currentPage - 1) * $perPage);
$featured    = getFeaturedProducts(8);
$categories  = getCategoryNames();
$colorSubs   = COLOR_SUBCATEGORIES;
$hasFilter   = $cat || $color || $search
               || $sqftMin !== null || $sqftMax !== null
               || $slMin   !== null || $slMax   !== null
               || $shMin   !== null || $shMax   !== null;
$defaultView   = in_array($_GET['view'] ?? '', PV_VIEWS, true) ? $_GET['view'] : getDefaultView('user');
$catalogTheme  = getCatalogTheme();

//  Pagination HTML 
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

//  AJAX response 
if ($isAjax) {
    $view = in_array($_GET['view'] ?? '', PV_VIEWS, true) ? $_GET['view'] : $defaultView;
    $html = renderProductGrid($products, $view) . renderPagination($currentPage, $totalPages);
    header('Content-Type: application/json');
    echo json_encode(['html'=>$html,'total'=>$totalCount,'pages'=>$totalPages,'current'=>$currentPage,'view'=>$view]);
    exit;
}

// Helper: input value fallback
function fv($v): string { return $v !== null ? h((string)$v) : ''; }
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<style>
  /*  Search suggest / history dropdown  */
.search-suggest-box {
  display:none; position:absolute; left:0; right:0; top:calc(100% + 6px);
  background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius);
  box-shadow:var(--shadow-lg); z-index:60; max-height:360px; overflow-y:auto;
}
.search-suggest-box.open { display:block; }
.ss-section-label {
  font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
  color:var(--text4); padding:10px 14px 6px;
}
.ss-item {
  display:flex; align-items:center; gap:10px; padding:9px 14px; cursor:pointer;
  transition:background .12s;
}
.ss-item:hover { background:var(--gray-50); }
.ss-item-icon { color:var(--text4); flex-shrink:0; display:flex; }
.ss-item-text { flex:1; min-width:0; font-size:13px; color:var(--text2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ss-item-thumb { width:32px; height:32px; border-radius:6px; overflow:hidden; flex-shrink:0; background:var(--gray-100); }
.ss-item-thumb img { width:100%; height:100%; object-fit:cover; }
.ss-item-sub { font-size:11px; color:var(--text4); }
.ss-item-remove {
  flex-shrink:0; width:20px; height:20px; border-radius:50%; display:flex;
  align-items:center; justify-content:center; color:var(--text4); cursor:pointer;
}
.ss-item-remove:hover { background:var(--gray-200); color:var(--text); }
.ss-clear-history {
  display:block; width:100%; text-align:center; padding:10px 14px; font-size:12px;
  font-weight:600; color:var(--text3); border-top:1px solid var(--border);
  cursor:pointer; background:none; border-left:none; border-right:none; border-bottom:none;
}
.ss-clear-history:hover { color:var(--danger); background:var(--gray-50); }
.ss-empty { padding:18px 14px; text-align:center; font-size:12px; color:var(--text4); }

  
/*  Fire 4: Table view (catalog)  */
.catalog-table-scroll { overflow-x:auto; background:var(--white); border:1px solid var(--border); border-radius:var(--radius-lg); }
.catalog-table { width:100%; border-collapse:collapse; min-width:640px; }
.catalog-table th { padding:10px 14px; text-align:left; font-size:11px; font-weight:700; color:var(--text4); text-transform:uppercase; letter-spacing:.4px; background:var(--gray-50); border-bottom:1px solid var(--border); white-space:nowrap; }
.catalog-table td { padding:10px 14px; font-size:13px; color:var(--text); border-bottom:1px solid var(--border); vertical-align:middle; }
.catalog-table tr:last-child td { border-bottom:none; }
.catalog-table tr:hover td { background:var(--gray-50); }
.catalog-table-thumb { display:block; width:52px; height:52px; border-radius:8px; overflow:hidden; background:var(--gray-100); }
.catalog-table-thumb img, .catalog-table-thumb svg { width:100%; height:100%; object-fit:cover; }
.catalog-table-name { font-weight:600; color:var(--text); text-decoration:none; }
/* Equal-size view toggle buttons (3-way) */
.view-toggle { display:flex; border:1.5px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.view-toggle .view-btn { width:34px; height:34px; }

/*  Catalog Themes (set via Settings → Product Views → User)  */
[data-catalog-theme="minimal"] .product-card,
[data-catalog-theme="minimal"] .list-card { border:none; box-shadow:none; background:transparent; }
[data-catalog-theme="minimal"] .product-thumb,
[data-catalog-theme="minimal"] .list-thumb { border-radius:0; }
[data-catalog-theme="minimal"] .catalog-table-scroll { border:none; }

[data-catalog-theme="bold_gold"] .product-card,
[data-catalog-theme="bold_gold"] .list-card { border-color:var(--gold,#c9a84c); border-width:1.5px; }
[data-catalog-theme="bold_gold"] .product-name,
[data-catalog-theme="bold_gold"] .list-name { color:var(--gold-dark,#a0822a); }
[data-catalog-theme="bold_gold"] .product-grid { gap:20px; }
[data-catalog-theme="bold_gold"] .shortlist-btn.saved,
[data-catalog-theme="bold_gold"] .shortlist-btn-list.saved { color:var(--gold,#c9a84c); }

[data-catalog-theme="compact"] .product-grid { gap:8px; }
[data-catalog-theme="compact"] .product-thumb { aspect-ratio:1/1; }
[data-catalog-theme="compact"] .product-body { padding:8px 10px 10px; }
[data-catalog-theme="compact"] .list-thumb { width:56px; height:56px; }
[data-catalog-theme="compact"] .catalog-table td, [data-catalog-theme="compact"] .catalog-table th { padding:6px 10px; }
</style>

<div class="page-content" data-catalog-theme="<?= h($catalogTheme) ?>">
  <div class="catalog-layout" style="padding-top:24px;">

    <!-- ══════════════════ LEFT SIDEBAR (desktop only) ═══════════════════ -->
    <aside class="catalog-filters-panel filter-sidebar">

      <div class="filter-sidebar-content">

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <p style="font-family:var(--font-display);font-size:15px;font-weight:700;">Filters</p>
          <?php if ($hasFilter): ?>
          <a href="index.php?page=catalog" style="font-size:12px;font-weight:600;color:var(--text3);text-decoration:underline;" id="sidebarClearAllLink">
            Clear all
          </a>
          <?php endif; ?>
        </div>

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

     <div class="catalog-search-wrap" style="position:relative;">
        <span class="catalog-search-icon"><?= icon('search',16) ?></span>
        <input type="search" id="searchInput" class="catalog-search-input"
               placeholder="Search by name or lot number…"
               value="<?= h($search) ?>" autocomplete="off"/>
        <?php if ($search): ?>
        <span class="catalog-search-clear" id="searchClear"><?= icon('close',13) ?></span>
        <?php endif; ?>
        <div id="searchSuggestBox" class="search-suggest-box"></div>
      </div>

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
            <button class="view-btn" id="viewGrid" title="Grid"><?= icon('grid',15) ?></button>
            <button class="view-btn" id="viewList" title="List"><?= icon('filter',15) ?></button>
            <button class="view-btn" id="viewTable" title="Table"><?= icon('file',15) ?></button>
          </div>
        </div>
      </div>

      <div id="ajaxLoader" class="ajax-loader">
        <div class="loader-spinner"></div>
      </div>

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
           data-view="<?= h($defaultView) ?>">
        <?= renderProductGrid($products, $defaultView) ?>
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
window.CATALOG_DEFAULT_VIEW = <?= json_encode($defaultView) ?>;
window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;

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
var _pending = Object.assign({}, _applied);

function sidebarPendingChip(btn, filterKey) {
  var list = btn.closest('.filter-option-list');
  list.querySelectorAll('.filter-chip').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
  _pending[filterKey] = btn.dataset.value;
  updateApplyBtnState();
}

function updateApplyBtnState() {
  var btn = document.getElementById('sidebarApplyBtn');
  if (!btn) return;
  var dirty = Object.keys(_pending).some(function(k) {
    return (_pending[k] || '') !== (_applied[k] || '');
  });
  btn.classList.toggle('has-pending', dirty);
}

document.getElementById('sidebarApplyBtn')?.addEventListener('click', function() {
  _pending.sqft_min = (document.getElementById('sidebarSqftMin')?.value || '').trim();
  _pending.sqft_max = (document.getElementById('sidebarSqftMax')?.value || '').trim();
  _pending.sl_min   = (document.getElementById('sidebarSlMin')?.value   || '').trim();
  _pending.sl_max   = (document.getElementById('sidebarSlMax')?.value   || '').trim();
  _pending.sh_min   = (document.getElementById('sidebarShMin')?.value   || '').trim();
  _pending.sh_max   = (document.getElementById('sidebarShMax')?.value   || '').trim();
  if (window.catalogApplyAllFilters) window.catalogApplyAllFilters(_pending);
  _applied = Object.assign({}, _pending);
  updateApplyBtnState();
});

['sidebarSqftMin','sidebarSqftMax','sidebarSlMin','sidebarSlMax','sidebarShMin','sidebarShMax'].forEach(function(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('input', function() { updateApplyBtnState(); });
});

function openFilterDrawer() {
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

document.getElementById('drawerApplyBtn')?.addEventListener('click', function() {
  var drawerState = {
    sqft_min: (document.getElementById('drawerSqftMin')?.value || '').trim(),
    sqft_max: (document.getElementById('drawerSqftMax')?.value || '').trim(),
    sl_min:   (document.getElementById('drawerSlMin')?.value   || '').trim(),
    sl_max:   (document.getElementById('drawerSlMax')?.value   || '').trim(),
    sh_min:   (document.getElementById('drawerShMin')?.value   || '').trim(),
    sh_max:   (document.getElementById('drawerShMax')?.value   || '').trim(),
  };
  var full = Object.assign({}, _applied, drawerState);
  if (window.catalogApplyAllFilters) window.catalogApplyAllFilters(full);
  _applied = Object.assign({}, full);
  _pending = Object.assign({}, full);
  updateApplyBtnState();
  closeFilterDrawer();
});

updateApplyBtnState();
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>