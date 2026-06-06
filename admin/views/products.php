<?php
/**
 * admin/views/products.php — Task 2: AJAX Pagination + Per-page dropdown
 */
$adminTitle = 'Products';

// ── AJAX handler — must run before any layout include ────────────────────────
if (!empty($_GET['ajax_products'])) {
    // Sanitise inputs
    $allowedPer = [25, 50, 75, 100];
    $perPage    = in_array((int)($_GET['per'] ?? 25), $allowedPer) ? (int)$_GET['per'] : 25;
    $currentPage    = max(1, (int)($_GET['p']   ?? 1));
    $search     = trim($_GET['q']   ?? '');
    $cat        = trim($_GET['cat'] ?? '');

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

    // Total count
    $cntSt = $db->prepare("SELECT COUNT(*) FROM products p $where");
    $cntSt->execute($params);
    $total      = (int)$cntSt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage    = min($currentPage, $totalPages);
    $offset     = ($currentPage - 1) * $perPage;

    // Fetch rows
    $rowParams   = array_merge($params, [$perPage, $offset]);
    $sql         = "SELECT p.*,
                    (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
                    FROM products p $where
                    ORDER BY p.sort_order ASC, p.id DESC
                    LIMIT ? OFFSET ?";
    $st = $db->prepare($sql);
    $st->execute($rowParams);
    $products = $st->fetchAll();

    // Build table rows HTML
    ob_start();
    if (empty($products)): ?>
    <tr><td colspan="10" class="admin-table-empty">No products found.</td></tr>
    <?php else:
        foreach ($products as $p):
            $pal = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
    ?>
    <tr>
      <td>
        <div class="tbl-thumb">
          <?php if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])): ?>
          <img src="../assets/uploads/photos/<?= h($p['primary_photo']) ?>" alt=""/>
          <?php else: ?>
          <?= marbleSVG($pal, 40, 40, 'ath'.$p['id']) ?>
          <?php endif; ?>
        </div>
      </td>
      <td style="font-weight:600;max-width:180px;">
        <a href="index.php?page=product_edit&id=<?= $p['id'] ?>" style="color:var(--text);"><?= h($p['name']) ?></a>
      </td>
      <td style="color:var(--text3);font-size:12px;"><?= h($p['quarry_number']) ?></td>
      <td><span class="badge badge-blue" style="font-size:10px;"><?= h($p['category']) ?></span></td>
      <td style="font-size:13px;"><?= number_format((float)$p['quantity_available'],0) ?> sq.ft.</td>
      <td style="font-size:13px;color:var(--text3);"><?= number_format((float)$p['quantity_on_hold'],0) ?> sq.ft.</td>
      <td style="font-size:12px;"><?= h($p['thickness']) ?> </td>
      <td><?= $p['in_stock'] ? '<span class="badge badge-green">In Stock</span>' : '<span class="badge badge-gray">Out</span>' ?></td>
      <td><?= $p['featured'] ? '<span class="badge badge-gold">✦ Yes</span>' : '<span style="color:var(--text3);font-size:12px;">—</span>' ?></td>
      <td>
        <div style="display:flex;gap:6px;">
          <a href="index.php?page=product_edit&id=<?= $p['id'] ?>" class="btn-admin-secondary btn-admin-sm"><?= icon('edit',13) ?></a>
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

    // Build pagination HTML
    ob_start();
    if ($totalPages > 1):
        $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range);
    ?>
    <div class="admin-pagination" id="adminPagination">
      <button class="apag-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>" data-page="<?= $currentPage - 1 ?>">&lsaquo;</button>
      <?php if ($s > 1): ?><button class="apag-btn" data-page="1">1</button><?php if ($s > 2): ?><span class="apag-ellipsis">…</span><?php endif; endif; ?>
      <?php for ($i = $s; $i <= $e; $i++): ?>
      <button class="apag-btn <?= $i === $currentPage ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></button>
      <?php endfor; ?>
      <?php if ($e < $totalPages): ?><?php if ($e < $totalPages - 1): ?><span class="apag-ellipsis">…</span><?php endif; ?><button class="apag-btn" data-page="<?= $totalPages ?>"><?= $totalPages ?></button><?php endif; ?>
      <button class="apag-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" data-page="<?= $currentPage + 1 ?>">&rsaquo;</button>
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
    ]);
    exit;
}

