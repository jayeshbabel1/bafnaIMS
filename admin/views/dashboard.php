<?php
$adminTitle = 'Dashboard';
requireAdminPermission('dashboard.view');
include __DIR__ . '/../_layout_top.php';
$db = getDB();

// AFTER — products table stats in a single query
$stats = $db->query("
    SELECT
      COUNT(*) AS total,
      SUM(in_stock = 1) AS in_stock,
      SUM(measurement_sheet IS NULL OR measurement_sheet = '') AS no_measurement,
      SUM(dna_report IS NULL OR dna_report = '') AS no_dna
    FROM products
")->fetch();
$pCount        = (int)$stats['total'];
$inStock       = (int)$stats['in_stock'];
$noMeasurement = (int)$stats['no_measurement'];
$noDNA         = (int)$stats['no_dna'];

// no_image needs the anti-join, kept separate (can't easily fold into the above without a LEFT JOIN + GROUP BY that changes cost profile)
$noImage = (int)$db->query("
    SELECT COUNT(*) FROM products p
    WHERE NOT EXISTS (SELECT 1 FROM product_photos pp WHERE pp.product_id = p.id)
")->fetchColumn();

$uCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$recProd = $db->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<div class="dashboard-grid">
  <div class="dash-card">
    <div class="dash-left">
      <div class="dash-icon accent"><?= icon('grid',22) ?></div>
      <div class="dash-info">
        <div class="dash-value"><?= $pCount ?></div>
        <div class="dash-label">Total Products</div>
      </div>
    </div>
  </div>
  <div class="dash-card">
    <div class="dash-left">
      <div class="dash-icon success"><?= icon('verified',22) ?></div>
      <div class="dash-info">
        <div class="dash-value"><?= $inStock ?></div>
        <div class="dash-label">In Stock</div>
      </div>
    </div>
  </div>
  <?php if (adminCan('users.view')): ?>
  <div class="dash-card">
    <div class="dash-left">
      <div class="dash-icon accent"><?= icon('users',22) ?></div>
      <div class="dash-info">
        <div class="dash-value"><?= $uCount ?></div>
        <div class="dash-label">Users</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if (adminCan('products.view')): ?>
<!-- Product Health -->
<div style="margin-bottom:20px;">
  <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:12px;">Product Health</p>
  <div class="dashboard-grid">

    <div class="dash-card" style="border-color:<?= $noImage>0?'var(--gold)':'var(--border)' ?>;">
      <div class="dash-left">
        <div class="dash-icon" style="background:<?= $noImage>0?'var(--gold-bg)':'var(--surface2)' ?>;color:<?= $noImage>0?'var(--gold)':'var(--text3)' ?>;">
          <?= icon('image',22) ?>
        </div>
        <div class="dash-info">
          <div class="dash-value" style="color:<?= $noImage>0?'var(--gold)':'var(--text)' ?>;"><?= $noImage ?></div>
          <div class="dash-label">Without Images</div>
        </div>
      </div>
      <?php if ($noImage > 0): ?>
      <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
        <a href="index.php?page=products&filter=no_image"
           style="font-size:11px;font-weight:600;color:var(--gold);text-decoration:none;display:flex;align-items:center;gap:4px;">
          <?= icon('forward',11) ?> View products
        </a>
      </div>
      <?php endif; ?>
    </div>

    <div class="dash-card" style="border-color:<?= $noMeasurement>0?'var(--gold)':'var(--border)' ?>;">
      <div class="dash-left">
        <div class="dash-icon" style="background:<?= $noMeasurement>0?'var(--gold-bg)':'var(--surface2)' ?>;color:<?= $noMeasurement>0?'var(--gold)':'var(--text3)' ?>;">
          <?= icon('file',22) ?>
        </div>
        <div class="dash-info">
          <div class="dash-value" style="color:<?= $noMeasurement>0?'var(--gold)':'var(--text)' ?>;"><?= $noMeasurement ?></div>
          <div class="dash-label">No Measurement Sheet</div>
        </div>
      </div>
      <?php if ($noMeasurement > 0): ?>
      <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
        <a href="index.php?page=products&filter=no_measurement"
           style="font-size:11px;font-weight:600;color:var(--gold);text-decoration:none;display:flex;align-items:center;gap:4px;">
          <?= icon('forward',11) ?> View products
        </a>
      </div>
      <?php endif; ?>
    </div>

    <div class="dash-card" style="border-color:<?= $noDNA>0?'var(--gold)':'var(--border)' ?>;">
      <div class="dash-left">
        <div class="dash-icon" style="background:<?= $noDNA>0?'var(--gold-bg)':'var(--surface2)' ?>;color:<?= $noDNA>0?'var(--gold)':'var(--text3)' ?>;">
          <?= icon('pdf',22) ?>
        </div>
        <div class="dash-info">
          <div class="dash-value" style="color:<?= $noDNA>0?'var(--gold)':'var(--text)' ?>;"><?= $noDNA ?></div>
          <div class="dash-label">No DNA Report</div>
        </div>
      </div>
      <?php if ($noDNA > 0): ?>
      <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
        <a href="index.php?page=products&filter=no_dna"
           style="font-size:11px;font-weight:600;color:var(--gold);text-decoration:none;display:flex;align-items:center;gap:4px;">
          <?= icon('forward',11) ?> View products
        </a>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Recent Products -->
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <p style="font-weight:700;font-size:14px;">Recent Products</p>
    <a href="index.php?page=products" style="font-size:12px;color:var(--accent);">View all</a>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Quarry #</th><th>Stock</th></tr></thead>
      <tbody>
        <?php if (empty($recProd)): ?>
        <tr><td colspan="3" style="text-align:center;color:var(--text3);padding:20px;">No products</td></tr>
        <?php else: foreach ($recProd as $p): ?>
        <tr>
          <td>
            <?php if (adminCan('products.edit')): ?>
            <a href="index.php?page=product_edit&id=<?= $p['id'] ?>" style="color:var(--accent);font-weight:500;"><?= h($p['name']) ?></a>
            <?php else: ?>
            <span style="font-weight:500;"><?= h($p['name']) ?></span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text3);font-size:12px;"><?= h($p['quarry_number']) ?></td>
          <td>
            <?php if (!$p['in_stock'] || (float)$p['quantity_available'] <= 0): ?>
              <span class="badge badge-gray">Out of Stock</span>
            <?php else: ?>
              <span class="badge badge-green">In Stock</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>