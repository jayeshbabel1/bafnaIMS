<?php
/**
 * admin/views/marketing_queue_health.php — Fire 14
 * Small read-only diagnostics view: queue depth by status/channel, oldest
 * pending row age, stuck-processing count, and whether the Fire 14 indexes
 * are actually present. Reuses marketing.reports.view rather than adding a
 * new permission — this is a reporting view, not a new capability.
 */
require_once BASE_PATH . '/includes/marketing_performance.php';

requireAdminPermission('marketing.reports.view');

// ── AJAX: repair (apply missing indexes) + optimize marketing tables ──────
if (!empty($_POST) && ($_POST['action'] ?? '') === 'marketing_repair_optimize') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('marketing.settings.manage');
    csrfVerify(true);
    if (!throttle('marketing_repair_optimize', 3, 60)) {
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment and try again.']);
        exit;
    }
    $indexResults    = applyMarketingPerformanceIndexes();
    $optimizeResults = optimizeMarketingTables();
    echo json_encode(['success' => true, 'indexes' => $indexResults, 'optimize' => $optimizeResults]);
    exit;
}

$adminTitle = 'Queue Health';
$canRepair  = adminCan('marketing.settings.manage');
include __DIR__ . '/../_layout_top.php';

$db = getDB();
$depthByStatus = $db->query("SELECT channel, status, COUNT(*) c FROM marketing_queue GROUP BY channel, status ORDER BY channel, status")->fetchAll();

$oldestPending = $db->query("SELECT MIN(next_attempt_at) FROM marketing_queue WHERE status IN ('pending','rate_limited')")->fetchColumn();
$oldestPendingAgeMins = $oldestPending ? round((time() - $oldestPending) / 60) : null;

$stuckProcessing = (int)$db->query("SELECT COUNT(*) FROM marketing_queue WHERE status='processing' AND locked_at < " . (time() - 1200))->fetchColumn();

$expectedIndexes = [
    ['marketing_campaign_recipients', 'idx_contact'],
    ['marketing_messages', 'idx_recipient'],
    ['marketing_queue', 'idx_claim'],
    ['marketing_queue', 'idx_stale_lock'],
    ['marketing_automation_runs', 'idx_automation_contact'],
    ['marketing_campaigns', 'idx_status_scheduled'],
    ['marketing_contact_tags', 'idx_tag'],
];
$indexStatus = [];
foreach ($expectedIndexes as [$table, $name]) {
    $chk = $db->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1");
    $chk->execute([$table, $name]);
    $indexStatus[] = ['table' => $table, 'index' => $name, 'present' => (bool)$chk->fetch()];
}
?>
<div class="admin-form-section">
  <p class="admin-form-section-title">Queue Depth</p>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Channel</th><th>Status</th><th>Count</th></tr></thead>
    <tbody>
    <?php if (empty($depthByStatus)): ?>
    <tr><td colspan="3" style="text-align:center;padding:16px;color:var(--admin-text3,var(--text3));">Queue is empty.</td></tr>
    <?php else: foreach ($depthByStatus as $row): ?>
    <tr><td><?= h($row['channel']) ?></td><td><?= h($row['status']) ?></td><td><?= $row['c'] ?></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="admin-form-section">
  <p class="admin-form-section-title">Health Signals</p>
  <p style="font-size:13px;margin-bottom:8px;">
    Oldest pending row age: <strong><?= $oldestPendingAgeMins !== null ? $oldestPendingAgeMins . ' min' : '—' ?></strong>
  </p>
  <p style="font-size:13px;<?= $stuckProcessing > 0 ? 'color:var(--danger,#E84040);' : '' ?>">
    Stuck in "processing" &gt;20min (should self-heal next worker run): <strong><?= $stuckProcessing ?></strong>
  </p>
</div>

<div class="admin-form-section">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <p class="admin-form-section-title" style="margin:0;">Fire 14 Index Status</p>
    <?php if ($canRepair): ?>
    <button type="button" id="mktRepairBtn" class="btn-admin-primary" style="white-space:nowrap;">
      <?= icon('refresh', 14) ?> Repair &amp; Optimize
    </button>
    <?php endif; ?>
  </div>
  <?php if ($canRepair): ?>
  <p id="mktRepairStatus" style="font-size:12px;margin:8px 0 0;color:var(--admin-text3,var(--text3));"></p>
  <?php endif; ?>
  <div class="admin-table-wrap" style="margin-top:10px;">
  <table class="admin-table">
    <thead><tr><th>Table</th><th>Index</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($indexStatus as $i): ?>
    <tr>
      <td><?= h($i['table']) ?></td><td style="font-family:monospace;font-size:12px;"><?= h($i['index']) ?></td>
      <td><span class="badge <?= $i['present'] ? 'badge-green' : 'badge-red' ?>"><?= $i['present'] ? 'Present' : 'Missing' ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if (in_array(false, array_column($indexStatus, 'present'), true)): ?>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-top:10px;">
    <?= icon('info',11) ?> Missing indexes will be added automatically on the next admin page load, or run
    <code>php tools/marketing_apply_indexes.php</code> now to apply them immediately instead of waiting.
  </p>
  <?php endif; ?>
</div>

<?php if ($canRepair): ?>
<script>
document.getElementById('mktRepairBtn').addEventListener('click', function () {
  if (!confirm('Apply any missing indexes and run OPTIMIZE TABLE on the marketing tables?\n\nThis briefly locks each table and can take a moment on a large database — best run during low traffic.')) return;

  var btn    = this;
  var status = document.getElementById('mktRepairStatus');
  btn.disabled = true;
  status.style.color = 'var(--admin-text3,var(--text3))';
  status.textContent = 'Running…';

  var body = new URLSearchParams();
  body.set('action', 'marketing_repair_optimize');
  body.set('csrf_token', <?= json_encode(csrfToken()) ?>);

  fetch('index.php?page=marketing_queue_health', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.success) {
        status.style.color = 'var(--danger,#E84040)';
        status.textContent = 'Error: ' + (d.error || 'repair failed');
        btn.disabled = false;
        return;
      }
      var created = d.indexes.filter(function (i) { return i.status === 'created'; }).length;
      var failed  = d.indexes.concat(d.optimize).filter(function (i) { return i.status === 'failed'; }).length;
      if (failed > 0) {
        status.style.color = 'var(--danger,#E84040)';
        status.textContent = failed + ' step(s) failed — check the error log for details.';
        btn.disabled = false;
        return;
      }
      status.style.color = 'var(--success,#3D8B6E)';
      status.textContent = 'Done — ' + created + ' index(es) created, ' + d.optimize.length + ' table(s) optimized. Reloading…';
      setTimeout(function () { window.location.reload(); }, 1200);
    })
    .catch(function (e) {
      status.style.color = 'var(--danger,#E84040)';
      status.textContent = 'Request failed: ' + e.message;
      btn.disabled = false;
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>