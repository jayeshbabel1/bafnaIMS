<?php
/**
 * admin/views/products.php
 * AJAX pagination · sortable columns · out-of-stock badge · 2-char search min
 */
 
// ── AJAX handler ──────────────────────────────────────────────────────────────
if (!empty($_GET['ajax_products'])) {
    $allowedPer  = [25, 50, 75, 100];
    $perPage     = in_array((int)($_GET['per'] ?? 25), $allowedPer) ? (int)$_GET['per'] : 25;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $search      = trim($_GET['q']      ?? '');
    $cat         = trim($_GET['cat']    ?? '');
    $filter      = trim($_GET['filter'] ?? '');
 
    // Sorting
    $allowedSort = ['name','quarry_number','quantity_available','quantity_on_hold','in_stock'];
    $sortCol     = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'sort_order';
    $sortDir     = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    if ($sortCol === 'sort_order') { $sortDir = 'ASC'; }
 
    $db     = getDB();
    $params = [];
    $where  = "WHERE 1=1";
 
    if ($search !== '') {
        $where    .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)";
        $params[]  = "%{$search}%";
        $params[]  = "%{$search}%";
    }
    if ($cat !== '') {
        $where   .= " AND p.category = ?";
        $params[] = $cat;
    }
    // Dashboard health filters
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
    $currentPage= min($currentPage, $totalPages);
    $offset     = ($currentPage - 1) * $perPage;
 
    $orderSQL = "p.{$sortCol} {$sortDir}" . ($sortCol !== 'sort_order' ? ", p.id DESC" : ", p.id DESC");
    $rowParams = array_merge($params, [$perPage, $offset]);
    $sql = "SELECT p.*,
                (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
            FROM products p $where
            ORDER BY {$orderSQL}
            LIMIT ? OFFSET ?";
    $st = $db->prepare($sql);
    $st->execute($rowParams);
    $products = $st->fetchAll();
 
    // Table rows
    ob_start();
    if (empty($products)): ?>
    <tr><td colspan="10" class="admin-table-empty">No products found.</td></tr>
    <?php else:
        foreach ($products as $p):
            $pal      = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
            $outStock = !$p['in_stock'] || (float)$p['quantity_available'] <= 0;
    ?>
    <tr>
      <td>
        <div class="tbl-thumb">
          <?php if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])): ?>
          <img src="../assets/uploads/photos/<?= h($p['primary_photo']) ?>" alt=""/>
          <?php else: ?><?= marbleSVG($pal, 40, 40, 'ath'.$p['id']) ?><?php endif; ?>
        </div>
      </td>
      <td style="font-weight:600;max-width:180px;">
        <a href="index.php?page=product_edit&id=<?= $p['id'] ?>" style="color:var(--text);"><?= h($p['name']) ?></a>
      </td>
      <td style="color:var(--text3);font-size:12px;"><?= h($p['quarry_number']) ?></td>
      <td><span class="badge badge-blue" style="font-size:10px;"><?= h($p['category']) ?></span></td>
      <td style="font-size:13px;"><?= number_format((float)$p['quantity_available'],0) ?> sq.ft.</td>
      <td style="font-size:13px;color:var(--text3);"><?= number_format((float)$p['quantity_on_hold'],0) ?> sq.ft.</td>
      <td>
        <?php if ($outStock): ?>
          <span class="badge badge-gray">Out of Stock</span>
        <?php else: ?>
          <span class="badge badge-green">In Stock</span>
        <?php endif; ?>
      </td>
      <td><?= $p['featured'] ? '<span class="badge badge-gold">✦ Yes</span>' : '<span style="color:var(--text3);font-size:12px;">—</span>' ?></td>
      <td>
        <div style="display:flex;gap:6px;align-items:center;">
          <a href="index.php?page=product_edit&id=<?= $p['id'] ?>" class="btn-admin-secondary btn-admin-sm"><?= icon('edit',13) ?></a>
          <?php
          $thumbSrc = ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo']))
              ? '../assets/uploads/photos/' . $p['primary_photo'] : '';
          ?>
          <button type="button"
                  onclick="openWaShare(<?= $p['id'] ?>, <?= h(json_encode($p['name'])) ?>, <?= h(json_encode($p['quarry_number'])) ?>, <?= h(json_encode($thumbSrc)) ?>)"
                  class="btn-admin-secondary btn-admin-sm"
                  style="color:#25D366;border-color:#25D366;"
                  title="Share via WhatsApp">
            <?= icon('whatsapp', 13) ?>
          </button>
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action"     value="delete_product"/>
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
            <button type="submit" class="btn-admin-danger btn-admin-sm"
                    data-confirm="Delete '<?= h(addslashes($p['name'])) ?>'?"><?= icon('trash',13) ?></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; endif;
    $tableRows = ob_get_clean();
 
    // Pagination HTML
    ob_start();
    if ($totalPages > 1):
        $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range);
    ?>
    <div class="admin-pagination">
      <button class="apag-btn <?= $currentPage<=1?'disabled':'' ?>" data-page="<?= $currentPage-1 ?>">&lsaquo;</button>
      <?php if ($s>1): ?><button class="apag-btn" data-page="1">1</button><?php if ($s>2): ?><span class="apag-ellipsis">…</span><?php endif; endif; ?>
      <?php for ($i=$s;$i<=$e;$i++): ?>
      <button class="apag-btn <?= $i===$currentPage?'active':'' ?>" data-page="<?= $i ?>"><?= $i ?></button>
      <?php endfor; ?>
      <?php if ($e<$totalPages): ?><?php if ($e<$totalPages-1): ?><span class="apag-ellipsis">…</span><?php endif; ?><button class="apag-btn" data-page="<?= $totalPages ?>"><?= $totalPages ?></button><?php endif; ?>
      <button class="apag-btn <?= $currentPage>=$totalPages?'disabled':'' ?>" data-page="<?= $currentPage+1 ?>">&rsaquo;</button>
    </div>
    <?php endif;
    $paginationHtml = ob_get_clean();
 
    header('Content-Type: application/json');
    echo json_encode([
        'rows'       => $tableRows,
        'pagination' => $paginationHtml,
        'total'      => $total,
        'page'       => $currentPage,
        'pages'      => $totalPages,
        'perPage'    => $perPage,
        'sort'       => $sortCol,
        'dir'        => $sortDir,
    ]);
    exit;
}
 
