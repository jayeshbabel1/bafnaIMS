<?php
/**
 * admin/views/user_clients.php — Admin: view clients of a specific user
 */
$adminTitle = 'User Clients';
include __DIR__ . '/../_layout_top.php';
require_once BASE_PATH . '/includes/clients.php';

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) redirect('index.php?page=users');

$db       = getDB();
$userSt   = $db->prepare("SELECT * FROM users WHERE id=?");
$userSt->execute([$userId]);
$pageUser = $userSt->fetch();
if (!$pageUser) { flash('error', 'User not found.'); redirect('index.php?page=users'); }

$search      = trim($_GET['q'] ?? '');
$perPage     = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));

$result  = adminGetClients($userId, [
    'search' => $search,
    'limit'  => $perPage,
    'offset' => ($currentPage - 1) * $perPage,
]);
$clients    = $result['rows'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
  <a href="index.php?page=users" class="btn-admin-secondary btn-admin-sm"><?= icon('back', 14) ?> Back to Users</a>
  <div>
    <p style="font-size:13px;color:var(--text3);">Clients of</p>
    <p style="font-size:16px;font-weight:700;color:var(--text);"><?= h($pageUser['name']) ?> <span style="font-size:13px;color:var(--text3);">&lt;<?= h($pageUser['firm']) ?>&gt;</span></p>
  </div>
  <span class="badge badge-blue" style="margin-left:auto;"><?= $total ?> client<?= $total !== 1 ? 's' : '' ?></span>
</div>

<!-- Search -->
<div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
  <form method="GET" action="index.php" style="display:flex;gap:8px;flex:1;">
    <input type="hidden" name="page"    value="user_clients"/>
    <input type="hidden" name="user_id" value="<?= $userId ?>"/>
    <input type="text" name="q" class="admin-input" placeholder="Search client name / mobile / mason…"
           value="<?= h($search) ?>" style="max-width:320px;"/>
    <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= icon('search', 14) ?></button>
    <?php if ($search): ?><a href="index.php?page=user_clients&user_id=<?= $userId ?>" class="btn-admin-secondary btn-admin-sm">Clear</a><?php endif; ?>
  </form>
</div>

<!-- Table -->
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Client</th>
        <th>Mobile</th>
        <th>Mason</th>
        <th>Mason Mobile</th>
        <th>Site Address</th>
        <th>Selections</th>
        <th>Added</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($clients)): ?>
      <tr><td colspan="8" class="admin-table-empty">No clients found.</td></tr>
      <?php else: foreach ($clients as $c): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-mid));color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">
              <?= strtoupper(mb_substr($c['client_name'], 0, 1)) ?>
            </div>
            <span style="font-weight:600;"><?= h($c['client_name']) ?></span>
          </div>
        </td>
        <td style="font-size:12px;"><?= h($c['client_mobile']) ?></td>
        <td style="font-size:12px;color:var(--text2);"><?= h($c['mansoner_name'] ?: '—') ?></td>
        <td style="font-size:12px;color:var(--text3);"><?= h($c['mansoner_mobile'] ?: '—') ?></td>
        <td style="font-size:11px;color:var(--text3);max-width:180px;">
          <?= $c['site_address'] ? h(mb_strimwidth($c['site_address'], 0, 60, '…')) : '—' ?>
        </td>
        <td>
          <a href="index.php?page=admin_selections&client_id=<?= $c['id'] ?>"
             class="badge badge-blue" style="text-decoration:none;cursor:pointer;">
            <?= $c['selection_count'] ?> items
          </a>
        </td>
        <td style="font-size:11px;color:var(--text3);"><?= date('d M Y', $c['created_at']) ?></td>
        <td>
          <div style="display:flex;gap:6px;">
            <a href="index.php?page=admin_selections&client_id=<?= $c['id'] ?>"
               class="btn-admin-secondary btn-admin-sm btn-admin-labeled"><?= icon('grid', 13) ?> Selections</a>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;margin-top:14px;">
  <?php
  $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range);
  $base  = 'index.php?page=user_clients&user_id='.$userId.($search?'&q='.urlencode($search):'');
  ?>
  <?php if ($currentPage > 1): ?><a href="<?= $base ?>&p=<?= $currentPage-1 ?>" class="apag-btn">&lsaquo;</a><?php endif; ?>
  <?php if ($s > 1): ?><a href="<?= $base ?>&p=1" class="apag-btn">1</a><?php if ($s>2): ?><span class="apag-ellipsis">…</span><?php endif; endif; ?>
  <?php for ($pi=$s;$pi<=$e;$pi++): ?>
  <a href="<?= $base ?>&p=<?= $pi ?>" class="apag-btn <?= $pi===$currentPage?'active':'' ?>"><?= $pi ?></a>
  <?php endfor; ?>
  <?php if ($e<$totalPages): ?><?php if ($e<$totalPages-1): ?><span class="apag-ellipsis">…</span><?php endif; ?><a href="<?= $base ?>&p=<?= $totalPages ?>" class="apag-btn"><?= $totalPages ?></a><?php endif; ?>
  <?php if ($currentPage<$totalPages): ?><a href="<?= $base ?>&p=<?= $currentPage+1 ?>" class="apag-btn">&rsaquo;</a><?php endif; ?>
</div>
<p style="font-size:12px;color:var(--text3);margin-top:8px;"><?= $total ?> clients · page <?= $currentPage ?> of <?= $totalPages ?></p>
<?php endif; ?>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>