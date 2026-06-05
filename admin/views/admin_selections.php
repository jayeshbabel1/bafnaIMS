<?php
/**
 * admin/views/admin_selections.php — Admin: view all product selections for a client
 */
$adminTitle = 'Client Selections';
include __DIR__ . '/../_layout_top.php';
require_once BASE_PATH . '/includes/clients.php';

$clientId = (int)($_GET['client_id'] ?? 0);
if (!$clientId) redirect('index.php?page=users');

$client = getClientById($clientId);
if (!$client) { flash('error', 'Client not found.'); redirect('index.php?page=users'); }

// Get the user who owns this client
$db       = getDB();
$ownerSt  = $db->prepare("SELECT * FROM users WHERE id=?");
$ownerSt->execute([$client['user_id']]);
$owner    = $ownerSt->fetch();

$search      = trim($_GET['q'] ?? '');
$perPage     = 15;
$currentPage = max(1, (int)($_GET['p'] ?? 1));

$result = adminGetSelections($clientId, [
    'search' => $search,
    'limit'  => $perPage,
    'offset' => ($currentPage - 1) * $perPage,
]);
$selections = $result['rows'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
?>

<!-- Breadcrumb -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-size:12px;color:var(--text3);flex-wrap:wrap;">
  <a href="index.php?page=users" style="color:var(--accent);">Users</a>
  <span>›</span>
  <?php if ($owner): ?>
  <a href="index.php?page=user_clients&user_id=<?= $owner['id'] ?>" style="color:var(--accent);"><?= h($owner['name']) ?></a>
  <span>›</span>
  <?php endif; ?>
  <span><?= h($client['client_name']) ?></span>
</div>

<!-- Client card -->
<div class="admin-form-section" style="margin-bottom:20px;">
  <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;">
      <p class="admin-form-section-title" style="border-bottom:none;padding-bottom:0;margin-bottom:8px;">
        <?= h($client['client_name']) ?>
      </p>
      <p style="font-size:13px;color:var(--text2);display:flex;align-items:center;gap:6px;">
        <?= icon('phone', 13) ?> <?= h($client['client_mobile']) ?>
      </p>
    </div>
    <?php if ($client['mansoner_name']): ?>
    <div style="flex:1;min-width:160px;">
      <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);margin-bottom:4px;">Mason</p>
      <p style="font-size:13px;font-weight:600;"><?= h($client['mansoner_name']) ?></p>
      <p style="font-size:12px;color:var(--text3);"><?= h($client['mansoner_mobile'] ?: '—') ?></p>
    </div>
    <?php endif; ?>
    <?php if ($client['site_address']): ?>
    <div style="flex:2;min-width:200px;">
      <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text3);margin-bottom:4px;">Site Address</p>
      <p style="font-size:12px;color:var(--text2);line-height:1.5;"><?= h($client['site_address']) ?></p>
    </div>
    <?php endif; ?>
    <div>
      <span class="badge badge-blue" style="font-size:11px;"><?= $total ?> product<?= $total !== 1 ? 's' : '' ?> selected</span>
    </div>
  </div>
</div>

<!-- Search -->
<div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
  <form method="GET" action="index.php" style="display:flex;gap:8px;flex:1;">
    <input type="hidden" name="page"      value="admin_selections"/>
    <input type="hidden" name="client_id" value="<?= $clientId ?>"/>
    <input type="text" name="q" class="admin-input" placeholder="Search product name / lot number…"
           value="<?= h($search) ?>" style="max-width:320px;"/>
    <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= icon('search', 14) ?></button>
    <?php if ($search): ?><a href="index.php?page=admin_selections&client_id=<?= $clientId ?>" class="btn-admin-secondary btn-admin-sm">Clear</a><?php endif; ?>
  </form>
</div>

<!-- Selections table -->
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Photo</th>
        <th>Product</th>
        <th>Quarry #</th>
        <th>Thickness</th>
        <th>Useable Size</th>
        <th>Italian Size</th>
        <th>Available Qty</th>
        <th>Required Qty</th>
        <th>Area</th>
        <th>Notes</th>
        <th>Added</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($selections)): ?>
      <tr><td colspan="11" class="admin-table-empty">No selections found.</td></tr>
      <?php else: foreach ($selections as $sel):
        $pal  = json_decode($sel['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
        $slab = formatDimension($sel['sizes_l'] ?? '', $sel['sizes_h'] ?? '');
        $cut  = formatDimension($sel['cutter_size_l'] ?? '', $sel['cutter_size_h'] ?? '');
      ?>
      <tr>
        <td>
          <div class="tbl-thumb">
            <?php if ($sel['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$sel['primary_photo'])): ?>
            <img src="../assets/uploads/photos/<?= h($sel['primary_photo']) ?>" alt=""/>
            <?php else: ?>
            <?= marbleSVG($pal, 40, 40, 'ast'.$sel['id']) ?>
            <?php endif; ?>
          </div>
        </td>
        <td>
          <a href="../index.php?page=product&id=<?= $sel['product_id'] ?>" target="_blank"
             style="font-weight:600;font-size:13px;color:var(--accent);">
            <?= h($sel['product_name']) ?>
          </a>
          <span class="badge badge-blue" style="font-size:9px;display:block;margin-top:3px;width:fit-content;"><?= h($sel['category']) ?></span>
        </td>
        <td style="font-size:12px;color:var(--text3);"><?= h($sel['quarry_number']) ?></td>
        <td style="font-size:12px;"><?= h($sel['thickness'] ?: '—') ?></td>
        <td style="font-size:12px;"><?= $slab ? h($slab) : '—' ?></td>
        <td style="font-size:12px;"><?= $cut ? h($cut) : '—' ?></td>
        <td style="font-size:13px;font-weight:600;color:var(--success);"><?= number_format((float)$sel['quantity_available']) ?> sqft</td>
        <td style="font-size:13px;font-weight:600;"><?= $sel['quantity_required'] > 0 ? number_format((float)$sel['quantity_required'], 0).' sqft' : '—' ?></td>
        <td style="font-size:12px;"><?= h($sel['selection_area'] ?: '—') ?></td>
        <td style="font-size:11px;color:var(--text3);max-width:140px;"><?= $sel['extra_notes'] ? h(mb_strimwidth($sel['extra_notes'], 0, 50, '…')) : '—' ?></td>
        <td style="font-size:11px;color:var(--text3);white-space:nowrap;"><?= date('d M Y', $sel['created_at']) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<p style="font-size:12px;color:var(--text3);margin-top:8px;"><?= $total ?> selections</p>

<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;margin-top:12px;">
  <?php
  $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range);
  $base  = 'index.php?page=admin_selections&client_id='.$clientId.($search?'&q='.urlencode($search):'');
  ?>
  <?php if ($currentPage > 1): ?><a href="<?= $base ?>&p=<?= $currentPage-1 ?>" class="apag-btn">&lsaquo;</a><?php endif; ?>
  <?php for ($pi=$s;$pi<=$e;$pi++): ?>
  <a href="<?= $base ?>&p=<?= $pi ?>" class="apag-btn <?= $pi===$currentPage?'active':'' ?>"><?= $pi ?></a>
  <?php endfor; ?>
  <?php if ($currentPage<$totalPages): ?><a href="<?= $base ?>&p=<?= $currentPage+1 ?>" class="apag-btn">&rsaquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>