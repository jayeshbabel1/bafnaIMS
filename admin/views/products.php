<?php
/**
 * admin/views/products.php — Fire 3: Grid / List / Table views,
 * field visibility & order driven by Settings → Product Views (admin panel).
 */
requireAdminPermission('products.view');
require_once BASE_PATH . '/includes/product_views.php';
ensureProductViewTables();

// ── Short header/card labels (table headers stay compact; config labels are longer) ─
function pvShortLabel(string $key, string $fallback): string {
    $short = [
        'photo' => 'Photo', 'quantity_available' => 'Qty Avail.', 'quantity_on_hold' => 'Qty Hold',
        'color_subcategory' => 'Color', 'quarry_number' => 'Quarry #', 'cutter_size' => 'Italian Size',
        'sizes' => 'Useable Size', 'in_stock' => 'Stock', 'actions' => 'Actions',
    ];
    return $short[$key] ?? $fallback;
}

// ── Render one field's value for a product row (shared by all 3 views) ──────
function pvAdminFieldHtml(array $p, string $key): string {
    switch ($key) {
        case 'photo':
            if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])) {
                 $thumbSrc = '../' . getPhotoThumbUrl($p['primary_photo']);
            }
             if ($thumbSrc) {
               return '<img src="'.h($thumbSrc).'" alt="'.h($p['name']).'" loading="lazy" decoding="async" width="100%" height="100%"/>';
            }
                       
            $pal = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
            return marbleSVG($pal, 60, 60, 'apv'.$p['id']);
        case 'name':
            return adminCan('products.edit')
                ? '<a href="index.php?page=product_edit&id='.$p['id'].'" style="color:var(--admin-text,var(--text));font-weight:600;">'.h($p['name']).'</a>'
                : '<span style="font-weight:600;">'.h($p['name']).'</span>';
        case 'quarry_number':   return h($p['quarry_number'] ?: '—');
        case 'category':        return $p['category'] ? '<span class="badge badge-blue" style="font-size:10px;">'.h($p['category']).'</span>' : '—';
        case 'subcategory':      return h($p['subcategory'] ?: '—');
        case 'color_subcategory':return h($p['color_subcategory'] ?: '—');
        case 'thickness':        return h($p['thickness'] ?: '—');
        case 'sizes':            return h(formatDimension($p['sizes_l'] ?? '', $p['sizes_h'] ?? '') ?: '—');
        case 'cutter_size':      return h(formatDimension($p['cutter_size_l'] ?? '', $p['cutter_size_h'] ?? '') ?: '—');
        case 'origin':           return h($p['origin'] ?: '—');
        case 'finish':           return h($p['finish'] ?: '—');
        case 'quantity_available': return number_format((float)$p['quantity_available'], 0) . ' sq.ft.';
        case 'quantity_on_hold':   return number_format((float)$p['quantity_on_hold'], 0) . ' sq.ft.';
        case 'in_stock':
            $outStock = !$p['in_stock'] || (float)$p['quantity_available'] <= 0;
            return $outStock ? '<span class="badge badge-gray">Out of Stock</span>' : '<span class="badge badge-green">In Stock</span>';
       // case 'featured':
        //    return $p['featured'] ? '<span class="badge badge-gold">✦ Yes</span>' : '<span style="color:var(--admin-text3,var(--text3));font-size:12px;">—</span>';
        case 'actions':
            return pvAdminActionButtons($p);
        default: return '';
    }
}

function pvAdminActionButtons(array $p): string {
    $thumbSrc = ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo']))
        ? '../assets/uploads/photos/' . $p['primary_photo'] : '';
    $h = '<div class="admin-table-actions">';
  if (adminCan('products.view')) {
        $h .= '<a href="index.php?page=product_edit" class="btn-admin-secondary btn-admin-sm" title="Edit">'.icon('View',13).'</a>';
    }
    if (adminCan('products.edit')) {
        $h .= '<a href="index.php?page=product_edit&id='.$p['id'].'" class="btn-admin-secondary btn-admin-sm" title="Edit">'.icon('edit',13).'</a>';
    }
    if (adminCan('products.whatsapp')) {
        $h .= '<button type="button" onclick="openWaShare('.$p['id'].', '.h(json_encode($p['name'])).', '.h(json_encode($p['quarry_number'])).', '.h(json_encode($thumbSrc)).')" class="btn-admin-secondary btn-admin-sm" style="color:#25D366;border-color:#25D366;" title="Share via WhatsApp">'.icon('whatsapp',13).'</button>';
    }
    if (adminCan('products.delete')) {
        $h .= '<form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action" value="delete_product"/>
            <input type="hidden" name="product_id" value="'.$p['id'].'"/>
            '.csrfField().'
            <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Delete \''.h(addslashes($p['name'])).'\'?">'.icon('trash',13).'</button>
        </form>';
    }
    $h .= '</div>';
    return $h;
}

