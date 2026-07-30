<?php
/**
 * admin/views/catalog_pdf_history.php — Fire 5: saved catalogs list
 */
requireAdminPermission('catalog.history');

require_once BASE_PATH . '/includes/catalog_pdf.php';

// ── AJAX: send email ─────────────────────────────────────────────────────
if (!empty($_POST) && ($_POST['action'] ?? '') === 'catalog_pdf_send_email') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('catalog.share');
    csrfVerify(true);
    if (!throttle('catalog_email', 10, 60)) {
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment.']);
        exit;
    }
    $catalogId = (int)($_POST['catalog_id'] ?? 0);
    $toRaw     = trim($_POST['to'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $message   = trim($_POST['message'] ?? '');
    $recipients = array_filter(array_map('trim', explode(',', $toRaw)));
    if (empty($recipients)) { echo json_encode(['success' => false, 'error' => 'No recipient email provided.']); exit; }

    $errors = [];
    foreach ($recipients as $to) {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $errors[] = "$to: invalid address"; continue; }
        $r = sendCatalogPdfEmail($catalogId, $to, $subject, $message);
        if (!$r['success']) $errors[] = "$to: " . ($r['error'] ?? 'send failed');
    }
    echo json_encode(['success' => empty($errors), 'error' => implode('; ', $errors)]);
    exit;
}

// ── AJAX: table rows (search + pagination) ──────────────────────────────────
if (!empty($_GET['ajax_catalogs'])) {
    requireAdminPermissionJson('catalog.history');
    $search      = trim($_GET['q'] ?? '');
    $perPage     = 15;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));

    $result = listCatalogs([
        'search' => $search,
        'limit'  => $perPage,
        'offset' => ($currentPage - 1) * $perPage,
    ]);
    $catalogs   = $result['rows'];
    $total      = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));

    ob_start();
    include __DIR__ . '/_catalog_pdf_rows.php';
    $html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['html' => $html, 'total' => $total, 'pages' => $totalPages, 'current' => $currentPage]);
    exit;
}

$adminTitle = 'Catalog PDF History';
include __DIR__ . '/../_layout_top.php';

$perPage     = 15;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$search      = trim($_GET['q'] ?? '');

$result = listCatalogs(['search' => $search, 'limit' => $perPage, 'offset' => ($currentPage - 1) * $perPage]);
$catalogs   = $result['rows'];
$total      = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
?>

<style>
.cph-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;}
.cph-search-wrap{position:relative;flex:1;min-width:220px;max-width:400px;}
.cph-search-wrap>svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--admin-text3,var(--text3));}
.cph-search-wrap input{padding-left:34px !important;}
#cphLoader{display:none;position:absolute;inset:0;background:rgba(255,255,255,.65);backdrop-filter:blur(2px);align-items:center;justify-content:center;z-index:50;border-radius:var(--admin-card-radius,var(--card-radius));}
#cphTableWrap{position:relative;}

#cphEmailModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;}
#cphEmailModal.open{display:flex;}
.cph-modal-card{background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:480px;padding:24px 22px;box-shadow:0 16px 48px rgba(0,0,0,.2);}
</style>

<div class="cph-toolbar">
  <a href="index.php?page=catalog_pdf_wizard" class="admin-toolbar-btn admin-toolbar-btn--primary">
    <?= icon('plus', 14) ?> New Catalog
  </a>
  <div class="cph-search-wrap">
    <?= icon('search', 14) ?>
    <input type="text" id="cphSearch" class="admin-input" placeholder="Search catalog name…" autocomplete="off"/>
  </div>
  <div id="cphCountEl" style="font-size:12px;color:var(--admin-text3,var(--text3));margin-left:auto;white-space:nowrap;">
    <?= $total ?> catalog<?= $total !== 1 ? 's' : '' ?>
  </div>
</div>

