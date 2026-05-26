<?php
$adminTitle = 'Products';
include __DIR__ . '/../_layout_top.php';
$db = getDB();
$search = trim($_GET['q'] ?? '');
$cat    = $_GET['cat'] ?? '';
$params = [];
$sql = "SELECT p.*, (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo FROM products p WHERE 1=1";
if ($search) { $sql .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
if ($cat)    { $sql .= " AND p.category=?"; $params[]=$cat; }
$sql .= " ORDER BY p.sort_order ASC, p.id DESC";
$st = $db->prepare($sql); $st->execute($params);
$products = $st->fetchAll();
?>

<!-- Toolbar -->
<div style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap;">
  <a href="index.php?page=product_edit" class="btn-admin-primary"><?= icon('plus',16) ?> Add Product</a>

  <!-- Import -->
  <form method="POST" action="index.php" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
    <input type="hidden" name="action" value="import_excel"/>
    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-weight:600;background:var(--surface);color:var(--text);">
      <?= icon('upload',14) ?> Import CSV
      <input type="file" name="excel_file" accept=".csv" style="display:none" onchange="this.form.submit()"/>
    </label>
  </form>

  <!-- Export -->
  <a href="index.php?action=export_csv" class="btn-admin-secondary btn-admin-sm"><?= icon('download',14) ?> Export CSV</a>

  <!-- Image folder import -->
  <form method="POST" action="index.php" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
    <input type="hidden" name="action" value="import_photos"/>
    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:1.5px dashed var(--accent);border-radius:8px;font-size:12px;font-weight:600;background:var(--accent-light);color:var(--accent);">
      <?= icon('image',14) ?> Upload Photos (ZIP or multi-select)
      <input type="file" name="photo_zip" accept=".zip,image/*" multiple style="display:none" onchange="this.form.submit()"/>
    </label>
  </form>

  <!-- Search -->
  <form method="GET" action="index.php" style="display:flex;gap:8px;margin-left:auto;">
    <input type="hidden" name="page" value="products"/>
    <input type="text" name="q" class="admin-input" placeholder="Search name / quarry…" value="<?= h($search) ?>" style="width:200px;"/>
    <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= icon('search',14) ?></button>
    <?php if ($search || $cat): ?>
    <a href="index.php?page=products" class="btn-admin-secondary btn-admin-sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Category tabs -->
<div style="display:flex;gap:6px;margin-bottom:16px;overflow-x:auto;padding-bottom:4px;">
  <a href="index.php?page=products<?= $search ? '&q='.urlencode($search) : '' ?>" class="tag-pill<?= !$cat ? ' active' : '' ?>" style="font-size:11px;">All</a>
  <?php foreach (CATEGORIES as $c): ?>
  <a href="index.php?page=products&cat=<?= urlencode($c) ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="tag-pill<?= $cat===$c ? ' active' : '' ?>" style="font-size:11px;"><?= h($c) ?></a>
  <?php endforeach; ?>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Photo</th><th>Name</th><th>Quarry #</th><th>Category</th>
        <th>Qty Available</th><th>Qty On Hold</th><th>Thickness</th>
        <th>Stock</th><th>Featured</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($products)): ?>
      <tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text3);">No products found.</td></tr>
      <?php else: foreach ($products as $p):
        $pal = json_decode($p['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
      ?>
      <tr>
        <td>
          <div class="tbl-thumb">
            <?php if ($p['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$p['primary_photo'])): ?>
            <img src="../assets/uploads/photos/<?= h($p['primary_photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"/>
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
        <td style="font-size:12px;"><?= h($p['thickness']) ?> mm</td>
        <td>
          <?= $p['in_stock']
            ? '<span class="badge badge-green">In Stock</span>'
            : '<span class="badge badge-gray">Out</span>' ?>
        </td>
        <td>
          <?= $p['featured']
            ? '<span class="badge badge-gold">✦ Yes</span>'
            : '<span style="color:var(--text3);font-size:12px;">—</span>' ?>
        </td>
        <td>
          <div style="display:flex;gap:6px;">
            <a href="index.php?page=product_edit&id=<?= $p['id'] ?>" class="btn-admin-secondary btn-admin-sm"><?= icon('edit',13) ?></a>
            <form method="POST" action="index.php" style="display:inline;">
              <input type="hidden" name="action"     value="delete_product"/>
              <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
              <button type="submit" class="btn-admin-danger btn-admin-sm"
                      data-confirm="Delete '<?= h(addslashes($p['name'])) ?>'? This cannot be undone."><?= icon('trash',13) ?></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<p style="font-size:12px;color:var(--text3);margin-top:10px;"><?= count($products) ?> products shown</p>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>
