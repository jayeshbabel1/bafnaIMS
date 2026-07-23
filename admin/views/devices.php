<?php
/**
 * admin/views/devices.php — Trusted Device management (Fire 4)
 * Lists ALL trusted devices across users + admins. Search, paginate,
 * enable/disable, delete. Mirrors admin_clients.php AJAX pattern.
 */
require_once BASE_PATH . '/includes/device_auth.php';
ensureDeviceTables();

// ── AJAX: table rows ────────────────────────────────────────────────────────
if (!empty($_GET['ajax_devices'])) {
    requireAdminPermissionJson('devices.view');
    $search      = trim($_GET['q'] ?? '');
    $perPage     = 20;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));

    $result = adminListAllDevices([
        'search' => $search,
        'limit'  => $perPage,
        'offset' => ($currentPage - 1) * $perPage,
    ]);
    $devices    = $result['rows'];
    $total      = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));

    ob_start();
    include __DIR__ . '/_admin_devices_rows.php';
    $rows = ob_get_clean();

    ob_start();
    if ($totalPages > 1):
        $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range);
    ?>
    <div class="admin-pagination">
      <button class="apag-btn <?= $currentPage<=1?'disabled':'' ?>" data-page="<?= $currentPage-1 ?>">&lsaquo;</button>
      <?php if ($s>1): ?><button class="apag-btn" data-page="1">1</button><?php if ($s>2): ?><span class="apag-ellipsis">…</span><?php endif; endif; ?>
      <?php for ($i=$s;$i<=$e;$i++): ?><button class="apag-btn <?= $i===$currentPage?'active':'' ?>" data-page="<?= $i ?>"><?= $i ?></button><?php endfor; ?>
      <?php if ($e<$totalPages): ?><?php if ($e<$totalPages-1): ?><span class="apag-ellipsis">…</span><?php endif; ?><button class="apag-btn" data-page="<?= $totalPages ?>"><?= $totalPages ?></button><?php endif; ?>
      <button class="apag-btn <?= $currentPage>=$totalPages?'disabled':'' ?>" data-page="<?= $currentPage+1 ?>">&rsaquo;</button>
    </div>
    <?php endif;
    $pag = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows, 'pagination' => $pag, 'total' => $total]);
    exit;
}

// ── AJAX: device login/activity history modal ───────────────────────────────
if (!empty($_GET['ajax_device_history'])) {
    requireAdminPermissionJson('devices.view');
    $did = (int)($_GET['device_id'] ?? 0);
    $db  = getDB();

    $hist = $db->prepare("SELECT * FROM device_login_history WHERE device_id=? ORDER BY created_at DESC LIMIT 30");
    $hist->execute([$did]);
    $loginHistory = $hist->fetchAll();

    $act = $db->prepare("SELECT * FROM device_activity_logs WHERE device_id=? ORDER BY created_at DESC LIMIT 30");
    $act->execute([$did]);
    $activityLog = $act->fetchAll();

    ob_start();
    if (empty($loginHistory) && empty($activityLog)):
    ?>
    <p style="text-align:center;color:var(--admin-text3,var(--text3));font-size:12px;padding:20px;">No history recorded yet.</p>
    <?php else: ?>
    <?php if (!empty($loginHistory)): ?>
    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:8px;">Login Attempts</p>
    <div style="max-height:220px;overflow-y:auto;margin-bottom:16px;">
      <?php foreach ($loginHistory as $h): ?>
      <div style="display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid var(--admin-table-border,var(--border));font-size:12px;">
        <span style="color:<?= $h['success'] ? 'var(--success)' : 'var(--danger)' ?>;font-weight:600;">
          <?= $h['success'] ? '✓ Success' : '✗ Failed' ?><?= $h['reason'] ? ' — '.h($h['reason']) : '' ?>
        </span>
        <span style="color:var(--admin-text3,var(--text3));white-space:nowrap;"><?= h($h['ip_address'] ?: '—') ?> · <?= date('d M Y H:i', $h['created_at']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($activityLog)): ?>
    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:8px;">Activity</p>
    <div style="max-height:220px;overflow-y:auto;">
      <?php foreach ($activityLog as $a): ?>
      <div style="display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid var(--admin-table-border,var(--border));font-size:12px;">
        <span style="font-weight:600;"><?= h(ucwords(str_replace('_',' ',$a['event']))) ?><?= $a['detail'] ? ' — '.h($a['detail']) : '' ?></span>
        <span style="color:var(--admin-text3,var(--text3));white-space:nowrap;"><?= date('d M Y H:i', $a['created_at']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif;
    $historyHtml = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['html' => $historyHtml]);
    exit;
}

