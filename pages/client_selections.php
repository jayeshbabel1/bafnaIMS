<?php
/**
 * pages/client_selections.php — View & manage product selections for a client
 */
$pageTitle = 'Selections — ' . APP_NAME;
$showNav   = true;

require_once BASE_PATH . '/includes/clients.php';

$clientId = (int)($_GET['client_id'] ?? 0);
if (!$clientId) redirect('index.php?page=clients');

$client = getClient($clientId, $_SESSION['user_id']);
if (!$client) { flash('error', 'Client not found.'); redirect('index.php?page=clients'); }

$pageTitle = h($client['client_name']) . ' — Selections';

$perPage     = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$search      = trim($_GET['q'] ?? '');
$isAjax      = !empty($_GET['ajax']);

$result = getSelections($clientId, $_SESSION['user_id'], [
    'search' => $search,
    'limit'  => $perPage,
    'offset' => ($currentPage - 1) * $perPage,
]);
$selections = $result['rows'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

if ($isAjax) {
    ob_start();
    include BASE_PATH . '/pages/_selection_rows.php';
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'total' => $total, 'pages' => $totalPages, 'current' => $currentPage]);
    exit;
}
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">

  <!-- Back nav -->
  <div style="display:flex;align-items:center;gap:12px;padding-top:20px;margin-bottom:20px;">
    <a href="index.php?page=clients" class="hero-icon-btn" style="flex-shrink:0;"><?= icon('back', 18) ?></a>
    <div style="flex:1;min-width:0;">
      <p class="page-eyebrow">Client Selections</p>
      <h1 class="page-title" style="font-size:22px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        <?= h($client['client_name']) ?>
      </h1>
    </div>
    <a href="index.php?page=client_form&id=<?= $clientId ?>"
       class="btn btn-secondary btn-sm"><?= icon('edit', 13) ?>&nbsp;Edit</a>
  </div>

  <!-- Client info card -->
  <div class="card" style="padding:16px 20px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">
    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:200px;">
      <div class="client-avatar" style="width:48px;height:48px;font-size:18px;flex-shrink:0;">
        <?= strtoupper(mb_substr($client['client_name'], 0, 1)) ?>
      </div>
      <div>
        <p style="font-weight:700;font-size:15px;"><?= h($client['client_name']) ?></p>
        <a href="tel:<?= h($client['client_mobile']) ?>" style="font-size:13px;color:var(--text3);display:flex;align-items:center;gap:4px;">
          <?= icon('phone', 12) ?> <?= h($client['client_mobile']) ?>
        </a>
      </div>
    </div>
    <?php if ($client['mansoner_name']): ?>
    <div style="flex:1;min-width:160px;">
      <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text4);margin-bottom:3px;">Mason</p>
      <p style="font-size:13px;font-weight:600;"><?= h($client['mansoner_name']) ?></p>
      <?php if ($client['mansoner_mobile']): ?>
      <p style="font-size:12px;color:var(--text3);"><?= h($client['mansoner_mobile']) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($client['site_address']): ?>
    <div style="flex:2;min-width:200px;">
      <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text4);margin-bottom:3px;">Site Address</p>
      <p style="font-size:12px;color:var(--text2);line-height:1.5;"><?= h($client['site_address']) ?></p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Search + Add -->
  <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
    <div class="catalog-search-wrap" style="flex:1;min-width:200px;margin-bottom:0;">
      <span class="catalog-search-icon"><?= icon('search', 16) ?></span>
      <input type="search" id="selSearch" class="catalog-search-input"
             placeholder="Search product name or lot number…"
             value="<?= h($search) ?>" autocomplete="off"/>
    </div>
    <a href="index.php?page=catalog" class="btn btn-primary btn-sm" style="flex-shrink:0;">
      <?= icon('plus', 14) ?>&nbsp; Add Products
    </a>
  </div>

  <!-- Count -->
  <div id="selCount" style="font-size:13px;color:var(--text3);margin-bottom:14px;">
    <strong style="font-size:18px;color:var(--text);"><?= $total ?></strong> products selected
  </div>

  <!-- Ajax loader -->
  <div id="selLoader" class="ajax-loader"><div class="loader-spinner"></div></div>

  <!-- Content -->
  <div id="selContent">
    <?php include BASE_PATH . '/pages/_selection_rows.php'; ?>
  </div>

</div>

