<?php
/**
 * admin/views/marketing_campaigns.php — Fire 7
 */
require_once BASE_PATH . '/includes/marketing_campaigns.php';

if (!empty($_GET['ajax_campaigns'])) {
    requireAdminPermissionJson('marketing.campaigns.view');
    $perPage = 20; $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $result = getMarketingCampaigns([
        'status' => $_GET['status'] ?? '', 'search' => trim($_GET['q'] ?? ''),
        'limit' => $perPage, 'offset' => ($currentPage - 1) * $perPage,
        'include_automation' => !empty($_GET['automation_id']),
        'automation_id' => (int)($_GET['automation_id'] ?? 0),
    ]);
    header('Content-Type: application/json');
    echo json_encode(['rows' => $result['rows'], 'total' => $result['total'], 'pages' => max(1, (int)ceil($result['total'] / $perPage)), 'current' => $currentPage]);
    exit;
}

$adminTitle = 'Marketing Campaigns';
requireAdminPermission('marketing.campaigns.view');
include __DIR__ . '/../_layout_top.php';

$canCreate  = adminCan('marketing.campaigns.create');
$canApprove = adminCan('marketing.campaigns.approve');
$canSend    = adminCan('marketing.campaigns.send');
?>
<style>
.mktc-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;}
.mktc-status-tabs{display:flex;gap:6px;overflow-x:auto;margin-bottom:16px;}
.mktc-status-tab{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;background:var(--admin-surface,var(--surface));border:1.5px solid var(--admin-table-border,var(--border));color:var(--admin-text3,var(--text3));cursor:pointer;white-space:nowrap;font-family:inherit;}
.mktc-status-tab.active{background:var(--admin-accent,var(--accent));border-color:var(--admin-accent,var(--accent));color:#fff;}
</style>

<?php if (!empty($_GET['automation_id'])): ?>
<div class="admin-form-section" style="background:var(--admin-accent-light,var(--accent-light));margin-bottom:14px;">
  <p style="font-size:12px;margin:0;"><?= icon('info',12) ?> Showing automation-triggered sends only.
    <a href="index.php?page=marketing_campaigns" style="margin-left:8px;">← Back to all campaigns</a></p>
</div>
<?php endif; ?>
<div class="mktc-toolbar">
  <?php if ($canCreate): ?>
  <a href="index.php?page=marketing_campaign_wizard" class="admin-toolbar-btn admin-toolbar-btn--primary"><?= icon('plus',14) ?> New Campaign</a>
  <?php endif; ?>
  <div style="position:relative;flex:1;min-width:200px;max-width:320px;">
    <?= icon('search',14) ?>
    <input type="text" id="mktcSearch" class="admin-input" placeholder="Search campaign name…" style="padding-left:34px;" autocomplete="off"/>
  </div>
</div>

<div class="mktc-status-tabs" id="mktcStatusTabs">
  <button class="mktc-status-tab active" data-status="">All</button>
  <?php foreach (['draft'=>'Draft','pending_approval'=>'Pending Approval','approved'=>'Approved','scheduled'=>'Scheduled','running'=>'Running','paused'=>'Paused','rate_limited'=>'Rate Limited','completed'=>'Completed','cancelled'=>'Cancelled','failed'=>'Failed'] as $st => $label): ?>
  <button class="mktc-status-tab" data-status="<?= $st ?>"><?= $label ?></button>
  <?php endforeach; ?>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Channel</th><th>Status</th><th>Schedule</th><th>Updated</th><th style="width:220px;">Actions</th></tr></thead>
    <tbody id="mktcTbody"><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--admin-text3,var(--text3));">Loading…</td></tr></tbody>
  </table>
</div>
<div id="mktcPagWrap" class="admin-pagination" style="margin-top:12px;"></div>

<script>
var mktcCanApprove = <?= json_encode($canApprove) ?>;
var mktcCanSend = <?= json_encode($canSend) ?>;
var mktcState = { status: '', q: '', page: 1, automation_id: <?= (int)($_GET['automation_id'] ?? 0) ?> };
var mktcPager = null;

var statusBadge = { draft:'badge-gray', pending_approval:'badge-gold', approved:'badge-blue', scheduled:'badge-blue',
  running:'badge-green', paused:'badge-gold', rate_limited:'badge-gold', completed:'badge-green', cancelled:'badge-gray', failed:'badge-red' };

function esc(s){ var d=document.createElement('div'); d.textContent=String(s==null?'':s); return d.innerHTML; }

function mktcLoad(page) {
  mktcState.page = page || 1;
    var params = new URLSearchParams({ page: 'marketing_campaigns', ajax_campaigns: '1', p: mktcState.page });
  if (mktcState.status) params.set('status', mktcState.status);
  if (mktcState.q) params.set('q', mktcState.q);
  if (mktcState.automation_id) params.set('automation_id', mktcState.automation_id);
  fetch('index.php?' + params).then(function(r){return r.json();}).then(function(d){
    mktcRender(d.rows);
    if (!mktcPager) mktcPager = initPagination({ wrapEl: document.getElementById('mktcPagWrap'), btnClass: 'apag-btn', onPage: mktcLoad });
    mktcPager.render(d.current, d.pages);
  });
}

