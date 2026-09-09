<?php
/**
 * admin/views/marketing_queue_health.php — Fire 14
 * Small read-only diagnostics view: queue depth by status/channel, oldest
 * pending row age, stuck-processing count, and whether the Fire 14 indexes
 * are actually present. Reuses marketing.reports.view rather than adding a
 * new permission — this is a reporting view, not a new capability.
 */
require_once BASE_PATH . '/includes/marketing_performance.php';

$adminTitle = 'Queue Health';
requireAdminPermission('marketing.reports.view');
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
  <p class="admin-form-section-title">Fire 14 Index Status</p>
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
  <?php if (in_array(false, array_column($indexStatus, 'present'), true)): ?>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-top:10px;">
    <?= icon('info',11) ?> Missing indexes will be added automatically on the next admin page load, or run
    <code>php tools/marketing_apply_indexes.php</code> now to apply them immediately instead of waiting.
  </p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>