$adminTitle = 'Products';
include __DIR__ . '/../_layout_top.php';
$db = getDB();
 
// Pre-select filter from dashboard link
$activeFilter = trim($_GET['filter'] ?? '');
$filterLabels = [
    'no_image'       => 'Missing: Photos',
    'no_measurement' => 'Missing: Measurement Sheet',
    'no_dna'         => 'Missing: DNA Report',
];
?>
 
<style>
/* ── Sortable column headers ────────────────────────────────── */
.sortable-th { cursor:pointer;user-select:none;white-space:nowrap; }
.sortable-th:hover { color:var(--accent); }
.sort-icon { display:inline-flex;flex-direction:column;gap:1px;vertical-align:middle;margin-left:4px;opacity:.35; }
.sort-icon.asc  .si-up   { opacity:1; }
.sort-icon.desc .si-down { opacity:1; }
.sort-icon.asc,
.sort-icon.desc { opacity:1; }
.si-up,.si-down { display:block;width:0;height:0;border-left:4px solid transparent;border-right:4px solid transparent; }
.si-up   { border-bottom:5px solid var(--text2); }
.si-down { border-top:5px solid var(--text2); }
 
/* ── Responsive toolbar ─────────────────────────────────────── */
.products-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
    align-items: stretch;
}
 
/* Group: primary action (always first, full width on xs) */
.products-toolbar-primary {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: stretch;
}
 
