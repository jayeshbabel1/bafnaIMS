<?php
/**
 * admin/views/admin_client_selections.php — Admin: manage product selections
 * for any client. Mirrors the user-side pages/client_selections.php flow,
 * but works for any client regardless of owner, and includes a built-in
 * product search/picker (no logged-in "user" session required).
 */
require_once BASE_PATH . '/includes/clients.php';

$clientId = (int)($_GET['client_id'] ?? 0);
if (!$clientId) redirect('index.php?page=admin_clients');

$client = adminGetClientWithOwner($clientId);
if (!$client) { flash('error', 'Client not found.'); redirect('index.php?page=admin_clients'); }

if (!empty($_GET['ajax']) || !empty($_GET['ajax_product_search'])) {
    requireAdminPermissionJson('clients.view');
} else {
    requireAdminPermission('clients.view');
}

// ── AJAX: selection rows (search + pagination) ──────────────────────────────
if (!empty($_GET['ajax'])) {
    $perPage     = 10;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $search      = trim($_GET['q'] ?? '');

    $result = adminGetSelections($clientId, [
        'search' => $search,
        'limit'  => $perPage,
        'offset' => ($currentPage - 1) * $perPage,
    ]);
    $selections = $result['rows'];
    $total      = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));

    ob_start();
    include __DIR__ . '/_admin_selection_rows.php';
    $html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'total' => $total, 'pages' => $totalPages, 'current' => $currentPage]);
    exit;
}

// ── AJAX: product search for the "Add Product" picker ───────────────────────
if (!empty($_GET['ajax_product_search'])) {
    $q = trim($_GET['q'] ?? '');
    header('Content-Type: application/json');
    echo json_encode(['products' => adminSearchProducts($q, 20)]);
    exit;
}

$adminTitle = 'Selections — ' . $client['client_name'];
include __DIR__ . '/../_layout_top.php';

$perPage     = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$search      = trim($_GET['q'] ?? '');

$result = adminGetSelections($clientId, [
    'search' => $search,
    'limit'  => $perPage,
    'offset' => ($currentPage - 1) * $perPage,
]);
$selections = $result['rows'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
?>

<style>
.acs-client-card { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start; }
.acs-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
.acs-search-wrap { position:relative; flex:1; min-width:200px; }
.acs-search-wrap > svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--admin-text3,var(--text3)); pointer-events:none; }
.acs-search-wrap input { padding-left:34px !important; }
#acsLoader { display:none; position:absolute; inset:0; background:rgba(255,255,255,.65); backdrop-filter:blur(2px); align-items:center; justify-content:center; z-index:50; border-radius:var(--admin-card-radius,var(--card-radius)); }
#acsTableWrap { position:relative; }

/* Product picker modal */
#acsAddProductModal, #acsEditSelModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9200; align-items:center; justify-content:center; padding:16px; }
.acs-modal-card { background:var(--admin-card-bg,var(--surface)); border-radius:14px; width:100%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 16px 48px rgba(0,0,0,.2); }
.acs-modal-header { padding:18px 20px; border-bottom:1px solid var(--admin-table-border,var(--border)); display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:var(--admin-card-bg,var(--surface)); z-index:2; }
.acs-modal-body { padding:20px; }
.acs-product-pick-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; cursor:pointer; border:1.5px solid var(--admin-table-border,var(--border)); margin-bottom:8px; transition:border-color .15s,background .15s; }
.acs-product-pick-item:hover { border-color:var(--admin-accent,var(--accent)); background:var(--admin-accent-light,var(--accent-light)); }
.acs-product-pick-item.selected { border-color:var(--admin-accent,var(--accent)); background:var(--admin-accent-light,var(--accent-light)); }
.acs-pick-thumb { width:42px; height:42px; border-radius:8px; overflow:hidden; flex-shrink:0; background:var(--admin-surface2,var(--surface2)); }
.acs-pick-thumb img { width:100%; height:100%; object-fit:cover; }
@media (max-width:768px) {
  .acs-toolbar { flex-direction:column; align-items:stretch; }
}
</style>