<div class="admin-table-wrap" id="cphTableWrap">
  <div id="cphLoader"><div class="admin-loader-ring"></div></div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Name</th><th>Products</th><th>Pages</th><th>Size</th>
        <th>Downloads</th><th>Status</th><th>Created</th><th>Actions</th>
      </tr>
    </thead>
    <tbody id="cphTbody">
      <?php include __DIR__ . '/_catalog_pdf_rows.php'; ?>
    </tbody>
  </table>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:10px;">
  <p class="admin-products-count" id="cphFooterCount"><?= $total ?> catalog<?= $total !== 1 ? 's' : '' ?></p>
  <div id="cphPagWrap"></div>
</div>

<!-- Email modal -->
<div id="cphEmailModal">
  <div class="cph-modal-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));">Email Catalog PDF</p>
      <button type="button" onclick="cphCloseEmail()" style="cursor:pointer;background:none;border:none;color:var(--admin-text3,var(--text3));"><?= icon('close',18) ?></button>
    </div>
    <form id="cphEmailForm">
      <input type="hidden" id="cphEmailCatalogId" value=""/>
      <div style="margin-bottom:14px;">
        <label class="admin-label">To (comma-separated for multiple)</label>
        <input type="text" id="cphEmailTo" class="admin-input" placeholder="client@example.com" required/>
      </div>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Subject</label>
        <input type="text" id="cphEmailSubject" class="admin-input"/>
      </div>
      <div style="margin-bottom:16px;">
        <label class="admin-label">Message</label>
        <textarea id="cphEmailMessage" class="admin-input" rows="5"></textarea>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;" id="cphEmailSendBtn">
          <?= icon('mail',15) ?> Send
        </button>
        <button type="button" class="btn-admin-secondary" onclick="cphCloseEmail()">Cancel</button>
      </div>
      <p id="cphEmailStatus" style="font-size:12px;margin-top:10px;"></p>
    </form>
  </div>
</div>

<!-- Delete confirm modal -->
<div id="cphDeleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;">
  <div class="cph-modal-card" style="max-width:400px;">
    <div style="width:48px;height:48px;border-radius:50%;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
      <?= icon('trash', 22) ?>
    </div>
    <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));margin-bottom:6px;">Delete Catalog?</p>
    <p style="font-size:13px;color:var(--admin-text3,var(--text3));line-height:1.6;margin-bottom:18px;" id="cphDeleteMsg"></p>
    <div style="display:flex;gap:10px;">
      <button type="button" class="btn-admin-secondary" style="flex:1;justify-content:center;" onclick="document.getElementById('cphDeleteModal').style.display='none'">Cancel</button>
      <form method="POST" action="index.php" style="flex:1;">
        <input type="hidden" name="action" value="catalog_pdf_delete"/>
        <input type="hidden" name="catalog_id" id="cphDeleteId" value=""/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;"><?= icon('trash', 14) ?> Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var tbody   = document.getElementById('cphTbody');
  var pagWrap = document.getElementById('cphPagWrap');
  var countEl = document.getElementById('cphCountEl');
  var footEl  = document.getElementById('cphFooterCount');
  var searchEl= document.getElementById('cphSearch');
  var loader  = document.getElementById('cphLoader');

  var state = { q: '', page: 1 };
  var timer = null;

  function load() {
    if (loader) loader.style.display = 'flex';
    if (tbody)  tbody.style.opacity  = '0.4';
    var params = new URLSearchParams({ page: 'catalog_pdf_history', ajax_catalogs: '1', p: state.page });
    if (state.q) params.set('q', state.q);

    fetch('index.php?' + params)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (tbody) { tbody.innerHTML = d.html; tbody.style.opacity = '1'; }
        bindPagination(d.pages, d.current);
        var txt = d.total + ' catalog' + (d.total !== 1 ? 's' : '');
        if (countEl) countEl.textContent = txt;
        if (footEl)  footEl.textContent  = txt;
        bindRowActions();
      })
      .catch(function () { if (tbody) tbody.style.opacity = '1'; })
      .finally(function () { if (loader) loader.style.display = 'none'; });
  }

  function bindPagination(totalPages, current) {
    if (!pagWrap) return;
    if (totalPages <= 1) { pagWrap.innerHTML = ''; return; }
    var range = 2, s = Math.max(1, current - range), e = Math.min(totalPages, current + range);
    var html = '<div class="admin-pagination">';
    html += '<button class="apag-btn' + (current<=1?' disabled':'') + '" data-page="' + (current-1) + '">&lsaquo;</button>';
    if (s > 1) { html += '<button class="apag-btn" data-page="1">1</button>'; if (s>2) html += '<span class="apag-ellipsis">…</span>'; }
    for (var i = s; i <= e; i++) html += '<button class="apag-btn' + (i===current?' active':'') + '" data-page="' + i + '">' + i + '</button>';
    if (e < totalPages) { if (e < totalPages-1) html += '<span class="apag-ellipsis">…</span>'; html += '<button class="apag-btn" data-page="' + totalPages + '">' + totalPages + '</button>'; }
    html += '<button class="apag-btn' + (current>=totalPages?' disabled':'') + '" data-page="' + (current+1) + '">&rsaquo;</button>';
    html += '</div>';
    pagWrap.innerHTML = html;
    pagWrap.querySelectorAll('.apag-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
        var pg = parseInt(btn.dataset.page, 10);
        if (!isNaN(pg)) { state.page = pg; load(); }
      });
    });
  }

  function bindRowActions() {
    document.querySelectorAll('.cph-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('cphDeleteId').value = btn.dataset.id;
        document.getElementById('cphDeleteMsg').textContent = 'Delete "' + btn.dataset.name + '"? This removes the PDF file permanently.';
        document.getElementById('cphDeleteModal').style.display = 'flex';
      });
    });
    document.querySelectorAll('.cph-email-btn').forEach(function (btn) {
      btn.addEventListener('click', function () { cphOpenEmail(btn.dataset.id, btn.dataset.name); });
    });
  }

  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var v = this.value.trim();
      clearTimeout(timer);
      if (v.length > 0 && v.length < 2) return;
      timer = setTimeout(function () { state.q = v; state.page = 1; load(); }, 300);
    });
  }

  document.getElementById('cphDeleteModal').addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
  });

  window._cphReload = load;
  bindRowActions();
})();