/* Group: data actions (Export / Import) */
.products-toolbar-data {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: stretch;
}
 
/* Group: sync / upload actions */
.products-toolbar-sync {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: stretch;
}
 
/* Divider between groups (desktop only) */
.products-toolbar-divider {
    width: 1px;
    background: var(--border);
    align-self: stretch;
    display: none;
}
@media (min-width: 640px) {
    .products-toolbar-divider { display: block; }
}
 
/* On very small screens each group takes full width */
@media (max-width: 479px) {
    .products-toolbar-primary,
    .products-toolbar-data,
    .products-toolbar-sync { width: 100%; }
    .products-toolbar-primary .admin-toolbar-btn,
    .products-toolbar-data   .admin-toolbar-btn,
    .products-toolbar-sync   .admin-toolbar-btn,
    .products-toolbar-primary .admin-toolbar-form .admin-toolbar-btn,
    .products-toolbar-data   .admin-toolbar-form .admin-toolbar-btn,
    .products-toolbar-sync   .admin-toolbar-form .admin-toolbar-btn { width: 100%; justify-content: center; }
}
 
/* ── Category tabs — responsive ─────────────────────────────── */
.admin-cat-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 14px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
    flex-wrap: nowrap;
}
.admin-cat-tabs::-webkit-scrollbar { display: none; }
.admin-cat-tabs .tag-pill {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    background: var(--surface);
    border: 1.5px solid var(--border);
    color: var(--text3);
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
    font-family: inherit;
}
.admin-cat-tabs .tag-pill:hover {
    border-color: var(--accent);
    color: var(--text);
}
.admin-cat-tabs .tag-pill.active {
    background: var(--nav-bg, var(--accent));
    border-color: var(--nav-bg, var(--accent));
    color: #fff;
}
 
/* Active filter banner */
.filter-banner {
    display:flex;align-items:center;gap:10px;
    padding:8px 14px;background:var(--gold-bg);
    border:1px solid var(--gold);border-radius:8px;
    margin-bottom:12px;font-size:12px;font-weight:600;
    color:var(--gold);flex-wrap:wrap;
}
</style>
 
<!-- ═══ RESPONSIVE TOOLBAR ══════════════════════════════════════════════ -->
<div class="products-toolbar">
 
    <!-- Group 1: Add Product -->
    <div class="products-toolbar-primary">
        <a href="index.php?page=product_edit"
           class="admin-toolbar-btn admin-toolbar-btn--primary">
            <?= icon('plus',14) ?> Add Product
        </a>
    </div>
 
    <div class="products-toolbar-divider"></div>
 
    <!-- Group 2: Data In/Out -->
    <div class="products-toolbar-data">
        <!-- Export Excel -->
        <form method="post" class="admin-toolbar-form">
            <input type="hidden" name="action" value="export"/>
            <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--solid"
                    title="Export all products to Excel">
                <?= icon('download',14) ?> Export Excel
            </button>
        </form>
        <!-- Import Excel -->
        <form method="POST" action="index.php" enctype="multipart/form-data" class="admin-toolbar-form">
            <input type="hidden" name="action" value="import"/>
            <label class="admin-toolbar-btn admin-toolbar-btn--solid"
                   title="Import products from Excel file">
                <?= icon('upload',14) ?> Import Excel
                <input type="file" name="xls_file" onchange="this.form.submit()"/>
            </label>
        </form>
    </div>
 
    <div class="products-toolbar-divider"></div>
 
    <!-- Group 3: Sync + Upload -->
    <div class="products-toolbar-sync">
        <!-- Sync Photos -->
        <form method="POST" action="index.php" class="admin-toolbar-form">
            <input type="hidden" name="action" value="sync_photos"/>
            <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed"
                    title="Scan photos folder and link to products">
                <?= icon('image',14) ?> Sync Photos
            </button>
        </form>
        <!-- Sync Measurements -->
        <form method="POST" action="index.php" class="admin-toolbar-form">
            <input type="hidden" name="action" value="sync_measurements"/>
            <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed"
                    title="Scan measurement sheets folder">
                <?= icon('file',14) ?> Sync Sheets
            </button>
        </form>
        <!-- Sync DNA -->
        <form method="POST" action="index.php" class="admin-toolbar-form">
            <input type="hidden" name="action" value="sync_dna"/>
            <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed"
                    title="Scan DNA reports folder">
                <?= icon('file',14) ?> Sync DNA
            </button>
        </form>
        <!-- Upload Photos -->
        <form method="POST" action="index.php" enctype="multipart/form-data" class="admin-toolbar-form">
            <input type="hidden" name="action" value="import_photos"/>
            <label class="admin-toolbar-btn admin-toolbar-btn--upload"
                   title="Upload photo files directly">
                <?= icon('image',14) ?> Upload Photos
                <input type="file" name="photo_zip[]" accept=".zip,image/*" multiple onchange="this.form.submit()"/>
            </label>
        </form>
    </div>
 
