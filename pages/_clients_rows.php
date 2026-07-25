<?php
/**
 * pages/_clients_rows.php — Partial: client cards + pagination
 * Expects: $clients, $total, $totalPages, $currentPage, $search, $perPage
 */
if (!isset($clients)) {
    require_once BASE_PATH . '/includes/clients.php';
    $perPage     = 10;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $search      = trim($_GET['q'] ?? '');
    $result      = getClients($_SESSION['user_id'], [
        'search' => $search, 'limit' => $perPage, 'offset' => ($currentPage - 1) * $perPage
    ]);
    $clients    = $result['rows'];
    $total      = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));
}
?>

<?php if (empty($clients) && $currentPage === 1): ?>
<div class="empty-state" style="padding-top:50px;">
  <div class="empty-icon"><?= icon('users', 28) ?></div>
  <p class="empty-title">No clients yet</p>
  <p class="empty-sub">
    <?= $search ? 'No clients match your search.' : 'Add your first client to start managing product selections.' ?>
  </p>
  <?php if (!$search): ?>
  <a href="index.php?page=client_form" class="btn btn-primary" style="margin-top:20px;text-decoration:none;">
    <?= icon('plus', 14) ?>&nbsp; Add First Client
  </a>
  <?php endif; ?>
</div>

<?php else: ?>

<!-- Client cards grid -->
<div class="client-cards-grid">
  <?php foreach ($clients as $i => $c): ?>
  <div class="client-card fade-up" style="animation-delay:<?= round($i * .04, 3) ?>s">
    <!-- Card header -->
    <div class="client-card-top">
      <div class="client-avatar"><?= strtoupper(mb_substr($c['client_name'], 0, 1)) ?></div>
      <div class="client-card-info">
        <p class="client-card-name"><?= h($c['client_name']) ?></p>
        <p class="client-card-mobile">
          <a href="tel:<?= h($c['client_mobile']) ?>" style="color:inherit;text-decoration:none;">
            <?= icon('phone', 11) ?>&nbsp;<?= h($c['client_mobile']) ?>
          </a>
        </p>
      </div>
      <span class="badge badge-black" style="flex-shrink:0;"><?= $c['selection_count'] ?> items</span>
    </div>

    <!-- Mason -->
    <?php if ($c['mansoner_name']): ?>
    <div class="client-card-mason">
      <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text4);">Mason</span>
      <span style="font-size:12px;color:var(--text2);font-weight:500;"><?= h($c['mansoner_name']) ?></span>
      <?php if ($c['mansoner_mobile']): ?>
      <a href="tel:<?= h($c['mansoner_mobile']) ?>" style="font-size:12px;color:var(--text3);"><?= h($c['mansoner_mobile']) ?></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Address -->
    <?php if ($c['site_address']): ?>
    <div class="client-card-addr">
      <?= icon('verified', 11) ?>
      <span><?= h(mb_strimwidth($c['site_address'], 0, 80, '…')) ?></span>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="client-card-actions">
      <a href="index.php?page=client_selections&client_id=<?= $c['id'] ?>"
         class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">
        <?= icon('grid', 13) ?>&nbsp; Selections
      </a>
      <a href="index.php?page=client_form&id=<?= $c['id'] ?>"
         class="btn btn-secondary btn-sm btn-icon" title="Edit">
        <?= icon('edit', 14) ?>
      </a>
      <button class="btn btn-danger btn-sm btn-icon client-delete-btn"
              data-id="<?= $c['id'] ?>" data-name="<?= h($c['client_name']) ?>"
              title="Delete">
        <?= icon('trash', 14) ?>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>


<!-- Pagination mount point — filled by pagination.js -->
 <div class="pagination" id="paginationWrap"></div>
 <?php if ($totalPages > 1): ?>
<p style="text-align:center;font-size:12px;color:var(--text4);margin-top:10px;margin-bottom:20px;">
   Showing <?= (($currentPage - 1) * $perPage) + 1 ?>–<?= min($currentPage * $perPage, $total) ?> of <?= $total ?>
 </p>
 <?php endif; ?>

<?php endif; ?>

<!-- Delete confirm modal -->
<div id="deleteClientModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius-xl);padding:28px 24px;max-width:380px;width:90%;box-shadow:var(--shadow-xl);">
    <div style="width:52px;height:52px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
      <?= icon('trash', 22) ?>
    </div>
    <p style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:8px;">Delete Client?</p>
    <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:22px;" id="deleteClientMsg">This will also delete all product selections for this client.</p>
    <div style="display:flex;gap:10px;">
      <button id="deleteClientCancel" class="btn btn-secondary btn-block">Cancel</button>
      <form method="POST" action="index.php" style="flex:1">
        <input type="hidden" name="action"    value="delete_client"/>
        <input type="hidden" name="client_id" id="deleteClientId" value=""/>
        <?= csrfField() ?>
        <button type="submit" class="btn btn-danger btn-block">Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const modal    = document.getElementById('deleteClientModal');
  const msgEl    = document.getElementById('deleteClientMsg');
  const inputEl  = document.getElementById('deleteClientId');
  const cancelBtn = document.getElementById('deleteClientCancel');

  document.querySelectorAll('.client-delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const name = btn.dataset.name;
      inputEl.value = btn.dataset.id;
      msgEl.textContent = 'Delete "' + name + '"? This will also remove all their product selections.';
      modal.style.display = 'flex';
    });
  });

  if (cancelBtn) cancelBtn.addEventListener('click', () => { modal.style.display = 'none'; });
  modal && modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
})();
</script>