// ── TABLE view ────────────────────────────────────────────────────────────
function renderAdminProductsTable(array $products, array $fields, string $sortCol, string $sortDir): string {
    if (empty($products)) return '<div class="admin-table-wrap"><div class="admin-table-empty">No products found.</div></div>';
    $sortable = ['name','quarry_number','quantity_available','quantity_on_hold','in_stock'];
    $h = '<div class="admin-table-scroll"><table class="admin-table" id="adminProductsTable"><thead><tr>';
    foreach ($fields as $f) {
        $label = h(pvShortLabel($f['key'], $f['label']));
        if (in_array($f['key'], $sortable, true)) {
            $h .= '<th class="sortable-th" data-col="'.$f['key'].'">'.$label.
                  ' <span class="sort-icon'.($sortCol===$f['key']?' '.strtolower($sortDir):'').'" id="si-'.$f['key'].'"><span class="si-up"></span><span class="si-down"></span></span></th>';
        } else {
            $h .= '<th>'.$label.'</th>';
        }
    }
    $h .= '</tr></thead><tbody>';
    foreach ($products as $p) {
        $h .= '<tr>';
        foreach ($fields as $f) {
            $cell = pvAdminFieldHtml($p, $f['key']);
            $h .= $f['key'] === 'photo' ? '<td><div class="tbl-thumb">'.$cell.'</div></td>' : '<td>'.$cell.'</td>';
        }
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div>';
    return $h;
}

// ── GRID view ─────────────────────────────────────────────────────────────
function renderAdminProductsGrid(array $products, array $fields): string {
    if (empty($products)) return '<div class="admin-table-empty">No products found.</div>';
    $h = '<div class="apv-grid">';
    foreach ($products as $p) {
        $h .= '<div class="apv-card">';
        foreach ($fields as $f) {
            if ($f['key'] === 'photo') {
                $h .= '<div class="apv-card-photo">'.pvAdminFieldHtml($p, 'photo').'</div>';
            } elseif ($f['key'] === 'actions') {
                $h .= '<div class="apv-card-actions">'.pvAdminFieldHtml($p, 'actions').'</div>';
            } elseif ($f['key'] === 'name') {
                $h .= '<div class="apv-card-name">'.pvAdminFieldHtml($p, 'name').'</div>';
            } else {
                $h .= '<div class="apv-card-row"><span class="apv-card-label">'.h(pvShortLabel($f['key'],$f['label'])).'</span><span class="apv-card-val">'.pvAdminFieldHtml($p, $f['key']).'</span></div>';
            }
        }
        $h .= '</div>';
    }
    $h .= '</div>';
    return $h;
}

// ── LIST view ─────────────────────────────────────────────────────────────
function renderAdminProductsList(array $products, array $fields): string {
    if (empty($products)) return '<div class="admin-table-empty">No products found.</div>';
    $h = '<div class="apv-list">';
    foreach ($products as $p) {
        $h .= '<div class="apv-list-row">';
        if (in_array('photo', array_column($fields, 'key'), true)) {
            $h .= '<div class="apv-list-thumb">'.pvAdminFieldHtml($p, 'photo').'</div>';
        }
        $h .= '<div class="apv-list-body">';
        foreach ($fields as $f) {
            if (in_array($f['key'], ['photo','actions'], true)) continue;
            if ($f['key'] === 'name') {
                $h .= '<div class="apv-list-name">'.pvAdminFieldHtml($p, 'name').'</div>';
            } else {
                $h .= '<span class="apv-list-chip"><b>'.h(pvShortLabel($f['key'],$f['label'])).':</b> '.pvAdminFieldHtml($p, $f['key']).'</span>';
            }
        }
        $h .= '</div>';
        if (in_array('actions', array_column($fields, 'key'), true)) {
            $h .= '<div class="apv-list-actions">'.pvAdminFieldHtml($p, 'actions').'</div>';
        }
        $h .= '</div>';
    }
    $h .= '</div>';
    return $h;
}

// ── AJAX handler ──────────────────────────────────────────────────────────
if (!empty($_GET['ajax_products'])) {
    $allowedPer  = [25, 50, 75, 100];
    $perPage     = in_array((int)($_GET['per'] ?? 25), $allowedPer) ? (int)$_GET['per'] : 25;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $search      = trim($_GET['q']      ?? '');
    $cat         = trim($_GET['cat']    ?? '');
    $filter      = trim($_GET['filter'] ?? '');
    $view        = in_array($_GET['view'] ?? '', PV_VIEWS, true) ? $_GET['view'] : getDefaultView('admin');

    $allowedSort = ['name','quarry_number','quantity_available','quantity_on_hold','in_stock'];
    $sortCol     = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'sort_order';
    $sortDir     = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    if ($sortCol === 'sort_order') { $sortDir = 'ASC'; }

    $db     = getDB();
    $params = [];
    $where  = "WHERE 1=1";

    if ($search !== '') {
        $where   .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($cat !== '') {
        $where   .= " AND p.category = ?";
        $params[] = $cat;
    }
    if ($filter === 'no_image') {
        $where .= " AND NOT EXISTS (SELECT 1 FROM product_photos pp WHERE pp.product_id=p.id)";
    } elseif ($filter === 'no_measurement') {
        $where .= " AND (p.measurement_sheet IS NULL OR p.measurement_sheet='')";
    } elseif ($filter === 'no_dna') {
        $where .= " AND (p.dna_report IS NULL OR p.dna_report='')";
    }

    $cntSt = $db->prepare("SELECT COUNT(*) FROM products p $where");
    $cntSt->execute($params);
    $total      = (int)$cntSt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset     = ($currentPage - 1) * $perPage;

    $orderSQL = "p.{$sortCol} {$sortDir}, p.id DESC";
    $rowParams = array_merge($params, [$perPage, $offset]);
    $sql = "SELECT p.* FROM products p $where ORDER BY {$orderSQL} LIMIT ? OFFSET ?";
    $st = $db->prepare($sql);
    $st->execute($rowParams);
    $products = $st->fetchAll();

    $fieldConfig = array_values(array_filter(getViewFieldConfig('admin', $view), fn($f) => $f['visible']));

    switch ($view) {
        case 'grid':  $bodyHtml = renderAdminProductsGrid($products, $fieldConfig); break;
        case 'list':  $bodyHtml = renderAdminProductsList($products, $fieldConfig); break;
        default:      $bodyHtml = renderAdminProductsTable($products, $fieldConfig, $sortCol, $sortDir); break;
    }

    // ── Pagination ──────────────────────────────────────────────────────────
    ob_start();
    if ($totalPages > 1):
        $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range);
    ?>
    <div class="admin-pagination">
      <button class="apag-btn <?= $currentPage<=1?'disabled':'' ?>" data-page="<?= $currentPage-1 ?>">&lsaquo;</button>
      <?php if ($s>1): ?><button class="apag-btn" data-page="1">1</button><?php if ($s>2): ?><span class="apag-ellipsis">…</span><?php endif; endif; ?>
      <?php for ($i=$s;$i<=$e;$i++): ?><button class="apag-btn <?= $i===$currentPage?'active':'' ?>" data-page="<?= $i ?>"><?= $i ?></button><?php endfor; ?>
      <?php if ($e<$totalPages): ?><?php if ($e<$totalPages-1): ?><span class="apag-ellipsis">…</span><?php endif; ?><button class="apag-btn" data-page="<?= $totalPages ?>"><?= $totalPages ?></button><?php endif; ?>
      <button class="apag-btn <?= $currentPage>=$totalPages?'disabled':'' ?>" data-page="<?= $currentPage+1 ?>">&rsaquo;</button>
    </div>
    <?php endif;
    $paginationHtml = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'html'       => $bodyHtml,
        'pagination' => $paginationHtml,
        'total'      => $total,
        'page'       => $currentPage,
        'pages'      => $totalPages,
        'perPage'    => $perPage,
        'sort'       => $sortCol,
        'dir'        => $sortDir,
        'view'       => $view,
    ]);
    exit;
}