</div>
<!-- /RESPONSIVE TOOLBAR -->
 
<!-- Active health filter banner -->
<?php if ($activeFilter && isset($filterLabels[$activeFilter])): ?>
<div class="filter-banner">
  <?= icon('info',14) ?>
  Showing: <?= h($filterLabels[$activeFilter]) ?>
  <a href="index.php?page=products" style="margin-left:auto;font-size:11px;color:var(--gold);text-decoration:underline;">
    Clear filter
  </a>
</div>
<?php endif; ?>
 
<!-- Category tabs -->
<div class="admin-cat-tabs" id="adminCatTabs">
  <button class="tag-pill active" data-cat="" type="button">All</button>
  <?php foreach (CATEGORIES as $c): ?>
  <button class="tag-pill" data-cat="<?= h($c) ?>" type="button"><?= h($c) ?></button>
  <?php endforeach; ?>
</div>
 
<!-- Search + Per-page -->
<div class="admin-products-searchbar">
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
 
<!-- Loading overlay -->
<div class="admin-products-loader" id="adminProductsLoader">
  <div class="admin-loader-ring"></div>
</div>
 
<!-- Products table -->
<div class="admin-table-wrap" id="adminProductsTableWrap">
  <table class="admin-table" id="adminProductsTable">
    <thead>
      <tr>
        <th style="width:52px;">Photo</th>
        <th class="sortable-th" data-col="name">
          Name <span class="sort-icon" id="si-name"><span class="si-up"></span><span class="si-down"></span></span>
        </th>
        <th class="sortable-th" data-col="quarry_number">
          Quarry # <span class="sort-icon" id="si-quarry_number"><span class="si-up"></span><span class="si-down"></span></span>
        </th>
        <th>Category</th>
        <th class="sortable-th" data-col="quantity_available">
          Qty Available <span class="sort-icon" id="si-quantity_available"><span class="si-up"></span><span class="si-down"></span></span>
        </th>
        <th class="sortable-th" data-col="quantity_on_hold">
          Qty On Hold <span class="sort-icon" id="si-quantity_on_hold"><span class="si-up"></span><span class="si-down"></span></span>
        </th>
        <th class="sortable-th" data-col="in_stock">
          Stock <span class="sort-icon" id="si-in_stock"><span class="si-up"></span><span class="si-down"></span></span>
        </th>
        <th>Featured</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="adminProductsTbody">
      <tr><td colspan="10" class="admin-table-empty">Loading…</td></tr>
    </tbody>
  </table>
</div>
 
<!-- Count + Pagination -->
<div class="admin-products-footer">
  <p class="admin-products-count" id="adminProductsCount"></p>
  <div id="adminPaginationWrap"></div>
</div>
 
<?php include __DIR__ . '/_wa_share_modal.php'; ?>
<?php include __DIR__ . '/../_layout_bottom.php'; ?>