function cphOpenEmail(id, name) {
  document.getElementById('cphEmailCatalogId').value = id;
  document.getElementById('cphEmailSubject').value = 'Your ' + name + ' Catalog';
  document.getElementById('cphEmailMessage').value = 'Hi,\n\nPlease find attached the requested product catalog.\n\nRegards';
  document.getElementById('cphEmailTo').value = '';
  document.getElementById('cphEmailStatus').textContent = '';
  document.getElementById('cphEmailModal').classList.add('open');
}
function cphCloseEmail() {
  document.getElementById('cphEmailModal').classList.remove('open');
}
document.getElementById('cphEmailModal').addEventListener('click', function (e) {
  if (e.target === this) cphCloseEmail();
});
document.getElementById('cphEmailForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var btn = document.getElementById('cphEmailSendBtn');
  var status = document.getElementById('cphEmailStatus');
  btn.disabled = true;
  status.style.color = 'var(--admin-text3,var(--text3))';
  status.textContent = 'Sending…';

  var body = new URLSearchParams();
  body.set('action', 'catalog_pdf_send_email');
  body.set('catalog_id', document.getElementById('cphEmailCatalogId').value);
  body.set('to', document.getElementById('cphEmailTo').value);
  body.set('subject', document.getElementById('cphEmailSubject').value);
  body.set('message', document.getElementById('cphEmailMessage').value);
  body.set('csrf_token', <?= json_encode(csrfToken()) ?>);

  fetch('index.php?page=catalog_pdf_history', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.success) {
        status.style.color = 'var(--success,#3D8B6E)';
        status.textContent = 'Sent ✓';
        setTimeout(cphCloseEmail, 1200);
      } else {
        status.style.color = 'var(--danger,#E84040)';
        status.textContent = 'Error: ' + (d.error || 'send failed');
      }
    })
    .catch(function (e) { status.style.color = 'var(--danger,#E84040)'; status.textContent = 'Request failed: ' + e.message; })
    .finally(function () { btn.disabled = false; });
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>