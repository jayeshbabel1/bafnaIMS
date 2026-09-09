<?php
/**
 * admin/views/marketing_contacts.php — Fire 3
 */
require_once BASE_PATH . '/includes/marketing_contacts.php';

// ── AJAX: contact rows (search + filters + pagination) ──────────────────
if (!empty($_GET['ajax_contacts'])) {
    requireAdminPermissionJson('marketing.contacts.view');
    $perPage     = 20;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $result = getMarketingContacts([
        'search'      => trim($_GET['q'] ?? ''),
        'tag_id'      => (int)($_GET['tag_id'] ?? 0),
        'group_id'    => (int)($_GET['group_id'] ?? 0),
        'source_type' => $_GET['source_type'] ?? '',
        'status'      => $_GET['status'] ?? '',
        'limit'       => $perPage,
        'offset'      => ($currentPage - 1) * $perPage,
    ]);
    $contacts   = $result['rows'];
    $total      = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));
    ob_start();
    include __DIR__ . '/_marketing_contacts_rows.php';
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'total' => $total, 'pages' => $totalPages, 'current' => $currentPage]);
    exit;
}

$adminTitle = 'Marketing Contacts';
requireAdminPermission('marketing.contacts.view');
include __DIR__ . '/../_layout_top.php';

$allTags   = getAllMarketingTags();
$allGroups = getMarketingGroups();
$canManage = adminCan('marketing.contacts.manage');
$canGroups = adminCan('marketing.groups.manage');
?>
<style>
.mkt-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;}
.mkt-search-wrap{position:relative;flex:1;min-width:200px;max-width:340px;}
.mkt-search-wrap>svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--admin-text3,var(--text3));}
.mkt-search-wrap input{padding-left:34px !important;}
.mkt-bulkbar{display:none;align-items:center;gap:10px;background:var(--admin-accent-light,var(--accent-light));border:1px solid var(--admin-accent,var(--accent));border-radius:8px;padding:10px 14px;margin-bottom:14px;flex-wrap:wrap;}
#mktLoader{display:none;position:absolute;inset:0;background:rgba(255,255,255,.65);backdrop-filter:blur(2px);align-items:center;justify-content:center;z-index:50;border-radius:var(--admin-card-radius,var(--card-radius));}
#mktTableWrap{position:relative;}
.mkt-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;}
.mkt-modal.open{display:flex;}
.mkt-modal-card{background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 16px 48px rgba(0,0,0,.2);}
.mkt-modal-header{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--admin-table-border,var(--border));}
.mkt-modal-body{padding:20px;}
</style>

<div class="mkt-toolbar">
  <?php if ($canManage): ?>
  <button type="button" class="admin-toolbar-btn admin-toolbar-btn--primary" onclick="mktOpenAdd()">
    <?= icon('plus', 14) ?> Add Contact
  </button>
  <form method="POST" action="index.php" class="admin-toolbar-form" enctype="multipart/form-data">
    <input type="hidden" name="action" value="marketing_import_contacts"/>
    <?= csrfField() ?>
    <label class="admin-toolbar-btn admin-toolbar-btn--upload" title="Import CSV (columns: Name, Mobile, Email, City)">
      <?= icon('upload', 14) ?> Import CSV
      <input type="file" name="contacts_file" accept=".csv" onchange="this.form.submit()"/>
    </label>
  </form>
  <form method="POST" action="index.php" class="admin-toolbar-form">
    <input type="hidden" name="action" value="marketing_sync_users"/>
    <?= csrfField() ?>
    <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed"><?= icon('refresh', 14) ?> Sync from Users</button>
  </form>
  <form method="POST" action="index.php" class="admin-toolbar-form">
    <input type="hidden" name="action" value="marketing_sync_clients"/>
    <?= csrfField() ?>
    <button type="submit" class="admin-toolbar-btn admin-toolbar-btn--dashed"><?= icon('refresh', 14) ?> Sync from Clients</button>
  </form>
  <?php endif; ?>
  <a href="index.php?marketing_export_contacts=1" class="admin-toolbar-btn admin-toolbar-btn--solid"><?= icon('download', 14) ?> Export CSV</a>
  <?php if ($canGroups): ?>
  <button type="button" class="admin-toolbar-btn admin-toolbar-btn--solid" onclick="document.getElementById('mktTagModal').classList.add('open')">
    <?= icon('grid', 14) ?> Manage Tags
  </button>
  <?php endif; ?>
</div>