$adminTitle = 'Products';
include __DIR__ . '/../_layout_top.php';
$db = getDB();

$activeFilter = trim($_GET['filter'] ?? '');
$filterLabels = [
    'no_image'       => 'Missing: Photos',
    'no_measurement' => 'Missing: Measurement Sheet',
    'no_dna'         => 'Missing: DNA Report',
];
$serverDefaultView = getDefaultView('admin');
?>

<style>
.sortable-th { cursor:pointer;user-select:none;white-space:nowrap; }
.sortable-th:hover { color:var(--accent); }
.sort-icon { display:inline-flex;flex-direction:column;gap:1px;vertical-align:middle;margin-left:4px;opacity:.35; }
.sort-icon.asc .si-up, .sort-icon.desc .si-down { opacity:1; }
.sort-icon.asc, .sort-icon.desc { opacity:1; }
.si-up, .si-down { display:block;width:0;height:0;border-left:4px solid transparent;border-right:4px solid transparent; }
.si-up   { border-bottom:5px solid var(--text2); }
.si-down { border-top:5px solid var(--text2); }
.products-toolbar { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:stretch; }
.products-toolbar-primary, .products-toolbar-data, .products-toolbar-sync { display:flex;gap:8px;flex-wrap:wrap;align-items:stretch; }
.products-toolbar-divider { width:1px;background:var(--border);align-self:stretch;display:none; }
@media (min-width:640px) { .products-toolbar-divider { display:block; } }
@media (max-width:479px) {
  .products-toolbar-primary,.products-toolbar-data,.products-toolbar-sync { width:100%; }
  .products-toolbar-primary .admin-toolbar-btn, .products-toolbar-data .admin-toolbar-btn,
  .products-toolbar-sync .admin-toolbar-btn, .products-toolbar-primary .admin-toolbar-form .admin-toolbar-btn,
  .products-toolbar-data .admin-toolbar-form .admin-toolbar-btn,
  .products-toolbar-sync .admin-toolbar-form .admin-toolbar-btn { width:100%;justify-content:center; }
}
.admin-cat-tabs { display:flex;gap:6px;margin-bottom:14px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none;flex-wrap:nowrap; }
.admin-cat-tabs::-webkit-scrollbar { display:none; }
.admin-cat-tabs .tag-pill { flex-shrink:0;display:inline-flex;align-items:center;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:500;background:var(--surface);border:1.5px solid var(--border);color:var(--text3);cursor:pointer;transition:all .15s;white-space:nowrap;font-family:inherit; }
.admin-cat-tabs .tag-pill:hover { border-color:var(--accent);color:var(--text); }
.admin-cat-tabs .tag-pill.active { background:var(--nav-bg,var(--accent));border-color:var(--nav-bg,var(--accent));color:#fff; }
.filter-banner { display:flex;align-items:center;gap:10px;padding:8px 14px;background:var(--gold-bg);border:1px solid var(--gold);border-radius:8px;margin-bottom:12px;font-size:12px;font-weight:600;color:var(--gold);flex-wrap:wrap; }

/* ── View switcher (sticky) ─────────────────────────────────────────── */
.apv-toolbar-sticky {
  position:sticky; top:0; z-index:40; background:var(--admin-bg,var(--bg));
  padding:8px 0; margin:-8px 0 12px; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
}
.apv-view-switch { display:flex; border:1.5px solid var(--admin-table-border,var(--border)); border-radius:8px; overflow:hidden; flex-shrink:0; }
.apv-view-btn { height:34px; min-width:34px; padding:0 12px; display:flex; align-items:center; gap:6px; background:var(--admin-surface,var(--surface)); color:var(--admin-text3,var(--text3)); border:none; cursor:pointer; font-size:12px; font-weight:600; font-family:inherit; box-sizing:border-box; }
.apv-view-btn + .apv-view-btn { border-left:1px solid var(--admin-table-border,var(--border)); }
.apv-view-btn.active { background:var(--admin-accent,var(--accent)); color:#fff; }

/* ── Table (horizontal scroll only in table view) ────────────────────── */
.admin-table-scroll { overflow-x:auto; background:var(--admin-card-bg,var(--surface)); border:1px solid var(--admin-card-border,var(--border)); border-radius:var(--admin-card-radius,var(--card-radius)); }
.admin-table-scroll .admin-table { min-width:640px; }

/* ── Grid view ────────────────────────────────────────────────────────── */
.apv-grid { display:grid; grid-template-columns:1fr; gap:14px; }
@media (min-width:560px)  { .apv-grid { grid-template-columns:repeat(2,1fr); } }
@media (min-width:900px)  { .apv-grid { grid-template-columns:repeat(3,1fr); } }
@media (min-width:1280px) { .apv-grid { grid-template-columns:repeat(4,1fr); } }
.apv-card { background:var(--admin-card-bg,var(--surface)); border:1px solid var(--admin-card-border,var(--border)); border-radius:var(--admin-card-radius,var(--card-radius)); padding:12px; display:flex; flex-direction:column; gap:6px; }
.apv-card-photo { width:100%; aspect-ratio:4/3; border-radius:8px; overflow:hidden; background:var(--admin-surface2,var(--surface2)); }
.apv-card-photo img, .apv-card-photo svg { width:100%; height:100%; object-fit:cover; display:block; }
.apv-card-name { font-size:14px; font-weight:700; margin-top:4px; }
.apv-card-row { display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; }
.apv-card-label { color:var(--admin-text3,var(--text3)); font-weight:600; flex-shrink:0; }
.apv-card-val { text-align:right; color:var(--admin-text,var(--text)); }
.apv-card-actions { margin-top:8px; padding-top:8px; border-top:1px solid var(--admin-table-border,var(--border)); }

/* ── List view ────────────────────────────────────────────────────────── */
.apv-list { display:flex; flex-direction:column; gap:8px; }
.apv-list-row { display:flex; align-items:center; gap:12px; background:var(--admin-card-bg,var(--surface)); border:1px solid var(--admin-card-border,var(--border)); border-radius:10px; padding:10px 12px; flex-wrap:wrap; }
.apv-list-thumb { width:56px; height:56px; border-radius:8px; overflow:hidden; background:var(--admin-surface2,var(--surface2)); flex-shrink:0; }
.apv-list-thumb img, .apv-list-thumb svg { width:100%; height:100%; object-fit:cover; }
.apv-list-body { flex:1; min-width:180px; display:flex; flex-direction:column; gap:4px; }
.apv-list-name { font-size:14px; font-weight:700; }
.apv-list-chip { font-size:11.5px; color:var(--admin-text2,var(--text2)); margin-right:10px; }
.apv-list-chip b { color:var(--admin-text3,var(--text3)); font-weight:600; }
.apv-list-actions { flex-shrink:0; margin-left:auto; }

@media (max-width:768px) { .admin-table-actions .btn-admin-sm { width:30px; } }
</style>

<!-- ═══ TOOLBAR ══════════════════════════════════════════════════════════════ -->
<div class="products-toolbar">

  <?php if (adminCan('products.create')): ?>
  <div class="products-toolbar-primary">
    <a href="index.php?page=product_edit" class="admin-toolbar-btn admin-toolbar-btn--primary">
      <?= icon('plus',14) ?> Add Product
    </a>
  </div>
  <?php endif; ?>

  <?php if (adminCan('products.export') || adminCan('products.import')): ?>
  <div class="products-toolbar-divider"></div>
  <div class="products-toolbar-data">
    <?php if (adminCan('products.export')): ?>
    <form method="post" class="admin-toolbar-form">
      <input type="hidden" name="action" value="export"/>
      <?= csrfField() ?>
      <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--solid" title="Export all products to Excel">
        <?= icon('download',14) ?> Export Excel
      </button>
    </form>
    <?php endif; ?>
    <?php if (adminCan('products.import')): ?>
    <form method="POST" action="index.php" enctype="multipart/form-data" class="admin-toolbar-form">
      <input type="hidden" name="action" value="import"/>
      <?= csrfField() ?>
      <label class="admin-toolbar-btn admin-toolbar-btn--solid" title="Import products from Excel file">
        <?= icon('upload',14) ?> Import Excel
        <input type="file" name="xls_file" onchange="this.form.submit()"/>
      </label>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (adminCan('products.sync_photos') || adminCan('products.sync_docs') || adminCan('products.upload_photos')): ?>
  <div class="products-toolbar-divider"></div>
  <div class="products-toolbar-sync">
    <?php if (adminCan('products.sync_photos')): ?>
    <form method="POST" action="index.php" class="admin-toolbar-form">
      <input type="hidden" name="action" value="sync_photos"/>
      <?= csrfField() ?>
      <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed" title="Sync photos folder">
        <?= icon('image',14) ?> Sync Photos
      </button>
    </form>
    <?php endif; ?>
    <?php if (adminCan('products.sync_docs')): ?>
    <form method="POST" action="index.php" class="admin-toolbar-form">
      <input type="hidden" name="action" value="sync_measurements"/>
      <?= csrfField() ?>
      <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed">
        <?= icon('file',14) ?> Sync Sheets
      </button>
    </form>
    <form method="POST" action="index.php" class="admin-toolbar-form">
      <input type="hidden" name="action" value="sync_dna"/>
      <?= csrfField() ?>
      <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed">
        <?= icon('file',14) ?> Sync DNA
      </button>
    </form>
    <?php endif; ?>
    <?php if (adminCan('products.upload_photos')): ?>
    <form method="POST" action="index.php" enctype="multipart/form-data" class="admin-toolbar-form">
      <input type="hidden" name="action" value="import_photos"/>
      <?= csrfField() ?>
      <label class="admin-toolbar-btn admin-toolbar-btn--upload" title="Upload photo files directly">
        <?= icon('image',14) ?> Upload Photos
        <input type="file" name="photo_zip[]" accept=".zip,image/*" multiple onchange="this.form.submit()"/>
      </label>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<?php if ($activeFilter && isset($filterLabels[$activeFilter])): ?>
<div class="filter-banner">
  <?= icon('info',14) ?>
  Showing: <?= h($filterLabels[$activeFilter]) ?>
  <a href="index.php?page=products" style="margin-left:auto;font-size:11px;color:var(--gold);text-decoration:underline;">Clear filter</a>
</div>
<?php endif; ?>

<!-- Category tabs -->
<div class="admin-cat-tabs" id="adminCatTabs">
  <button class="tag-pill active" data-cat="" type="button">All</button>
  <?php foreach (CATEGORIES as $c): ?>
  <button class="tag-pill" data-cat="<?= h($c) ?>" type="button"><?= h($c) ?></button>
  <?php endforeach; ?>
</div>

<!-- Search + Per-page + View switcher (sticky) -->
<div class="apv-toolbar-sticky">
  <div class="admin-products-searchbar" style="margin-bottom:0;flex:1;">
    <div class="admin-search-wrap">
      <?= icon('search', 14) ?>
      <input type="text" id="adminProductSearch" class="admin-input admin-search-input"
             placeholder="Search name / quarry (min 2 chars)…" autocomplete="off"/>
      <button class="admin-search-clear" id="adminSearchClear" style="display:none;" type="button"><?= icon('close',12) ?></button>
    </div>
    <div class="admin-perpage-wrap">
      <label class="admin-label" style="margin:0;white-space:nowrap;">Show</label>
      <select id="adminPerPage" class="admin-input admin-select admin-perpage-select">
        <option value="25" selected>25</option>
        <option value="50">50</option>
        <option value="75">75</option>
        <option value="100">100</option>
      </select>
      <span class="admin-perpage-label">per page</span>
    </div>
  </div>
  <div class="apv-view-switch" id="apvViewSwitch">
    <button type="button" class="apv-view-btn" data-view="grid" title="Grid view"><?= icon('grid',14) ?> Grid</button>
    <button type="button" class="apv-view-btn" data-view="list" title="List view"><?= icon('filter',14) ?> List</button>
    <button type="button" class="apv-view-btn" data-view="table" title="Table view"><?= icon('file',14) ?> Table</button>
  </div>
</div>

<div class="admin-products-loader" id="adminProductsLoader">
  <div class="admin-loader-ring"></div>
</div>

<div id="adminProductsTableWrap" style="position:relative;">
  <div id="adminProductsContent">
    <div class="admin-table-empty">Loading…</div>
  </div>
</div>

<div class="admin-products-footer">
  <p class="admin-products-count" id="adminProductsCount"></p>
  <div id="adminPaginationWrap"></div>
</div>

<script>
window.ADMIN_PRODUCT_DEFAULT_VIEW = <?= json_encode($serverDefaultView) ?>;
</script>

<?php if (adminCan('products.whatsapp')): ?>
<?php include __DIR__ . '/_wa_share_modal.php'; ?>
<?php endif; ?>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>