<!-- Back nav -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
  <a href="index.php?page=admin_clients" class="btn-admin-secondary btn-admin-sm"><?= icon('back', 14) ?> Back to Clients</a>
  <a href="index.php?page=admin_client_form&id=<?= $clientId ?>" class="btn-admin-secondary btn-admin-sm"><?= icon('edit', 13) ?> Edit Client</a>
</div>

<!-- Client info card -->
<div class="admin-form-section">
  <div class="acs-client-card">
    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:220px;">
      <div class="client-avatar" style="width:48px;height:48px;font-size:18px;flex-shrink:0;">
        <?= strtoupper(mb_substr($client['client_name'], 0, 1)) ?>
      </div>
      <div>
        <p style="font-weight:700;font-size:15px;color:var(--admin-text,var(--text));"><?= h($client['client_name']) ?></p>
        <p style="font-size:13px;color:var(--admin-text3,var(--text3));"><?= h($client['client_mobile']) ?></p>
      </div>
    </div>
    <?php if ($client['mansoner_name']): ?>
    <div style="flex:1;min-width:160px;">
      <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:3px;">Mason</p>
      <p style="font-size:13px;font-weight:600;color:var(--admin-text,var(--text));"><?= h($client['mansoner_name']) ?></p>
      <?php if ($client['mansoner_mobile']): ?>
      <p style="font-size:12px;color:var(--admin-text3,var(--text3));"><?= h($client['mansoner_mobile']) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($client['site_address']): ?>
    <div style="flex:2;min-width:200px;">
      <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:3px;">Site Address</p>
      <p style="font-size:12px;color:var(--admin-text2,var(--text2));line-height:1.5;"><?= h($client['site_address']) ?></p>
    </div>
    <?php endif; ?>
    <div style="flex:1;min-width:160px;">
      <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:3px;">Belongs To</p>
      <p style="font-size:13px;font-weight:600;color:var(--admin-text,var(--text));"><?= h($client['owner_name']) ?></p>
      <?php if ($client['owner_firm']): ?><p style="font-size:12px;color:var(--admin-text3,var(--text3));"><?= h($client['owner_firm']) ?></p><?php endif; ?>
    </div>
  </div>
</div>

<!-- Toolbar -->
<div class="acs-toolbar">
  <div class="acs-search-wrap">
    <?= icon('search', 14) ?>
    <input type="text" id="acsSearch" class="admin-input" placeholder="Search product name or lot number…"
           value="<?= h($search) ?>" autocomplete="off"/>
  </div>
  <button type="button" id="acsAddProductBtn" class="btn-admin-primary">
    <?= icon('plus', 14) ?> Add Product
  </button>
</div>

<div id="acsCount" style="font-size:13px;color:var(--admin-text3,var(--text3));margin-bottom:14px;">
  <strong style="font-size:18px;color:var(--admin-text,var(--text));" id="acsCountNum"><?= $total ?></strong> products selected
</div>

<!-- Table -->
<div class="admin-table-wrap" id="acsTableWrap">
  <div id="acsLoader"><div class="admin-loader-ring"></div></div>
  <div id="acsContent">
    <?php include __DIR__ . '/_admin_selection_rows.php'; ?>
  </div>
</div>

