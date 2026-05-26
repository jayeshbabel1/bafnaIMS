<?php
$adminTitle = 'Dashboard';
include __DIR__ . '/../_layout_top.php';
$db = getDB();
$pCount = $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'];
$uCount = $db->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
$iCount = $db->query("SELECT COUNT(*) as c FROM inquiries")->fetch()['c'];
$iPend  = $db->query("SELECT COUNT(*) as c FROM inquiries WHERE status='pending'")->fetch()['c'];
$inStock= $db->query("SELECT COUNT(*) as c FROM products WHERE in_stock=1")->fetch()['c'];
$recInq = $db->query("SELECT i.*,u.name as uname,p.name as pname FROM inquiries i JOIN users u ON i.user_id=u.id JOIN products p ON i.product_id=p.id ORDER BY i.created_at DESC LIMIT 5")->fetchAll();
$recProd= $db->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--accent-light);color:var(--accent);"><?= icon('grid',22) ?></div>
    <div class="stat-val"><?= $pCount ?></div>
    <div class="stat-label">Total Products</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--success-bg);color:var(--success);"><?= icon('verified',22) ?></div>
    <div class="stat-val"><?= $inStock ?></div>
    <div class="stat-label">In Stock</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:var(--accent-light);color:var(--accent);"><?= icon('users',22) ?></div>
    <div class="stat-val"><?= $uCount ?></div>
    <div class="stat-label">Registered Users</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $iPend ? 'var(--gold-bg)' : 'var(--surface2)' ?>;color:<?= $iPend ? 'var(--gold)' : 'var(--text3)' ?>;"><?= icon('msg',22) ?></div>
    <div class="stat-val"><?= $iCount ?></div>
    <div class="stat-label">Total Inquiries <span class="badge badge-gold" style="font-size:10px;"><?= $iPend ?> pending</span></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <!-- Recent Inquiries -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <p style="font-weight:700;font-size:14px;">Recent Inquiries</p>
      <a href="index.php?page=inquiries" style="font-size:12px;color:var(--accent);">View all</a>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>User</th><th>Product</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
          <?php if (empty($recInq)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:20px;">No inquiries yet</td></tr>
          <?php else: foreach ($recInq as $inq):
            $sc = ['pending'=>'badge-gray','replied'=>'badge-green'][$inq['status']]??'badge-gray'; ?>
          <tr>
            <td style="font-weight:500;"><?= h($inq['uname']) ?></td>
            <td style="color:var(--text2);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($inq['pname']) ?></td>
            <td><span class="badge <?= $sc ?>"><?= ucfirst($inq['status']) ?></span></td>
            <td style="color:var(--text3);font-size:11px;"><?= date('d M', $inq['created_at']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
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
            <td><a href="index.php?page=product_edit&id=<?= $p['id'] ?>" style="color:var(--accent);font-weight:500;"><?= h($p['name']) ?></a></td>
            <td style="color:var(--text3);font-size:12px;"><?= h($p['quarry_number']) ?></td>
            <td><?= $p['in_stock'] ? '<span class="badge badge-green">In Stock</span>' : '<span class="badge badge-gray">Out</span>' ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>