$adminTitle = 'Trusted Devices';
requireAdminPermission('devices.view');
include __DIR__ . '/../_layout_top.php';
?>

<style>
.dev-status-dot { display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px; }
.dev-status-dot.active   { background:var(--success,#3D8B6E); }
.dev-status-dot.disabled { background:var(--admin-text3,#8FA3B1); }
.dev-owner-chip { display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--admin-text3,var(--text3)); }
.dev-owner-avatar { width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--admin-accent,var(--accent)),var(--admin-accent-mid,var(--accent-mid)));color:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;flex-shrink:0; }
#devicesLoader { display:none;position:absolute;inset:0;background:rgba(255,255,255,.65);backdrop-filter:blur(2px);align-items:center;justify-content:center;z-index:50;border-radius:var(--admin-card-radius,var(--card-radius)); }
#devicesTableWrap { position:relative; }
.dev-search-wrap { position:relative;flex:1;min-width:220px;max-width:400px; }
.dev-search-wrap>svg { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--admin-text3,var(--text3)); }
.dev-search-wrap input { padding-left:34px !important; }
#devHistoryModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px; }
#devHistoryModal.open { display:flex; }
.dev-modal-card { background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:520px;max-height:80vh;overflow-y:auto;box-shadow:0 16px 48px rgba(0,0,0,.2); }
@media (max-width:768px) { .devices-toolbar { flex-direction:column;align-items:stretch; } .dev-search-wrap { max-width:100%; } }
</style>
<!-- Trust current device — only actionable from an already-authenticated
     admin session, never from the login screen, so a stolen password alone
     can't plant a persistent bypass. -->
<div class="admin-form-section" style="margin-bottom:20px;">
  <p class="admin-form-section-title">Trust This Device</p>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:14px;line-height:1.6;">
    Skip the admin login screen on this browser going forward. Only do this on devices you control.
  </p>
  <form method="POST" action="index.php" style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="hidden" name="action" value="register_admin_device"/>
    <?= csrfField() ?>
    <input type="text" name="device_name" class="admin-input" style="flex:1;min-width:200px;"
           placeholder="Device name (e.g. My Office PC)"/>
    <button type="submit" class="btn-admin-primary">
      <?= icon('check',15) ?> Trust This Device
    </button>
  </form>
</div>
<div class="devices-toolbar" style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
  <div class="dev-search-wrap">
    <?= icon('search', 14) ?>
    <input type="text" id="devSearch" class="admin-input" placeholder="Search device, owner, or IP…" autocomplete="off"/>
  </div>
  <div id="devCountEl" style="font-size:12px;color:var(--admin-text3,var(--text3));margin-left:auto;white-space:nowrap;"></div>
</div>

<div class="admin-table-wrap" id="devicesTableWrap">
  <div id="devicesLoader"><div class="admin-loader-ring"></div></div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Device</th>
        <th>Owner</th>
        <th>Panel</th>
        <th>Status</th>
        <th>Last Seen</th>
        <th>Last IP</th>
        <th>Registered</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="devTbody">
      <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--admin-text3,var(--text3));">Loading…</td></tr>
    </tbody>
  </table>
</div>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:8px;">
  <p class="admin-products-count" id="devFooterCount"></p>
  <div id="devPagWrap"></div>
</div>