<!-- ══════════════════ ADD PRODUCT MODAL ══════════════════════════════════ -->
<div id="acsAddProductModal">
  <div class="acs-modal-card">
    <div class="acs-modal-header">
      <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));">Add Product to Selection</p>
      <button type="button" id="acsAddProductClose" style="color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border:none;"><?= icon('close', 18) ?></button>
    </div>
    <div class="acs-modal-body">

      <!-- Step 1: search & pick product -->
      <div id="acsPickStep">
        <div style="position:relative;margin-bottom:14px;">
          <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--admin-text3,var(--text3));"><?= icon('search', 14) ?></span>
          <input type="text" id="acsProductSearch" class="admin-input" style="padding-left:36px;"
                 placeholder="Type product name or quarry number…" autocomplete="off"/>
        </div>
        <div id="acsProductResults" style="max-height:320px;overflow-y:auto;">
          <p style="font-size:12px;color:var(--admin-text3,var(--text3));text-align:center;padding:20px 0;">Start typing to search products…</p>
        </div>
      </div>

      <!-- Step 2: details form (hidden until a product is picked) -->
      <div id="acsDetailsStep" style="display:none;">
        <div id="acsPickedProductPreview" style="display:flex;gap:12px;align-items:center;padding:12px;background:var(--admin-surface2,var(--surface2));border-radius:10px;margin-bottom:16px;"></div>
        <form method="POST" action="index.php" id="acsAddForm">
          <input type="hidden" name="action"    value="admin_add_selection"/>
          <input type="hidden" name="client_id" value="<?= $clientId ?>"/>
          <input type="hidden" name="product_id" id="acsPickedProductId"/>
          <?= csrfField() ?>
          <div class="acf-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
              <label class="admin-label">Area / Room</label>
              <input type="text" name="selection_area" class="admin-input" placeholder="e.g. Living Room"/>
            </div>
            <div>
              <label class="admin-label">Qty Required (sqft)</label>
              <input type="number" name="quantity_required" class="admin-input" min="0" step="0.01" placeholder="0"/>
            </div>
          </div>
          <div style="margin-top:14px;">
            <label class="admin-label">Notes</label>
            <textarea name="extra_notes" class="admin-input" rows="2" placeholder="Special requirements, finish preferences…"></textarea>
          </div>
          <div style="display:flex;gap:10px;margin-top:18px;">
            <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;">
              <?= icon('check', 15) ?> Save to Selection
            </button>
            <button type="button" id="acsBackToPick" class="btn-admin-secondary">← Change Product</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<!-- ══════════════════ EDIT SELECTION MODAL ═══════════════════════════════ -->
<div id="acsEditSelModal">
  <div class="acs-modal-card" style="max-width:480px;">
    <div class="acs-modal-header">
      <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));">Edit Selection</p>
      <button type="button" id="acsEditSelClose" style="color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border:none;"><?= icon('close', 18) ?></button>
    </div>
    <div class="acs-modal-body">
      <form method="POST" action="index.php" id="acsEditSelForm">
        <input type="hidden" name="action"       value="admin_update_selection"/>
        <input type="hidden" name="selection_id" id="acsEditSelId"/>
        <input type="hidden" name="client_id"    value="<?= $clientId ?>"/>
        <?= csrfField() ?>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Selection Area / Room</label>
          <input type="text" name="selection_area" id="acsEditSelArea" class="admin-input" placeholder="e.g. Master Bedroom"/>
        </div>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Quantity Required (sqft)</label>
          <input type="number" name="quantity_required" id="acsEditSelQty" class="admin-input" min="0" step="0.01" placeholder="0.00"/>
        </div>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Notes</label>
          <textarea name="extra_notes" id="acsEditSelNotes" class="admin-input" rows="3" placeholder="Any special requirements…"></textarea>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;"><?= icon('check', 14) ?> Save</button>
          <button type="button" id="acsEditSelCancel" class="btn-admin-secondary">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete confirm modal -->