function mktcRender(rows) {
  var tbody = document.getElementById('mktcTbody');
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--admin-text3,var(--text3));">No campaigns found.</td></tr>'; return; }
  tbody.innerHTML = rows.map(function(c) {
    var sched = c.schedule_type === 'now' ? 'Send Now' : (c.scheduled_at ? new Date(c.scheduled_at*1000).toLocaleString() : '—');
    var updated = new Date(c.updated_at*1000).toLocaleDateString();
    var actions = '<div style="display:flex;gap:5px;flex-wrap:wrap;">';
    actions += '<a href="index.php?page=marketing_campaign_wizard&id=' + c.id + '" class="btn-admin-secondary btn-admin-sm" title="View/Edit"><?= icon('edit',13) ?></a>';
    if (c.status === 'pending_approval' && mktcCanApprove) {
      actions += '<button type="button" class="btn-admin-secondary btn-admin-sm" style="color:var(--success);" onclick="mktcAction(' + c.id + ',\'marketing_approve_campaign\')" title="Approve"><?= icon('check',13) ?></button>';
      actions += '<button type="button" class="btn-admin-danger btn-admin-sm" onclick="mktcAction(' + c.id + ',\'marketing_reject_campaign\')" title="Reject"><?= icon('close',13) ?></button>';
    }
    if (c.status === 'running' && mktcCanSend) actions += '<button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktcAction(' + c.id + ',\'marketing_pause_campaign\')" title="Pause">⏸</button>';
    if (c.status === 'paused' && mktcCanSend)  actions += '<button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktcAction(' + c.id + ',\'marketing_resume_campaign\')" title="Resume">▶</button>';
    if (['scheduled','running','paused','rate_limited'].indexOf(c.status) !== -1 && mktcCanSend)
      actions += '<button type="button" class="btn-admin-danger btn-admin-sm" onclick="mktcAction(' + c.id + ',\'marketing_cancel_campaign\')" title="Cancel"><?= icon('close',13) ?></button>';
    actions += '<button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktcDuplicate(' + c.id + ')" title="Duplicate"><?= icon('copy',13) ?></button>';
    if (['draft','cancelled','failed'].indexOf(c.status) !== -1)
      actions += '<button type="button" class="btn-admin-danger btn-admin-sm" onclick="mktcDelete(' + c.id + ',\'' + esc(c.name).replace(/\047/g,"\\\047") + '\')" title="Delete"><?= icon('trash',13) ?></button>';
    actions += '</div>';

    return '<tr><td style="font-weight:600;">' + esc(c.name) + '</td><td>' + esc(c.channel) + '</td>' +
      '<td><span class="badge ' + (statusBadge[c.status]||'badge-gray') + '">' + esc(c.status.replace(/_/g,' ')) + '</span></td>' +
      '<td style="font-size:12px;">' + esc(sched) + '</td>' +
      '<td style="font-size:11px;color:var(--admin-text3,var(--text3));">' + updated + '</td>' +
      '<td>' + actions + '</td></tr>';
  }).join('');
}

function mktcAction(id, action) {
  var body = new URLSearchParams();
  body.set('action', action);
  body.set('campaign_id', id);
  body.set('csrf_token', <?= json_encode(csrfToken()) ?>);
  fetch('index.php', { method: 'POST', body: body }).then(function(){ location.reload(); });
}
function mktcDuplicate(id) {
  var f = document.createElement('form'); f.method='POST'; f.action='index.php';
  f.innerHTML = '<input type="hidden" name="action" value="marketing_duplicate_campaign"/><input type="hidden" name="campaign_id" value="'+id+'"/><?= csrfField() ?>';
  document.body.appendChild(f); f.submit();
}
function mktcDelete(id, name) {
  if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
  var f = document.createElement('form'); f.method='POST'; f.action='index.php';
  f.innerHTML = '<input type="hidden" name="action" value="marketing_delete_campaign"/><input type="hidden" name="campaign_id" value="'+id+'"/><?= csrfField() ?>';
  document.body.appendChild(f); f.submit();
}

document.getElementById('mktcStatusTabs').addEventListener('click', function(e) {
  var btn = e.target.closest('.mktc-status-tab'); if (!btn) return;
  document.querySelectorAll('.mktc-status-tab').forEach(function(t){t.classList.remove('active');});
  btn.classList.add('active');
  mktcState.status = btn.dataset.status;
  mktcLoad(1);
});
var mktcTimer = null;
document.getElementById('mktcSearch').addEventListener('input', function() {
  var v = this.value.trim();
  clearTimeout(mktcTimer);
  mktcTimer = setTimeout(function(){ mktcState.q = v; mktcLoad(1); }, 300);
});

mktcLoad(1);
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>