<!-- History modal -->
<div id="devHistoryModal">
  <div class="dev-modal-card">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--admin-table-border,var(--border));position:sticky;top:0;background:var(--admin-card-bg,var(--surface));">
      <p style="font-size:15px;font-weight:700;color:var(--admin-text,var(--text));">Device History</p>
      <button type="button" onclick="closeDevHistory()" style="color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border:none;"><?= icon('close',18) ?></button>
    </div>
    <div style="padding:20px;" id="devHistoryBody">Loading…</div>
  </div>
</div>

<!-- Delete confirm modal -->
<div id="devDeleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;">
  <div class="dev-modal-card" style="max-width:420px;">
    <div style="padding:24px;">
      <div style="width:48px;height:48px;border-radius:50%;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
        <?= icon('trash', 22) ?>
      </div>
      <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));margin-bottom:6px;">Delete Trusted Device?</p>
      <p style="font-size:13px;color:var(--admin-text3,var(--text3));line-height:1.6;margin-bottom:18px;" id="devDeleteMsg"></p>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn-admin-secondary" style="flex:1;justify-content:center;" onclick="document.getElementById('devDeleteModal').style.display='none'">Cancel</button>
        <form method="POST" action="index.php" style="flex:1;">
          <input type="hidden" name="action"    value="admin_delete_device"/>
          <input type="hidden" name="device_id" id="devDeleteId" value=""/>
          <?= csrfField() ?>
          <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;"><?= icon('trash', 14) ?> Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var tbody   = document.getElementById('devTbody');
  var pagWrap = document.getElementById('devPagWrap');
  var countEl = document.getElementById('devCountEl');
  var footEl  = document.getElementById('devFooterCount');
  var searchEl= document.getElementById('devSearch');
  var loader  = document.getElementById('devicesLoader');

  var state = { q: '', page: 1 };
  var timer = null;

  function load() {
    if (loader) loader.style.display = 'flex';
    if (tbody)  tbody.style.opacity  = '0.4';
    var params = new URLSearchParams({ page: 'devices', ajax_devices: '1', p: state.page });
    if (state.q) params.set('q', state.q);

    fetch('index.php?' + params)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (tbody)  { tbody.innerHTML = d.rows; tbody.style.opacity = '1'; }
        if (pagWrap){ pagWrap.innerHTML = d.pagination || ''; bindPag(); }
        var txt = d.total + ' device' + (d.total !== 1 ? 's' : '');
        if (countEl) countEl.textContent = txt;
        if (footEl)  footEl.textContent  = txt;
        bindRowActions();
      })
      .catch(function () { if (tbody) tbody.style.opacity = '1'; })
      .finally(function () { if (loader) loader.style.display = 'none'; });
  }

  function bindPag() {
    if (!pagWrap) return;
    pagWrap.querySelectorAll('.apag-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
        var pg = parseInt(btn.dataset.page, 10);
        if (!isNaN(pg)) { state.page = pg; load(); }
      });
    });
  }

  function bindRowActions() {
    document.querySelectorAll('.dev-delete-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('devDeleteId').value = btn.dataset.id;
        document.getElementById('devDeleteMsg').textContent = 'Delete "' + btn.dataset.name + '"? This cannot be undone and will sign the device out immediately.';
        document.getElementById('devDeleteModal').style.display = 'flex';
      });
    });
    document.querySelectorAll('.dev-history-btn').forEach(function (btn) {
      btn.addEventListener('click', function () { openDevHistory(btn.dataset.id); });
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

  document.getElementById('devDeleteModal').addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
  });

  window._devReload = load;
  load();
})();

function openDevHistory(id) {
  document.getElementById('devHistoryModal').classList.add('open');
  document.getElementById('devHistoryBody').innerHTML = 'Loading…';
  fetch('index.php?page=devices&ajax_device_history=1&device_id=' + id)
    .then(function (r) { return r.json(); })
    .then(function (d) { document.getElementById('devHistoryBody').innerHTML = d.html; });
}
function closeDevHistory() {
  document.getElementById('devHistoryModal').classList.remove('open');
}
document.getElementById('devHistoryModal').addEventListener('click', function (e) {
  if (e.target === this) closeDevHistory();
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>