include __DIR__ . '/../_layout_top.php';
$db = getDB();
?>

<!-- Toolbar -->
<div class="admin-products-toolbar">
  <a href="index.php?page=product_edit" class="btn-admin-primary"><?= icon('plus',16) ?> Add Product</a>

  <!-- Import CSV 
  <form method="POST" action="index.php" enctype="multipart/form-data" class="admin-toolbar-form">
    <input type="hidden" name="action" value="import_excel"/>
    <label class="admin-toolbar-file-btn">
      <?//= icon('upload',14) ?> Import CSV
      <input type="file" name="excel_file" accept=".csv" onchange="this.form.submit()"/>
    </label>
  </form> -->

  <!-- Export CSV -->
 <!-- <a href="index.php?action=export_csv" class="btn-admin-secondary btn-admin-sm"><?//= icon('download',14) ?> Export CSV</a> -->

  <!-- Export Excel -->
  <form method="post" class="admin-toolbar-form">
    <input type="hidden" name="action" value="export"/>
    <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= icon('download',14) ?> Export Excel</button>
  </form>

  <!-- Import Excel -->
  <form method="POST" action="index.php" enctype="multipart/form-data" class="admin-toolbar-form">
    <input type="hidden" name="action" value="import"/>
    <label class="admin-toolbar-file-btn">
      <?= icon('upload',14) ?> Import Excel
      <input type="file" name="xls_file" onchange="this.form.submit()"/>
    </label>
  </form>

  <!-- Sync Photos -->
  <form method="POST" action="index.php" class="admin-toolbar-form">
    <input type="hidden" name="action" value="sync_photos"/>
    <button type="submit" class="admin-toolbar-sync-btn"><?= icon('image',14) ?> Sync Photos</button>
  </form>

  <!-- Sync Measurement PDFs -->
  <form method="POST" action="index.php" class="admin-toolbar-form">
    <input type="hidden" name="action" value="sync_measurements"/>
    <button type="submit" class="admin-toolbar-sync-btn"><?= icon('file',14) ?> Sync Measurements</button>
  </form>

  <!-- Sync DNA PDFs -->
  <form method="POST" action="index.php" class="admin-toolbar-form">
    <input type="hidden" name="action" value="sync_dna"/>
    <button type="submit" class="admin-toolbar-sync-btn"><?= icon('file',14) ?> Sync DNA</button>
  </form>

  <!-- Upload Photos ZIP -->
  <form method="POST" action="index.php" enctype="multipart/form-data" class="admin-toolbar-form">
    <input type="hidden" name="action" value="import_photos"/>
    <label class="admin-toolbar-file-btn admin-toolbar-file-btn--accent">
      <?= icon('image',14) ?> Upload Photos
      <input type="file" name="photo_zip[]" accept=".zip,image/*" multiple onchange="this.form.submit()"/>
    </label>
  </form>
</div>

<!-- Category tabs -->
<div class="admin-cat-tabs" id="adminCatTabs">
  <button class="tag-pill active" data-cat="">All</button>
  <?php foreach (CATEGORIES as $c): ?>
  <button class="tag-pill" data-cat="<?= h($c) ?>"><?= h($c) ?></button>
  <?php endforeach; ?>
</div>

<!-- Search + Per-page bar -->
<div class="admin-products-searchbar">
  <div class="admin-search-wrap">
    <?= icon('search', 14) ?>
    <input type="text" id="adminProductSearch" class="admin-input admin-search-input"
           placeholder="Search name / quarry…" autocomplete="off"/>
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
        <th>Photo</th><th>Name</th><th>Quarry #</th><th>Category</th>
        <th>Qty Available</th><th>Qty On Hold</th><th>Thickness</th>
        <th>Stock</th><th>Featured</th><th>Actions</th>
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

<?php include __DIR__ . '/../_layout_bottom.php'; ?>