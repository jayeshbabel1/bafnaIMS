<?php
/**
 * admin/views/admin_clients.php — Admin: list ALL clients across all users
 * with search, pagination, and links to add/edit/manage selections.
 */

// ── AJAX handler (search + pagination) ──────────────────────────────────────
if (!empty($_GET['ajax_clients'])) {
    $search      = trim($_GET['q'] ?? '');
    $perPage     = 10;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));

    $result = adminListAllClients([
        'search' => $search,
        'limit'  => $perPage,
        'offset' => ($currentPage - 1) * $perPage,
    ]);
    $clients    = $result['rows'];
    $total      = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));

    ob_start();
    include __DIR__ . '/_admin_clients_rows.php';
    $html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'total' => $total, 'pages' => $totalPages, 'current' => $currentPage]);
    exit;
}

$adminTitle = 'Client Selections';
requireAdminPermission('clients.view');
include __DIR__ . '/../_layout_top.php';
require_once BASE_PATH . '/includes/clients.php';

$perPage     = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$search      = trim($_GET['q'] ?? '');

$result = adminListAllClients([
    'search' => $search,
    'limit'  => $perPage,
    'offset' => ($currentPage - 1) * $perPage,
]);
$clients    = $result['rows'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
?>

<style>
.admin-clients-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
.admin-clients-search-wrap { position:relative; flex:1; min-width:220px; max-width:420px; }
.admin-clients-search-wrap > svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--admin-text3,var(--text3)); pointer-events:none; }
.admin-clients-search-wrap input { padding-left:34px !important; padding-right:32px !important; }
.admin-clients-search-clear { position:absolute; right:8px; top:50%; transform:translateY(-50%); width:20px; height:20px; border-radius:50%; background:var(--admin-surface2,var(--surface2)); color:var(--admin-text3,var(--text3)); display:none; align-items:center; justify-content:center; cursor:pointer; border:none; }
.ac-owner-chip { display:inline-flex; align-items:center; gap:6px; font-size:11px; color:var(--admin-text3,var(--text3)); }
.ac-owner-avatar { width:22px; height:22px; border-radius:50%; background:linear-gradient(135deg,var(--admin-accent,var(--accent)),var(--admin-accent-mid,var(--accent-mid))); color:#fff; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:700; flex-shrink:0; }
#adminClientsLoader { display:none; position:absolute; inset:0; background:rgba(255,255,255,.65); backdrop-filter:blur(2px); align-items:center; justify-content:center; z-index:50; border-radius:var(--admin-card-radius,var(--card-radius)); }
#adminClientsTableWrap { position:relative; }
@media (max-width:768px) {
  .admin-clients-toolbar { flex-direction:column; align-items:stretch; }
  .admin-clients-search-wrap { max-width:100%; }
}
</style>

<div class="admin-clients-toolbar">
  <?php if (adminCan('clients.create')): ?>
  <a href="index.php?page=admin_client_form" class="admin-toolbar-btn admin-toolbar-btn--primary">
    <?= icon('plus', 14) ?> Add Client
  </a>
  <?php endif; ?>
  <div class="admin-clients-search-wrap" id="acSearchWrap">
    <?= icon('search', 14) ?>
    <input type="text" id="acSearch" class="admin-input" placeholder="Search client, mason, or user…" autocomplete="off"/>
    <button type="button" class="admin-clients-search-clear" id="acSearchClear"><?= icon('close', 11) ?></button>
  </div>
  <div id="acCountEl" style="font-size:12px;color:var(--admin-text3,var(--text3));white-space:nowrap;margin-left:auto;">
    <?= $total ?> client<?= $total !== 1 ? 's' : '' ?>
  </div>
</div>

<div class="admin-table-wrap" id="adminClientsTableWrap">
  <div id="adminClientsLoader"><div class="admin-loader-ring"></div></div>
  <table class="admin-table" id="adminClientsTable">
    <thead>
      <tr>
        <th>Client</th>
        <th>Mason</th>
        <th>Belongs To</th>
        <th>Selections</th>
        <th>Added</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="adminClientsTbody">
      <?php include __DIR__ . '/_admin_clients_rows.php'; ?>
    </tbody>
  </table>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:10px;">
  <p class="admin-products-count" id="acFooterCount"><?= $total ?> client<?= $total !== 1 ? 's' : '' ?></p>
  <div id="adminClientsPaginationWrap" style="display:none;"></div>
   <div id="paginationWrap"></div>
</div>

<!-- Delete confirm modal -->
<div id="acDeleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9100;align-items:center;justify-content:center;padding:16px;">
  <div class="usr-modal-card" style="max-width:420px;">
    <div style="width:52px;height:52px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
      <?= icon('trash', 22) ?>
    </div>
    <p style="font-size:17px;font-weight:700;color:var(--admin-text,var(--text));margin-bottom:8px;">Delete Client?</p>
    <p style="font-size:13px;color:var(--admin-text3,var(--text3));line-height:1.6;margin-bottom:20px;" id="acDeleteMsg"></p>
    <div style="display:flex;gap:10px;">
      <button type="button" class="btn-admin-secondary" style="flex:1;justify-content:center;" id="acDeleteCancel">Cancel</button>
      <form method="POST" action="index.php" style="flex:1;">
        <input type="hidden" name="action"    value="admin_delete_client"/>
        <input type="hidden" name="client_id" id="acDeleteId" value=""/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;">
          <?= icon('trash', 14) ?> Delete
        </button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var tbody   = document.getElementById('adminClientsTbody');
  var countEl = document.getElementById('acFooterCount');
  var countEl2= document.getElementById('acCountEl');
  var searchEl= document.getElementById('acSearch');
  var clearBtn= document.getElementById('acSearchClear');
  var loader  = document.getElementById('adminClientsLoader');

  var state = { q: '', page: <?= $currentPage ?> };
  var timer = null;
  var totalPages = <?= (int)$totalPages ?>;
  var pager = null;

  function load() {
    if (loader) loader.style.display = 'flex';
    if (tbody)  tbody.style.opacity  = '0.4';
    var params = new URLSearchParams({ page: 'admin_clients', ajax_clients: '1', p: state.page });
    if (state.q) params.set('q', state.q);

    fetch('index.php?' + params)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (tbody) { tbody.innerHTML = d.html; tbody.style.opacity = '1'; }
        totalPages = d.pages;
       if (pager) {
         pager.setWrapEl(document.getElementById('paginationWrap'));
         pager.render(d.current, d.pages);
       }
        if (countEl)  countEl.textContent  = d.total + ' client' + (d.total !== 1 ? 's' : '');
        if (countEl2) countEl2.textContent = d.total + ' client' + (d.total !== 1 ? 's' : '');
        bindRowActions();
      })
      .catch(function () { if (tbody) tbody.style.opacity = '1'; })
      .finally(function () { if (loader) loader.style.display = 'none'; });
  }

  
  function bindRowActions() {
    document.querySelectorAll('.ac-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('acDeleteId').value = btn.dataset.id;
        document.getElementById('acDeleteMsg').textContent =
          'Delete "' + btn.dataset.name + '"? This will also remove all their product selections. This cannot be undone.';
        document.getElementById('acDeleteModal').style.display = 'flex';
      });
    });
  }

  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var v = this.value.trim();
      if (clearBtn) clearBtn.style.display = v ? 'flex' : 'none';
      clearTimeout(timer);
      if (v.length > 0 && v.length < 2) return;
      timer = setTimeout(function () { state.q = v; state.page = 1; load(); }, 300);
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      searchEl.value = ''; clearBtn.style.display = 'none';
      state.q = ''; state.page = 1; load();
      searchEl.focus();
    });
  }

  document.getElementById('acDeleteCancel')?.addEventListener('click', function () {
    document.getElementById('acDeleteModal').style.display = 'none';
  });
  document.getElementById('acDeleteModal')?.addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
  });

  bindRowActions();
  document.addEventListener('DOMContentLoaded', function () {
   pager = initPagination({
     wrapEl: document.getElementById('paginationWrap'),
     btnClass: 'apag-btn',
     onPage: function (page) { state.page = page; load(); }
   });
   pager.render(state.page, totalPages);
 });
})();
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>