<div id="acsDeleteSelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9200;align-items:center;justify-content:center;padding:16px;">
  <div class="usr-modal-card" style="max-width:400px;">
    <div style="width:48px;height:48px;border-radius:50%;background:var(--admin-btn-danger-bg,var(--danger-bg));color:var(--admin-btn-danger-color,var(--danger));display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
      <?= icon('trash', 20) ?>
    </div>
    <p style="font-size:15px;font-weight:700;color:var(--admin-text,var(--text));margin-bottom:8px;">Remove Product?</p>
    <p style="font-size:13px;color:var(--admin-text3,var(--text3));line-height:1.6;margin-bottom:18px;" id="acsDeleteSelMsg"></p>
    <div style="display:flex;gap:10px;">
      <button type="button" class="btn-admin-secondary" style="flex:1;justify-content:center;" id="acsDeleteSelCancel">Cancel</button>
      <form method="POST" action="index.php" style="flex:1;">
        <input type="hidden" name="action"       value="admin_delete_selection"/>
        <input type="hidden" name="selection_id" id="acsDeleteSelId" value=""/>
        <input type="hidden" name="client_id"    value="<?= $clientId ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;"><?= icon('trash', 14) ?> Remove</button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var clientId = <?= $clientId ?>;
  var content  = document.getElementById('acsContent');
  var loader   = document.getElementById('acsLoader');
  var countNum = document.getElementById('acsCountNum');
  var searchEl = document.getElementById('acsSearch');

  var state = { q: <?= json_encode($search) ?>, page: <?= $currentPage ?> };
  var timer = null;

  function load(page, push) {
    state.page = page;
    if (loader) loader.style.display = 'flex';
    var params = new URLSearchParams({ page: 'admin_client_selections', ajax: '1', client_id: clientId, p: page });
    if (state.q) params.set('q', state.q);

    fetch('index.php?' + params)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        content.innerHTML = d.html;
        if (countNum) countNum.textContent = d.total;
        bindButtons();
      })
      .finally(function () { if (loader) loader.style.display = 'none'; });
  }

  function bindButtons() {
    content.querySelectorAll('.apag-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
        load(parseInt(btn.dataset.page, 10));
      });
    });
    content.querySelectorAll('.acs-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('acsEditSelId').value    = btn.dataset.id;
        document.getElementById('acsEditSelArea').value  = btn.dataset.area  || '';
        document.getElementById('acsEditSelQty').value   = btn.dataset.qty   || '';
        document.getElementById('acsEditSelNotes').value = btn.dataset.notes || '';
        document.getElementById('acsEditSelModal').style.display = 'flex';
      });
    });
    content.querySelectorAll('.acs-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('acsDeleteSelId').value = btn.dataset.id;
        document.getElementById('acsDeleteSelMsg').textContent = 'Remove "' + btn.dataset.name + '" from this selection?';
        document.getElementById('acsDeleteSelModal').style.display = 'flex';
      });
    });
  }

  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var v = this.value.trim();
      clearTimeout(timer);
      if (v.length > 0 && v.length < 2) return;
      timer = setTimeout(function () { state.q = v; load(1); }, 300);
    });
  }

  // ── Edit modal close ───────────────────────────────────────────────────
  document.getElementById('acsEditSelClose')?.addEventListener('click', function () {
    document.getElementById('acsEditSelModal').style.display = 'none';
  });
  document.getElementById('acsEditSelCancel')?.addEventListener('click', function () {
    document.getElementById('acsEditSelModal').style.display = 'none';
  });
  document.getElementById('acsEditSelModal')?.addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
  });
  document.getElementById('acsEditSelForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = new FormData(this);
    fetch('index.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (d) {
        if (d && d.success === false) throw new Error(d.error || 'Update failed.');
        document.getElementById('acsEditSelModal').style.display = 'none';
        load(state.page);
      })
      .catch(function (err) {
        alert('Could not save changes: ' + err.message + '. Please refresh and try again.');
      });
  });

  // ── Delete modal close ─────────────────────────────────────────────────
  document.getElementById('acsDeleteSelCancel')?.addEventListener('click', function () {
    document.getElementById('acsDeleteSelModal').style.display = 'none';
  });
  document.getElementById('acsDeleteSelModal')?.addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
  });

  // ══════════════════════════════════════════════════════════════════════
  // ADD PRODUCT MODAL
  // ══════════════════════════════════════════════════════════════════════
  var addModal     = document.getElementById('acsAddProductModal');
  var pickStep      = document.getElementById('acsPickStep');
  var detailsStep   = document.getElementById('acsDetailsStep');
  var prodSearch    = document.getElementById('acsProductSearch');
  var prodResults   = document.getElementById('acsProductResults');
  var pickedPreview = document.getElementById('acsPickedProductPreview');
  var pickedIdInput = document.getElementById('acsPickedProductId');
  var prodTimer     = null;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  function openAddModal() {
    pickStep.style.display = 'block';
    detailsStep.style.display = 'none';
    prodSearch.value = '';
    prodResults.innerHTML = '<p style="font-size:12px;color:var(--admin-text3,var(--text3));text-align:center;padding:20px 0;">Start typing to search products…</p>';
    addModal.style.display = 'flex';
    setTimeout(function () { prodSearch.focus(); }, 100);
  }
  function closeAddModal() { addModal.style.display = 'none'; }

  document.getElementById('acsAddProductBtn')?.addEventListener('click', openAddModal);
  document.getElementById('acsAddProductClose')?.addEventListener('click', closeAddModal);
  addModal?.addEventListener('click', function (e) { if (e.target === this) closeAddModal(); });

  function renderProductResults(products) {
    if (!products.length) {
      prodResults.innerHTML = '<p style="font-size:12px;color:var(--admin-text3,var(--text3));text-align:center;padding:20px 0;">No products found.</p>';
      return;
    }
    prodResults.innerHTML = products.map(function (p) {
      var thumb = (p.primary_photo)
        ? '<img src="../assets/uploads/photos/' + esc(p.primary_photo) + '" alt=""/>'
        : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--admin-text3,var(--text3));">No img</div>';
      return '<div class="acs-product-pick-item" data-id="' + p.id + '" data-name="' + esc(p.name) +
        '" data-quarry="' + esc(p.quarry_number) + '" data-photo="' + esc(p.primary_photo || '') +
        '" data-available="' + esc(p.quantity_available) + '">' +
        '<div class="acs-pick-thumb">' + thumb + '</div>' +
        '<div style="flex:1;min-width:0;">' +
          '<p style="font-weight:600;font-size:13px;color:var(--admin-text,var(--text));white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(p.name) + '</p>' +
          '<p style="font-size:11px;color:var(--admin-text3,var(--text3));">Lot ' + esc(p.quarry_number) + ' · ' + esc(p.category || '') + '</p>' +
        '</div>' +
        '<span class="badge badge-green" style="flex-shrink:0;font-size:10px;">' + Number(p.quantity_available || 0).toLocaleString() + ' sqft</span>' +
      '</div>';
    }).join('');

    prodResults.querySelectorAll('.acs-product-pick-item').forEach(function (item) {
      item.addEventListener('click', function () { pickProduct(item); });
    });
  }

  function pickProduct(item) {
    var name      = item.dataset.name;
    var quarry    = item.dataset.quarry;
    var photo     = item.dataset.photo;
    var available = item.dataset.available;
    pickedIdInput.value = item.dataset.id;

    var thumb = photo
      ? '<img src="../assets/uploads/photos/' + esc(photo) + '" alt="" style="width:48px;height:48px;border-radius:8px;object-fit:cover;flex-shrink:0;"/>'
      : '<div style="width:48px;height:48px;border-radius:8px;background:var(--admin-surface3,var(--surface3));flex-shrink:0;"></div>';

    pickedPreview.innerHTML = thumb +
      '<div style="flex:1;min-width:0;">' +
        '<p style="font-weight:700;font-size:14px;color:var(--admin-text,var(--text));">' + esc(name) + '</p>' +
        '<p style="font-size:12px;color:var(--admin-text3,var(--text3));">Lot ' + esc(quarry) + ' · ' + Number(available || 0).toLocaleString() + ' sqft available</p>' +
      '</div>';

    pickStep.style.display = 'none';
    detailsStep.style.display = 'block';
  }

  document.getElementById('acsBackToPick')?.addEventListener('click', function () {
    detailsStep.style.display = 'none';
    pickStep.style.display = 'block';
  });

  prodSearch?.addEventListener('input', function () {
    var v = this.value.trim();
    clearTimeout(prodTimer);
    prodTimer = setTimeout(function () {
      fetch('index.php?page=admin_client_selections&client_id=' + clientId + '&ajax_product_search=1&q=' + encodeURIComponent(v))
        .then(function (r) { return r.json(); })
        .then(function (d) { renderProductResults(d.products || []); });
    }, 250);
  });
  // Preload some products when opened
  document.getElementById('acsAddProductBtn')?.addEventListener('click', function () {
    fetch('index.php?page=admin_client_selections&client_id=' + clientId + '&ajax_product_search=1&q=')
      .then(function (r) { return r.json(); })
      .then(function (d) { renderProductResults(d.products || []); });
  });

  document.getElementById('acsAddForm')?.addEventListener('submit', function (e) {
    if (!pickedIdInput.value) {
      e.preventDefault();
      alert('Please choose a product first.');
      return;
    }
    e.preventDefault();
    var body = new FormData(this);
    fetch('index.php', { method: 'POST', body: body })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        closeAddModal();
        load(1);
        if (d && d.error) alert(d.error);
      });
  });

  bindButtons();
})();
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>