<!-- Edit Selection Modal -->
<div id="editSelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--white);border-radius:var(--radius-xl);width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-xl);">
    <div style="padding:20px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <p style="font-size:16px;font-weight:700;">Edit Selection</p>
      <button id="editSelClose" style="color:var(--text3);cursor:pointer;"><?= icon('close', 18) ?></button>
    </div>
    <div style="padding:22px;">
      <form method="POST" action="index.php" id="editSelForm">
        <input type="hidden" name="action"       value="update_selection"/>
        <input type="hidden" name="selection_id" id="editSelId"/>
        <input type="hidden" name="client_id"    value="<?= $clientId ?>"/>
        <div class="input-group">
          <label class="input-label">Selection Area / Room</label>
          <input type="text" name="selection_area" id="editSelArea" class="input-field" placeholder="e.g. Master Bedroom"/>
        </div>
        <div class="input-group">
          <label class="input-label">Quantity Required (sqft)</label>
          <input type="number" name="quantity_required" id="editSelQty" class="input-field" min="0" step="0.01" placeholder="0.00"/>
        </div>
        <div class="input-group">
          <label class="input-label">Notes</label>
          <textarea name="extra_notes" id="editSelNotes" class="input-field" rows="3" placeholder="Any special requirements…"></textarea>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary" style="flex:1;"><?= icon('check', 14) ?>&nbsp; Save</button>
          <button type="button" id="editSelCancelBtn" class="btn btn-secondary">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const content  = document.getElementById('selContent');
  const loader   = document.getElementById('selLoader');
  const countEl  = document.getElementById('selCount');
  const searchEl = document.getElementById('selSearch');
  const modal    = document.getElementById('editSelModal');
  const clientId = <?= $clientId ?>;

  let state = { q: <?= json_encode($search) ?>, page: 1 };
  let timer = null;

  async function load(page, push) {
    state.page = page;
    if (loader) loader.style.display = 'flex';
    const params = new URLSearchParams({ page: 'client_selections', ajax: '1', client_id: clientId, p: page });
    if (state.q) params.set('q', state.q);
    try {
      const r = await fetch('index.php?' + params);
      const d = await r.json();
      content.innerHTML = d.html;
      if (countEl) countEl.innerHTML = '<strong style="font-size:18px;color:var(--text);">' + d.total + '</strong> products selected';
      bindButtons();
    } finally {
      if (loader) loader.style.display = 'none';
    }
  }

  function bindButtons() {
    // Pagination
    content.querySelectorAll('.pag-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
        load(parseInt(btn.dataset.page));
      });
    });
    // Edit buttons
    content.querySelectorAll('.sel-edit-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('editSelId').value    = btn.dataset.id;
        document.getElementById('editSelArea').value  = btn.dataset.area  || '';
        document.getElementById('editSelQty').value   = btn.dataset.qty   || '';
        document.getElementById('editSelNotes').value = btn.dataset.notes || '';
        modal.style.display = 'flex';
      });
    });
    // Delete buttons
    content.querySelectorAll('.sel-delete-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (!confirm('Remove "' + btn.dataset.name + '" from this selection?')) return;
        const form = document.createElement('form');
        form.method = 'POST'; form.action = 'index.php';
        form.innerHTML = '<input type="hidden" name="action" value="delete_selection"/>' +
          '<input type="hidden" name="selection_id" value="' + btn.dataset.id + '"/>' +
          '<input type="hidden" name="client_id" value="' + clientId + '"/>';
        document.body.appendChild(form); form.submit();
      });
    });
  }

  // Search
  if (searchEl) {
    searchEl.addEventListener('input', () => {
      const v = searchEl.value.trim();
      clearTimeout(timer);
      if (v.length > 0 && v.length < 3) return;
      timer = setTimeout(() => { state.q = v; load(1); }, 350);
    });
  }

  // Modal close
  document.getElementById('editSelClose')?.addEventListener('click',     () => { modal.style.display = 'none'; });
  document.getElementById('editSelCancelBtn')?.addEventListener('click', () => { modal.style.display = 'none'; });
  modal?.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

  // Handle edit form submit via AJAX
  document.getElementById('editSelForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const body = new FormData(this);
    const r    = await fetch('index.php', { method: 'POST', body });
    modal.style.display = 'none';
    load(state.page, false);
  });

  bindButtons();
})();
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>