<div class="mkt-toolbar">
  <div class="mkt-search-wrap">
    <?= icon('search', 14) ?>
    <input type="text" id="mktSearch" class="admin-input" placeholder="Search name, mobile, email…" autocomplete="off"/>
  </div>
  <select id="mktFilterSource" class="admin-input admin-select" style="max-width:150px;">
    <option value="">All Sources</option>
    <option value="user">App Users</option>
    <option value="client">Clients</option>
    <option value="manual">Manual</option>
    <option value="import">Imported</option>
  </select>
  <select id="mktFilterTag" class="admin-input admin-select" style="max-width:160px;">
    <option value="">All Tags</option>
    <?php foreach ($allTags as $t): ?>
    <option value="<?= $t['id'] ?>"><?= h($t['name']) ?> (<?= $t['contact_count'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select id="mktFilterGroup" class="admin-input admin-select" style="max-width:180px;">
    <option value="">All Groups</option>
    <?php foreach ($allGroups as $g): ?>
    <option value="<?= $g['id'] ?>"><?= h($g['name']) ?> (<?= $g['contact_count'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <span id="mktCountEl" style="font-size:12px;color:var(--admin-text3,var(--text3));margin-left:auto;"></span>
</div>

<?php if ($canManage): ?>
<div class="mkt-bulkbar" id="mktBulkBar">
  <span style="font-size:12px;font-weight:600;"><span id="mktSelCount">0</span> selected</span>
  <select id="mktBulkTagSel" class="admin-input admin-select" style="max-width:150px;">
    <option value="">Add tag…</option>
    <?php foreach ($allTags as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?>
  </select>
  <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktBulk('add_tag')">Apply</button>
  <select id="mktBulkGroupSel" class="admin-input admin-select" style="max-width:170px;">
    <option value="">Add to group…</option>
    <?php foreach ($allGroups as $g): if ($g['type']==='static'): ?><option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option><?php endif; endforeach; ?>
  </select>
  <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktBulk('add_group')">Apply</button>
  <button type="button" class="btn-admin-danger btn-admin-sm" onclick="mktBulk('delete')" style="margin-left:auto;"><?= icon('trash',13) ?> Delete Selected</button>
</div>
<?php endif; ?>

<div class="admin-table-wrap" id="mktTableWrap">
  <div id="mktLoader"><div class="admin-loader-ring"></div></div>
  <table class="admin-table">
    <thead>
      <tr>
        <th style="width:32px;"><input type="checkbox" id="mktSelectAll"/></th>
        <th>Name / Source</th><th>Mobile</th><th>Email</th><th>City</th><th>Tags</th><th>Opt-in</th><th style="width:90px;">Actions</th>
      </tr>
    </thead>
    <tbody id="mktTbody"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--admin-text3,var(--text3));">Loading…</td></tr></tbody>
  </table>
</div>
<div id="mktPagWrap" class="admin-pagination" style="margin-top:12px;"></div>

<!-- Add/Edit contact modal -->
<div id="mktContactModal" class="mkt-modal">
  <div class="mkt-modal-card">
    <div class="mkt-modal-header">
      <p id="mktModalTitle" style="font-size:16px;font-weight:700;">Add Contact</p>
      <button type="button" onclick="mktCloseModal()" style="background:none;border:none;color:var(--admin-text3,var(--text3));cursor:pointer;"><?= icon('close',18) ?></button>
    </div>
    <div class="mkt-modal-body">
      <form method="POST" action="index.php" id="mktContactForm">
        <input type="hidden" name="action" id="mktFormAction" value="marketing_create_contact"/>
        <input type="hidden" name="contact_id" id="mktContactId" value=""/>
        <?= csrfField() ?>
        <div style="margin-bottom:14px;"><label class="admin-label">Name *</label><input type="text" name="name" id="mktName" class="admin-input" required/></div>
        <div style="margin-bottom:14px;"><label class="admin-label">Mobile</label><input type="text" name="mobile" id="mktMobile" class="admin-input"/></div>
              <div style="margin-bottom:14px;"><label class="admin-label">Email</label><input type="email" name="email" id="mktEmail" class="admin-input" autocomplete="off" data-lpignore="true"/></div>
        <div style="margin-bottom:14px;"><label class="admin-label">City</label><input type="text" name="city" id="mktCity" class="admin-input"/></div>
        <div style="margin-bottom:14px;" id="mktStatusRow">
          <label class="admin-label">Status</label>
          <select name="status" id="mktStatus" class="admin-input admin-select">
            <option value="active">Active</option><option value="inactive">Inactive</option>
          </select>
        </div>
        <div style="display:flex;gap:18px;margin-bottom:16px;">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;"><input type="checkbox" name="whatsapp_opt_in" id="mktWaOptin" value="1" checked/> WhatsApp Opt-in</label>
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;"><input type="checkbox" name="email_opt_in" id="mktEmailOptin" value="1" checked/> Email Opt-in</label>
        </div>
        <button type="submit" class="btn-admin-primary" style="width:100%;justify-content:center;"><?= icon('check',15) ?> Save Contact</button>
      </form>
    </div>
  </div>
</div>

<!-- Delete confirm modal -->
<div id="mktDeleteModal" class="mkt-modal">
  <div class="mkt-modal-card" style="max-width:400px;">
    <div class="mkt-modal-body" style="text-align:center;">
      <div style="width:48px;height:48px;border-radius:50%;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;"><?= icon('trash',22) ?></div>
      <p style="font-weight:700;margin-bottom:8px;">Delete Contact?</p>
      <p id="mktDeleteMsg" style="font-size:13px;color:var(--admin-text3,var(--text3));margin-bottom:18px;"></p>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn-admin-secondary" style="flex:1;" onclick="document.getElementById('mktDeleteModal').classList.remove('open')">Cancel</button>
        <form method="POST" action="index.php" style="flex:1;">
          <input type="hidden" name="action" value="marketing_delete_contact"/>
          <input type="hidden" name="contact_id" id="mktDeleteId" value=""/>
          <?= csrfField() ?>
          <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;"><?= icon('trash',14) ?> Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Tag manager modal -->
<div id="mktTagModal" class="mkt-modal">
  <div class="mkt-modal-card">
    <div class="mkt-modal-header">
      <p style="font-size:16px;font-weight:700;">Manage Tags</p>
      <button type="button" onclick="document.getElementById('mktTagModal').classList.remove('open')" style="background:none;border:none;color:var(--admin-text3,var(--text3));cursor:pointer;"><?= icon('close',18) ?></button>
    </div>
    <div class="mkt-modal-body">
      <form method="POST" action="index.php" style="display:flex;gap:8px;margin-bottom:16px;">
        <input type="hidden" name="action" value="marketing_create_tag"/>
        <?= csrfField() ?>
        <input type="text" name="name" class="admin-input" placeholder="New tag name" required style="flex:1;"/>
        <button type="submit" class="btn-admin-primary btn-admin-sm"><?= icon('plus',13) ?> Add</button>
      </form>
      <?php foreach ($allTags as $t): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--admin-table-border,var(--border));">
        <span style="flex:1;font-size:13px;"><?= h($t['name']) ?> <span style="color:var(--admin-text3,var(--text3));">(<?= $t['contact_count'] ?>)</span></span>
        <form method="POST" action="index.php">
          <input type="hidden" name="action" value="marketing_delete_tag"/>
          <input type="hidden" name="tag_id" value="<?= $t['id'] ?>"/>
          <?= csrfField() ?>
          <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Delete tag '<?= h(addslashes($t['name'])) ?>'?"><?= icon('trash',12) ?></button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<form id="mktBulkForm" method="POST" action="index.php" style="display:none;">
  <input type="hidden" name="action" value="marketing_bulk_action"/>
  <input type="hidden" name="bulk_action" id="mktBulkActionField"/>
  <input type="hidden" name="tag_id" id="mktBulkTagField"/>
  <input type="hidden" name="group_id" id="mktBulkGroupField"/>
  <div id="mktBulkIdsWrap"></div>
  <?= csrfField() ?>
</form>

<script>
(function () {
  var tbody = document.getElementById('mktTbody');
  var loader = document.getElementById('mktLoader');
  var pagWrap = document.getElementById('mktPagWrap');
  var countEl = document.getElementById('mktCountEl');
  var searchEl = document.getElementById('mktSearch');
  var state = { q: '', tag_id: '', group_id: '', source_type: '', page: 1 };
  var timer = null; var pager = null;

  function load(page) {
    state.page = page || 1;
    if (loader) loader.style.display = 'flex';
    var params = new URLSearchParams({ page: 'marketing_contacts', ajax_contacts: '1', p: state.page });
    if (state.q) params.set('q', state.q);
    if (state.tag_id) params.set('tag_id', state.tag_id);
    if (state.group_id) params.set('group_id', state.group_id);
    if (state.source_type) params.set('source_type', state.source_type);

    fetch('index.php?' + params)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        tbody.innerHTML = d.html;
        countEl.textContent = d.total + ' contact(s)';
        if (!pager) pager = initPagination({ wrapEl: pagWrap, btnClass: 'apag-btn', onPage: load });
        pager.render(d.current, d.pages);
        bindRowEvents();
      })
      .finally(function () { if (loader) loader.style.display = 'none'; });
  }

  function bindRowEvents() {
    tbody.querySelectorAll('.mkt-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () { mktOpenEdit(btn.dataset); });
    });
    tbody.querySelectorAll('.mkt-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('mktDeleteId').value = btn.dataset.id;
        document.getElementById('mktDeleteMsg').textContent = 'Delete "' + btn.dataset.name + '"? This cannot be undone.';
        document.getElementById('mktDeleteModal').classList.add('open');
      });
    });
    tbody.querySelectorAll('.mkt-contact-check').forEach(function (cb) {
      cb.addEventListener('change', updateBulkBar);
    });
    document.getElementById('mktSelectAll').addEventListener('change', function () {
      tbody.querySelectorAll('.mkt-contact-check').forEach(function (cb) { cb.checked = this.checked; }.bind(this));
      updateBulkBar();
    });
  }

  function updateBulkBar() {
    var checked = tbody.querySelectorAll('.mkt-contact-check:checked');
    var bar = document.getElementById('mktBulkBar');
    if (bar) {
      bar.style.display = checked.length ? 'flex' : 'none';
      document.getElementById('mktSelCount').textContent = checked.length;
    }
  }
  window.mktGetSelectedIds = function () {
    return Array.from(tbody.querySelectorAll('.mkt-contact-check:checked')).map(function (cb) { return cb.value; });
  };
  window.mktBulk = function (action) {
    var ids = mktGetSelectedIds();
    if (!ids.length) { alert('No contacts selected.'); return; }
    if (action === 'delete' && !confirm('Delete ' + ids.length + ' contact(s)? This cannot be undone.')) return;
    document.getElementById('mktBulkActionField').value = action;
    document.getElementById('mktBulkTagField').value = document.getElementById('mktBulkTagSel') ? document.getElementById('mktBulkTagSel').value : '';
    document.getElementById('mktBulkGroupField').value = document.getElementById('mktBulkGroupSel') ? document.getElementById('mktBulkGroupSel').value : '';
    var wrap = document.getElementById('mktBulkIdsWrap'); wrap.innerHTML = '';
    ids.forEach(function (id) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'contact_ids[]'; inp.value = id;
      wrap.appendChild(inp);
    });
    document.getElementById('mktBulkForm').submit();
  };

  searchEl.addEventListener('input', function () {
    var v = this.value.trim();
    clearTimeout(timer);
    if (v.length > 0 && v.length < 2) return;
    timer = setTimeout(function () { state.q = v; load(1); }, 300);
  });
  ['mktFilterSource','mktFilterTag','mktFilterGroup'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', function () {
      state.source_type = document.getElementById('mktFilterSource').value;
      state.tag_id = document.getElementById('mktFilterTag').value;
      state.group_id = document.getElementById('mktFilterGroup').value;
      load(1);
    });
  });

  window.mktOpenAdd = function () {
    document.getElementById('mktModalTitle').textContent = 'Add Contact';
    document.getElementById('mktFormAction').value = 'marketing_create_contact';
    document.getElementById('mktContactId').value = '';
    ['mktName','mktMobile','mktEmail','mktCity'].forEach(function (id) { document.getElementById(id).value = ''; });
    document.getElementById('mktStatus').value = 'active';
    document.getElementById('mktWaOptin').checked = true;
    document.getElementById('mktEmailOptin').checked = true;
    document.getElementById('mktStatusRow').style.display = 'none';
    document.getElementById('mktContactModal').classList.add('open');
  };
  window.mktOpenEdit = function (ds) {
    document.getElementById('mktModalTitle').textContent = 'Edit Contact';
    document.getElementById('mktFormAction').value = 'marketing_update_contact';
    document.getElementById('mktContactId').value = ds.id;
    document.getElementById('mktName').value = ds.name || '';
    document.getElementById('mktMobile').value = ds.mobile || '';
    document.getElementById('mktEmail').value = ds.email || '';
    document.getElementById('mktCity').value = ds.city || '';
    document.getElementById('mktStatus').value = ds.status || 'active';
    document.getElementById('mktWaOptin').checked = ds.waOptin === '1';
    document.getElementById('mktEmailOptin').checked = ds.emailOptin === '1';
    document.getElementById('mktStatusRow').style.display = '';
    document.getElementById('mktContactModal').classList.add('open');
  };
  window.mktCloseModal = function () { document.getElementById('mktContactModal').classList.remove('open'); };

  document.querySelectorAll('.mkt-modal').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('open'); });
  });

  load(1);
})();
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>